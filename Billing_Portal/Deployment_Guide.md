# Comprehensive Deployment & Integration Guide: Zalpro Custom Billing Portal

## 1. System Prerequisites & Environment Setup

Before deploying the custom billing portal, ensure your Linux server (CentOS/Ubuntu) meets the following requirements:
*   **Web Server:** Apache or Nginx (with `mod_rewrite` enabled for clean URLs).
*   **PHP:** PHP 7.4 or 8.x with extensions: `mysqli`, `pdo_mysql`, `curl`, `json`.
*   **Database:** MySQL 5.7+ or MariaDB 10.x.
*   **Radius Utility:** `freeradius-utils` must be installed on the server to allow the portal to send Disconnect-Requests (Packet 3799) to the NAS.
    *   *CentOS:* `yum install freeradius-utils`
    *   *Ubuntu:* `apt install freeradius-utils`

---

## 2. Directory Structure & Security

The portal operates from your web root, but sensitive configuration files should remain outside the public directory.

*   **Public Portal Path:** `/var/www/html/billing_portal/`
*   **Secure Credentials Path:** `/var/z_secure_configs/` (Create this manually for security)

**Security Note:** The portal relies on session variables (`custom_username` and `custom_admin_id`). Ensure your upstream login controller securely validates these before routing to this directory.

---

## 3. Database (SQL) Architecture & Radius Integration

The backend interacts with standard FreeRADIUS/Zalpro tables (`radcheck`, `radusergroup`, `nas`, `usersinfo`) but requires the following custom schema to handle bulk invoicing, dynamic packages, and historical logging.

Execute these queries directly in your `zalpro` database:

### A. Monthly Bills Ledger
Manages bulk invoice generation, prevents duplicate billing cycles, and handles partial payments.
```sql
CREATE TABLE `monthly_bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `billing_month` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `paid_at` datetime DEFAULT NULL,
  `collected_by` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_username` (`username`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### B. User Package Audit History
Maintains a strict ledger of all manual and automated package transitions (e.g., Active -> Expired -> Active).
```sql
CREATE TABLE `user_package_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) NOT NULL,
  `username` varchar(64) NOT NULL,
  `old_package_id` int(11) DEFAULT NULL,
  `old_package_name` varchar(100) DEFAULT NULL,
  `old_package_price` decimal(10,2) DEFAULT NULL,
  `new_package_id` int(11) DEFAULT NULL,
  `new_package_name` varchar(100) DEFAULT NULL,
  `new_package_price` decimal(10,2) DEFAULT NULL,
  `change_type` varchar(50) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `changed_by` varchar(64) DEFAULT NULL,
  `changed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_userid` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### C. `usersinfo` Schema Modification
Adds memory states to standard user accounts so the system knows which package to restore after an account is suspended/expired (Package ID 25).
```sql
ALTER TABLE `usersinfo` 
ADD COLUMN `last_active_package_id` int(11) DEFAULT NULL,
ADD COLUMN `last_active_package_name` varchar(100) DEFAULT NULL,
ADD COLUMN `last_active_package_price` decimal(10,2) DEFAULT NULL,
ADD COLUMN `last_active_package_updated_at` datetime DEFAULT NULL;
```

---

## 4. Deployment Execution via CLI / WinSCP

Follow these exact commands/steps to deploy the system onto your ISP server:

1.  **Establish Secure DB Configuration:**
    ```bash
    mkdir -p /var/z_secure_configs/
    nano /var/z_secure_configs/db_config.php
    ```
    *Add the following PHP code:*
    ```php
    <?php
    define('DB_HOST', '127.0.0.1');
    define('DB_USER', 'root');
    define('DB_PASS', 'your_secure_mysql_password');
    define('DB_NAME', 'zalpro');
    ?>
    ```

2.  **Deploy Portal Files:**
    Use WinSCP (SFTP) to upload all HTML, CSS, JS, and PHP files into `/var/www/html/billing_portal/`.

3.  **Assign Strict File Permissions:**
    Run these commands in PuTTY to grant Apache/Nginx the correct ownership while restricting root execution.
    *For Ubuntu/Debian:*
    ```bash
    chown -R www-data:www-data /var/www/html/billing_portal/
    chmod -R 755 /var/www/html/billing_portal/
    ```
    *For CentOS/RHEL:*
    ```bash
    chown -R apache:apache /var/www/html/billing_portal/
    chmod -R 755 /var/www/html/billing_portal/
    ```

---

## 5. FreeRADIUS & MikroTik NAS Integration

To ensure users are instantly disconnected when their package changes or expires, the backend API uses the `radclient` utility.

1.  The API queries the `nas` table to find the MikroTik IP (`nasname`) and RADIUS `secret`.
2.  It executes a system call to send a disconnect packet:
    ```bash
    echo "User-Name='username'" | radclient -x <NAS_IP>:3799 disconnect <SECRET>
    ```
3.  **Important:** Ensure your MikroTik/Edge router has RADIUS Incoming Connections enabled.
    *   *MikroTik CLI:* `/radius incoming set accept=yes port=3799`

---

## 6. Cron Jobs for Automated Invoicing (Optional)

If your billing logic requires auto-generating invoices on the 1st of every month, add a cron job.

1.  Edit the crontab: `crontab -e`
2.  Add the following line to run a generation script at 12:01 AM on the 1st of every month:
    ```bash
    1 0 1 * * /usr/bin/php /var/www/html/billing_portal/cron_generate_bills.php > /dev/null 2>&1
    ```

---

## 7. Troubleshooting & Logs

If AJAX calls are failing or the portal throws HTTP 500 errors, check the following logs:

*   **Apache Error Log (CentOS):** `tail -f /var/log/httpd/error_log`
*   **Apache Error Log (Ubuntu):** `tail -f /var/log/apache2/error.log`
*   **FreeRADIUS Log:** `tail -f /var/log/radius/radius.log` (Check for Access-Reject or Disconnect-NAK errors).
*   **Console Debugging:** Press `F12` in your browser, go to the **Network** tab, and inspect the response payload of `backend_api.php`. Ensure it returns valid JSON.
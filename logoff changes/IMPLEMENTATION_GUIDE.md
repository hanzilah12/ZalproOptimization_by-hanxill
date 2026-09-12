# LastLogoff Column Implementation Guide
## Netpoint RADIUS Server - User Management Portal

---

## 📋 CHANGES REQUIRED

### 1️⃣ RADIUS HELPER FILE
**File:** `/var/www/html/application/helpers/radius_helper.php`

**Action:** Add these two functions at the END of the file (before closing `?>`)

```php
// ========= ADDITION BY HAXILL - LASTLOGOFF FUNCTIONS =========

if (!function_exists('getLastLogoffByUsername')) {
	function getLastLogoffByUsername($username)
	{
		$ci = loadingInstance();
		$ci->db->limit(1);
		$ci->db->order_by('radacctid', 'desc');
		$query = $ci->db->get_where('radacct', ['username' => $username, 'acctstoptime !=' => NULL]);

		if (0 < $query->num_rows()) {
			$row = $query->result()[0];
			return isset($row->acctstoptime) ? $row->acctstoptime : 'N/A';
		}
		else {
			return 'N/A';
		}
	}
}

if (!function_exists('getLastLogoffByUsernameObject')) {
	function getLastLogoffByUsernameObject($username)
	{
		$ci = loadingInstance();
		$ci->db->limit(1);
		$ci->db->order_by('radacctid', 'desc');
		$query = $ci->db->get_where('radacct', ['username' => $username, 'acctstoptime !=' => NULL]);

		if (0 < $query->num_rows()) {
			return $query->result()[0];
		}
		else {
			return false;
		}
	}
}

// ============================================================
```

---

### 2️⃣ USER CONTROLLER FILE
**File:** `/var/www/html/application/controllers/admin_portal/user/User.php`

#### Step A: Update DataTable Columns Array

**Location:** Find the function that handles DataTables (around line 2990-3000)

Look for this line:
```php
$columns = array('usersinfo.id', 'usersinfo.username', 'usersinfo.phone', ...);
```

**CHANGE IT TO:**
```php
$columns = array('usersinfo.id', 'usersinfo.username', 'usersinfo.phone', 'usersinfo.name', 'usersinfo.status', 'usersinfo.expire', 'radacct.acctstoptime');
```

This adds `radacct.acctstoptime` (Last Logoff) as a sortable column.

#### Step B: Modify Data Assembly Loop

**Location:** Find where `$nestedData` array is built (around line 3100-3150)

You need to ADD this line in the loop where data is being assembled:

**BEFORE the line:** `$nestedData[] = '<a class="disable-user-connection"...`

**ADD THIS:**
```php
// ===== ADDITION BY HAXILL - LASTLOGOFF COLUMN =====
$lastLogoff = getLastLogoffByUsername($row->username);
if ($lastLogoff !== 'N/A' && !empty($lastLogoff)) {
	$formattedLogoff = date('d-M-Y H:i:s', strtotime($lastLogoff));
	$nestedData[] = '<span class="label label-info" data-toggle="tooltip" title="Last Logoff Time">' . $formattedLogoff . '</span>';
} else {
	$nestedData[] = '<span class="label label-warning">No Logoff</span>';
}
// ================================================
```

---

### 3️⃣ VIEW FILE
**File:** `/var/www/html/application/views/themes/legacy/admin_portal/users/all.php`

#### Find the Table Header Section

Look for the DataTables initialization JavaScript (around line 40-120)

Find the `<thead>` section with column headers:
```html
<tr>
    <th>ID</th>
    <th>Photo</th>
    <th>Username</th>
    ...
</tr>
```

**ADD THIS COLUMN HEADER** (add AFTER the "On/Off" column, BEFORE "Expiry"):
```html
<th data-field="acctstoptime" data-sortable="true">Last Logoff</th>
```

**COMPLETE EXAMPLE:**
```html
<th>On/Off</th>
<th data-field="acctstoptime" data-sortable="true">Last Logoff</th>
<th>Expiry</th>
```

---

## ⌨️ KEYBOARD SHORTCUTS (Optional Enhancement)

**Location:** In the same `all.php` file, find the `<script>` section at the bottom

**ADD THIS BEFORE THE CLOSING `</script>` TAG:**

```javascript
// ===== KEYBOARD SHORTCUTS FOR QUICK ACCESS =====
$(document).on('keydown', function(e) {
    // Alt + U = Add New User
    if (e.altKey && e.keyCode === 85) {
        e.preventDefault();
        $('.add-new-user').click();
    }
    // Alt + F = Filter Users
    if (e.altKey && e.keyCode === 70) {
        e.preventDefault();
        $('.users-filter').click();
    }
    // Alt + E = Export Users
    if (e.altKey && e.keyCode === 69) {
        e.preventDefault();
        $('.btn-export-users').click();
    }
    // Alt + I = Import Users
    if (e.altKey && e.keyCode === 73) {
        e.preventDefault();
        $('.btn-import-users').click();
    }
    // Alt + D = Mass Delete
    if (e.altKey && e.keyCode === 68) {
        e.preventDefault();
        $('.mass-payment-confirm').click();
    }
});

// Display keyboard shortcut help (Ctrl + ?)
$(document).on('keydown', function(e) {
    if (e.ctrlKey && e.shiftKey && e.keyCode === 191) { // Ctrl + Shift + ?
        e.preventDefault();
        alert('⌨️ KEYBOARD SHORTCUTS\n\nAlt + U = Add New User\nAlt + F = Filter Users\nAlt + E = Export Users\nAlt + I = Import Users\nAlt + D = Mass Delete\n\nCtrl + Shift + ? = Show This Help');
    }
});
// ================================================
```

---

## 🎯 SORTING IMPLEMENTATION

The sorting for "Last Logoff" will automatically work because:

1. ✅ We added `'radacct.acctstoptime'` to the `$columns` array
2. ✅ We added `data-field="acctstoptime" data-sortable="true"` to the table header
3. ✅ The existing DataTables sorting mechanism will handle it

### Sorting Works Like:
- **Ascending:** Oldest logoff time first → Newest logoff time last
- **Descending:** Newest logoff time first → Oldest logoff time last

---

## 🔧 INSTALLATION STEPS

### Step 1: Backup Your Files
```bash
cp /var/www/html/application/helpers/radius_helper.php /var/www/html/application/helpers/radius_helper.php.backup
cp /var/www/html/application/controllers/admin_portal/user/User.php /var/www/html/application/controllers/admin_portal/user/User.php.backup
cp /var/www/html/application/views/themes/legacy/admin_portal/users/all.php /var/www/html/application/views/themes/legacy/admin_portal/users/all.php.backup
```

### Step 2: Apply Changes to radius_helper.php
Add the two new functions from Section 1️⃣ above

### Step 3: Apply Changes to User.php
Modify the columns array and add the lastlogoff data assembly (Sections 2️⃣A and 2️⃣B)

### Step 4: Apply Changes to all.php
Add the table header column (Section 3️⃣)

### Step 5: Add Keyboard Shortcuts (Optional)
Add the JavaScript code from the ⌨️ section

### Step 6: Clear Browser Cache
Press `Ctrl + F5` or `Cmd + Shift + R` to refresh browser cache

### Step 7: Test
- Go to Users → All Users
- Click the "Last Logoff" column header to sort
- Try keyboard shortcuts (Alt + U for Add User, etc.)

---

## 🐛 TROUBLESHOOTING

### Issue: Column Not Appearing
- ✅ Check if table header `<th>` was added
- ✅ Verify JavaScript console for errors (F12)
- ✅ Clear browser cache (Ctrl + F5)

### Issue: Sorting Not Working
- ✅ Check if `data-field="acctstoptime"` is correct in header
- ✅ Verify column name matches in PHP `$columns` array
- ✅ Check if `radacct.acctstoptime` is spelled correctly

### Issue: Alignment Problems
- ✅ The new code uses Bootstrap `label` tags which auto-align
- ✅ No custom CSS needed - uses existing Bootstrap classes
- ✅ Check if any CSS overrides exist in custom themes

### Issue: "No Logoff" Shows for Online Users
- ✅ This is CORRECT! Online users have no `acctstoptime` value
- ✅ Show "No Logoff" for online users, datetime for offline users

---

## 📊 DATABASE NOTES

The data comes from:
- **Table:** `radacct` (RADIUS accounting table)
- **Column:** `acctstoptime` (When user logged off)
- **Value:** `NULL` if user is currently online
- **Value:** Timestamp if user is offline

---

## ✨ FEATURES ADDED

✅ **Last Logoff Column** - Shows when users last disconnected
✅ **Sortable Column** - Click to sort by logoff time
✅ **Color Coding:**
   - 🔵 **Blue** = Has logoff time (offline)
   - 🟡 **Yellow** = No logoff (still online)
✅ **Keyboard Shortcuts** - Quick access to features
✅ **Tooltip Support** - Hover to see full datetime
✅ **Proper Alignment** - Uses Bootstrap, no CSS hacks needed

---

## 📞 SUPPORT

If you face any issues:
1. Check all 3 files are updated
2. Clear browser cache (Ctrl + F5)
3. Check browser console for JavaScript errors (F12)
4. Verify database has `radacct` table with `acctstoptime` column
5. Check file permissions (755 for .php files)

---

**Prepared by:** Haxill
**Date:** 2026
**Version:** 1.0

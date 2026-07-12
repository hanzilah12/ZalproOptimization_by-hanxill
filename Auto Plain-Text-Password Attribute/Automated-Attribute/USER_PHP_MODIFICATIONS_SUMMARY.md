# User.php Modifications - Cleartext-Password Auto-Add

## Overview
Modified the User controller to automatically add/update the `Cleartext-Password` RADIUS attribute whenever a user is created or password is changed.

---

## Changes Made

### 1. **User Creation - Auto-Add Cleartext-Password** (Lines 436-447)

**Location:** After user insert in database (after `$userid = $this->db->insert_id()`)

**What it does:**
- When a new user is created, automatically inserts the `Cleartext-Password` attribute into the `radcheck` table
- Uses the plain password from the creation form
- Attribute format: `Cleartext-Password := <password>`

**Code Added:**
```php
// =====================================================
// AUTO-ADD CLEARTEXT-PASSWORD RADIUS ATTRIBUTE
// =====================================================
if ($true && !empty($userid)) {
    $radCheckData = [
        'username'  => $data['username'],
        'attribute' => 'Cleartext-Password',
        'op'        => ':=',
        'value'     => $password
    ];
    $this->main->insertData('radcheck', $radCheckData);
}
```

**Result:** ✅ New users automatically get Cleartext-Password in RADIUS

---

### 2. **Password Change - Auto-Update Cleartext-Password** (Lines 839-857)

**Location:** In the `changepassword()` function

**What it does:**
- When a user's password is changed, automatically updates the `Cleartext-Password` attribute
- If attribute doesn't exist, it creates it
- Always updates regardless of user connection status (previous logic only updated if user was connected)

**Code Changes:**
```php
// =====================================================
// AUTO-UPDATE CLEARTEXT-PASSWORD RADIUS ATTRIBUTE
// =====================================================
$radQuery = $this->main->singleQuery('radcheck', ['username' => $username, 'attribute' => 'Cleartext-Password']);

if ($radQuery) {
    // UPDATE existing Cleartext-Password
    $data2['value'] = $newPassword;
    $this->main->singleUpdate('radcheck', $data2, ['username' => $username, 'attribute' => 'Cleartext-Password']);
} else {
    // INSERT if doesn't exist
    $radCheckData = [
        'username'  => $username,
        'attribute' => 'Cleartext-Password',
        'op'        => ':=',
        'value'     => $newPassword
    ];
    $this->main->insertData('radcheck', $radCheckData);
}
```

**Result:** ✅ Password changes automatically sync to RADIUS attribute

---

## Deployment Instructions

### Step 1: Backup Original File
```bash
cp /var/www/html/application/controllers/admin_portal/user/User.php \
   /var/www/html/application/controllers/admin_portal/user/User.php.backup
```

### Step 2: Replace with Modified File
```bash
cp User.php /var/www/html/application/controllers/admin_portal/user/User.php
```

### Step 3: Verify Permissions
```bash
chmod 644 /var/www/html/application/controllers/admin_portal/user/User.php
chown www-data:www-data /var/www/html/application/controllers/admin_portal/user/User.php
```

### Step 4: Test

**Test 1: Create New User**
1. Go to User Management → Create New User
2. Fill form and submit
3. Check RADIUS database:
   ```sql
   SELECT * FROM radcheck WHERE username='newuser' AND attribute='Cleartext-Password';
   ```
   ✅ Should see the password in plaintext

**Test 2: Change Password**
1. Go to user profile → Change Password
2. Enter new password and submit
3. Check RADIUS database again:
   ```sql
   SELECT * FROM radcheck WHERE username='testuser' AND attribute='Cleartext-Password';
   ```
   ✅ Should see updated password

---

## What Changed in Database

### Before (Manual):
- User created → No Cleartext-Password added → Manual add required
- Password changed → No auto-update → Manual update required

### After (Automatic):
- User created → ✅ Auto-adds Cleartext-Password 
- Password changed → ✅ Auto-updates Cleartext-Password

---

## Important Notes

⚠️ **Database Requirements:**
- Ensure `radcheck` table exists
- User must have INSERT/UPDATE permissions on `radcheck` table

⚠️ **Compatibility:**
- Uses existing `$this->main->insertData()` and `$this->main->singleUpdate()` methods
- Compatible with existing Zalpro codebase

✅ **No Breaking Changes:**
- All existing functionality preserved
- Only adds automatic RADIUS attribute sync
- Backwards compatible with current user management

---

## Troubleshooting

If `Cleartext-Password` is not being created:

1. Check if `radcheck` table exists:
   ```sql
   SHOW TABLES LIKE 'radcheck%';
   ```

2. Verify INSERT permissions:
   ```sql
   GRANT INSERT, UPDATE ON radius.radcheck TO 'zalpro_user'@'localhost';
   ```

3. Check PHP error logs:
   ```bash
   tail -f /var/log/apache2/error.log
   ```

4. Verify the file was deployed correctly:
   ```bash
   grep -n "AUTO-ADD CLEARTEXT-PASSWORD" /var/www/html/application/controllers/admin_portal/user/User.php
   ```

---

## Summary

✅ **Complete:** Jab b new user create ho → Cleartext-Password auto-add hota hai  
✅ **Complete:** Jab b password change ho → Cleartext-Password auto-update hota hai  
✅ **No Manual Work:** Sab automatic handle ho raha hai!


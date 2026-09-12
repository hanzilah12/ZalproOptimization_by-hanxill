# 🚀 QUICK REFERENCE GUIDE

## FILES TO MODIFY

```
/var/www/html/application/helpers/radius_helper.php      ← Add 2 functions
/var/www/html/application/controllers/admin_portal/user/User.php  ← Modify 2 sections
/var/www/html/application/views/themes/legacy/admin_portal/users/all.php ← Add 2 sections
```

---

## 1️⃣ RADIUS HELPER (radius_helper.php)

### ADD at the END of file (before `?>`)

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
		} else {
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
		} else {
			return false;
		}
	}
}

// ============================================================
```

---

## 2️⃣ USER CONTROLLER (User.php)

### CHANGE #A: Find columns array (~line 2990-3050)

**FROM:**
```php
$columns = array('usersinfo.id', 'usersinfo.username', 'usersinfo.phone', 'usersinfo.name', 'usersinfo.status', 'usersinfo.expire');
```

**TO:**
```php
$columns = array('usersinfo.id', 'usersinfo.username', 'usersinfo.phone', 'usersinfo.name', 'usersinfo.status', 'usersinfo.expire', 'radacct.acctstoptime');
```

### CHANGE #B: Find data loop (~line 3100-3200)

**BEFORE THIS LINE:**
```php
$nestedData[] = '<a class="disable-user-connection"...';
```

**ADD THIS CODE:**
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

## 3️⃣ VIEW FILE (all.php)

### CHANGE #A: Find table headers

**ADD THIS COLUMN:**
```html
<th data-field="acctstoptime" data-sortable="true">Last Logoff</th>
```

**Position it AFTER:**
```html
<th>On/Off</th>
```

**And BEFORE:**
```html
<th>Expiry</th>
```

### CHANGE #B: Add keyboard shortcuts

**BEFORE THIS:**
```javascript
    });
</script>
```

**ADD THIS:**
```javascript
    });

    // ===== KEYBOARD SHORTCUTS =====
    $(document).on('keydown', function(e) {
        // Alt + U = Add New User
        if (e.altKey && e.keyCode === 85) {
            e.preventDefault();
            $('.add-new-user').trigger('click');
        }
        // Alt + F = Filter
        if (e.altKey && e.keyCode === 70) {
            e.preventDefault();
            $('.users-filter-btn').trigger('click');
        }
        // Alt + E = Export
        if (e.altKey && e.keyCode === 69) {
            e.preventDefault();
            $('button[title*="Export"]').trigger('click');
        }
        // Alt + I = Import
        if (e.altKey && e.keyCode === 73) {
            e.preventDefault();
            $('button[title*="Import"]').trigger('click');
        }
        // Alt + D = Delete
        if (e.altKey && e.keyCode === 68) {
            e.preventDefault();
            $('.mass-payment-confirm').trigger('click');
        }
        // Ctrl + Shift + ? = Help
        if (e.ctrlKey && e.shiftKey && e.keyCode === 191) {
            e.preventDefault();
            alert('⌨️ SHORTCUTS:\nAlt+U=Add User\nAlt+F=Filter\nAlt+E=Export\nAlt+I=Import\nAlt+D=Delete\nCtrl+Shift+?=Help');
        }
    });
    // ================================
</script>
```

---

## ✅ INSTALLATION CHECKLIST

### Backup
- [ ] Backup radius_helper.php
- [ ] Backup User.php
- [ ] Backup all.php

### radius_helper.php
- [ ] Add two functions at end
- [ ] Verify no syntax errors
- [ ] Check file saved

### User.php
- [ ] Update $columns array (add 'radacct.acctstoptime')
- [ ] Add lastlogoff code in data loop
- [ ] Verify syntax with `php -l User.php`
- [ ] Check file saved

### all.php
- [ ] Add table header <th> tag
- [ ] Add keyboard shortcuts JavaScript
- [ ] Verify syntax
- [ ] Check file saved

### Browser
- [ ] Clear cache (Ctrl + F5)
- [ ] Refresh page
- [ ] Test sorting on Last Logoff column
- [ ] Test keyboard shortcuts

---

## 🧪 QUICK TEST

1. Go to **Users → All Users**
2. Look for **"Last Logoff"** column between "On/Off" and "Expiry"
3. Click column header to **sort** ↑↓
4. Press **Alt + U** to test shortcut
5. Press **Ctrl + Shift + ?** to see help

---

## 🎨 COLUMN COLORS

- 🔵 **Blue** = Has logoff time (offline)
- 🟡 **Yellow** = No logoff (online)

---

## 🔑 KEYBOARD SHORTCUTS

| Key | Action |
|-----|--------|
| Alt + U | Add New User |
| Alt + F | Filter Users |
| Alt + E | Export Users |
| Alt + I | Import Users |
| Alt + D | Mass Delete |
| Ctrl + Shift + ? | Show Help |

---

## 🚨 IF SOMETHING BREAKS

### Step 1: Restore from backup
```bash
cp /var/www/html/application/helpers/radius_helper.php.backup /var/www/html/application/helpers/radius_helper.php
```

### Step 2: Check PHP syntax
```bash
php -l /var/www/html/application/controllers/admin_portal/user/User.php
```

### Step 3: Clear browser cache
Press `Ctrl + F5` or `Cmd + Shift + R`

### Step 4: Check browser console
Press `F12` and look for errors

---

## 💡 COMMON ISSUES

| Issue | Solution |
|-------|----------|
| Column not visible | Clear cache (Ctrl+F5) |
| Sorting doesn't work | Check 'radacct.acctstoptime' in $columns |
| Keyboard shortcuts fail | Check F12 console for errors |
| PHP syntax error | Run `php -l filename.php` |
| Alignment broken | Use Bootstrap classes (label, label-info) |

---

## 📞 NEED HELP?

1. Check IMPLEMENTATION_GUIDE.md for detailed steps
2. Check USER_PHP_CHANGES.md for controller changes
3. Check VIEW_FILE_CHANGES.md for view changes
4. Verify all 3 files have been modified
5. Clear browser cache and refresh

---

**Quick & Clean Solution ✨**
**No alignment issues | Proper sorting | Keyboard shortcuts**
**Prepared by: Haxill**

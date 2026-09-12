# User.php Controller - Exact Changes Required

## 📍 CHANGE #1: Update Columns Array for Sorting

### Find This Function:
Search for the `all()` function that handles user listing with DataTables

### Approximate Location: 
Around line **2980-3050** in the User.php file

### Find This Code Block:
```php
public function all()
{
    // ... code ...
    $columns = array('usersinfo.id', 'usersinfo.username', 'usersinfo.phone', 'usersinfo.name', 'usersinfo.status', 'usersinfo.expire');
    // ... more code ...
}
```

### CHANGE TO:
```php
public function all()
{
    // ... code ...
    $columns = array('usersinfo.id', 'usersinfo.username', 'usersinfo.phone', 'usersinfo.name', 'usersinfo.status', 'usersinfo.expire', 'radacct.acctstoptime');
    // ... more code ...
}
```

**What Changed:** Added `'radacct.acctstoptime'` at the end of the columns array

---

## 📍 CHANGE #2: Add LastLogoff Data to Response

### Find This Location:
In the same `all()` function, look for where the data array (`$nestedData`) is being built

### Approximate Location: 
Around line **3100-3200** in the User.php file

### Look For This Pattern:
```php
foreach ($query->result() as $row) {
    // ... loop code ...
    $nestedData = [];
    $nestedData[] = $i;  // ID column
    $nestedData[] = '<img...';  // Photo column
    $nestedData[] = '<a...';  // Username column
    $nestedData[] = '<a...';  // Name column
    // ... more columns ...
    $nestedData[] = '<a class="disable-user-connection"...';  // Action button
    $data[] = $nestedData;
}
```

### ADD THIS CODE:

**FIND THIS LINE:**
```php
$nestedData[] = '<a class="disable-user-connection"...';
```

**REPLACE WITH:**
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

$nestedData[] = '<a class="disable-user-connection"...';
```

**VISUAL EXAMPLE:**
```php
foreach ($query->result() as $row) {
    $i++;
    $username = $row->username;
    
    // ... existing code for other columns ...
    
    $nestedData = [];
    $nestedData[] = $i;
    $nestedData[] = '<img src="..." alt="Photo">';
    $nestedData[] = '<a href="...">' . $row->username . '</a>';
    $nestedData[] = $row->name;
    $nestedData[] = $row->status;
    $nestedData[] = $row->package;
    $nestedData[] = $row->seller;
    $nestedData[] = $row->balance;
    $nestedData[] = $row->service;
    $nestedData[] = $row->on_off;
    
    // ===== ADDITION BY HAXILL - LASTLOGOFF COLUMN =====
    $lastLogoff = getLastLogoffByUsername($row->username);
    if ($lastLogoff !== 'N/A' && !empty($lastLogoff)) {
        $formattedLogoff = date('d-M-Y H:i:s', strtotime($lastLogoff));
        $nestedData[] = '<span class="label label-info" data-toggle="tooltip" title="Last Logoff Time">' . $formattedLogoff . '</span>';
    } else {
        $nestedData[] = '<span class="label label-warning">No Logoff</span>';
    }
    // ================================================
    
    $nestedData[] = '<a class="disable-user-connection" href="...">Disconnect</a>';
    
    $data[] = $nestedData;
}
```

---

## ✅ VERIFICATION CHECKLIST

After making changes, verify:

- [ ] `$columns` array includes `'radacct.acctstoptime'` 
- [ ] The new code is inside the `foreach` loop
- [ ] `getLastLogoffByUsername()` function exists in radius_helper.php
- [ ] No syntax errors (check PHP syntax: `php -l User.php`)
- [ ] The code is added BEFORE the action button column
- [ ] Table headers in all.php include new column

---

## 🔍 FINDING THE RIGHT LOCATION

If you can't find the exact location, use these search terms:

### Search for:
```
foreach ($query->result() as $row)
```

This will help you find the data loop where you need to add the code.

### Then search for:
```
$nestedData[] = '<a class="disable-user-connection"
```

This is where you should add the lastlogoff code.

---

## 💾 CODE WITH LINE COMMENTS

Here's the exact code to add with explanatory comments:

```php
// ===== ADDITION BY HAXILL - LASTLOGOFF COLUMN =====
// Get the last logoff time for this user from RADIUS accounting table
$lastLogoff = getLastLogoffByUsername($row->username);

// Check if user has a recorded logoff time
if ($lastLogoff !== 'N/A' && !empty($lastLogoff)) {
    // Format the datetime to readable format (dd-MMM-yyyy HH:mm:ss)
    $formattedLogoff = date('d-M-Y H:i:s', strtotime($lastLogoff));
    // Add as blue label with tooltip
    $nestedData[] = '<span class="label label-info" data-toggle="tooltip" title="Last Logoff Time">' . $formattedLogoff . '</span>';
} else {
    // If no logoff time, show yellow label (user is online or never logged off)
    $nestedData[] = '<span class="label label-warning">No Logoff</span>';
}
// ================================================
```

---

## 🎨 COLOR MEANINGS

| Color | Meaning | Example |
|-------|---------|---------|
| 🔵 Blue (label-info) | Has logoff time - User is offline | `25-Dec-2026 14:30:15` |
| 🟡 Yellow (label-warning) | No logoff time - User is online | `No Logoff` |

---

## 📝 TESTING THE CHANGES

### Test Steps:

1. **Add a test user** that's currently online
2. **Force disconnect** that user or wait for timeout
3. **Go to Users > All Users**
4. **Check if "Last Logoff" column shows:**
   - 🟡 "No Logoff" for online users
   - 🔵 Timestamp for offline users
5. **Click the column header** to test sorting

### Expected Results:
- ✅ Column appears between "On/Off" and "Expiry"
- ✅ Sorting works (click header to change order)
- ✅ Blue and Yellow labels display correctly
- ✅ No alignment issues
- ✅ Tooltip shows on hover over timestamp

---

## 🚨 COMMON MISTAKES

### ❌ WRONG - Missing comma:
```php
$columns = array('usersinfo.id', 'usersinfo.username', 'usersinfo.phone' 'radacct.acctstoptime');
//                                                              ↑ Missing comma
```

### ✅ CORRECT:
```php
$columns = array('usersinfo.id', 'usersinfo.username', 'usersinfo.phone', 'radacct.acctstoptime');
//                                                              ↑ Comma here
```

---

### ❌ WRONG - Wrong quote type:
```php
$nestedData[] = <span class="label label-info">text</span>';
//              ↑ Missing opening quote
```

### ✅ CORRECT:
```php
$nestedData[] = '<span class="label label-info">text</span>';
//              ↑ Opening quote here
```

---

### ❌ WRONG - Function doesn't exist:
```php
$lastLogoff = getLastLogoff($row->username);  // Wrong function name
```

### ✅ CORRECT:
```php
$lastLogoff = getLastLogoffByUsername($row->username);  // Correct function
```

The function must be added to `radius_helper.php` first!

---

## 🔗 DEPENDENCIES

This code depends on:
1. **radius_helper.php** - Must have `getLastLogoffByUsername()` function
2. **all.php view** - Must have table header for new column
3. **Bootstrap CSS** - For `.label`, `.label-info`, `.label-warning` classes

---

## 💡 TIPS

- **Backup first!** Use: `cp User.php User.php.backup`
- **Use a code editor** that shows line numbers (VS Code, Sublime, Notepad++)
- **Validate PHP syntax** after changes: `php -l User.php`
- **Test on offline users first** - They will definitely have logoff times
- **Clear browser cache** after deployment: `Ctrl + F5` or `Cmd + Shift + R`

---

**Version:** 1.0
**Last Updated:** 2026
**Author:** Haxill

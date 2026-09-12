# all.php View File - Complete Changes Required

## 📍 CHANGE #1: Add Table Header Column

### Find the Table Header Section

In your `/var/www/html/application/views/themes/legacy/admin_portal/users/all.php` file

Look for the HTML table structure that displays user columns. It should look something like:

```html
<table class="table table-striped table-bordered dt-responsive nowrap" width="100%" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Photo</th>
            <th>Username</th>
            <th>Phone</th>
            <th>Package</th>
            <th>Seller</th>
            <th>Balance</th>
            <th>Service</th>
            <th>On/Off</th>
            <th>Expiry</th>
            <th>Actions</th>
        </tr>
    </thead>
</table>
```

### ADD THIS COLUMN HEADER

**Find this line:**
```html
<th>On/Off</th>
```

**CHANGE TO:**
```html
<th>On/Off</th>
<th data-field="acctstoptime" data-sortable="true">Last Logoff</th>
```

**COMPLETE EXAMPLE:**
```html
<table class="table table-striped table-bordered dt-responsive nowrap" width="100%" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Photo</th>
            <th>Username</th>
            <th>Phone</th>
            <th>Package</th>
            <th>Seller</th>
            <th>Balance</th>
            <th>Service</th>
            <th>On/Off</th>
            <th data-field="acctstoptime" data-sortable="true">Last Logoff</th>
            <th>Expiry</th>
            <th>Actions</th>
        </tr>
    </thead>
</table>
```

---

## 📍 CHANGE #2: Add Keyboard Shortcuts JavaScript

### Find the JavaScript Section

At the **BOTTOM** of the `all.php` file, you'll find the closing `</script>` tag

The file should end with something like:

```javascript
        }

    });
</script>

?>
```

### ADD THIS CODE BEFORE `</script>`

**Find this closing tag:**
```javascript
    });
</script>
```

**CHANGE TO:**
```javascript
    });

    // ===== KEYBOARD SHORTCUTS IMPLEMENTATION =====
    // Alt + U = Add New User
    // Alt + F = Filter Users
    // Alt + E = Export Users
    // Alt + I = Import Users
    // Alt + D = Mass Delete
    // Ctrl + Shift + ? = Show Help
    $(document).on('keydown', function(e) {
        // ALT + U: Open Add New User Modal
        if (e.altKey && e.keyCode === 85) {
            e.preventDefault();
            $('.add-new-user').trigger('click');
            showNotification('info', 'Opening Add New User dialog...', 2000);
        }
        
        // ALT + F: Open Filter Modal
        if (e.altKey && e.keyCode === 70) {
            e.preventDefault();
            $('.users-filter-btn').trigger('click');
            showNotification('info', 'Opening Filter dialog...', 2000);
        }
        
        // ALT + E: Export Users
        if (e.altKey && e.keyCode === 69) {
            e.preventDefault();
            // Find the export button and click it
            var exportBtn = $('a.exportBtn, button.btn-export, .export-users-btn').first();
            if (exportBtn.length) {
                exportBtn.click();
                showNotification('success', 'Exporting users...', 2000);
            } else {
                showNotification('warning', 'Export button not found', 2000);
            }
        }
        
        // ALT + I: Import Users
        if (e.altKey && e.keyCode === 73) {
            e.preventDefault();
            var importBtn = $('a:contains("Import"), button:contains("Import")').first();
            if (importBtn.length) {
                importBtn.click();
                showNotification('success', 'Opening Import dialog...', 2000);
            } else {
                showNotification('warning', 'Import button not found', 2000);
            }
        }
        
        // ALT + D: Mass Delete
        if (e.altKey && e.keyCode === 68) {
            e.preventDefault();
            var deleteBtn = $('.mass-payment-confirm').first();
            if (deleteBtn.length) {
                deleteBtn.trigger('click');
                showNotification('warning', 'Opening Mass Delete dialog...', 2000);
            } else {
                showNotification('warning', 'Mass Delete button not found', 2000);
            }
        }
        
        // CTRL + SHIFT + ?: Show Keyboard Shortcuts Help
        if (e.ctrlKey && e.shiftKey && e.keyCode === 191) {
            e.preventDefault();
            showKeyboardShortcutsHelp();
        }
    });

    // Function to show keyboard shortcuts help
    function showKeyboardShortcutsHelp() {
        var helpMessage = '<div style="text-align: left; padding: 15px;">' +
            '<h4 style="color: #333; margin-top: 0;">⌨️ Keyboard Shortcuts</h4>' +
            '<table style="width: 100%; border-collapse: collapse;">' +
            '<tr style="border-bottom: 1px solid #ddd;">' +
            '<td style="padding: 8px;"><strong>Alt + U</strong></td>' +
            '<td style="padding: 8px;">Add New User</td>' +
            '</tr>' +
            '<tr style="border-bottom: 1px solid #ddd;">' +
            '<td style="padding: 8px;"><strong>Alt + F</strong></td>' +
            '<td style="padding: 8px;">Filter Users</td>' +
            '</tr>' +
            '<tr style="border-bottom: 1px solid #ddd;">' +
            '<td style="padding: 8px;"><strong>Alt + E</strong></td>' +
            '<td style="padding: 8px;">Export Users</td>' +
            '</tr>' +
            '<tr style="border-bottom: 1px solid #ddd;">' +
            '<td style="padding: 8px;"><strong>Alt + I</strong></td>' +
            '<td style="padding: 8px;">Import Users</td>' +
            '</tr>' +
            '<tr style="border-bottom: 1px solid #ddd;">' +
            '<td style="padding: 8px;"><strong>Alt + D</strong></td>' +
            '<td style="padding: 8px;">Mass Delete</td>' +
            '</tr>' +
            '<tr>' +
            '<td style="padding: 8px;"><strong>Ctrl + Shift + ?</strong></td>' +
            '<td style="padding: 8px;">Show This Help</td>' +
            '</tr>' +
            '</table>' +
            '</div>';
        
        $.alert({
            title: '⌨️ Keyboard Shortcuts Help',
            content: helpMessage,
            type: 'blue',
            buttons: {
                ok: function() {
                    // Close alert
                }
            }
        });
    }

    // Function to show notifications
    function showNotification(type, message, duration) {
        $.notify({
            message: message
        }, {
            type: type,
            placement: {
                from: 'top',
                align: 'right'
            },
            autoHide: true,
            animate: {
                enter: 'animated fadeInDown',
                exit: 'animated fadeOutUp'
            },
            delay: duration
        });
    }

    // ================================================
});
</script>
```

---

## 📋 COMPLETE FILE STRUCTURE EXAMPLE

Here's how the end of your all.php file should look:

```php
<?php
    // ... existing PHP code ...
?>

<!-- HTML Content -->
<div class="right_col">
    <!-- ... page content ... -->
    
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>On/Off</th>
                <th data-field="acctstoptime" data-sortable="true">Last Logoff</th>
                <th>Expiry</th>
            </tr>
        </thead>
    </table>
</div>

<!-- JavaScript Section -->
<script>
    $(document).ready(function() {
        // ... existing code ...
        
        // Existing user selection code, filters, etc.
        $(document).on('click', '.checkedAll input', function() {
            // ... existing code ...
        });
        
        // ... more existing code ...
        
        // ===== KEYBOARD SHORTCUTS IMPLEMENTATION =====
        $(document).on('keydown', function(e) {
            // ALT + U: Open Add New User Modal
            if (e.altKey && e.keyCode === 85) {
                e.preventDefault();
                $('.add-new-user').trigger('click');
                showNotification('info', 'Opening Add New User dialog...', 2000);
            }
            
            // ALT + F: Open Filter Modal
            if (e.altKey && e.keyCode === 70) {
                e.preventDefault();
                $('.users-filter-btn').trigger('click');
                showNotification('info', 'Opening Filter dialog...', 2000);
            }
            
            // ALT + E: Export Users
            if (e.altKey && e.keyCode === 69) {
                e.preventDefault();
                var exportBtn = $('a.exportBtn, button.btn-export, .export-users-btn').first();
                if (exportBtn.length) {
                    exportBtn.click();
                    showNotification('success', 'Exporting users...', 2000);
                } else {
                    showNotification('warning', 'Export button not found', 2000);
                }
            }
            
            // ALT + I: Import Users
            if (e.altKey && e.keyCode === 73) {
                e.preventDefault();
                var importBtn = $('a:contains("Import"), button:contains("Import")').first();
                if (importBtn.length) {
                    importBtn.click();
                    showNotification('success', 'Opening Import dialog...', 2000);
                } else {
                    showNotification('warning', 'Import button not found', 2000);
                }
            }
            
            // ALT + D: Mass Delete
            if (e.altKey && e.keyCode === 68) {
                e.preventDefault();
                var deleteBtn = $('.mass-payment-confirm').first();
                if (deleteBtn.length) {
                    deleteBtn.trigger('click');
                    showNotification('warning', 'Opening Mass Delete dialog...', 2000);
                } else {
                    showNotification('warning', 'Mass Delete button not found', 2000);
                }
            }
            
            // CTRL + SHIFT + ?: Show Keyboard Shortcuts Help
            if (e.ctrlKey && e.shiftKey && e.keyCode === 191) {
                e.preventDefault();
                showKeyboardShortcutsHelp();
            }
        });

        function showKeyboardShortcutsHelp() {
            var helpMessage = '<div style="text-align: left; padding: 15px;">' +
                '<h4 style="color: #333; margin-top: 0;">⌨️ Keyboard Shortcuts</h4>' +
                '<table style="width: 100%; border-collapse: collapse;">' +
                '<tr style="border-bottom: 1px solid #ddd;">' +
                '<td style="padding: 8px;"><strong>Alt + U</strong></td>' +
                '<td style="padding: 8px;">Add New User</td>' +
                '</tr>' +
                '<tr style="border-bottom: 1px solid #ddd;">' +
                '<td style="padding: 8px;"><strong>Alt + F</strong></td>' +
                '<td style="padding: 8px;">Filter Users</td>' +
                '</tr>' +
                '<tr style="border-bottom: 1px solid #ddd;">' +
                '<td style="padding: 8px;"><strong>Alt + E</strong></td>' +
                '<td style="padding: 8px;">Export Users</td>' +
                '</tr>' +
                '<tr style="border-bottom: 1px solid #ddd;">' +
                '<td style="padding: 8px;"><strong>Alt + I</strong></td>' +
                '<td style="padding: 8px;">Import Users</td>' +
                '</tr>' +
                '<tr style="border-bottom: 1px solid #ddd;">' +
                '<td style="padding: 8px;"><strong>Alt + D</strong></td>' +
                '<td style="padding: 8px;">Mass Delete</td>' +
                '</tr>' +
                '<tr>' +
                '<td style="padding: 8px;"><strong>Ctrl + Shift + ?</strong></td>' +
                '<td style="padding: 8px;">Show This Help</td>' +
                '</tr>' +
                '</table>' +
                '</div>';
            
            $.alert({
                title: '⌨️ Keyboard Shortcuts Help',
                content: helpMessage,
                type: 'blue',
                buttons: {
                    ok: function() {
                        // Close alert
                    }
                }
            });
        }

        function showNotification(type, message, duration) {
            $.notify({
                message: message
            }, {
                type: type,
                placement: {
                    from: 'top',
                    align: 'right'
                },
                autoHide: true,
                animate: {
                    enter: 'animated fadeInDown',
                    exit: 'animated fadeOutUp'
                },
                delay: duration
            });
        }
        // ================================================
    });
</script>

?>
```

---

## 🎯 TESTING KEYBOARD SHORTCUTS

### Test Each Shortcut:

| Shortcut | Action | Result |
|----------|--------|--------|
| Alt + U | Add New User | Modal opens |
| Alt + F | Filter Users | Filter modal opens |
| Alt + E | Export Users | Export process starts |
| Alt + I | Import Users | Import modal opens |
| Alt + D | Mass Delete | Delete modal opens |
| Ctrl + Shift + ? | Show Help | Help dialog shows |

### How to Test:

1. Go to Users > All Users page
2. Press **Alt + U**
3. Verify the "Add New User" modal opens
4. Close the modal
5. Press **Ctrl + Shift + ?**
6. Verify the help dialog shows all shortcuts

---

## 🔍 FINDING HTML COLUMNS IN your all.php

The file provided was mostly JavaScript. To find the actual table headers, look for:

```html
<thead>
    <tr>
        <!-- Column headers here -->
    </tr>
</thead>
```

Or search for these common column names:
- `<th>ID</th>`
- `<th>Username</th>`
- `<th>Phone</th>`
- `<th>On/Off</th>`
- `<th>Expiry</th>`

If the table is generated by DataTables JavaScript initialization, look for the `columns` array in the JavaScript:

```javascript
$('#userTable').DataTable({
    columns: [
        { data: 'id', title: 'ID' },
        { data: 'username', title: 'Username' },
        // ... etc
    ]
});
```

---

## ⚙️ KEYBOARD SHORTCUT CUSTOMIZATION

### To Change Shortcut Keys:

Find this line and modify the key code:
```javascript
if (e.altKey && e.keyCode === 85) {  // 85 = U
```

**Common Key Codes:**
- A = 65
- D = 68
- E = 69
- F = 70
- I = 73
- U = 85
- T = 84
- P = 80

### To Change Notification Message:

Find this line:
```javascript
showNotification('info', 'Opening Add New User dialog...', 2000);
```

Change the message text to whatever you want.

### To Change Notification Duration:

The last number is milliseconds:
```javascript
showNotification('info', 'Message', 2000);  // 2000ms = 2 seconds
```

---

## 🚨 TROUBLESHOOTING

### Issue: Keyboard shortcuts not working
- ✅ Check browser console for JavaScript errors (F12)
- ✅ Verify all opening and closing braces `{` and `}`
- ✅ Verify the script is inside `$(document).ready(function() {`
- ✅ Make sure `$.alert` and `$.notify` are available (jQuery libraries loaded)

### Issue: Table column doesn't appear
- ✅ Verify you added the `<th>` header in the correct table
- ✅ Check if there's a `<tbody>` that needs updating too
- ✅ Clear browser cache (Ctrl + F5)

### Issue: Sorting not working on new column
- ✅ Make sure `data-sortable="true"` is in the `<th>` tag
- ✅ Verify the column exists in the PHP controller's `$columns` array
- ✅ Check browser console for JavaScript errors

### Issue: Notifications not showing
- ✅ Verify the jQuery Notify library is included
- ✅ Check if notifications are disabled in browser
- ✅ Try simplifying the notification code to use `alert()` instead

---

## 💾 BACKUP COMMAND

Before making changes:
```bash
cp /var/www/html/application/views/themes/legacy/admin_portal/users/all.php /var/www/html/application/views/themes/legacy/admin_portal/users/all.php.backup
```

To restore:
```bash
cp /var/www/html/application/views/themes/legacy/admin_portal/users/all.php.backup /var/www/html/application/views/themes/legacy/admin_portal/users/all.php
```

---

## ✨ SUMMARY OF CHANGES

✅ **Added 1 table header column** - "Last Logoff"
✅ **Added keyboard shortcuts** - 6 quick access keys
✅ **Added help dialog** - Shows all shortcuts
✅ **Added notifications** - User feedback for shortcuts
✅ **Clean code** - Proper indentation and comments
✅ **No conflicts** - Uses existing jQuery plugins

---

**Version:** 1.0
**Last Updated:** 2026
**Author:** Haxill

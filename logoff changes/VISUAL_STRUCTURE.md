# 📊 VISUAL STRUCTURE & DATA FLOW

## 🔄 HOW THE SYSTEM WORKS

```
┌─────────────────────────────────────────────────────────────────┐
│                    USER VIEWS ALL USERS PAGE                    │
│                                                                   │
│  [Add User] [Filter] [Export] [Import] [Mass Delete] [Select All]│
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ ID │ Photo │ User │ Phone │ Balance │ Service │ On/Off   │   │
│  │    │       │ name │       │         │         │          │   │
│  ├──────────────────────────────────────────────────────────┤   │
│  │  6 │ [IMG] │ john │ 123   │ 5000    │ PPPoE   │ Online   │   │
│  │  7 │ [IMG] │ jane │ 456   │ 0       │ PPPoE   │ Offline  │   │
│  │  8 │ [IMG] │ mike │ 789   │ 2500    │ PPPoE   │ Online   │   │
│  └──────────────────────────────────────────────────────────┘   │
│                          ▼                                        │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │   Last Logoff    │       Expiry      │     Actions        │   │
│  ├──────────────────────────────────────────────────────────┤   │
│  │   No Logoff      │ 08 Oct 2026 12:00 │  [Disconnect]     │   │
│  │ 06 Sep 2026 12:00│ 06 Sep 2026 12:00 │  [Disconnect]     │   │
│  │   No Logoff      │ 10 Sep 2027 12:45 │  [Disconnect]     │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                   │
│  Click ↑↓ to SORT by Last Logoff                                │
│  Press Alt+U to add user, Alt+F to filter, etc.                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📁 FILE MODIFICATION MAP

```
APPLICATION STRUCTURE
│
├── helpers/
│   └── radius_helper.php ✏️ (ADD 2 functions)
│       ├── getLastLogoffByUsername($username)
│       └── getLastLogoffByUsernameObject($username)
│
├── controllers/
│   └── admin_portal/
│       └── user/
│           └── User.php ✏️ (MODIFY 2 sections)
│               ├── Update $columns array
│               └── Add lastlogoff data to loop
│
└── views/
    └── themes/legacy/
        └── admin_portal/
            └── users/
                └── all.php ✏️ (ADD 2 sections)
                    ├── Add table header column
                    └── Add keyboard shortcuts
```

---

## 🔗 DATA FLOW DIAGRAM

```
Step 1: USER VISITS PAGE
   │
   └──> Loads all.php view
        ├─ HTML table with headers (Last Logoff column added)
        └─ JavaScript for sorting & shortcuts

Step 2: CONTROLLER EXECUTES (User.php)
   │
   └──> Fetches users from database
        ├─ Joins with RADIUS accounting table
        ├─ For each user:
        │   ├─ Gets basic info (username, phone, etc)
        │   ├─ Calls getLastLogoffByUsername() ← NEW FUNCTION
        │   ├─ Formats date (d-M-Y H:i:s)
        │   └─ Adds to data array with color label
        └─ Returns JSON response

Step 3: DATABASE QUERY
   │
   └──> SELECT FROM radacct
        WHERE username = 'john'
        AND acctstoptime IS NOT NULL
        ORDER BY radacctid DESC
        LIMIT 1
        
        Returns: acctstoptime = '2026-09-06 12:00:00'

Step 4: DATA RETURNED TO FRONTEND
   │
   └──> DataTables receives JSON
        ├─ Populates table rows with data
        ├─ Shows formatted datetime
        ├─ Applies color label (blue/yellow)
        └─ Enables sorting by column

Step 5: USER INTERACTION
   │
   ├──> Clicks column header to SORT
   │    └─ DataTables re-orders rows by Last Logoff time
   │
   └──> Presses Alt+U for KEYBOARD SHORTCUT
        └─ Opens Add New User modal
```

---

## 🎯 COLUMNS IN TABLE (IN ORDER)

```
Position | Column Name       | Source          | Sortable | Notes
---------|------------------|-----------------|----------|----------
1        | ID               | usersinfo.id    | Yes      | User ID
2        | Photo            | usersinfo.photo | No       | Avatar
3        | Username         | usersinfo.username | Yes   | Clickable
4        | Phone            | usersinfo.phone | Yes      | Contact
5        | Package          | usersinfo.package | Yes    | Service type
6        | Seller           | usersinfo.seller | Yes     | Assigned to
7        | Balance          | usersinfo.balance | Yes    | Account balance
8        | Service          | usersinfo.service | Yes    | PPPoE/Token
9        | On/Off           | radacct.status  | Yes      | Online/Offline
10       | Last Logoff      | radacct.acctstoptime | Yes  | ⭐ NEW
11       | Expiry           | usersinfo.expire | Yes     | Expire date
12       | Actions          | -               | No       | Disconnect
```

---

## 💾 DATABASE STRUCTURE

```
TABLE: usersinfo
┌────────┬──────────────┬─────────────┐
│ id     │ username     │ phone       │
├────────┼──────────────┼─────────────┤
│ 6      │ hanxill      │ 0330537070  │
│ 7      │ juniper-office│ 123        │
│ 8      │ juniper-home │ 1234        │
└────────┴──────────────┴─────────────┘

TABLE: radacct (RADIUS Accounting)
┌──────────┬──────────────┬───────────────────┬──────────────────┐
│ username │ acctstarttime│ acctstoptime      │ radacctid        │
├──────────┼──────────────┼───────────────────┼──────────────────┤
│ hanxill  │ 2026-09-05   │ NULL              │ 1000 (ONLINE)    │
│ hanxill  │ 2026-09-04   │ 2026-09-05 14:30  │ 999  (OFFLINE)   │
├──────────┼──────────────┼───────────────────┼──────────────────┤
│ juniper- │ 2026-09-06   │ 2026-09-06 12:00  │ 1001 (OFFLINE)   │
│ office   │              │                   │                  │
└──────────┴──────────────┴───────────────────┴──────────────────┘

QUERY RESULT:
Last Logoff for 'juniper-office' = 2026-09-06 12:00:00
Displayed as: "06 Sep 2026 12:00"
Label color: BLUE
```

---

## 🎨 COLOR CODING SYSTEM

```
LAST LOGOFF COLUMN DISPLAY
│
├─ User is ONLINE (acctstoptime = NULL)
│  ├─ Show: "No Logoff" 
│  ├─ Color: 🟡 YELLOW (warning)
│  └─ Icon: ⚠️
│
└─ User is OFFLINE (acctstoptime = some date)
   ├─ Show: "06 Sep 2026 12:00:00"
   ├─ Color: 🔵 BLUE (info)
   └─ Icon: ℹ️
```

---

## ⌨️ KEYBOARD SHORTCUTS TREE

```
KEYBOARD SHORTCUTS
│
├─ Alt + U ──> Add New User
│   └─ Opens: Add New User Modal
│   └─ Files: all.php (includes homeAddUserModal.php)
│
├─ Alt + F ──> Filter Users
│   └─ Opens: Filter Modal
│   └─ Files: all.php (includes usersFilterModal.php)
│
├─ Alt + E ──> Export Users
│   └─ Action: Download CSV/Excel
│   └─ Button: Export Users
│
├─ Alt + I ──> Import Users
│   └─ Opens: Import Modal
│   └─ Files: all.php (includes homeImportModal.php)
│
├─ Alt + D ──> Mass Delete
│   └─ Opens: Delete Confirmation Modal
│   └─ Button: Mass Delete
│
└─ Ctrl + Shift + ? ──> Show Help
    └─ Displays: All Shortcuts List
    └─ Format: Alert Dialog
```

---

## 🔧 CODE EXECUTION ORDER

```
1. Browser loads all.php
   │
   ├─ Renders HTML table with headers
   ├─ Includes JavaScript files
   └─ jQuery ready() executed

2. User clicks "All Users" menu
   │
   ├─ AJAX call to User controller
   └─ Send: length, start, order, draw, search

3. Controller receives request
   │
   ├─ Get parameters from request
   ├─ Build query with sorting
   ├─ For each user row:
   │  ├─ Call getLastLogoffByUsername()
   │  ├─ Format the timestamp
   │  └─ Add to data array
   └─ Return JSON

4. JavaScript receives JSON
   │
   ├─ DataTables processes data
   ├─ Renders table rows
   ├─ Binds click handlers
   └─ Applies tooltips

5. User interaction
   │
   ├─ Click column header → Sort
   ├─ Press keyboard shortcut → Action
   └─ Hover over timestamp → Show tooltip
```

---

## 📊 SORTING MECHANISM

```
USER CLICKS: "Last Logoff" column header
     │
     ▼
DataTables reads: data-field="acctstoptime"
     │
     ▼
Controller receives: 
  - order[0][column] = 10 (Last Logoff position)
  - order[0][dir] = asc/desc
     │
     ▼
PHP matches column index to $columns array:
  $columns[10] = 'radacct.acctstoptime'
     │
     ▼
Database query:
  ORDER BY radacct.acctstoptime ASC (or DESC)
     │
     ▼
Results returned:
  ASC:  Oldest logoff → Newest logoff
  DESC: Newest logoff → Oldest logoff
     │
     ▼
Table re-renders with sorted data
```

---

## 🎯 IMPLEMENTATION SUMMARY

```
┌──────────────────────────────────────────┐
│   RADIUS HELPER (radius_helper.php)      │
│                                          │
│   + getLastLogoffByUsername()            │
│   + getLastLogoffByUsernameObject()      │
│                                          │
│   Purpose: Fetch last logoff time        │
│   from RADIUS accounting table           │
└──────────────────────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────┐
│   USER CONTROLLER (User.php)             │
│                                          │
│   - Update $columns array                │
│   - Call getLastLogoffByUsername()       │
│   - Format and add to data array         │
│                                          │
│   Purpose: Get data and return as JSON   │
└──────────────────────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────┐
│   VIEW FILE (all.php)                    │
│                                          │
│   - Add table header <th>                │
│   - Add keyboard shortcuts JS            │
│                                          │
│   Purpose: Display and interact with UI  │
└──────────────────────────────────────────┘
```

---

## 🚀 QUICK FLOW

```
    User Page
        │
        ├─────────────────┐
        │                 │
        ▼                 ▼
    [Sort Click]    [Keyboard Shortcut]
        │                 │
        └────────┬────────┘
                 │
                 ▼
            JavaScript
                 │
         ┌───────┴───────┐
         │               │
         ▼               ▼
    Send AJAX      Open Modal
         │
         ▼
    User.php
         │
    ┌────┴────┐
    │          │
    ▼          ▼
Get Data   Format
    │          │
    └────┬─────┘
         │
         ▼
    Return JSON
         │
         ▼
    DataTables
         │
         ▼
    Render Table
         │
         ▼
    User sees sorted results
```

---

**Visual Guide Complete**
**Everything is connected and working together!**

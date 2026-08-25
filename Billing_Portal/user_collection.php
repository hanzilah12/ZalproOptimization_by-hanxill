<?php
session_start();
require_once __DIR__ . '/session_guard.php';
zalpro_enforce_session_timeout();
if (!isset($_SESSION['custom_logged_in']) || $_SESSION['custom_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
$operator_name = $_SESSION['custom_name']; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Collections - Netpoint IT & Communications Pvt. Ltd</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- SweetAlert2 CSS & JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        body { 
            background-color: #f0f4f8;
            font-family: 'Poppins', sans-serif; 
            padding-bottom: 40px;
        }
        
        /* --- NAYI ANIMATIONS START --- */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(25px); }
            to { opacity: 1; transform: translateY(0); }
        }

        #userProfileCard:not(.d-none) {
            animation: fadeInUp 0.4s ease-out forwards;
        }

        .modern-card { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.04); 
            margin-bottom: 25px; 
            background: #ffffff; 
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
        }

        .modern-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(27,32,79,0.08);
        }

        .table-hover tbody tr {
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }
        
        .table-hover tbody tr:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            background-color: #f8fbff !important;
        }

        .modal.fade .modal-dialog {
            transition: transform 0.3s ease-out, opacity 0.3s ease-out;
            transform: scale(0.9);
        }
        
        .modal.show .modal-dialog {
            transform: scale(1);
        }
        /* --- NAYI ANIMATIONS END --- */

        .top-navbar {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            padding: 15px 25px;
            margin-bottom: 30px;
            margin-top: 20px;
        }
        
        .brand-title { font-size: 18px; font-weight: 700; color: #1b204f; margin: 0; }
        
        .table-custom th { background-color: #1b204f; color: #ffffff; font-weight: 500; font-size: 14px; border: none; padding: 12px 15px; }
        .table-custom th:first-child { border-top-left-radius: 10px; }
        .table-custom th:last-child { border-top-right-radius: 10px; }
        .table td { vertical-align: middle; font-size: 14px; padding: 12px 15px; border-bottom: 1px solid #f1f1f1; }
        
        .section-title { font-size: 18px; font-weight: 600; color: #1b204f; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        
        .search-group { box-shadow: 0 4px 12px rgba(32, 121, 176, 0.1); border-radius: 12px; overflow: hidden; }
        .search-group .form-control { border: none; padding: 15px 20px; font-size: 15px; }
        .search-group .form-control:focus { box-shadow: none; }
        
        .btn-gradient { background: linear-gradient(to right, #1b204f, #2079b0); color: white; border: none; transition: all 0.3s ease; }
        .btn-gradient:hover { background: linear-gradient(to right, #2079b0, #1b204f); color: white; transform: translateY(-1px); }
        
        #autocompleteList { position: absolute; z-index: 1000; width: 100%; max-height: 250px; overflow-y: auto; border-radius: 10px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-top: 5px; }
        .autocomplete-item { padding: 10px 20px; font-size: 14px; cursor: pointer; transition: background 0.2s; }
        .autocomplete-item.active, .autocomplete-item:hover { background-color: #f0f4f8; color: #1b204f; font-weight: 600; }
        
        .bg-light-blue { background-color: #e9f2ff !important; }
        .badge-operator { background: #2079b0; color: white; padding: 8px 15px; border-radius: 8px; font-weight: 500; font-size: 13px; }
        
        /* Customizing SweetAlert to match theme */
        .swal2-popup { font-family: 'Poppins', sans-serif !important; border-radius: 15px !important; }
        .swal2-confirm { border-radius: 8px !important; }
        .swal2-cancel { border-radius: 8px !important; }
    </style>
</head>
<body>

    <!-- Search Card -->
    <div class="card modern-card p-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10 position-relative">
                <h5 class="text-center mb-3 text-muted fw-medium" style="font-size: 15px;">Search Customer to Manage Billing</h5>
                <div class="input-group input-group-lg search-group">
                    <span class="input-group-text bg-white text-muted border-0 ps-4"><i class="bi bi-search"></i></span>
                    <input type="text" id="usernameInput" class="form-control" placeholder="Enter username e.g. hanxill" autocomplete="off">
                    <button id="searchBtn" class="btn btn-gradient px-4 fw-semibold">Find User</button>
                </div>
                <div id="autocompleteList" class="dropdown-menu bg-white"></div>
            </div>
        </div>
    </div>

    <!-- Main Profile Card -->
    <div id="userProfileCard" class="card modern-card p-4 d-none">
        
        <!-- Base Profile Info -->
        <h5 class="section-title"><i class="bi bi-person-vcard text-primary"></i> User Profile & Status</h5>
        <div class="table-responsive mb-5">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Package</th>
                        <th>Current Expiry</th>
                        <th>Monthly Fee</th>
                        <th>Remaining Balance</th>
                        <th>Connection Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="userProfileBody"></tbody>
            </table>
        </div>

        <!-- Monthly Ledger Section -->
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 border-bottom pb-2">
            <h5 class="section-title mb-0"><i class="bi bi-receipt text-danger"></i> Unpaid Monthly Bills (Ledger)</h5>
            <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
                <button id="addAmountBtn" class="btn btn-sm btn-dark fw-medium px-3">
                    <i class="bi bi-plus-circle me-1"></i> Add Amount
                </button>
            </div>
        </div>
        <div class="table-responsive mb-5">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light-blue text-dark" style="border-radius: 10px;">
                    <tr>
                        <th class="ps-3 border-0">Month / Year</th>
                        <th class="border-0">Due Amount</th>
                        <th class="border-0">Status</th>
                        <th class="text-end pe-3 border-0">Action</th>
                    </tr>
                </thead>
                <tbody id="unpaidBillsBody"></tbody>
            </table>
        </div>

        <!-- History Section -->
        <h5 class="section-title border-bottom pb-2 mb-3"><i class="bi bi-clock-history text-success"></i> Previous Payment History</h5>
        <div class="table-responsive">
            <table class="table table-hover table-striped text-center align-middle mb-0 border">
                <thead class="table-light text-secondary">
                    <tr>
                        <th>Invoice ID</th>
                        <th>Month / Year</th>
                        <th>Date & Time</th>
                        <th>Transaction ID</th>
                        <th>Amount Paid</th>
                        <th>Collected By</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="invoiceHistoryBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for Custom Expiry -->
<div class="modal fade" id="promiseModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 15px; border: none;">
      <div class="modal-header" style="background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); color: white; border-radius: 15px 15px 0 0;">
        <h5 class="modal-title fw-bold text-dark"><i class="bi bi-calendar-plus me-2"></i> Set Custom Expiry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="mb-3">
            <label class="form-label text-muted fw-semibold">Username</label>
            <input type="text" id="promiseUsername" class="form-control bg-light" readonly>
        </div>
        <div class="mb-3">
            <label class="form-label text-muted fw-semibold">Select New Expiry Date & Time</label>
            <input type="datetime-local" id="customExpiryDate" class="form-control border-warning">
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
        <button type="button" id="confirmPromiseBtn" class="btn btn-warning fw-bold text-dark px-4 shadow-sm">Apply Date</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal for Add Amount -->
<div class="modal fade" id="amountModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 15px; border: none;">
      <div class="modal-header" style="background: linear-gradient(135deg, #1b204f 0%, #2079b0 100%); color: white; border-radius: 15px 15px 0 0;">
        <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i> Add Amount to Account</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="mb-3">
            <label class="form-label text-muted fw-semibold">Amount Type</label>
            <div class="row g-2">
                <div class="col-6">
                    <div class="form-check border rounded p-3 h-100">
                        <input class="form-check-input" type="radio" name="amountType" id="monthlyAmountType" value="monthly" checked>
                        <label class="form-check-label fw-semibold" for="monthlyAmountType">
                            Monthly Bill
                            <small class="d-block text-muted fw-normal mt-1">Use the user's fixed monthly fee</small>
                        </label>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-check border rounded p-3 h-100">
                        <input class="form-check-input" type="radio" name="amountType" id="otherAmountType" value="other">
                        <label class="form-check-label fw-semibold" for="otherAmountType">
                            Other Amount
                            <small class="d-block text-muted fw-normal mt-1">Router, device, installation, etc.</small>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div id="monthlyAmountFields">
            <div class="mb-3">
                <label class="form-label text-muted fw-semibold">Billing Month</label>
                <input type="month" id="newBillMonth" class="form-control">
            </div>
            <div class="alert alert-info py-2 mb-0">
                <i class="bi bi-info-circle me-1"></i> Fixed monthly amount: <strong id="monthlyFixedAmount">Rs. 0</strong>
            </div>
        </div>

        <div id="otherAmountFields" class="d-none">
            <div class="mb-3">
                <label class="form-label text-muted fw-semibold">Custom Amount (Rs.)</label>
                <input type="number" id="otherAmount" class="form-control form-control-lg" min="0.01" step="0.01" placeholder="e.g. 8500">
            </div>
            <div class="mb-1">
                <label class="form-label text-muted fw-semibold">Description / Note</label>
                <input type="text" id="otherAmountText" class="form-control" maxlength="150" placeholder="e.g. Router Purchase">
                <small class="text-muted">This note will stay with the amount in the user's ledger.</small>
            </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmAddAmountBtn" class="btn btn-dark fw-bold px-4 shadow-sm">Add Amount</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal for Receive Specific Bill -->
<div class="modal fade" id="receiveModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 15px; border: none;">
      <div class="modal-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; border-radius: 15px 15px 0 0;">
        <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack me-2"></i> Receive Payment & Clear Bill</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <input type="hidden" id="modalBillId">
        <input type="hidden" id="modalMonths" value="1">
        <input type="hidden" id="modalPkgPrice" value="0">
        <input type="hidden" id="modalAdvanceBalance" value="0">
        
        <div class="mb-3">
            <label class="form-label text-muted fw-semibold">Collected By</label>
            <input type="text" id="modalStaffUser" class="form-control text-primary fw-bold bg-light" readonly>
        </div>
        <div class="row mb-3 g-2">
            <div class="col-6">
                <label class="form-label text-muted fw-semibold">Username</label>
                <input type="text" id="modalUsername" class="form-control bg-light" readonly>
            </div>
            <div class="col-6">
                <label class="form-label text-muted fw-semibold">Billing Month</label>
                <input type="text" id="modalMonth" class="form-control bg-light" readonly>
            </div>
        </div>
        <div class="mb-3 p-3 bg-light rounded border border-success border-opacity-25">
            <label class="form-label text-success fw-bold">Receiving Amount (Rs.)</label>
            <input type="number" id="modalAmount" class="form-control form-control-lg text-success fw-bold border-success" style="font-size: 24px;">
            <div id="smartPrompt"></div>
        </div>
        <div class="mb-2">
            <label class="form-label text-muted fw-semibold">Payment Method</label>
            <select id="modalMethod" class="form-select">
                <option value="Cash">Cash</option>
                <option value="EasyPaisa">EasyPaisa</option>
                <option value="Bank">Bank Transfer</option>
            </select>
        </div>
        <div class="mb-2 d-none" id="transactionIdWrap">
            <label class="form-label text-primary fw-semibold">Transaction ID / Reference No.</label>
            <input type="text" id="modalTransactionId" class="form-control border-primary" maxlength="100" placeholder="Enter transaction ID from online slip">
            <small class="text-muted">Required for EasyPaisa and Bank Transfer.</small>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmReceiveBtn" class="btn btn-success fw-bold px-4 shadow-sm">Confirm & Receive</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentUserData = null;
let searchTimeout;
let currentFocus = -1; 
let activeStaffUser = "System";

$(document).ready(function() {
    let today = new Date();
    let yyyy = today.getFullYear();
    let mm = String(today.getMonth() + 1).padStart(2, '0');
    $('#newBillMonth').val(`${yyyy}-${mm}`);

    $('#searchBtn').click(function() {
        $('#autocompleteList').hide();
        performSearch();
    });

    $('#usernameInput').on('keydown', function(e) {
        let items = $('.autocomplete-item');
        if (e.which == 40) { currentFocus++; addActive(items); } 
        else if (e.which == 38) { currentFocus--; addActive(items); } 
        else if (e.which == 13) {
            e.preventDefault();
            if (currentFocus > -1) { if (items.length > 0) items[currentFocus].click(); } 
            else { $('#autocompleteList').hide(); performSearch(); }
        }
    });

    $('#usernameInput').on('input', function() {
        clearTimeout(searchTimeout);
        let term = $(this).val().trim();
        currentFocus = -1;
        if(term.length === 0) { $('#autocompleteList').hide(); return; }

        searchTimeout = setTimeout(function() {
            $.post('backend_api.php', { action: 'autocomplete', term: term }, function(data) {
                let html = '';
                if(data && data.length > 0) {
                    data.forEach(function(item) { html += `<div class="autocomplete-item" data-username="${item.username}"><i class="bi bi-search text-muted me-2"></i>${item.username}</div>`; });
                    $('#autocompleteList').html(html).show();
                } else { $('#autocompleteList').hide(); }
            }, 'json');
        }, 200);
    });

    $(document).on('mouseover', '.autocomplete-item', function() {
        let items = $('.autocomplete-item');
        removeActive(items);
        currentFocus = items.index(this);
        $(this).addClass('active');
    });

    $(document).on('click', '.autocomplete-item', function() {
        $('#usernameInput').val($(this).data('username'));
        $('#autocompleteList').hide();
        performSearch();
    });

    $(document).click(function(e) {
        if(!$(e.target).closest('#usernameInput, #autocompleteList').length) { $('#autocompleteList').hide(); }
    });

    function addActive(items) {
        if (!items || items.length === 0) return false;
        removeActive(items);
        if (currentFocus >= items.length) currentFocus = 0;
        if (currentFocus < 0) currentFocus = (items.length - 1);
        let activeItem = $(items[currentFocus]);
        activeItem.addClass('active');
        let list = $('#autocompleteList');
        let itemTop = activeItem.position().top;
        if (itemTop < 0) list.scrollTop(list.scrollTop() + itemTop);
        else if (itemTop + activeItem.outerHeight() > list.innerHeight()) list.scrollTop(list.scrollTop() + itemTop + activeItem.outerHeight() - list.innerHeight());
    }
    function removeActive(items) { items.removeClass('active'); }
});

function performSearch() {
    let username = $('#usernameInput').val().trim();
    if(!username) { 
        Swal.fire({ icon: 'warning', title: 'Oops...', text: 'Please enter a username!' });
        return; 
    }

    $('#searchBtn').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>').prop('disabled', true);

    // CSS Animation reset trick
    $('#userProfileCard').removeClass('d-none').css('animation', 'none');
    $('#userProfileCard')[0].offsetHeight; 
    $('#userProfileCard').addClass('d-none').css('animation', '');

    $.post('backend_api.php', { action: 'search', username: username }, function(data) {
        $('#searchBtn').html('Find User').prop('disabled', false);

        if(data.error) { 
            Swal.fire({ icon: 'error', title: 'Not Found', text: data.error });
            $('#userProfileCard').addClass('d-none'); 
            return; 
        }
        currentUserData = data;
        
        if (data.logged_in_staff) { activeStaffUser = data.logged_in_staff; }

        renderUserTable(data);
        renderUnpaidBills(data.unpaid_bills, data.price);
        renderInvoiceTable(data.invoices);
        $('#userProfileCard').removeClass('d-none');
    }, 'json').fail(function() { 
        $('#searchBtn').html('Find User').prop('disabled', false);
        Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to fetch user data from server.' });
    });
}

function renderUserTable(user) {
    let pkgName = user.package_name ? user.package_name : ('Package ID: ' + user.package);
    let expiryDate = user.formatted_expiry ? user.formatted_expiry : user.current_expiration_date;
    let dueAmount = user.price !== undefined ? user.price : 0;
    
    let balanceHtml = '<span class="badge bg-success px-2 py-1">Rs. 0</span>';
    if (user.remaining_balance > 0) {
        balanceHtml = `<span class="badge bg-danger px-2 py-1 shadow-sm">Rs. ${user.remaining_balance}</span>`;
    }
    let walletBadge = '';
    if (user.advance_balance > 0) {
        walletBadge = `<br><span class="badge bg-info text-dark mt-2 shadow-sm border border-info"><i class="bi bi-wallet2 me-1"></i>Wallet: Rs. ${user.advance_balance}</span>`;
    }

    let html = `
        <tr>
            <td><strong class="text-primary fs-6">${user.username}</strong></td>
            <td><span class="text-dark fw-medium">${pkgName}</span></td>
            <td><span class="text-dark fw-bold bg-light px-2 py-1 rounded border">${expiryDate}</span></td>
            <td class="fw-medium">Rs. ${dueAmount}</td>
            <td>${balanceHtml}${walletBadge}</td>
            <td>${user.status_badge}</td>
            <td>
                <button class="btn btn-outline-warning btn-sm fw-bold w-100 text-dark shadow-sm" style="border-width: 2px;" onclick="openPromiseModal('${user.username}')">
                    <i class="bi bi-pencil-square"></i> Set Custom Expiry
                </button>
            </td>
        </tr>
    `;

    $('#userProfileBody').html(html);
}

function renderUnpaidBills(bills, defaultAmount) {
    if(!bills || bills.length === 0) {
        $('#unpaidBillsBody').html('<tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-check-circle text-success fs-4 d-block mb-2"></i>All clear! No unpaid monthly bills found.</td></tr>');
        return;
    }
    
    let html = '';
    let totalDues = 0;
    let monthsList = [];

    bills.forEach(function(bill) {
        let due = parseFloat(bill.amount);
        totalDues += due;
        monthsList.push(bill.billing_month);

        let statusBadge = (bill.status === 'partial') ? '<span class="badge bg-warning text-dark px-2 py-1">Partial</span>' : '<span class="badge bg-danger px-2 py-1">Unpaid</span>';
        let isCustom = String(bill.billing_month || '').toUpperCase().indexOf('OTHER:') === 0;
        let displayLabel = isCustom
            ? '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 me-2">Other</span>' + String(bill.billing_month).substring(6)
            : bill.billing_month;
        
        html += `
            <tr>
                <td class="fw-bold text-dark ps-3">${displayLabel}</td>
                <td class="text-danger fw-bold">Rs. ${bill.amount}</td>
                <td>${statusBadge}</td>
                <td class="text-end pe-3">
                    <button class="btn btn-outline-danger btn-sm fw-bold px-3" onclick="deleteBill('${bill.id}', '${bill.billing_month}')">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </td>
            </tr>
        `;
    });

    let advBal = currentUserData.advance_balance || 0;
    let monthsStr = monthsList.join(', ');

    html += `
        <tr class="table-light">
            <td class="text-end fw-bold text-dark align-middle">Total Dues:</td>
            <td class="fw-bold text-danger fs-5 align-middle">Rs. ${totalDues}</td>
            <td colspan="2" class="text-end pe-3">
                <button class="btn btn-success fw-bold px-4 shadow-sm" onclick="openReceiveModal('bulk', '${currentUserData.username}', '${monthsStr}', '${totalDues}', ${defaultAmount}, ${advBal})">
                    <i class="bi bi-check2-circle me-1"></i> Pay Total Dues
                </button>
            </td>
        </tr>
    `;

    $('#unpaidBillsBody').html(html);
}

function renderInvoiceTable(invoices) {
    if(!invoices || invoices.length === 0) {
        $('#invoiceHistoryBody').html('<tr><td colspan="8" class="text-center text-muted py-4">No previous invoice history found.</td></tr>');
        return;
    }
    let html = '';
    invoices.forEach(function(inv) {
        let invDate = inv.formatted_datetime ? inv.formatted_datetime : inv.datetime;
        let monthYear = inv.month_year ? inv.month_year : 'N/A'; 
        let addedBy = inv.added_by ? inv.added_by : 'System';

        html += `
            <tr>
                <td><a href="/accounting/invoice/view/${inv.id}" target="_blank" class="fw-bold text-decoration-none badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">#${inv.id}</a></td>
                <td class="fw-bold text-dark">${monthYear}</td>
                <td class="text-muted" style="font-size: 13px;">${invDate}</td>
                <td style="font-size: 13px;">${inv.transaction_id || 'N/A'}</td>
                <td class="fw-medium text-success">Rs. ${inv.amount}</td>
                <td><span class="badge bg-secondary px-2">${addedBy}</span></td>
                <td><span class="badge bg-success bg-opacity-75"><i class="bi bi-check-circle me-1"></i>Paid</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-danger fw-bold shadow-sm" onclick="revertPayment('${inv.id}', '${currentUserData.username}')" title="Revert to Unpaid">
                        <i class="bi bi-arrow-counterclockwise"></i> Revert
                    </button>
                </td>
            </tr>
        `;
    });
    $('#invoiceHistoryBody').html(html);
}

function revertPayment(invoiceId, username) {
    Swal.fire({
        title: '103.170.179.155 says',
        html: `Are you sure you want to REVERT this payment?<br><br>
               <div style="text-align: left; font-size: 14px; background: #f8f9fa; padding: 15px; border-radius: 8px;">
               - Invoice #${invoiceId} will be deleted.<br>
               - Bill status will change to Unpaid.<br>
               - User's expiry date will be reversed.
               </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Revert it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('backend_api.php', { action: 'revert_payment', invoice_id: invoiceId, username: username }, function(res) {
                if (res.success) {
                    Swal.fire('Reverted!', 'Payment successfully reverted. The bill is now unpaid.', 'success');
                    performSearch(); 
                } else { 
                    Swal.fire('Error', 'Error reversing payment: ' + res.error, 'error'); 
                }
            }, 'json').fail(function() {
                Swal.fire('Error', 'Failed to connect to the server.', 'error');
            });
        }
    });
}

$('#addAmountBtn').click(function() {
    if (!currentUserData) {
        Swal.fire({ icon: 'warning', title: 'Select User First', text: 'Please search and select a customer first.' });
        return;
    }

    let today = new Date();
    let yyyy = today.getFullYear();
    let mm = String(today.getMonth() + 1).padStart(2, '0');
    $('#newBillMonth').val(`${yyyy}-${mm}`);
    $('#monthlyFixedAmount').text('Rs. ' + (currentUserData.price !== undefined ? currentUserData.price : 0));
    $('#otherAmount').val('');
    $('#otherAmountText').val('');
    $('#monthlyAmountType').prop('checked', true);
    $('#monthlyAmountFields').removeClass('d-none');
    $('#otherAmountFields').addClass('d-none');

    new bootstrap.Modal(document.getElementById('amountModal')).show();
});

$('input[name="amountType"]').on('change', function() {
    const isMonthly = $(this).val() === 'monthly';
    $('#monthlyAmountFields').toggleClass('d-none', !isMonthly);
    $('#otherAmountFields').toggleClass('d-none', isMonthly);
});

$('#confirmAddAmountBtn').click(function() {
    if (!currentUserData) return;

    const type = $('input[name="amountType"]:checked').val();
    const btn = $(this);
    let payload = { action: type === 'monthly' ? 'generate_bill' : 'add_custom_bill', username: currentUserData.username };

    if (type === 'monthly') {
        const monthInput = $('#newBillMonth').val();
        if (!monthInput) {
            Swal.fire({ icon: 'warning', title: 'Required', text: 'Select a billing month first!' });
            return;
        }
        const dateObj = new Date(monthInput + '-01T00:00:00');
        const monthStr = dateObj.toLocaleString('en-US', { month: 'short' }) + ' ' + dateObj.getFullYear();
        payload.month = monthStr;
    } else {
        const amount = parseFloat($('#otherAmount').val()) || 0;
        const description = $('#otherAmountText').val().trim();
        if (amount <= 0) {
            Swal.fire({ icon: 'warning', title: 'Invalid Amount', text: 'Enter a valid custom amount.' });
            return;
        }
        if (!description) {
            Swal.fire({ icon: 'warning', title: 'Description Required', text: 'Enter a short description such as Router Purchase.' });
            return;
        }
        payload.amount = amount;
        payload.description = description;
    }

    btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Adding...');

    $.post('backend_api.php', payload, function(res) {
        btn.prop('disabled', false).html('Add Amount');
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('amountModal')).hide();
            Swal.fire({ icon: 'success', title: 'Added!', text: res.message || 'Amount added to the user ledger.', timer: 1500, showConfirmButton: false });
            performSearch();
        } else {
            Swal.fire('Error', res.error || 'Failed to add amount.', 'error');
        }
    }, 'json').fail(function() {
        btn.prop('disabled', false).html('Add Amount');
        Swal.fire('Error', 'Failed to connect to server.', 'error');
    });
});

$('#modalMethod').on('change', function() {
    const online = $(this).val() === 'EasyPaisa' || $(this).val() === 'Bank';
    $('#transactionIdWrap').toggleClass('d-none', !online);
    if (!online) $('#modalTransactionId').val('');
});

function openPromiseModal(username) {
    $('#promiseUsername').val(username);
    let currentExp = currentUserData.current_expiration_date; 
    if (currentExp && currentExp !== '0000-00-00 00:00:00' && currentExp !== null) {
        let parts = currentExp.split(' ');
        if (parts.length === 2) {
            let formattedDate = parts[0] + 'T' + parts[1].substring(0, 5);
            $('#customExpiryDate').val(formattedDate);
        }
    } else {
        let now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        $('#customExpiryDate').val(now.toISOString().slice(0, 16));
    }

    let myModal = new bootstrap.Modal(document.getElementById('promiseModal'));
    myModal.show();
}

$('#confirmPromiseBtn').off('click').on('click', function() {
    let username = $('#promiseUsername').val();
    let newDate = $('#customExpiryDate').val();
    
    if(!newDate) {
        Swal.fire({ icon: 'warning', title: 'Required', text: 'Please select a date and time from the calendar!' });
        return;
    }

    let mysqlDate = newDate.replace('T', ' ') + ':00';
    let btn = $(this);
    btn.prop('disabled', true).text('Applying...');

    $.post('backend_api.php', { action: 'custom_expiry', username: username, new_date: mysqlDate }, function(res) {
        btn.prop('disabled', false).text('Apply Date');
        if(res.success) {
            bootstrap.Modal.getInstance(document.getElementById('promiseModal')).hide();
            Swal.fire({ icon: 'success', title: 'Updated!', text: 'Custom expiry date has been applied.', timer: 1500, showConfirmButton: false });
            performSearch();
        } else { 
            Swal.fire('Error', res.error, 'error'); 
        }
    }, 'json');
});

function openReceiveModal(billId, username, monthStr, billAmount, pkgPrice, advanceBalance) {
    billAmount = parseFloat(billAmount); 
    
    $('#modalBillId').val(billId);
    $('#modalStaffUser').val(activeStaffUser);
    $('#modalUsername').val(username);
    $('#modalMonth').val(monthStr); 
    $('#modalPkgPrice').val(pkgPrice);
    $('#modalAdvanceBalance').val(advanceBalance || 0);
    $('#modalMonths').val(1);
    $('#modalTransactionId').val('');
    $('#modalMethod').val('Cash').trigger('change');
    $('#smartPrompt').html('');
    
    let initialAmount = billAmount; 
    
    if (advanceBalance > 0) {
        let payable = billAmount - advanceBalance;
        if (payable <= 0) {
            initialAmount = 0;
            $('#smartPrompt').html(`<div class="alert alert-success py-2 mt-3 mb-0 border-0 shadow-sm" style="font-size:13px;"><i class="bi bi-info-circle me-1"></i>Rs. ${billAmount} will be paid from Wallet. Collect Rs. 0.</div>`);
        } else {
            initialAmount = payable;
            $('#smartPrompt').html(`<div class="alert alert-warning py-2 mt-3 mb-0 border-0 shadow-sm" style="font-size:13px;"><i class="bi bi-info-circle me-1"></i>Rs. ${advanceBalance} used from Wallet. Collect remaining Rs. ${payable}.</div>`);
        }
    } else {
        if (billId === 'bulk') {
            $('#smartPrompt').html(`<div class="alert alert-info py-2 mt-3 mb-0 fw-medium border-0 shadow-sm" style="font-size:13px;"><i class="bi bi-lightning-charge text-warning"></i> Smart Pay: Amount will automatically clear oldest bills first.</div>`);
        }
    }
    
    $('#modalAmount').val(initialAmount);
    let myModal = new bootstrap.Modal(document.getElementById('receiveModal'));
    myModal.show();
}

$('#modalAmount').on('input', function() {
    let entered = parseFloat($(this).val()) || 0;
    let pkgPrice = parseFloat($('#modalPkgPrice').val()) || 0;
    let advanceBal = parseFloat($('#modalAdvanceBalance').val()) || 0;
    let totalAvailable = entered + advanceBal;

    if (pkgPrice > 0 && totalAvailable >= (pkgPrice * 2)) {
        let possibleMonths = Math.floor(totalAvailable / pkgPrice);
        let extra = totalAvailable - (possibleMonths * pkgPrice);
        
        let html = `<div class="alert alert-info py-2 mt-3 mb-0 border-0 shadow-sm" style="font-size:13px;">
            <i class="bi bi-star-fill text-warning me-1"></i><b>Advance Detected!</b> Covers <b>${possibleMonths} months</b>. 
            ${extra > 0 ? `<br><small class="text-muted">(Rs. ${extra} will save to Wallet)</small>` : ''}
            <button type="button" class="btn btn-sm btn-dark mt-2 w-100 fw-medium" onclick="applyMonths(${possibleMonths})">Yes, Receive for ${possibleMonths} Months</button>
        </div>`;
        $('#smartPrompt').html(html);
    } else {
        let extra = totalAvailable - pkgPrice;
        if (extra > 0) {
            $('#smartPrompt').html(`<div class="alert alert-success py-2 mt-3 mb-0 border-0 shadow-sm" style="font-size:13px;"><i class="bi bi-wallet2 me-1"></i>Rs. ${extra} extra will be saved in User Wallet.</div>`);
        } else if (totalAvailable < pkgPrice && totalAvailable > 0) {
            $('#smartPrompt').html(`<div class="alert alert-danger py-2 mt-3 mb-0 border-0 shadow-sm" style="font-size:13px;"><i class="bi bi-exclamation-triangle me-1"></i>Warning: Partial payment (Short by Rs. ${pkgPrice - totalAvailable})</div>`);
        } else {
            $('#smartPrompt').html('');
        }
        $('#modalMonths').val(1);
    }
});

window.applyMonths = function(m) {
    $('#modalMonths').val(m);
    $('#smartPrompt').html(`<div class="alert alert-success py-2 mt-3 mb-0 border-0 shadow-sm" style="font-size:13px;"><i class="bi bi-check-circle-fill me-1"></i><i class="fw-bold">Confirmed!</i> Bill will be generated & Expiry extended for ${m} months.</div>`);
};

$('#confirmReceiveBtn').off('click').on('click', function() {
    let billId = $('#modalBillId').val();
    let username = $('#modalUsername').val();
    let amount = $('#modalAmount').val();
    let method = $('#modalMethod').val();
    let months = $('#modalMonths').val();
    let pkgPrice = $('#modalPkgPrice').val();
    let monthStr = $('#modalMonth').val();
    let transactionId = $('#modalTransactionId').val().trim();

    if ((method === 'EasyPaisa' || method === 'Bank') && !transactionId) {
        Swal.fire({ icon: 'warning', title: 'Transaction ID Required', text: 'Enter the transaction ID/reference number from the online payment slip.' });
        return;
    }

    let btn = $(this);
    btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm"></i> Processing...');

    $.post('backend_api.php', { 
        action: 'receive', 
        username: username, 
        amount: amount, 
        method: method, 
        bill_id: billId,
        months: months,
        pkg_price: pkgPrice,
        month_str: monthStr,
        transaction_id: transactionId
    }, function(res) {
        btn.prop('disabled', false).html('Confirm & Receive');
        if(res.success) {
            bootstrap.Modal.getInstance(document.getElementById('receiveModal')).hide();
            
            // AUTO-PRINT LOGIC: Backend must return res.invoice_id
            if (res.invoice_id) {
                window.open('/accounting/invoice/view/' + res.invoice_id, '_blank');
            }
            
            // MODERN POPUP
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Bill RECEIVED',
                confirmButtonColor: '#4CAF50',
                confirmButtonText: 'OK'
            }).then(() => {
                performSearch();
            });

        } else { 
            Swal.fire('Error', res.error, 'error'); 
        }
    }, 'json');
});

function deleteBill(billId, monthName) {
    Swal.fire({
        title: 'Delete Bill?',
        text: `Are you sure you want to DELETE the bill for ${monthName}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('backend_api.php', { action: 'delete_bill', bill_id: billId }, function(res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Bill has been deleted.', timer: 1500, showConfirmButton: false });
                    performSearch();
                } else {
                    Swal.fire('Error', 'Error deleting bill: ' + res.error, 'error');
                }
            }, 'json').fail(function() {
                Swal.fire('Error', 'Failed to connect to server.', 'error');
            });
        }
    });
}
</script>
<script src="session_timeout.js"></script>
</body>
</html>

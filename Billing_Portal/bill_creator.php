<?php
require_once __DIR__ . '/session_guard.php';
zalpro_enforce_session_timeout();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Bill Creator - Netpoint IT & Communications</title>
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
            padding: 30px;
        }
        
        .modern-card { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.04); 
            background: #ffffff;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: #1b204f;
            text-align: center;
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: #4a5568;
        }

        .btn-gradient {
            background: linear-gradient(to right, #1b204f, #2079b0);
            color: white;
            border: none;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-gradient:hover { 
            background: linear-gradient(to right, #2079b0, #1b204f); 
            color: white; 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(32, 121, 176, 0.3);
        }

        .alert-info-custom {
            background-color: #e9f2ff;
            border-left: 4px solid #2079b0;
            color: #1b204f;
            border-radius: 8px;
        }
        
        /* SweetAlert Customization */
        .swal2-popup { font-family: 'Poppins', sans-serif !important; border-radius: 15px !important; }
    
        /* Motion animations - visual only, existing functionality untouched */
        @keyframes pageFadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes iconFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        @keyframes softPulse {
            0%, 100% { box-shadow: 0 5px 20px rgba(0,0,0,0.04); }
            50% { box-shadow: 0 9px 28px rgba(32,121,176,0.10); }
        }

        .modern-card {
            animation: pageFadeUp 0.55s ease-out both;
        }

        .modern-card > .text-center {
            animation: pageFadeUp 0.55s ease-out 0.08s both;
        }

        .modern-card > .alert-info-custom {
            animation: pageFadeUp 0.55s ease-out 0.18s both;
        }

        .modern-card > .mb-4 {
            animation: pageFadeUp 0.55s ease-out 0.26s both;
        }

        #generateBulkBtn {
            animation: pageFadeUp 0.55s ease-out 0.34s both;
        }

        .modern-card .bi-receipt-cutoff {
            animation: iconFloat 2.8s ease-in-out 0.6s infinite;
        }

        .modern-card {
            animation-name: pageFadeUp, softPulse;
            animation-duration: 0.55s, 3.8s;
            animation-delay: 0s, 0.7s;
            animation-timing-function: ease-out, ease-in-out;
            animation-iteration-count: 1, infinite;
        }

        #generateBulkBtn {
            transition: transform 0.22s ease, box-shadow 0.22s ease, filter 0.22s ease;
        }

        #generateBulkBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(32, 121, 176, 0.25) !important;
            filter: brightness(1.03);
        }

        #generateBulkBtn:active {
            transform: translateY(0);
        }

        @media (prefers-reduced-motion: reduce) {
            .modern-card,
            .modern-card > .text-center,
            .modern-card > .alert-info-custom,
            .modern-card > .mb-4,
            #generateBulkBtn,
            .modern-card .bi-receipt-cutoff {
                animation: none !important;
            }

            #generateBulkBtn {
                transition: none !important;
            }
        }

    </style>
</head>
<body>

    <div class="container">
        <div class="card modern-card p-5 mt-4">
            
            <div class="text-center mb-4">
                <div class="d-inline-block bg-light rounded-circle p-3 mb-3 shadow-sm">
                    <i class="bi bi-receipt-cutoff text-primary" style="font-size: 2rem;"></i>
                </div>
                <h2 class="page-title">Bulk Bill Generator</h2>
                <p class="text-muted" style="font-size: 14px;">Automatically generate monthly bills for all active customers in a single click.</p>
            </div>

            <div class="alert alert-info-custom p-3 mb-4 d-flex align-items-center" style="font-size: 13px;">
                <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                <div>
                    System will automatically fetch each user's assigned package price and create an unpaid invoice for the selected month.
                </div>
            </div>

            <div class="mb-4">
                <label for="bulkMonth" class="form-label">Select Billing Month & Year</label>
                <!-- Type="month" automatically gives a nice Year/Month picker in modern browsers -->
                <input type="month" id="bulkMonth" class="form-control form-control-lg bg-light border-secondary">
            </div>

            <button id="generateBulkBtn" class="btn btn-gradient shadow-sm">
                <i class="bi bi-magic me-2"></i> Generate Bills For All Users
            </button>
            
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Auto-select next month by default (e.g. If current is Aug, it will select Sep)
            let today = new Date();
            today.setMonth(today.getMonth() + 1); // Move to next month
            let yyyy = today.getFullYear();
            let mm = String(today.getMonth() + 1).padStart(2, '0');
            $('#bulkMonth').val(`${yyyy}-${mm}`);

            $('#generateBulkBtn').click(function() {
                let monthValue = $('#bulkMonth').val(); // format: YYYY-MM
                
                if (!monthValue) {
                    Swal.fire('Required', 'Please select a month and year first!', 'warning');
                    return;
                }

                // Convert "2026-09" to "Sep 2026" for better readability in confirmation
                let dateObj = new Date(monthValue + '-01');
                let formattedMonth = dateObj.toLocaleString('en-US', { month: 'short' }) + ' ' + dateObj.getFullYear();

                Swal.fire({
                    title: 'Are you sure?',
                    text: `You are about to generate bills for ALL active users for the month of ${formattedMonth}. This process might take a few moments.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#1b204f',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Generate All Bills!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        
                        // Show loading state
                        let btn = $(this);
                        let originalText = btn.html();
                        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Generating...');

                        // AJAX call to backend
                        $.post('backend_api.php', { 
                            action: 'bulk_generate_bills', 
                            raw_month: monthValue,       // Send 2026-09
                            formatted_month: formattedMonth // Send Sep 2026
                        }, function(response) {
                            
                            btn.prop('disabled', false).html(originalText);

                            if (response.success) {
                                Swal.fire(
                                    'Success!',
                                    `Successfully generated bills for ${response.count} users for ${formattedMonth}.`,
                                    'success'
                                );
                            } else {
                                Swal.fire('Error', response.error, 'error');
                            }
                            
                        }, 'json').fail(function() {
                            btn.prop('disabled', false).html(originalText);
                            Swal.fire('Network Error', 'Failed to connect to the server. Please check your connection.', 'error');
                        });
                    }
                });
            });
        });
    </script>
    <script src="session_timeout.js"></script>
</body>
</html>

<?php
session_start();
// require_once __DIR__ . '/session_guard.php'; 

require_once '/zalpro-optimization/credentials/db_config.php';

$host = 'localhost';
$db   = 'zalpro';
$user = DB_USER;
$pass = DB_PASS;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Failed");
}

// ==========================================
// PICHLEY 6 MAHINAY KA DATA TAIYAR KARNA
// ==========================================
$months_data = [];
$chart_labels = [];
$chart_values = [];

// Pichlay 6 mahinay ka khali array banayein
for ($i = 5; $i >= 0; $i--) {
    $month_key = date('Y-m', strtotime("-$i months"));
    $month_label = date('M', strtotime("-$i months")); // e.g., Mar, Apr, May
    $months_data[$month_key] = [
        'label' => $month_label,
        'total' => 0
    ];
}

$start_date = date('Y-m-01 00:00:00', strtotime("-5 months"));

try {
    $query = "SELECT DATE_FORMAT(createdate, '%Y-%m') as m_key, SUM(totalcost) as total 
              FROM invoices 
              WHERE createdate >= :start_date AND status = 1 
              GROUP BY m_key";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['start_date' => $start_date]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($months_data[$row['m_key']])) {
            $months_data[$row['m_key']]['total'] = (float)$row['total'];
        }
    }
} catch (Exception $e) { }

foreach ($months_data as $key => $data) {
    $chart_labels[] = $data['label'];
    $chart_values[] = $data['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Collection Report</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f4f8;
            padding: 25px;
        }

        .fade-in { animation: fadeIn 0.6s ease-in-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dashboard-card {
            background: #fff;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            padding: 25px;
        }

        .card-header-title {
            font-size: 1.25rem;
            font-weight: 500;
            color: #495057;
            margin-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 15px;
        }

        .chart-container {
            position: relative;
            height: 450px;
            width: 100%;
        }
    </style>
</head>
<body class="fade-in">

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header-title">
                        Last 6 Month Receiving
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="collectionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

    <script>
        const chartLabels = <?= json_encode($chart_labels) ?>;
        const chartValues = <?= json_encode($chart_values) ?>;

        Chart.register(ChartDataLabels);
        const ctx = document.getElementById('collectionChart').getContext('2d');
        
        const collectionChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Total Receiving',
                    data: chartValues,
                    backgroundColor: '#4fb0f6',
                    hoverBackgroundColor: '#3b9ded',
                    borderRadius: 2,
                    barPercentage: 0.7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#ffffff',
                        titleColor: '#333',
                        bodyColor: '#000',
                        borderColor: '#cccccc',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': Rs. ';
                                if (context.parsed.y !== null) {
                                    label += context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                }
                                return label;
                            }
                        }
                    },
                    datalabels: {
                        anchor: 'end',
                        align: 'bottom',
                        offset: -25,
                        color: '#333',
                        font: { weight: 'bold', size: 11 },
                        formatter: function(value) {
                            if(value > 0) return value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            return '';
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#eaeaea', drawBorder: false },
                        ticks: {
                            color: '#888',
                            callback: function(value) { return value.toLocaleString('en-US'); }
                        }
                    },
                    x: {
                        grid: { display: false, drawBorder: true },
                        ticks: { color: '#888' }
                    }
                },
                animation: { duration: 1200, easing: 'easeOutQuart' }
            }
        });
    </script>
</body>
</html>

<?php
require_once '/zalpro-optimization/credentials/db_config.php';

header('Content-Type: application/json');

$host = 'localhost';
$db   = 'zalpro';
$user = DB_USER;
$pass = DB_PASS;

$search = isset($_GET['q']) ? trim($_GET['q']) : (isset($_GET['term']) ? trim($_GET['term']) : '');

if (empty($search)) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Single letter se username, name, ya address mein live search query
    $stmt = $pdo->prepare("
        SELECT username, 
               IFNULL(name, '') as name, 
               IFNULL(address, '') as address 
        FROM usersinfo 
        WHERE username LIKE :term 
           OR name LIKE :term 
           OR address LIKE :term 
        LIMIT 30
    ");
    
    $term = "%$search%";
    $stmt->execute(['term' => $term]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $row) {
        $text = $row['username'];
        if (!empty($row['name'])) {
            $text .= " - " . $row['name'];
        }
        if (!empty($row['address'])) {
            $text .= " - " . $row['address'];
        }

        $results[] = [
            'id'   => $row['username'],
            'text' => $text
        ];
    }

    echo json_encode(['results' => $results]);

} catch (Exception $e) {
    echo json_encode(['results' => [], 'error' => $e->getMessage()]);
}
?>

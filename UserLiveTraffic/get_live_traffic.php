<?php
error_reporting(0);
header('Content-Type: application/json');
$router_ip = "103.170.179.40";
$api_user  = "zalpro-api";
$api_pass  = "zalpro123";
$api_port  = "8080";
$interface = isset($_GET['interface']) ? trim($_GET['interface']) : '';
if (empty($interface)) { echo json_encode(["error" => "Interface required", "status" => "error"]); exit; }
if (!preg_match('/^pp0\.\d+$/', $interface)) { echo json_encode(["error" => "Invalid interface", "status" => "error"]); exit; }
$in_bytes = 0; $out_bytes = 0; $error_msg = null;
try {
    $start_time = microtime(true);
    $url = "http://{$router_ip}:{$api_port}/rpc/get-interface-information?interface-name=" . urlencode($interface) . "&extensive=";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "{$api_user}:{$api_pass}");
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/xml']);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);
    $query_time_ms = (microtime(true) - $start_time) * 1000;
    if ($http_code !== 200 || !$response) throw new Exception("REST API failed: HTTP {$http_code} - {$curl_err}");
    $xml_clean = preg_replace('/xmlns(:[a-z0-9]+)?="[^"]*"/i', '', $response);
    $xml_clean = preg_replace('/[a-z0-9]+:(style|indent)="[^"]*"/i', '', $xml_clean);
    $xml = simplexml_load_string($xml_clean);
    if (!$xml) throw new Exception("Invalid XML response");
    $target = $xml->xpath("//logical-interface[name='{$interface}']");
    if (!empty($target)) {
        $traffic   = $target[0]->{'traffic-statistics'};
        $in_bytes  = (int)$traffic->{'input-bytes'};
        $out_bytes = (int)$traffic->{'output-bytes'};
    }
} catch (Exception $e) { $error_msg = $e->getMessage(); }
if (!$error_msg) echo json_encode(['interface' => $interface, 'input_bytes' => $in_bytes, 'output_bytes' => $out_bytes, 'status' => 'success', 'timestamp' => microtime(true) * 1000, 'server_time_ms' => round($query_time_ms, 2), 'method' => 'rest_api']);
else echo json_encode(["error" => $error_msg, "interface" => $interface, "status" => "error"]);
?>

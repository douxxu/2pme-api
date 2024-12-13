<?php
header('Content-Type: application/json');
require "../../utils/logsapi.php";
require "../../utils/secrets.php";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'result' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$apiKey = $_GET['key'] ?? null;

logapi($apiKey, $_SERVER['REMOTE_ADDR']);

if (!$apiKey) {
    echo json_encode(['success' => false, 'result' => 'Missing required parameter: key.']);
    exit();
}

$keyCheckQuery = $conn->prepare("SELECT id FROM api_keys WHERE api_key = ?");
$keyCheckQuery->bind_param("s", $apiKey);
$keyCheckQuery->execute();
$keyCheckQuery->store_result();
if ($keyCheckQuery->num_rows === 0) {
    echo json_encode(['success' => false, 'result' => 'Invalid API key.']);
    exit();
}
$keyCheckQuery->close();

$getSubdomainsQuery = $conn->prepare("SELECT name, type, value, created_at FROM subdomains WHERE api_key = ?");
$getSubdomainsQuery->bind_param("s", $apiKey);
$getSubdomainsQuery->execute();
$getSubdomainsQuery->bind_result($name, $type, $value, $createdAt);

$subdomains = [];
while ($getSubdomainsQuery->fetch()) {
    $subdomains[] = [
        'name' => $name,
        'type' => $type,
        'value' => $value,
        'created_at' => $createdAt
    ];
}
$getSubdomainsQuery->close();

echo json_encode(['success' => true, 'subdomains' => $subdomains]);

$conn->close();
?>

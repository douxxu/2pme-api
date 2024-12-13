<?php
header('Content-Type: application/json');
require "../../utils/secrets.php";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'result' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit();
}

$apiKey = $_GET['key'] ?? null;


if (!$apiKey) {
    echo json_encode([
        'success' => false,
        'result' => 'Missing required parameter: key.'
    ]);
    exit();
}

$keyCheckQuery = $conn->prepare("
    SELECT api_key, last_used, times_used, created_at 
    FROM api_keys 
    WHERE api_key = ?
");
$keyCheckQuery->bind_param("s", $apiKey);
$keyCheckQuery->execute();
$result = $keyCheckQuery->get_result();

if ($result->num_rows === 0) {
    $keyCheckQuery->close();
    $conn->close();
    echo json_encode([
        'success' => false,
        'result' => 'API key not found'
    ]);
    exit();
}

$keyData = $result->fetch_assoc();
$keyCheckQuery->close();
$conn->close();

echo json_encode([
    'success' => true,
    'result' => [
        'key' => $keyData['api_key'],
        'last_used' => $keyData['last_used'],
        'times_used' => $keyData['times_used'],
        'created_at' => $keyData['created_at']
    ]
]);
?>

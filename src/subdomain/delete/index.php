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
$subdomain = $_GET['subdomain'] ?? null;

logapi($apiKey, $_SERVER['REMOTE_ADDR']);

if (!$apiKey || !$subdomain) {
    echo json_encode(['success' => false, 'result' => 'Missing required parameters: key or subdomain.']);
    exit();
}

$getSubdomainQuery = $conn->prepare("SELECT dns_record_id, api_key FROM subdomains WHERE name = ?");
$getSubdomainQuery->bind_param("s", $subdomain);
$getSubdomainQuery->execute();
$getSubdomainQuery->bind_result($dnsRecordId, $dbApiKey);
$getSubdomainQuery->fetch();
$getSubdomainQuery->close();

if (!$dnsRecordId) {
    echo json_encode(['success' => false, 'result' => 'Subdomain does not exist.']);
    exit();
}

if ($apiKey !== $dbApiKey) {
    echo json_encode(['success' => false, 'result' => 'API key does not match the subdomain.']);
    exit();
}

$cloudflare_api_url = $cloudflare_api_url . '/' . $dnsRecordId;

$options = [
    'http' => [
        'method' => 'DELETE',
        'header' => [
            'Authorization: Bearer ' . $cloudflare_api_key
        ],
    ]
];

$context = stream_context_create($options);
$response = file_get_contents($cloudflare_api_url, false, $context);
$responseData = json_decode($response, true);

if (!$responseData || !$responseData['success']) {
    echo json_encode(['success' => false, 'result' => 'Failed to delete DNS record on Cloudflare.']);
    exit();
}

$deleteQuery = $conn->prepare("DELETE FROM subdomains WHERE name = ?");
$deleteQuery->bind_param("s", $subdomain);
if (!$deleteQuery->execute()) {
    echo json_encode(['success' => false, 'result' => 'Failed to delete subdomain from database.']);
    exit();
}
$deleteQuery->close();

echo json_encode(['success' => true, 'result' => "Subdomain [$subdomain] successfully deleted."]);

$conn->close();
?>

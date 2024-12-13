<?php
header('Content-Type: application/json');
require "../../utils/secrets.php";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'result' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$apiKey = $_GET['key'] ?? null;

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

$getSubdomainsQuery = $conn->prepare("SELECT dns_record_id FROM subdomains WHERE api_key = ?");
$getSubdomainsQuery->bind_param("s", $apiKey);
$getSubdomainsQuery->execute();
$getSubdomainsQuery->bind_result($dnsRecordId);

$dnsRecordIds = [];
while ($getSubdomainsQuery->fetch()) {
    if ($dnsRecordId) {
        $dnsRecordIds[] = $dnsRecordId;
    }
}
$getSubdomainsQuery->close();

foreach ($dnsRecordIds as $dnsRecordId) {
    $deleteDnsRecordUrl = $cloudflare_api_url . '/' . $dnsRecordId;
    
    $options = [
        'http' => [
            'method' => 'DELETE',
            'header' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $cloudflare_api_key
            ],
        ],
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents($deleteDnsRecordUrl, false, $context);
    $responseData = json_decode($response, true);
    
    if (!$responseData['success']) {
        echo json_encode(['success' => false, 'result' => 'Failed to delete DNS record on Cloudflare.']);
        exit();
    }
}

$deleteSubdomainsQuery = $conn->prepare("DELETE FROM subdomains WHERE api_key = ?");
$deleteSubdomainsQuery->bind_param("s", $apiKey);
$deleteSubdomainsQuery->execute();
$deleteSubdomainsQuery->close();

$deleteKeyQuery = $conn->prepare("DELETE FROM api_keys WHERE api_key = ?");
$deleteKeyQuery->bind_param("s", $apiKey);
if (!$deleteKeyQuery->execute()) {
    echo json_encode(['success' => false, 'result' => 'Failed to delete API key.']);
    exit();
}
$deleteKeyQuery->close();

echo json_encode(['success' => true, 'result' => "API key [$apiKey] and all associated subdomains successfully deleted."]);

$conn->close();
?>

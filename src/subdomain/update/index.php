<?php
header('Content-Type: application/json');
require "../../utils/logsapi.php";
include "../../utils/secrets.php";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'result' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$apiKey = $_GET['key'] ?? null;
$subdomain = $_GET['subdomain'] ?? null;
$newType = $_GET['type'] ?? null;
$newValue = $_GET['value'] ?? null;

logapi($apiKey, $_SERVER['REMOTE_ADDR']);

if (!$apiKey || !$subdomain || !$newType || !$newValue) {
    echo json_encode(['success' => false, 'result' => 'Missing required parameters: key, subdomain, type, or value.']);
    exit();
}

function isValidSubdomain($subdomain) {
    return preg_match('/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\\.[A-Za-z]{2,})*$/', $subdomain);
}

function isValidIP($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

$validTypes = ['A', 'AAAA', 'CNAME', 'TXT'];
if (!in_array($newType, $validTypes)) {
    echo json_encode(['success' => false, 'result' => 'Invalid DNS type.']);
    exit();
}

if (!isValidSubdomain($subdomain)) {
    echo json_encode(['success' => false, 'result' => 'Invalid subdomain format.']);
    exit();
}

if ($newType === 'A' || $newType === 'AAAA') {
    if (!isValidIP($newValue)) {
        echo json_encode(['success' => false, 'result' => 'Invalid IP address.']);
        exit();
    }
} elseif ($newType === 'CNAME') {
    if (!isValidSubdomain($newValue)) {
        echo json_encode(['success' => false, 'result' => 'Invalid CNAME value.']);
        exit();
    }
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

$dnsRecord = [
    'type' => $newType,
    'name' => $subdomain,
    'content' => $newValue,
    'ttl' => 1, // def
    'proxied' => false
];

$options = [
    'http' => [
        'method' => 'PUT',
        'header' => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $cloudflare_api_key
        ],
        'content' => json_encode($dnsRecord)
    ]
];

$context = stream_context_create($options);
$response = file_get_contents($cloudflare_api_url, false, $context);
$responseData = json_decode($response, true);

if (!$responseData || !$responseData['success']) {
    echo json_encode(['success' => false, 'result' => 'Failed to update DNS record on Cloudflare.']);
    exit();
}

$updateQuery = $conn->prepare("UPDATE subdomains SET type = ?, value = ? WHERE name = ?");
$updateQuery->bind_param("sss", $newType, $newValue, $subdomain);
if (!$updateQuery->execute()) {
    echo json_encode(['success' => false, 'result' => 'Failed to update subdomain in database: ' . $updateQuery->error]);
    exit();
}
$updateQuery->close();

echo json_encode(['success' => true, 'result' => "Subdomain successfully updated to [$subdomain => $newValue | $newType]."]);

$conn->close();
?>

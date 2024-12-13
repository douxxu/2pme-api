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
$type = $_GET['type'] ?? null;
$value = $_GET['value'] ?? null;

if (!$apiKey || !$subdomain || !$type || !$value) {
    echo json_encode(['success' => false, 'result' => 'Missing required parameters: key, subdomain, type, or value.']);
    exit();
}

function isValidSubdomain($subdomain) {
    return preg_match('/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.[A-Za-z]{2,})*$/', $subdomain) && !strpos($subdomain, '_');
}

function isValidCNAME($cname) {
    return preg_match('/^(?!-)([A-Za-z0-9-]{1,63}(?<!-)(\.[A-Za-z0-9-]{1,63})*)$/', $cname);
}

function isValidIP($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

$validTypes = ['A', 'AAAA', 'CNAME', 'TXT'];
if (!in_array($type, $validTypes)) {
    echo json_encode(['success' => false, 'result' => 'Invalid DNS type.']);
    exit();
}

if (!isValidSubdomain($subdomain)) {
    echo json_encode(['success' => false, 'result' => 'Invalid subdomain format.']);
    exit();
}

if ($type === 'A' || $type === 'AAAA') {
    if (!isValidIP($value)) {
        echo json_encode(['success' => false, 'result' => 'Invalid IP address.']);
        exit();
    }
} elseif ($type === 'CNAME') {
    if (!isValidCNAME($value)) {
        echo json_encode(['success' => false, 'result' => 'Invalid CNAME value.']);
        exit();
    }
}

logapi($apiKey, $_SERVER['REMOTE_ADDR']);

$keyCheckQuery = $conn->prepare("SELECT id FROM api_keys WHERE api_key = ?");
$keyCheckQuery->bind_param("s", $apiKey);
$keyCheckQuery->execute();
$keyCheckQuery->store_result();
if ($keyCheckQuery->num_rows === 0) {
    echo json_encode(['success' => false, 'result' => 'Invalid API key.']);
    exit();
}
$keyCheckQuery->close();

$createTableQuery = "
CREATE TABLE IF NOT EXISTS subdomains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(10) NOT NULL,
    value VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    dns_record_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if (!$conn->query($createTableQuery)) {
    echo json_encode(['success' => false, 'result' => 'Failed to create table: ' . $conn->error]);
    exit();
}

$checkSubdomainQuery = $conn->prepare("SELECT id FROM subdomains WHERE name = ?");
$checkSubdomainQuery->bind_param("s", $subdomain);
$checkSubdomainQuery->execute();
$checkSubdomainQuery->store_result();
if ($checkSubdomainQuery->num_rows > 0) {
    echo json_encode(['success' => false, 'result' => 'Subdomain already exists.']);
    exit();
}
$checkSubdomainQuery->close();

$insertQuery = $conn->prepare("INSERT INTO subdomains (name, type, value, api_key, dns_record_id) VALUES (?, ?, ?, ?, ?)");
$dnsRecordId = null;
$insertQuery->bind_param("sssss", $subdomain, $type, $value, $apiKey, $dnsRecordId);
if (!$insertQuery->execute()) {
    echo json_encode(['success' => false, 'result' => 'Failed to insert subdomain: ' . $insertQuery->error]);
    exit();
}
$insertQuery->close();

$dnsRecord = [
    'type' => $type,
    'name' => $subdomain,
    'content' => $value,
    'ttl' => 1,
    'proxied' => false
];

$options = [
    'http' => [
        'method' => 'POST',
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
    echo json_encode(['success' => false, 'result' => 'Failed to create DNS record on Cloudflare.']);
    exit();
}

$dnsRecordId = $responseData['result']['id'] ?? null;
if ($dnsRecordId === null) {
    echo json_encode(['success' => false, 'result' => 'DNS record ID not returned from Cloudflare.']);
    exit();
}

$updateQuery = $conn->prepare("UPDATE subdomains SET dns_record_id = ? WHERE name = ?");
$updateQuery->bind_param("ss", $dnsRecordId, $subdomain);
if (!$updateQuery->execute()) {
    echo json_encode(['success' => false, 'result' => 'Failed to update DNS record ID.']);
    exit();
}
$updateQuery->close();

echo json_encode(['success' => true, 'result' => "Subdomain [$subdomain => $value | $type] successfully created."]);

$conn->close();
?>

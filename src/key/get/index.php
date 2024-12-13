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

$tableCheckQuery = "SHOW TABLES LIKE 'api_keys'";
$result = $conn->query($tableCheckQuery);

if ($result->num_rows === 0) {
    $createTableQuery = "
        CREATE TABLE api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            api_key VARCHAR(64) NOT NULL UNIQUE,
            last_used VARCHAR(45) DEFAULT NULL,
            times_used INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ";
    if (!$conn->query($createTableQuery)) {
        echo json_encode([
            'success' => false,
            'result' => 'Error creating table: ' . $conn->error
        ]);
        exit();
    }
}

do {
    $apiKey = bin2hex(random_bytes(16));
    $keyCheckQuery = $conn->prepare("SELECT COUNT(*) FROM api_keys WHERE api_key = ?");
    $keyCheckQuery->bind_param("s", $apiKey);
    $keyCheckQuery->execute();
    $keyCheckQuery->bind_result($keyCount);
    $keyCheckQuery->fetch();
    $keyCheckQuery->close();
} while ($keyCount > 0);

$insertQuery = $conn->prepare("
    INSERT INTO api_keys (api_key, last_used, times_used)
    VALUES (?, NULL, 0)
");
$insertQuery->bind_param("s", $apiKey);

if ($insertQuery->execute()) {
    echo json_encode([
        'success' => true,
        'result' => $apiKey
    ]);
} else {
    echo json_encode([
        'success' => false,
        'result' => 'Error inserting API key: ' . $insertQuery->error
    ]);
}

$insertQuery->close();
$conn->close();

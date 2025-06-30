<?php
// 데이터베이스 연결 설정
$db_host = '127.0.0.1';
$db_user = 'jintech';
$db_pass = 'jin2010!!';
$db_name = 'jintech';
$db_port = '3306';

// 시간대 설정
date_default_timezone_set('Asia/Seoul');

// PDO를 사용하여 데이터베이스 연결
try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("데이터베이스 연결 실패: " . $e->getMessage());
}

try {
    $stmt = $pdo->query("DESCRIBE delivery_request_details");
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "--- Columns in 'delivery_request_details' table ---\n";
    foreach ($fields as $field) {
        echo "Name: " . $field['Field'] . "\n";
        echo "  Type: " . $field['Type'] . "\n";
        echo "  Null: " . $field['Null'] . "\n";
        echo "  Key: " . $field['Key'] . "\n";
        echo "  Default: " . $field['Default'] . "\n";
        echo "--------------------------------------------------\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 
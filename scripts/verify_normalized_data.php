<?php
// 데이터베이스 연결 설정
$db_host = '127.0.0.1'; // 로컬에서 실행하므로 localhost
$db_user = 'jintech';
$db_pass = 'jin2010!!';
$db_name = 'jintech';
$db_port = '3306';

date_default_timezone_set('Asia/Seoul');

function print_table_data($pdo, $table_name, $limit = 5) {
    echo "==================================================\n";
    echo "Verifying table: {$table_name}\n";
    echo "==================================================\n";

    // 데이터 건수 확인
    $count = $pdo->query("SELECT count(*) FROM {$table_name}")->fetchColumn();
    echo "Total rows: {$count}\n\n";

    if ($count > 0) {
        // 샘플 데이터 확인
        echo "--- Sample Data (LIMIT {$limit}) ---\n";
        $stmt = $pdo->query("SELECT * FROM {$table_name} LIMIT {$limit}");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $index => $row) {
            echo "Row " . ($index + 1) . ":\n";
            print_r($row);
            echo "---------------------------------\n";
        }
    }
}


try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    print_table_data($pdo, 'delivery_requests');
    print_table_data($pdo, 'delivery_request_items');

} catch (PDOException $e) {
    die("데이터베이스 오류: " . $e->getMessage());
} 
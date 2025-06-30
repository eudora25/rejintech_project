<?php
// 스크립트 실행 환경 설정
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Seoul');

// 데이터베이스 연결 설정
$db_host = '127.0.0.1';
$db_user = 'jintech';
$db_pass = 'jin2010!!';
$db_name = 'jintech';
$db_port = '3306';

$pdo = null;

function output_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
}

function check_null_dates() {
    global $pdo;
    output_message("--- 정규화된 테이블 'delivery_requests'의 NULL 날짜 데이터 샘플 조회 ---");
    
    // 이전에 '0000-00-00' 값을 가졌을 것으로 추정되는 데이터를 확인
    $stmt = $pdo->prepare("
        SELECT 
            delivery_request_number, 
            delivery_request_date,
            delivery_receipt_date,
            delivery_deadline_date
        FROM delivery_requests 
        WHERE delivery_request_date IS NULL
           OR delivery_receipt_date IS NULL
           OR delivery_deadline_date IS NULL
        LIMIT 10
    ");
    
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($rows) > 0) {
        output_message("NULL 값을 포함하는 날짜 데이터 발견:");
        foreach ($rows as $row) {
            echo "  - 납품요구번호: " . $row['delivery_request_number'] . 
                 ", 납품요구일자: " . var_export($row['delivery_request_date'], true) . 
                 ", 접수일자: " . var_export($row['delivery_receipt_date'], true) . 
                 ", 납기일자: " . var_export($row['delivery_deadline_date'], true) . 
                 PHP_EOL;
        }
    } else {
        output_message("날짜 컬럼에서 NULL 값을 찾지 못했습니다.");
    }
    echo PHP_EOL;
}


try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    output_message("DB 연결 성공.");

    check_null_dates();

} catch (Exception $e) {
    output_message("오류 발생: " . $e->getMessage(), 'ERROR');
} 
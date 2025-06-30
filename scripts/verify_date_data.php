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

function check_date_column($column_name) {
    global $pdo;
    output_message("--- 컬럼 '{$column_name}' 데이터 샘플 조회 ---");
    
    $stmt = $pdo->prepare("
        SELECT dlvr_req_no, {$column_name}
        FROM delivery_request_details 
        WHERE {$column_name} IS NULL OR {$column_name} = ''
        LIMIT 10
    ");
    
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($rows) > 0) {
        output_message("'{$column_name}' 컬럼에 NULL 또는 빈 문자열이 포함된 데이터 발견:");
        foreach ($rows as $row) {
            echo "  - 납품요구번호: " . $row['dlvr_req_no'] . ", 값: [" . $row[$column_name] . "]" . PHP_EOL;
        }
    } else {
        output_message("'{$column_name}' 컬럼에서 NULL 또는 빈 문자열 데이터를 찾지 못했습니다.");
    }
    echo PHP_EOL;
}


try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    output_message("DB 연결 성공.");

    $date_columns = ['dlvr_req_dt', 'dlvr_req_rcpt_date', 'dlvr_tmlmt_date'];
    
    foreach ($date_columns as $col) {
        check_date_column($col);
    }

} catch (Exception $e) {
    output_message("오류 발생: " . $e->getMessage(), 'ERROR');
} 
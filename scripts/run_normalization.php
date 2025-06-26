<?php
// 스크립트 실행 환경 설정
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '1G');
set_time_limit(0);
date_default_timezone_set('Asia/Seoul');

// 데이터베이스 연결 설정
$db_host = '127.0.0.1';
$db_user = 'jintech';
$db_pass = 'jin2010!!';
$db_name = 'jintech';
$db_port = '3306';

$pdo = null;

// 로거 함수
function output_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    flush();
}

// 메인 실행 로직
function main() {
    global $pdo, $db_host, $db_port, $db_name, $db_user, $db_pass;

    output_message("=== 납품요구 데이터 정규화 배치 시작 (Standalone) ===");
    $start_time = microtime(true);

    try {
        $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->beginTransaction();
        output_message("트랜잭션 시작.");

        clear_tables();
        populate_normalized_data();
        
        $pdo->commit();
        output_message("트랜잭션 커밋 완료.");

    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
            output_message("트랜잭션이 롤백되었습니다.", 'ERROR');
        }
        output_message("[CRITICAL] 배치 실행 중 심각한 오류 발생: " . $e->getMessage(), 'ERROR');
    }

    $end_time = microtime(true);
    output_message("총 실행 시간: " . round($end_time - $start_time, 2) . "초");
    output_message("=== 납품요구 데이터 정규화 배치 종료 (Standalone) ===");
}

function clear_tables() {
    global $pdo;
    output_message("[1단계] 테이블 초기화 시작");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    $tables = [
        'delivery_request_items', 'delivery_requests', 'products', 
        'product_categories', 'institutions', 'companies', 'contracts'
    ];
    
    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE TABLE `{$table}`;");
        output_message("- {$table} 테이블 초기화 완료");
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    output_message("[1단계] 테이블 초기화 완료");

    // '미분류' 카테고리 기본값 추가
    $stmt = $pdo->prepare("INSERT INTO product_categories (id, category_code, category_name) VALUES (0, '0000', '미분류') ON DUPLICATE KEY UPDATE category_name = '미분류'");
    $stmt->execute();
    output_message("- '미분류' 카테고리 처리 완료 (ID: 0)");
}

function populate_normalized_data() {
    global $pdo;
    
    // 실제 delivery_request_details 스키마 기반으로 모든 쿼리 수정
    $steps = [
        'product_categories' => "
            INSERT INTO product_categories (category_code, category_name)
            SELECT DISTINCT d.prdct_clsfc_no, d.prdct_clsfc_no_nm
            FROM delivery_request_details d
            WHERE d.prdct_clsfc_no IS NOT NULL AND d.prdct_clsfc_no != ''
            ON DUPLICATE KEY UPDATE category_name = VALUES(category_name)",
        
        'products' => "
            INSERT INTO products (product_code, product_name, category_id, unit)
            SELECT DISTINCT 
                d.prdct_idnt_no, 
                d.prdct_idnt_no_nm, 
                COALESCE(pc.id, 0),
                d.unit_nm
            FROM delivery_request_details d
            LEFT JOIN product_categories pc ON d.prdct_clsfc_no = pc.category_code
            WHERE d.prdct_idnt_no IS NOT NULL AND d.prdct_idnt_no != ''
            ON DUPLICATE KEY UPDATE product_name = VALUES(product_name)",

        'institutions' => "
            INSERT INTO institutions (institution_code, institution_name, region_name)
            SELECT DISTINCT d.dminstt_cd, d.dminstt_nm, d.dminstt_rgn_nm
            FROM delivery_request_details d
            WHERE d.dminstt_cd IS NOT NULL AND d.dminstt_cd != ''
            ON DUPLICATE KEY UPDATE institution_name = VALUES(institution_name), region_name = VALUES(region_name)",

        'companies' => "
            INSERT INTO companies (business_number, company_name)
            SELECT DISTINCT d.cntrct_corp_bizno, d.corp_nm
            FROM delivery_request_details d
            WHERE d.cntrct_corp_bizno IS NOT NULL AND d.cntrct_corp_bizno != ''
            ON DUPLICATE KEY UPDATE company_name = VALUES(company_name)",

        'contracts' => "
            INSERT INTO contracts (contract_number, contract_change_order)
            SELECT DISTINCT d.cntrct_no, d.cntrct_chg_ord
            FROM delivery_request_details d
            WHERE d.cntrct_no IS NOT NULL AND d.cntrct_no != ''
            ON DUPLICATE KEY UPDATE contract_change_order=VALUES(contract_change_order)",

        'delivery_requests' => "
            INSERT INTO delivery_requests (delivery_request_number, delivery_request_change_order, delivery_request_name, delivery_request_date, delivery_receipt_date, delivery_deadline_date, international_delivery_date, institution_id, company_id, contract_id, is_excellent_product, is_final_delivery, is_sme_product, total_quantity, total_amount, data_sync_date)
            SELECT 
                d.dlvr_req_no,
                d.dlvr_req_chg_ord,
                d.dlvr_req_nm,
                d.dlvr_req_dt,
                d.dlvr_req_rcpt_date,
                d.dlvr_tmlmt_date,
                d.intl_cntrct_dlvr_req_date,
                (SELECT id FROM institutions WHERE institution_code = d.dminstt_cd),
                (SELECT id FROM companies WHERE business_number = d.cntrct_corp_bizno),
                (SELECT id FROM contracts WHERE contract_number = d.cntrct_no AND contract_change_order = d.cntrct_chg_ord),
                IF(d.exclc_prodct_yn = 'Y', 1, 0),
                IF(d.fnl_dlvr_req_yn = 'Y', 1, 0),
                IF(d.smetpr_cmpt_prodct_yn = 'Y', 1, 0),
                d.dlvr_req_qty,
                d.dlvr_req_amt,
                d.rgst_dt
            FROM 
                delivery_request_details d
            WHERE 
                d.dlvr_req_no IS NOT NULL AND d.dlvr_req_no != ''
            GROUP BY d.dlvr_req_no, d.dlvr_req_chg_ord
            ON DUPLICATE KEY UPDATE
                delivery_request_name = VALUES(delivery_request_name),
                institution_id = VALUES(institution_id),
                company_id = VALUES(company_id),
                contract_id = VALUES(contract_id),
                is_excellent_product = VALUES(is_excellent_product),
                total_amount = VALUES(total_amount)",

        'delivery_request_items' => "
            INSERT INTO delivery_request_items (delivery_request_id, sequence_number, product_id, unit_price, request_quantity, delivery_quantity, increase_decrease_quantity, increase_decrease_amount, total_amount, delivery_expected_date, delivery_completion_date, delivery_status_code, delivery_status_name)
            SELECT
                (SELECT id FROM delivery_requests WHERE delivery_request_number = drd.dlvr_req_no AND delivery_request_change_order = drd.dlvr_req_chg_ord) AS delivery_request_id,
                drd.dlvr_req_dtl_seq AS sequence_number,
                (SELECT id FROM products WHERE product_code = drd.prdct_idnt_no) AS product_id,
                drd.prdct_uprc AS unit_price,
                drd.req_qty AS request_quantity,
                drd.dlvr_qty AS delivery_quantity,
                drd.incdec_qty AS increase_decrease_quantity,
                drd.incdec_amt AS increase_decrease_amount,
                drd.prdct_amt AS total_amount,
                drd.dlvr_expect_dt AS delivery_expected_date,
                drd.dlvr_cmplt_dt AS delivery_completion_date,
                drd.dlvr_status_cd AS delivery_status_code,
                drd.dlvr_status_nm as delivery_status_name
            FROM
                delivery_request_details drd
            WHERE
                (SELECT id FROM delivery_requests WHERE delivery_request_number = drd.dlvr_req_no AND delivery_request_change_order = drd.dlvr_req_chg_ord) IS NOT NULL
                AND (SELECT id FROM products WHERE product_code = drd.prdct_idnt_no) IS NOT NULL"
    ];

    foreach ($steps as $table => $sql) {
        output_message("[2단계] {$table} 데이터 생성 시작");
        $affected_rows = $pdo->exec($sql);
        output_message("- {$table} 데이터 생성 완료 (영향 받은 row: {$affected_rows})");
    }
}

// 스크립트 실행
main(); 
<?php
/**
 * cntrctCorpBizno 필터링 기능 테스트 스크립트
 * 
 * 사용법:
 * - CLI: php scripts/test_filtering.php
 * - 웹: http://localhost/scripts/test_filtering.php
 */

// CodeIgniter 환경 설정
$system_path = __DIR__ . '/../source/system';
$application_folder = __DIR__ . '/../source/application';

define('BASEPATH', $system_path . '/');
define('APPPATH', $application_folder . '/');
define('ENVIRONMENT', 'development');

// CodeIgniter 로드
require_once $system_path . '/core/CodeIgniter.php';

// 출력 함수
function output_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[{$timestamp}] [{$level}] {$message}";
    
    if (php_sapi_name() === 'cli') {
        echo $formatted . "\n";
    } else {
        echo "<p style='font-family:monospace; margin:2px;'>" . htmlspecialchars($formatted) . "</p>";
        flush();
    }
}

try {
    // CodeIgniter 인스턴스 가져오기
    $CI =& get_instance();
    $CI->load->database();
    $CI->load->model('Delivery_request_model');

    output_message("=== cntrctCorpBizno 필터링 기능 테스트 ===");

    // 1. filtering_companies 테이블 현황 확인
    output_message("\n1. filtering_companies 테이블 현황");
    $total_companies = $CI->db->count_all('filtering_companies');
    $active_companies = $CI->db->where('is_active', 1)->count_all_results('filtering_companies');
    
    output_message("- 전체 등록 업체: {$total_companies}개");
    output_message("- 활성화된 업체: {$active_companies}개");

    // 2. 필터링 메서드 테스트
    output_message("\n2. 필터링 메서드 테스트");
    
    // 샘플 사업자번호 조회
    $sample_businesses = $CI->db->select('business_number, company_name')
                               ->from('filtering_companies')
                               ->where('is_active', 1)
                               ->limit(3)
                               ->get()
                               ->result_array();
    
    foreach ($sample_businesses as $business) {
        $is_allowed = $CI->Delivery_request_model->is_allowed_business_number($business['business_number']);
        $status = $is_allowed ? '✓ 허용' : '✗ 차단';
        output_message("- {$business['business_number']} ({$business['company_name']}): {$status}");
    }
    
    // 존재하지 않는 사업자번호 테스트
    $fake_business_number = '9999999999';
    $is_allowed = $CI->Delivery_request_model->is_allowed_business_number($fake_business_number);
    $status = $is_allowed ? '✓ 허용' : '✗ 차단';
    output_message("- {$fake_business_number} (존재하지 않는 번호): {$status}");

    // 3. 현재 delivery_request_details 매칭 현황
    output_message("\n3. 현재 매칭 현황");
    $matching_query = "
        SELECT 
            COUNT(*) as total_records,
            COUNT(CASE WHEN fc.business_number IS NOT NULL THEN 1 END) as matched_records,
            COUNT(CASE WHEN fc.business_number IS NULL AND drd.cntrct_corp_bizno IS NOT NULL THEN 1 END) as unmatched_records,
            COUNT(CASE WHEN drd.cntrct_corp_bizno IS NULL THEN 1 END) as no_business_number_records
        FROM delivery_request_details drd
        LEFT JOIN filtering_companies fc ON drd.cntrct_corp_bizno = fc.business_number AND fc.is_active = 1
    ";
    
    $result = $CI->db->query($matching_query)->row_array();
    
    output_message("- 전체 납품요구 데이터: " . number_format($result['total_records']) . "건");
    output_message("- 필터링 업체와 매칭: " . number_format($result['matched_records']) . "건");
    output_message("- 미매칭 (사업자번호 있음): " . number_format($result['unmatched_records']) . "건");
    output_message("- 사업자번호 없음: " . number_format($result['no_business_number_records']) . "건");
    
    $total_with_bizno = $result['matched_records'] + $result['unmatched_records'];
    if ($total_with_bizno > 0) {
        $match_rate = round(($result['matched_records'] / $total_with_bizno) * 100, 2);
        output_message("- 매칭률: {$match_rate}%");
    }

    // 4. 필터링 시뮬레이션
    output_message("\n4. 필터링 시뮬레이션 (최근 10건)");
    $recent_data = $CI->db->select('cntrct_corp_bizno, corp_nm, dlvr_req_no')
                         ->from('delivery_request_details')
                         ->where('cntrct_corp_bizno IS NOT NULL')
                         ->order_by('data_sync_dt', 'DESC')
                         ->limit(10)
                         ->get()
                         ->result_array();
    
    $simulation_stats = array(
        'total' => 0,
        'allowed' => 0,
        'filtered' => 0
    );
    
    foreach ($recent_data as $data) {
        $simulation_stats['total']++;
        $is_allowed = $CI->Delivery_request_model->is_allowed_business_number($data['cntrct_corp_bizno']);
        
        if ($is_allowed) {
            $simulation_stats['allowed']++;
            $status = '✓ 저장됨';
        } else {
            $simulation_stats['filtered']++;
            $status = '✗ 필터링';
        }
        
        output_message("- {$data['dlvr_req_no']}: {$data['cntrct_corp_bizno']} ({$data['corp_nm']}) → {$status}");
    }
    
    output_message("\n시뮬레이션 결과:");
    output_message("- 전체: {$simulation_stats['total']}건");
    output_message("- 저장 예상: {$simulation_stats['allowed']}건");
    output_message("- 필터링 예상: {$simulation_stats['filtered']}건");

    // 5. 권장사항
    output_message("\n5. 권장사항");
    if ($result['matched_records'] < $result['unmatched_records']) {
        output_message("⚠️  현재 미매칭 데이터가 매칭 데이터보다 많습니다.");
        output_message("   filtering_companies 테이블에 더 많은 업체를 등록하는 것을 권장합니다.");
    } else {
        output_message("✓  매칭률이 양호합니다.");
    }
    
    if ($active_companies < 50) {
        output_message("⚠️  활성화된 업체 수가 적습니다. 더 많은 업체 등록을 권장합니다.");
    }

    output_message("\n=== 테스트 완료 ===");

} catch (Exception $e) {
    output_message("오류 발생: " . $e->getMessage(), 'ERROR');
    output_message("스택 트레이스: " . $e->getTraceAsString(), 'ERROR');
}
?> 
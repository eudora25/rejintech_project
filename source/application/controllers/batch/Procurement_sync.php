<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 조달청 데이터 동기화 배치 컨트롤러
 * 
 * 조달청 종합쇼핑몰 납품요구 상세정보를 주기적으로 동기화하는 배치 작업
 * 
 * 사용법:
 * - 웹 브라우저: http://localhost/batch/procurement_sync/sync_delivery_requests
 * - CLI: php index.php batch/procurement_sync sync_delivery_requests
 * - 크론: 0 2 * * * cd /var/www/html && php index.php batch/procurement_sync sync_delivery_requests
 */
class Procurement_sync extends CI_Controller
{
    private $batch_name = 'procurement_delivery_sync';
    private $batch_log_id;
    
    public function __construct()
    {
        parent::__construct();
        
        // 모델 및 라이브러리 로드
        $this->load->model('Delivery_request_model');
        $this->load->library('Procurement_api');
        
        // CLI 환경에서만 실행 허용 (보안)
        if (!$this->input->is_cli_request()) {
            // 웹에서 실행 시 간단한 인증 체크 (개발용)
            $auth_key = $this->input->get('auth_key');
            if ($auth_key !== 'rejintech_batch_2025') {
                show_error('배치 작업은 CLI에서만 실행할 수 있습니다.', 403);
            }
        }
        
        // 출력 버퍼링 비활성화 (실시간 출력)
        if (ob_get_level()) {
            ob_end_flush();
        }
        
        // 실행 시간 제한 해제
        set_time_limit(0);
        
        // 메모리 제한 증가
        ini_set('memory_limit', '512M');
    }

    /**
     * 납품요구 상세정보 동기화 메인 함수
     */
    public function sync_delivery_requests()
    {
        $this->output_message("=== 조달청 납품요구 상세정보 동기화 시작 ===");
        $this->output_message("시작 시간: " . date('Y-m-d H:i:s'));
        
        // 배치 로그 시작
        $this->batch_log_id = $this->Delivery_request_model->start_batch_log($this->batch_name);
        $this->output_message("배치 로그 ID: " . $this->batch_log_id);
        
        $stats = array(
            'total' => 0,
            'success' => 0,
            'error' => 0,
            'api_calls' => 0
        );
        
        try {
            // 페이지별 데이터 수집
            $page_no = 1;
            $has_more_data = true;
            
            while ($has_more_data) {
                $this->output_message("\n--- 페이지 {$page_no} 처리 중 ---");
                
                // API 호출 파라미터 설정
                $api_params = array(
                    'pageNo' => $page_no,
                    'numOfRows' => 100
                );
                
                // 날짜 조건 추가 (2025년 1월 1일부터 현재까지)
                $end_date = date('Ymd');
                $start_date = '20200101';
                $api_params['startDate'] = $start_date;
                $api_params['endDate'] = $end_date;
                
                // API 호출
                $api_result = $this->procurement_api->get_delivery_request_details($api_params);
                $stats['api_calls']++;
                
                // API 호출 이력 저장
                $api_result['batch_log_id'] = $this->batch_log_id;
                $this->Delivery_request_model->save_api_call_history($api_result);
                
                if (!$api_result['success']) {
                    $error_msg = "API 호출 실패 (페이지 {$page_no}): " . $api_result['error_message'];
                    $this->output_message($error_msg, 'ERROR');
                    $stats['error']++;
                    
                    // API 호출 실패 시 재시도 또는 중단
                    if ($page_no === 1) {
                        // 첫 페이지 실패 시 배치 중단
                        throw new Exception($error_msg);
                    } else {
                        // 중간 페이지 실패 시 다음 페이지 시도
                        $page_no++;
                        continue;
                    }
                }
                
                // API 응답 데이터 처리
                $parsed_data = $api_result['parsed_data'];
                
                if (!isset($parsed_data['response']) || !isset($parsed_data['response']['body'])) {
                    $this->output_message("API 응답 구조 오류 (페이지 {$page_no})", 'ERROR');
                    $stats['error']++;
                    break;
                }
                
                $response_body = $parsed_data['response']['body'];
                $total_count = isset($response_body['totalCount']) ? (int)$response_body['totalCount'] : 0;
                $items = isset($response_body['items']) ? $response_body['items'] : array();
                
                $this->output_message("총 {$total_count}건 중 " . count($items) . "건 조회됨");
                
                // 데이터가 없으면 중단
                if (empty($items)) {
                    $this->output_message("더 이상 처리할 데이터가 없습니다.");
                    $has_more_data = false;
                    break;
                }
                
                // 각 항목 처리
                foreach ($items as $item) {
                    try {
                        // API 데이터를 DB 저장 형식으로 변환
                        $transformed_data = $this->procurement_api->transform_api_data($item);
                        
                        // 필수 필드 검증
                        if (empty($transformed_data['dlvr_req_no']) || empty($transformed_data['dlvr_req_dtl_seq'])) {
                            $this->output_message("필수 필드 누락: " . json_encode($item), 'WARNING');
                            $stats['error']++;
                            continue;
                        }
                        
                        // DB 저장
                        $saved_id = $this->Delivery_request_model->save_delivery_request($transformed_data);
                        
                        $stats['total']++;
                        $stats['success']++;
                        
                        if ($stats['total'] % 10 === 0) {
                            $this->output_message("처리 완료: {$stats['total']}건 (성공: {$stats['success']}, 오류: {$stats['error']})");
                        }
                        
                    } catch (Exception $e) {
                        $this->output_message("데이터 저장 오류: " . $e->getMessage(), 'ERROR');
                        $stats['error']++;
                    }
                }
                
                // 페이지 처리 완료
                $this->output_message("페이지 {$page_no} 처리 완료 (성공: {$stats['success']}, 오류: {$stats['error']})");
                
                // 다음 페이지 확인
                $current_page_items = count($items);
                $items_per_page = 100;
                
                if ($current_page_items < $items_per_page) {
                    // 마지막 페이지
                    $has_more_data = false;
                } else {
                    $page_no++;
                    
                    // API 호출 간격 (API 제한 고려)
                    sleep(1);
                }
            }
            
            // 배치 완료
            $this->output_message("\n=== 동기화 완료 ===");
            $this->output_message("총 처리 건수: {$stats['total']}");
            $this->output_message("성공: {$stats['success']}");
            $this->output_message("오류: {$stats['error']}");
            $this->output_message("API 호출 횟수: {$stats['api_calls']}");
            
            // 배치 로그 완료
            $this->Delivery_request_model->complete_batch_log($this->batch_log_id, 'SUCCESS', $stats);
            
            $this->output_message("종료 시간: " . date('Y-m-d H:i:s'));
            
        } catch (Exception $e) {
            $error_message = "배치 실행 중 오류 발생: " . $e->getMessage();
            $this->output_message($error_message, 'ERROR');
            
            // 배치 로그 실패 처리
            $this->Delivery_request_model->complete_batch_log($this->batch_log_id, 'FAILED', $stats, $error_message);
            
            // CLI에서는 exit code 1로 종료
            if ($this->input->is_cli_request()) {
                exit(1);
            }
        }
    }

    /**
     * 배치 상태 확인
     */
    public function status()
    {
        $this->output_message("=== 배치 상태 확인 ===");
        
        // 통계 정보 조회
        $stats = $this->Delivery_request_model->get_statistics();
        
        $this->output_message("총 데이터 건수: " . number_format($stats['total_records']));
        $this->output_message("오늘 동기화 건수: " . number_format($stats['today_synced']));
        
        if ($stats['last_batch']) {
            $last_batch = $stats['last_batch'];
            $this->output_message("\n최근 배치 실행 정보:");
            $this->output_message("- 배치명: " . $last_batch['batch_name']);
            $this->output_message("- 실행 시간: " . $last_batch['start_time']);
            $this->output_message("- 상태: " . $last_batch['status']);
            $this->output_message("- 처리 건수: " . number_format($last_batch['success_count']) . "/" . number_format($last_batch['total_count']));
            
            if ($last_batch['error_message']) {
                $this->output_message("- 오류: " . $last_batch['error_message']);
            }
        }
        
        // 최근 배치 로그 조회
        $recent_logs = $this->Delivery_request_model->get_batch_logs(5);
        
        if (!empty($recent_logs)) {
            $this->output_message("\n최근 배치 실행 이력:");
            foreach ($recent_logs as $log) {
                $duration = '';
                if ($log['end_time']) {
                    $start = new DateTime($log['start_time']);
                    $end = new DateTime($log['end_time']);
                    $duration = $start->diff($end)->format('%H:%I:%S');
                }
                
                $this->output_message("- {$log['start_time']} | {$log['status']} | {$log['success_count']}/{$log['total_count']} | {$duration}");
            }
        }
    }

    /**
     * 테스트 API 호출
     */
    public function test_api()
    {
        $this->output_message("=== API 테스트 ===");
        
        // 테스트 파라미터 (2025년 1월 1일부터 현재까지)
        $end_date = date('Ymd');
        $start_date = '20250101';
        
        $test_params = array(
            'pageNo' => 1,
            'numOfRows' => 10,
            'inqryDiv' => 1,
            'startDate' => $start_date,
            'endDate' => $end_date
        );
        
        $this->output_message("테스트 파라미터: " . json_encode($test_params));
        $this->output_message("조회 기간: {$start_date} ~ {$end_date} (2025년 1월 1일부터 현재까지)");
        
        // API 호출
        $result = $this->procurement_api->get_delivery_request_details($test_params);
        
        $this->output_message("API 호출 결과:");
        $this->output_message("- 성공 여부: " . ($result['success'] ? 'YES' : 'NO'));
        $this->output_message("- 응답 코드: " . $result['response_code']);
        $this->output_message("- 응답 시간: " . $result['response_time'] . "ms");
        
        if ($result['success']) {
            $parsed_data = isset($result['parsed_data']) ? $result['parsed_data'] : null;
            
            if ($parsed_data && isset($parsed_data['response']['body']['totalCount'])) {
                $total_count = $parsed_data['response']['body']['totalCount'];
                $this->output_message("- 총 데이터 수: " . number_format($total_count));
            }
            
            if ($parsed_data && isset($parsed_data['response']['body']['items'])) {
                $items = $parsed_data['response']['body']['items'];
                $this->output_message("- 조회된 항목 수: " . count($items));
                
                if (!empty($items)) {
                    $this->output_message("\n첫 번째 항목 샘플:");
                    $sample_item = $items[0];
                    $transformed = $this->procurement_api->transform_api_data($sample_item);
                    
                    $this->output_message("- 납품요구번호: " . $transformed['dlvr_req_no']);
                    $this->output_message("- 품목명: " . $transformed['item_nm']);
                    $this->output_message("- 납품요구일자: " . $transformed['dlvr_req_dt']);
                    $this->output_message("- 공급업체: " . $transformed['supplier_nm']);
                }
            } else {
                $this->output_message("API 응답 데이터 구조를 파싱할 수 없습니다.");
                if (isset($result['raw_response'])) {
                    $this->output_message("원본 응답 (처음 500자): " . substr($result['raw_response'], 0, 500));
                }
            }
        } else {
            $this->output_message("오류 메시지: " . $result['error_message']);
        }
    }

    /**
     * 메시지 출력 (CLI/웹 환경 지원)
     * 
     * @param string $message 출력할 메시지
     * @param string $level 로그 레벨 (INFO, WARNING, ERROR)
     */
    private function output_message($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $formatted_message = "[{$timestamp}] [{$level}] {$message}";
        
        if ($this->input->is_cli_request()) {
            // CLI 환경
            echo $formatted_message . "\n";
        } else {
            // 웹 환경
            echo "<p style='margin:2px; font-family:monospace; font-size:12px;'>";
            echo htmlspecialchars($formatted_message);
            echo "</p>";
            flush();
        }
        
        // 로그 파일에도 기록
        log_message($level === 'ERROR' ? 'error' : 'info', $formatted_message);
    }
} 
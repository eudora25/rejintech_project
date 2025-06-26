<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 데이터 정규화 배치 컨트롤러
 * 
 * delivery_request_details 테이블의 데이터를 정규화된 테이블들로 분산하는 배치 작업
 * 
 * 사용법:
 * - 웹 브라우저: http://localhost/batch/data_normalization/normalize_delivery_data
 * - CLI: php index.php batch/data_normalization normalize_delivery_data
 * - 크론: 0 3 * * * cd /var/www/html && php index.php batch/data_normalization normalize_delivery_data
 */
class Data_normalization extends CI_Controller
{
    private $batch_name = 'data_normalization';
    private $batch_log_id;
    
    public function __construct()
    {
        parent::__construct();
        
        // 모델 로드
        $this->load->model('Delivery_request_model');
        
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
        ini_set('memory_limit', '1G');
    }

    /**
     * 납품요구 데이터 정규화 메인 함수
     */
    public function normalize_delivery_data()
    {
        $this->output_message("=== 납품요구 데이터 정규화 시작 ===");
        $this->output_message("시작 시간: " . date('Y-m-d H:i:s'));
        
        // 배치 로그 시작
        $this->batch_log_id = $this->Delivery_request_model->start_batch_log($this->batch_name);
        $this->output_message("배치 로그 ID: " . $this->batch_log_id);
        
        $stats = array(
            'total_processed' => 0,
            'delivery_requests_created' => 0,
            'delivery_request_items_created' => 0,
            'institutions_created' => 0,
            'companies_created' => 0,
            'contracts_created' => 0,
            'products_created' => 0,
            'errors' => 0
        );
        
        try {
            // 1. 기존 정규화된 테이블들 초기화 (선택사항)
            $this->output_message("\n--- 기존 정규화된 데이터 초기화 ---");
            $this->db->query("SET FOREIGN_KEY_CHECKS = 0");
            $this->output_message("테이블 초기화: delivery_request_items");
            $this->db->truncate('delivery_request_items');
            $this->output_message("테이블 초기화: delivery_requests");
            $this->db->truncate('delivery_requests');
            $this->db->query("SET FOREIGN_KEY_CHECKS = 1");
            
            // 2. 배치 단위로 데이터 처리
            $batch_size = 1000;
            $offset = 0;
            $total_count = $this->db->count_all('delivery_request_details');
            $this->output_message("총 " . $total_count . "건의 데이터 발견");
            
            while ($offset < $total_count) {
                $this->output_message("\n--- 배치 처리: OFFSET {$offset} ---");
                $details_data = $this->db->select('*')
                    ->from('delivery_request_details')
                    ->order_by('dlvr_req_no, dlvr_req_dtl_seq')
                    ->limit($batch_size, $offset)
                    ->get()
                    ->result_array();
                
                if (empty($details_data)) {
                    break;
                }
                
                // 데이터 그룹화 (납품요구번호별)
                $grouped_data = $this->group_data_by_delivery_request($details_data);
                $this->output_message("배치 내 그룹 수: " . count($grouped_data));
                
                // 각 그룹별로 정규화된 테이블에 데이터 삽입
                foreach ($grouped_data as $delivery_req_no => $group_items) {
                    try {
                        $this->process_delivery_group($delivery_req_no, $group_items, $stats);
                        $stats['total_processed']++;
                        if ($stats['total_processed'] % 100 === 0) {
                            $this->output_message("누적 처리 완료: {$stats['total_processed']}개 그룹");
                        }
                    } catch (Exception $e) {
                        $this->output_message("그룹 처리 오류 ({$delivery_req_no}): " . $e->getMessage(), 'ERROR');
                        $stats['errors']++;
                    }
                }
                $offset += $batch_size;
            }
            
            // 5. 배치 완료
            $this->output_message("\n=== 정규화 완료 ===");
            $this->output_message("총 처리 그룹: {$stats['total_processed']}");
            $this->output_message("생성된 납품요구: {$stats['delivery_requests_created']}");
            $this->output_message("생성된 납품항목: {$stats['delivery_request_items_created']}");
            $this->output_message("생성된 기관: {$stats['institutions_created']}");
            $this->output_message("생성된 업체: {$stats['companies_created']}");
            $this->output_message("생성된 계약: {$stats['contracts_created']}");
            $this->output_message("생성된 상품: {$stats['products_created']}");
            $this->output_message("오류: {$stats['errors']}");
            
            // 배치 로그 완료
            $this->Delivery_request_model->complete_batch_log($this->batch_log_id, 'SUCCESS', $stats);
            $this->output_message("종료 시간: " . date('Y-m-d H:i:s'));
        } catch (Exception $e) {
            $error_message = "배치 실행 중 오류 발생: " . $e->getMessage();
            $this->output_message($error_message, 'ERROR');
            $this->Delivery_request_model->complete_batch_log($this->batch_log_id, 'FAILED', $stats, $error_message);
            if ($this->input->is_cli_request()) {
                exit(1);
            }
        }
    }

    /**
     * 기존 정규화된 테이블들 초기화
     */
    private function clear_normalized_tables()
    {
        $tables = [
            'delivery_request_items',
            'delivery_requests',
            'products',
            'contracts',
            'companies',
            'institutions'
        ];
        
        foreach ($tables as $table) {
            $this->db->truncate($table);
            $this->output_message("테이블 초기화: {$table}");
        }
    }

    /**
     * 모든 delivery_request_details 데이터 조회
     */
    private function get_all_delivery_details()
    {
        return $this->db->select('*')
                       ->from('delivery_request_details')
                       ->order_by('dlvr_req_no, dlvr_req_dtl_seq')
                       ->get()
                       ->result_array();
    }

    /**
     * 데이터를 납품요구번호별로 그룹화
     */
    private function group_data_by_delivery_request($details_data)
    {
        $grouped = [];
        
        foreach ($details_data as $item) {
            $delivery_req_no = $item['dlvr_req_no'];
            if (!isset($grouped[$delivery_req_no])) {
                $grouped[$delivery_req_no] = [];
            }
            $grouped[$delivery_req_no][] = $item;
        }
        
        return $grouped;
    }

    /**
     * 납품요구 그룹 처리
     */
    private function process_delivery_group($delivery_req_no, $group_items, &$stats)
    {
        // 첫 번째 아이템에서 공통 정보 추출
        $first_item = $group_items[0];
        
        // 1. 기관 정보 처리
        $institution_id = $this->process_institution($first_item, $stats);
        
        // 2. 업체 정보 처리
        $company_id = $this->process_company($first_item, $stats);
        
        // 3. 계약 정보 처리
        $contract_id = $this->process_contract($first_item, $stats);
        
        // 4. 납품요구 정보 처리
        $delivery_request_id = $this->process_delivery_request($first_item, $institution_id, $company_id, $contract_id, $group_items, $stats);
        
        // 5. 각 아이템별 상품 및 납품항목 처리
        foreach ($group_items as $item) {
            $this->process_delivery_item($item, $delivery_request_id, $stats);
        }
    }

    /**
     * 기관 정보 처리
     */
    private function process_institution($item, &$stats)
    {
        $institution_data = [
            'institution_code' => $item['dminstt_cd'] ?? '',
            'institution_name' => $item['dminstt_nm'] ?? '',
            'region_name' => $item['dminstt_rgn_nm'] ?? '',
            'institution_type' => $item['dmnd_instt_div_nm'] ?? '',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        // 중복 확인
        $existing = $this->db->select('id')->from('institutions')->where('institution_code', $institution_data['institution_code'])->get()->row();
        if ($existing) {
            return $existing->id;
        }
        $this->db->insert('institutions', $institution_data);
        $institution_id = $this->db->insert_id();
        $stats['institutions_created']++;
        return $institution_id;
    }

    /**
     * 업체 정보 처리
     */
    private function process_company($item, &$stats)
    {
        $company_data = [
            'business_number' => $item['cntrct_corp_bizno'] ?? '',
            'company_name' => $item['corp_nm'] ?? '',
            'company_type' => $item['corp_entrprs_div_nm_nm'] ?? '',
            'is_sme' => ($item['smetpr_cmpt_prodct_yn'] === 'Y') ? 1 : 0,
            'branch_office' => $item['brnofce_nm'] ?? '',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $existing = $this->db->select('id')->from('companies')->where('business_number', $company_data['business_number'])->get()->row();
        if ($existing) {
            return $existing->id;
        }
        $this->db->insert('companies', $company_data);
        $company_id = $this->db->insert_id();
        $stats['companies_created']++;
        return $company_id;
    }

    /**
     * 계약 정보 처리
     */
    private function process_contract($item, &$stats)
    {
        $contract_data = [
            'contract_number' => $item['cntrct_no'] ?? '',
            'contract_change_order' => $item['cntrct_chg_ord'] ?? '00',
            'contract_type' => $item['cntrct_cncls_stle_nm'] ?? '',
            'is_mas' => ($item['mas_yn'] === 'Y') ? 1 : 0,
            'is_construction_material' => ($item['cnstwk_mtrl_drct_purchs_obj_yn'] === 'Y') ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $existing = $this->db->select('id')->from('contracts')
            ->where('contract_number', $contract_data['contract_number'])
            ->where('contract_change_order', $contract_data['contract_change_order'])
            ->get()->row();
        if ($existing) {
            return $existing->id;
        }
        $this->db->insert('contracts', $contract_data);
        $contract_id = $this->db->insert_id();
        $stats['contracts_created']++;
        return $contract_id;
    }

    /**
     * 납품요구 정보 처리
     */
    private function process_delivery_request($first_item, $institution_id, $company_id, $contract_id, $group_items, &$stats)
    {
        // 총 수량과 금액 계산
        $total_quantity = 0;
        $total_amount = 0;
        
        foreach ($group_items as $item) {
            $total_quantity += floatval($item['dlvr_req_qty'] ?? 0);
            $total_amount += floatval($item['dlvr_req_amt'] ?? 0);
        }
        
        $delivery_request_data = [
            'delivery_request_number' => $first_item['dlvr_req_no'],
            'delivery_request_change_order' => $first_item['dlvr_req_chg_ord'] ?? '00',
            'delivery_request_name' => $first_item['dlvr_req_nm'] ?? '',
            'delivery_request_date' => $this->format_date($first_item['dlvr_req_dt']),
            'delivery_receipt_date' => $this->format_date($first_item['dlvr_req_rcpt_date']),
            'delivery_deadline_date' => $this->format_date($first_item['dlvr_tmlmt_date']),
            'international_delivery_date' => $this->format_date($first_item['intl_cntrct_dlvr_req_date']),
            'institution_id' => $institution_id,
            'company_id' => $company_id,
            'contract_id' => $contract_id,
            'is_excellent_product' => ($first_item['exclc_prodct_yn'] === 'Y') ? 1 : 0,
            'is_final_delivery' => ($first_item['fnl_dlvr_req_yn'] === 'Y') ? 1 : 0,
            'is_sme_product' => ($first_item['smetpr_cmpt_prodct_yn'] === 'Y') ? 1 : 0,
            'total_quantity' => $total_quantity,
            'total_amount' => $total_amount,
            'data_sync_date' => $first_item['data_sync_dt'] ?? date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('delivery_requests', $delivery_request_data);
        $delivery_request_id = $this->db->insert_id();
        $stats['delivery_requests_created']++;
        
        return $delivery_request_id;
    }

    /**
     * 납품항목 처리
     */
    private function process_delivery_item($item, $delivery_request_id, &$stats)
    {
        // 1. 상품 정보 처리
        $product_id = $this->process_product($item, $stats);
        
        // 2. 납품항목 정보 처리
        $delivery_item_data = [
            'delivery_request_id' => $delivery_request_id,
            'sequence_number' => intval($item['dlvr_req_dtl_seq']),
            'product_id' => $product_id,
            'unit_price' => floatval($item['unit_price'] ?? 0),
            'request_quantity' => floatval($item['req_qty'] ?? 0),
            'delivery_quantity' => floatval($item['dlvr_qty'] ?? 0),
            'increase_decrease_quantity' => floatval($item['incdec_qty'] ?? 0),
            'increase_decrease_amount' => floatval($item['incdec_amt'] ?? 0),
            'total_amount' => floatval($item['total_amt'] ?? 0),
            'delivery_expected_date' => $this->format_date($item['dlvr_expect_dt']),
            'delivery_completion_date' => $this->format_date($item['dlvr_cmplt_dt']),
            'delivery_status_code' => $item['dlvr_status_cd'] ?? '',
            'delivery_status_name' => $item['dlvr_status_nm'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('delivery_request_items', $delivery_item_data);
        $stats['delivery_request_items_created']++;
    }

    /**
     * 상품 정보 처리
     */
    private function process_product($item, &$stats)
    {
        $product_data = [
            'product_code' => $item['prdct_idnt_no'] ?? '',
            'product_name' => $item['prdct_idnt_no_nm'] ?? '',
            'category_id' => 0, // 필요시 category 테이블과 연동
            'unit' => $item['unit_nm'] ?? '',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $existing = $this->db->select('id')->from('products')->where('product_code', $product_data['product_code'])->get()->row();
        if ($existing) {
            return $existing->id;
        }
        $this->db->insert('products', $product_data);
        $product_id = $this->db->insert_id();
        $stats['products_created']++;
        return $product_id;
    }

    /**
     * 날짜 형식 변환 (0000-00-00 → null)
     */
    private function format_date($date_string)
    {
        if (empty($date_string) || $date_string === '0000-00-00') {
            return null;
        }
        return $date_string;
    }

    /**
     * 배치 상태 확인
     */
    public function status()
    {
        $this->output_message("=== 데이터 정규화 배치 상태 ===");
        
        // 최근 배치 로그 조회
        $recent_logs = $this->Delivery_request_model->get_batch_logs(5);
        
        if (empty($recent_logs)) {
            $this->output_message("실행된 배치가 없습니다.");
            return;
        }
        
        foreach ($recent_logs as $log) {
            $this->output_message("배치: {$log['batch_name']}");
            $this->output_message("시작: {$log['start_time']}");
            $this->output_message("종료: {$log['end_time']}");
            $this->output_message("상태: {$log['status']}");
            $this->output_message("총 처리: {$log['total_count']}");
            $this->output_message("성공: {$log['success_count']}");
            $this->output_message("오류: {$log['error_count']}");
            $this->output_message("---");
        }
    }

    /**
     * 메시지 출력
     */
    private function output_message($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $formatted_message = "[{$timestamp}] [{$level}] {$message}";
        
        if ($this->input->is_cli_request()) {
            echo $formatted_message . PHP_EOL;
        } else {
            echo $formatted_message . '<br>';
        }
        
        // 즉시 출력
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
} 
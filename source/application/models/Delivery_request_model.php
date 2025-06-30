<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 납품요구 상세정보 모델
 * 
 * 조달청 종합쇼핑몰 납품요구 상세정보 API 데이터를 처리하는 모델
 */
class Delivery_request_model extends CI_Model
{
    private $table = 'delivery_request_details';
    private $batch_table = 'batch_logs';
    private $api_history_table = 'api_call_history';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * 납품요구 상세정보 저장 또는 업데이트
     * 
     * @param array $data 납품요구 상세정보 데이터
     * @return int 저장된/업데이트된 레코드 ID
     */
    public function save_delivery_request($data)
    {
        // 중복 확인 (납품요구번호 + 상세순번)
        $existing = $this->db->where('dlvr_req_no', $data['dlvr_req_no'])
                            ->where('dlvr_req_dtl_seq', $data['dlvr_req_dtl_seq'])
                            ->get($this->table)
                            ->row();

        $data['data_sync_dt'] = date('Y-m-d H:i:s');

        if ($existing) {
            // 업데이트
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $existing->id)->update($this->table, $data);
            return $existing->id;
        } else {
            // 신규 삽입
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert($this->table, $data);
            return $this->db->insert_id();
        }
    }

    /**
     * 배치 작업 로그 시작
     * 
     * @param string $batch_name 배치 작업명
     * @return int 배치 로그 ID
     */
    public function start_batch_log($batch_name)
    {
        $data = array(
            'batch_name' => $batch_name,
            'start_time' => date('Y-m-d H:i:s'),
            'status' => 'RUNNING',
            'created_at' => date('Y-m-d H:i:s')
        );

        $this->db->insert($this->batch_table, $data);
        return $this->db->insert_id();
    }

    /**
     * 배치 작업 로그 완료
     * 
     * @param int $batch_log_id 배치 로그 ID
     * @param string $status 완료 상태 (SUCCESS/FAILED)
     * @param array $stats 통계 정보
     * @param string $error_message 오류 메시지 (선택사항)
     */
    public function complete_batch_log($batch_log_id, $status, $stats = array(), $error_message = null)
    {
        $data = array(
            'end_time' => date('Y-m-d H:i:s'),
            'status' => $status,
            'total_count' => isset($stats['total']) ? $stats['total'] : 0,
            'success_count' => isset($stats['success']) ? $stats['success'] : 0,
            'error_count' => isset($stats['error']) ? $stats['error'] : 0,
            'api_call_count' => isset($stats['api_calls']) ? $stats['api_calls'] : 0
        );

        if ($error_message) {
            $data['error_message'] = $error_message;
        }

        $this->db->where('id', $batch_log_id)->update($this->batch_table, $data);
    }

    /**
     * API 호출 이력 저장
     * 
     * @param array $api_data API 호출 정보
     * @return int API 호출 이력 ID
     */
    public function save_api_call_history($api_data)
    {
        $data = array(
            'api_name' => $api_data['api_name'],
            'api_url' => $api_data['api_url'],
            'request_params' => json_encode($api_data['request_params']),
            'response_code' => $api_data['response_code'],
            'response_data' => $api_data['response_data'],
            'call_time' => $api_data['call_time'],
            'response_time' => $api_data['response_time'],
            'batch_log_id' => isset($api_data['batch_log_id']) ? $api_data['batch_log_id'] : null,
            'status' => $api_data['status'],
            'error_message' => isset($api_data['error_message']) ? $api_data['error_message'] : null,
            'created_at' => date('Y-m-d H:i:s')
        );

        $this->db->insert($this->api_history_table, $data);
        return $this->db->insert_id();
    }

    /**
     * 납품요구 상세정보 조회
     * 
     * @param array $where 조건
     * @param int $limit 제한
     * @param int $offset 오프셋
     * @return array 조회 결과
     */
    public function get_delivery_requests($where = array(), $limit = null, $offset = null)
    {
        if (!empty($where)) {
            $this->db->where($where);
        }

        if ($limit) {
            $this->db->limit($limit, $offset);
        }

        $this->db->order_by('dlvr_req_dt', 'DESC');
        return $this->db->get($this->table)->result_array();
    }

    /**
     * 배치 로그 조회
     * 
     * @param int $limit 제한
     * @return array 배치 로그 목록
     */
    public function get_batch_logs($limit = 50)
    {
        return $this->db->select('*')
                       ->from($this->batch_table)
                       ->order_by('start_time', 'DESC')
                       ->limit($limit)
                       ->get()
                       ->result_array();
    }

    /**
     * 최근 동기화 일시 조회
     * 
     * @return string 최근 동기화 일시
     */
    public function get_last_sync_time()
    {
        $result = $this->db->select_max('data_sync_dt')
                          ->from($this->table)
                          ->get()
                          ->row();

        return $result ? $result->data_sync_dt : null;
    }

    /**
     * 데이터 통계 조회
     * 
     * @return array 통계 정보
     */
    public function get_statistics()
    {
        $stats = array();

        // 전체 레코드 수
        $stats['total_records'] = $this->db->count_all($this->table);

        // 오늘 동기화된 레코드 수
        $today = date('Y-m-d');
        $stats['today_synced'] = $this->db->where('DATE(data_sync_dt)', $today)
                                         ->count_all_results($this->table);

        // 최근 배치 실행 정보
        $last_batch = $this->db->select('*')
                              ->from($this->batch_table)
                              ->order_by('start_time', 'DESC')
                              ->limit(1)
                              ->get()
                              ->row_array();

        $stats['last_batch'] = $last_batch;

        return $stats;
    }

    /**
     * filtering_companies 테이블에서 활성화된 사업자번호 목록 조회
     * 
     * @return array 허용된 사업자번호 배열
     */
    public function get_allowed_business_numbers()
    {
        $result = $this->db->select('business_number')
                          ->from('filtering_companies')
                          ->where('is_active', 1)
                          ->get()
                          ->result_array();
        
        return array_column($result, 'business_number');
    }

    /**
     * 사업자번호가 filtering_companies에 등록되어 있는지 확인
     * 
     * @param string $business_number 사업자번호
     * @return bool 허용 여부
     */
    public function is_allowed_business_number($business_number)
    {
        if (empty($business_number)) {
            return false;
        }

        $count = $this->db->where('business_number', $business_number)
                         ->where('is_active', 1)
                         ->count_all_results('filtering_companies');
        
        return $count > 0;
    }

    /**
     * 납품요구 상세정보 저장 또는 업데이트 (필터링 적용)
     * 
     * @param array $data 납품요구 상세정보 데이터
     * @param bool $apply_filtering 필터링 적용 여부 (기본값: true)
     * @return int|false 저장된/업데이트된 레코드 ID 또는 false (필터링으로 제외됨)
     */
    public function save_delivery_request_with_filtering($data, $apply_filtering = true)
    {
        // 필터링 적용 시 사업자번호 검증
        if ($apply_filtering) {
            $business_number = isset($data['cntrct_corp_bizno']) ? $data['cntrct_corp_bizno'] : null;
            
            if (!$this->is_allowed_business_number($business_number)) {
                // 로깅용으로 필터링된 정보 기록
                log_message('info', "Filtered out by business_number: {$business_number} - " . 
                          (isset($data['corp_nm']) ? $data['corp_nm'] : 'Unknown Company'));
                return false;
            }
        }

        // 기존 저장 로직 실행
        return $this->save_delivery_request($data);
    }

    /**
     * 필터링 통계 조회
     * 
     * @return array 필터링 관련 통계
     */
    public function get_filtering_statistics()
    {
        $stats = array();

        // filtering_companies 테이블 통계
        $stats['total_filtering_companies'] = $this->db->count_all('filtering_companies');
        $stats['active_filtering_companies'] = $this->db->where('is_active', 1)
                                                       ->count_all_results('filtering_companies');

        // delivery_request_details에서 매칭 통계
        $this->db->select('
            COUNT(*) as total_records,
            COUNT(CASE WHEN fc.business_number IS NOT NULL THEN 1 END) as matched_records,
            COUNT(CASE WHEN fc.business_number IS NULL AND drd.cntrct_corp_bizno IS NOT NULL THEN 1 END) as unmatched_records,
            COUNT(CASE WHEN drd.cntrct_corp_bizno IS NULL THEN 1 END) as no_business_number_records
        ');
        $this->db->from($this->table . ' drd');
        $this->db->join('filtering_companies fc', 'drd.cntrct_corp_bizno = fc.business_number AND fc.is_active = 1', 'left');
        
        $result = $this->db->get()->row_array();
        
        $stats['delivery_matching'] = $result;

        return $stats;
    }
} 
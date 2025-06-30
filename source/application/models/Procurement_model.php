<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Procurement_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    /**
     * [정규화된 데이터 기반] 조달청 납품 요구 데이터 리스트 조회
     * 
     * @param array $params 필터 및 페이징 파라미터
     * @return array
     */
    public function get_delivery_requests($params)
    {
        // 기본값 설정
        $page = max(1, (int)$params['page']);
        $limit = max(1, min(100, (int)$params['limit']));
        $offset = ($page - 1) * $limit;
        
        // 기본 쿼리 구성
        $this->db->select('dr.*, i.institution_name, c.company_name');
        $this->db->from('delivery_requests dr');
        $this->db->join('institutions i', 'dr.institution_id = i.id', 'left');
        $this->db->join('companies c', 'dr.company_id = c.id', 'left');
        
        // 검색 조건 적용
        if (!empty($params['start_date'])) {
            $this->db->where('dr.delivery_request_date >=', $params['start_date']);
        }
        if (!empty($params['end_date'])) {
            $this->db->where('dr.delivery_request_date <=', $params['end_date']);
        }
        if (!empty($params['institution_id'])) {
            $this->db->where('dr.institution_id', $params['institution_id']);
        }
        if (!empty($params['company_id'])) {
            $this->db->where('dr.company_id', $params['company_id']);
        }
        if (!empty($params['product_id'])) {
            $this->db->join('delivery_request_items dri', 'dr.id = dri.delivery_request_id', 'inner');
            $this->db->where('dri.product_id', $params['product_id']);
            $this->db->group_by('dr.id');
        }
        
        // 전체 레코드 수 조회
        $total = $this->db->count_all_results('', false);
        
        // 페이징 적용
        $this->db->limit($limit, $offset);
        
        // 정렬
        $this->db->order_by('dr.delivery_request_date', 'DESC');
        $this->db->order_by('dr.id', 'DESC');
        
        // 쿼리 실행
        $items = $this->db->get()->result_array();
        
        // 날짜 형식 정리
        foreach ($items as &$item) {
            // 날짜가 0000-00-00인 경우 null로 변환
            if ($item['delivery_request_date'] === '0000-00-00') {
                $item['delivery_request_date'] = null;
            }
            if ($item['delivery_receipt_date'] === '0000-00-00') {
                $item['delivery_receipt_date'] = null;
            }
            if ($item['delivery_deadline_date'] === '0000-00-00') {
                $item['delivery_deadline_date'] = null;
            }
            
            // 기타 날짜 필드도 빈 문자열이면 null로 변환
            $item['international_delivery_date'] = $item['international_delivery_date'] ?: null;
            $item['data_sync_date'] = $item['data_sync_date'] ?: null;
        }
        
        return array(
            'total' => $total,
            'items' => $items
        );
    }
    
    /**
     * 쿼리 빌더에 필터 조건을 적용하는 헬퍼 함수
     */
    private function _apply_filters(&$db, $params, $exclude = []) {
        // 기간 필터 (delivery_requests 테이블 기준)
        if (!empty($params['startDate'])) {
            $db->where('dr.delivery_receipt_date >=', $params['startDate']);
        }
        if (!empty($params['endDate'])) {
            $db->where('dr.delivery_receipt_date <=', $params['endDate']);
        }

        // 구분 필터 (delivery_requests 테이블 기준)
        if (!empty($params['exclcProdctYn'])) {
            if ($params['exclcProdctYn'] === '조달') {
                $db->where('dr.is_excellent_product', 1);
            } elseif ($params['exclcProdctYn'] === '마스') {
                $db->where('dr.is_excellent_product', 0);
            }
        }

        // 수요기관 필터 (institutions 테이블 기준)
        if (!empty($params['dminsttNm'])) {
            $db->like('i.institution_name', $params['dminsttNm']);
        }

        // 계약업체 필터 (companies 테이블 기준)
        if (!empty($params['corpNm'])) {
            $db->like('c.company_name', $params['corpNm']);
        }

        // 사업명 검색 (delivery_requests 테이블 기준)
        if (!empty($params['dlvrReqNmSearch'])) {
            $db->like('dr.delivery_request_name', $params['dlvrReqNmSearch']);
        }

        // 계약업체명 검색 (companies 테이블 기준)
        if (!empty($params['corpNameSearch'])) {
            $db->like('c.company_name', $params['corpNameSearch']);
        }
    }
    
    /**
     * 쿼리 빌더에 정렬 조건을 적용하는 헬퍼 함수
     */
    private function _apply_sorting($params) {
        $sortBy = $params['sortBy'] ?? 'dlvrReqRcptDate';
        $sortOrder = $params['sortOrder'] ?? 'DESC';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $fieldMapping = [
            'dlvrReqNo' => 'dr.delivery_request_number',
            'dlvrReqRcptDate' => 'dr.delivery_receipt_date',
            'dlvrTmlmtDate' => 'dr.delivery_deadline_date',
            'dminsttNm' => 'i.institution_name',
            'corpNm' => 'c.company_name',
            'bizName' => 'c.company_name',
            'dlvrReqNm' => 'dr.delivery_request_name',
            'totalAmount' => 'dr.total_amount'
        ];

        if (array_key_exists($sortBy, $fieldMapping)) {
            $this->db->order_by($fieldMapping[$sortBy], $sortOrder);
        } else {
            // 기본 정렬
            $this->db->order_by('dr.delivery_receipt_date', 'DESC');
        }
        // 2차 정렬 기준 추가
        $this->db->order_by('dr.id', 'ASC');
    }
    
    /**
     * 필터링된 결과에 대한 금액 합계를 계산
     */
    public function get_amount_totals($params = []) {
        $total_db = clone $this->db;
        $total_db->reset_query();

        $total_db->select("
            SUM(CASE WHEN dr.is_excellent_product = 1 THEN dr.total_amount ELSE 0 END) as jodalTotalAmount,
            SUM(CASE WHEN dr.is_excellent_product = 0 THEN dr.total_amount ELSE 0 END) as masTotalAmount,
            SUM(dr.total_amount) as totalAmount
        ");
        
        $total_db->from('delivery_requests dr');
        $total_db->join('institutions i', 'dr.institution_id = i.id', 'left');
        $total_db->join('companies c', 'dr.company_id = c.id', 'left');
        $total_db->join('contracts ct', 'dr.contract_id = ct.id', 'left');
        
        // 동일한 필터 조건 적용
        $this->_apply_filters($total_db, $params);
        
        $result = $total_db->get()->row_array();
        
        return [
            'jodalTotalAmount' => (float)($result['jodalTotalAmount'] ?? 0),
            'masTotalAmount' => (float)($result['masTotalAmount'] ?? 0),
            'totalAmount' => (float)($result['totalAmount'] ?? 0)
        ];
    }
    
    /**
     * 수요기관별 통계 조회 (PowerPoint 3번째 슬라이드 요구사항 반영)
     * 
     * PowerPoint 요구사항:
     * 1. 납품요구접수일자에서 연도만 필터링
     * 2. 수요기관별 그룹핑
     * 3. 전년 대비 증감률 계산
     * 4. 우수제품여부별 분류
     * 5. 증감금액 합산 표기
     * 6. 페이징 지원 추가
     */
    public function get_institution_statistics($params = []) {
        $year = isset($params['year']) ? (int)$params['year'] : (int)date('Y');
        $include_prev_year = isset($params['includePrevYear']) ? $params['includePrevYear'] : true;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $size = isset($params['size']) ? (int)$params['size'] : 10;
        
        // 전체 개수 조회
        $total_count = $this->_get_institution_stats_count($year, $params);
        
        // 당해년도 통계 조회 (페이징 적용)
        $current_year_stats = $this->_get_yearly_institution_stats($year, $params, $page, $size);
        
        // 전년도 통계 조회 (증감률 계산용 - 페이징 없이 전체 데이터)
        $prev_year_stats = [];
        if ($include_prev_year) {
            $prev_year_params = $params;
            $prev_year_params['year'] = $year - 1;
            // 페이징 없이 전체 데이터 조회하여 증감률 계산에 사용
            $prev_year_stats = $this->_get_yearly_institution_stats($year - 1, $prev_year_params);
        }
        
        // 전년 대비 증감률 계산
        $institutions_with_growth = $this->_calculate_growth_rates($current_year_stats, $prev_year_stats);
        
        // 전체 통계 계산 (전체 데이터 기준)
        $all_current_stats = $this->_get_yearly_institution_stats($year, $params);
        $total_current_amount = array_sum(array_column($all_current_stats, 'current_amount'));
        $total_prev_amount = array_sum(array_column($prev_year_stats, 'current_amount'));
        
        $overall_growth_rate = 0;
        if ($total_prev_amount > 0) {
            $overall_growth_rate = (($total_current_amount - $total_prev_amount) / $total_prev_amount) * 100;
        }
        
        return [
            'total' => $total_count,
            'totalAmount' => $total_current_amount,
            'prevYearAmount' => $total_prev_amount,
            'growthRate' => round($overall_growth_rate, 2),
            'institutions' => $institutions_with_growth
        ];
    }
    
    /**
     * 특정 연도의 수요기관별 통계 조회 (정규화된 테이블 구조 반영)
     */
    private function _get_yearly_institution_stats($year, $params = [], $page = null, $size = null) {
        // 정규화된 테이블 구조에 맞는 SELECT 구문
        $this->db->select("
            i.institution_name as dminsttNm,
            i.institution_code as dminsttCd,
            COUNT(DISTINCT dr.delivery_request_number) as delivery_count,
            SUM(dr.total_amount) as current_amount,
            SUM(CASE WHEN dr.is_excellent_product = 1 THEN dr.total_amount ELSE 0 END) as exclc_prodct_amount,
            SUM(CASE WHEN dr.is_excellent_product = 0 THEN dr.total_amount ELSE 0 END) as general_prodct_amount,
            CASE 
                WHEN SUM(CASE WHEN dr.is_excellent_product = 1 THEN dr.total_amount ELSE 0 END) > 
                     SUM(CASE WHEN dr.is_excellent_product = 0 THEN dr.total_amount ELSE 0 END) 
                THEN 'Y' ELSE 'N' 
            END as main_exclc_prodct_yn
        ");
        
        $this->db->from('delivery_requests dr');
        $this->db->join('institutions i', 'dr.institution_id = i.id', 'left');
        $this->db->join('companies c', 'dr.company_id = c.id', 'left');
        $this->db->join('contracts ct', 'dr.contract_id = ct.id', 'left');
        
        // 연도별 필터링
        $this->db->where('YEAR(dr.delivery_receipt_date)', $year);
        
        // 기타 필터 적용
        if (!empty($params['dminsttNm'])) {
            $this->db->like('i.institution_name', $params['dminsttNm']);
        }
        
        if (!empty($params['exclcProdctYn'])) {
            $this->db->where('dr.is_excellent_product', $params['exclcProdctYn'] === 'Y' ? 1 : 0);
        }
        
        if (!empty($params['corpNm'])) {
            $this->db->like('c.company_name', $params['corpNm']);
        }
        
        // 수요기관별 그룹핑
        $this->db->group_by(['i.id', 'i.institution_name', 'i.institution_code']);
        $this->db->order_by('current_amount', 'DESC');
        
        // 페이징 적용 (페이지 정보가 있을 때만)
        if ($page !== null && $size !== null) {
            $offset = ($page - 1) * $size;
            $this->db->limit($size, $offset);
        }
        
        return $this->db->get()->result_array();
    }
    
    /**
     * 수요기관별 통계 전체 개수 조회 (정규화된 테이블 구조 반영)
     */
    private function _get_institution_stats_count($year, $params = []) {
        $this->db->select("COUNT(DISTINCT i.id) as total_count");
        $this->db->from('delivery_requests dr');
        $this->db->join('institutions i', 'dr.institution_id = i.id', 'left');
        $this->db->join('companies c', 'dr.company_id = c.id', 'left');
        
        // 연도별 필터링
        $this->db->where('YEAR(dr.delivery_receipt_date)', $year);
        
        // 필터 적용
        if (!empty($params['dminsttNm'])) {
            $this->db->like('i.institution_name', $params['dminsttNm']);
        }
        
        if (!empty($params['exclcProdctYn'])) {
            $this->db->where('dr.is_excellent_product', $params['exclcProdctYn'] === 'Y' ? 1 : 0);
        }
        
        if (!empty($params['corpNm'])) {
            $this->db->like('c.company_name', $params['corpNm']);
        }
        
        $result = $this->db->get()->row_array();
        return $result ? (int)$result['total_count'] : 0;
    }
    
    /**
     * 전년 대비 증감률 계산
     */
    private function _calculate_growth_rates($current_stats, $prev_stats) {
        // 전년도 데이터를 기관별로 인덱싱
        $prev_stats_indexed = [];
        foreach ($prev_stats as $stat) {
            $prev_stats_indexed[$stat['dminsttCd']] = $stat;
        }
        
        $result = [];
        foreach ($current_stats as $current) {
            $institution_code = $current['dminsttCd'];
            $current_amount = (float)$current['current_amount'];
            
            // 전년도 데이터 찾기
            $prev_amount = 0;
            if (isset($prev_stats_indexed[$institution_code])) {
                $prev_amount = (float)$prev_stats_indexed[$institution_code]['current_amount'];
            }
            
            // 증감률 계산
            $growth_rate = 0;
            if ($prev_amount > 0) {
                $growth_rate = (($current_amount - $prev_amount) / $prev_amount) * 100;
            } elseif ($current_amount > 0) {
                $growth_rate = 100; // 전년도 데이터가 없고 올해 데이터가 있으면 100% 증가
            }
            
            // PowerPoint 요구사항에 맞는 응답 구조
            $result[] = [
                'dminsttNm' => $current['dminsttNm'],
                'dminsttCd' => $current['dminsttCd'],
                'currentAmount' => $current_amount,
                'prevAmount' => $prev_amount,
                'growthRate' => round($growth_rate, 2),
                'deliveryCount' => (int)$current['delivery_count'],
                'exclcProdctAmount' => (float)$current['exclc_prodct_amount'],
                'generalProdctAmount' => (float)$current['general_prodct_amount'],
                'exclcProdctYn' => $current['main_exclc_prodct_yn']
            ];
        }
        
        return $result;
    }
    
    /**
     * 업체별 통계 조회 (실제 테이블 구조 반영)
     */
    public function get_company_statistics($params = []) {
        $this->db->select("
            drd.corp_nm as company_name,
            drd.corp_entrprs_div_nm_nm as company_type,
            COUNT(DISTINCT drd.dlvr_req_no) as delivery_count,
            SUM(drd.incdec_amt) as total_amount,
            AVG(drd.incdec_amt) as avg_amount
        ");
        
        $this->db->from('delivery_request_details drd');
        
        $this->_apply_filters($params);
        
        $this->db->group_by(['drd.cntrct_corp_bizno', 'drd.corp_nm', 'drd.corp_entrprs_div_nm_nm']);
        $this->db->order_by('total_amount', 'DESC');
        
        return $this->db->get()->result_array();
    }
    
    /**
     * 품목별 통계 조회 (실제 테이블 구조 반영)
     */
    public function get_product_statistics($params = []) {
        $this->db->select("
            drd.prdct_clsfc_no_nm as category_name,
            drd.dtil_prdct_clsfc_no_nm as detail_category_name,
            COUNT(drd.id) as item_count,
            SUM(drd.incdec_amt) as total_amount,
            AVG(drd.incdec_amt) as avg_amount,
            SUM(drd.incdec_qty) as total_quantity
        ");
        
        $this->db->from('delivery_request_details drd');
        
        $this->_apply_filters($params);
        
        $this->db->group_by(['drd.prdct_clsfc_no', 'drd.prdct_clsfc_no_nm', 'drd.dtil_prdct_clsfc_no_nm']);
        $this->db->order_by('total_amount', 'DESC');
        
        return $this->db->get()->result_array();
    }

    /**
     * 제품 목록을 조회합니다.
     */
    public function get_products()
    {
        $this->db->select('*');
        $this->db->from('products');
        $this->db->order_by('id', 'ASC');
        
        $query = $this->db->get();
        return $query->result_array();
    }
} 
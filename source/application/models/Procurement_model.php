<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Procurement_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    /**
     * 조달청 데이터 전체 리스트 조회 (API 요구사항에 맞는 형식)
     */
    public function get_delivery_requests($params = []) {
        // 기본 파라미터 설정 (page, size로 변경)
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $size = isset($params['size']) ? (int)$params['size'] : 50;
        $offset = ($page - 1) * $size;
        
        // 기본 쿼리 구성
        $this->db->select("
            dr.delivery_request_number as dlvrReqNo,
            DATE_FORMAT(dr.delivery_receipt_date, '%Y-%m-%d') as dlvrReqRcptDate,
            i.institution_name as dminsttNm,
            i.region_name as dminsttRgnNm,
            c.company_name as corpNm,
            dr.delivery_request_name as dlvrReqNm,
            pc.category_name as prdctClsfcNoNm,
            pc.detail_category_name as dtilPrdctClsfcNoNm,
            p.product_code as prdctIdntNo,
            p.product_name as prdctIdntNoNm,
            dri.increase_decrease_quantity as incdecQty,
            dri.unit_price as prdctUprc,
            dri.increase_decrease_amount as incdecAmt,
            i.institution_code as dminsttCd,
            CASE WHEN dr.is_excellent_product THEN 'Y' ELSE 'N' END as exclcProdctYn,
            CASE WHEN dr.is_excellent_product THEN 'CSO' ELSE 'MAS' END as type,
            c.company_name as bizName
        ");
        
        $this->db->from('delivery_request_items dri');
        $this->db->join('delivery_requests dr', 'dr.id = dri.delivery_request_id');
        $this->db->join('institutions i', 'i.id = dr.institution_id');
        $this->db->join('companies c', 'c.id = dr.company_id');
        $this->db->join('products p', 'p.id = dri.product_id');
        $this->db->join('product_categories pc', 'pc.id = p.category_id');
        
        // 필터 조건 적용
        $this->_apply_filters($params);
        
        // 전체 카운트 구하기 (페이징용)
        $total_query = clone $this->db;
        $total = $total_query->count_all_results('', FALSE);
        
        // 정렬 처리 (sortModel 적용)
        $this->_apply_sorting($params);
        
        // 페이징 적용
        $this->db->limit($size, $offset);
        
        $data = $this->db->get()->result_array();
        
        // 금액 집계 정보
        $totals = $this->get_amount_totals($params);
        
        // 필터 옵션 정보
        $filters = $this->get_filter_options($params);
        
        return [
            'page' => $page,
            'size' => $size,
            'total' => $total,
            'jodalTotalAmount' => $totals['jodalTotalAmount'],
            'masTotalAmount' => $totals['masTotalAmount'],
            'totalAmount' => $totals['totalAmount'],
            'filterPrdctClsfcNoNm' => $filters['filterPrdctClsfcNoNm'],
            'filterDminsttNm' => $filters['filterDminsttNm'],
            'filterCorpNm' => $filters['filterCorpNm'],
            'data' => $data
        ];
    }
    
    /**
     * 정렬 조건 적용
     */
    private function _apply_sorting($params) {
        if (isset($params['sortModel']) && is_array($params['sortModel'])) {
            foreach ($params['sortModel'] as $field => $direction) {
                $direction = strtoupper($direction);
                if (!in_array($direction, ['ASC', 'DESC'])) {
                    $direction = 'ASC';
                }
                
                // 필드 매핑
                switch ($field) {
                    case 'bizName':
                        $this->db->order_by('c.company_name', $direction);
                        break;
                    case 'dlvrReqRcptDate':
                        $this->db->order_by('dr.delivery_receipt_date', $direction);
                        break;
                    case 'dminsttNm':
                        $this->db->order_by('i.institution_name', $direction);
                        break;
                    case 'corpNm':
                        $this->db->order_by('c.company_name', $direction);
                        break;
                    case 'incdecAmt':
                        $this->db->order_by('dri.increase_decrease_amount', $direction);
                        break;
                    case 'prdctUprc':
                        $this->db->order_by('dri.unit_price', $direction);
                        break;
                    default:
                        // 기본 정렬
                        $this->db->order_by('dr.delivery_receipt_date', 'DESC');
                        $this->db->order_by('dr.id', 'ASC');
                        break;
                }
            }
        } else {
            // 기본 정렬
            $this->db->order_by('dr.delivery_receipt_date', 'DESC');
            $this->db->order_by('dr.id', 'ASC');
            $this->db->order_by('dri.sequence_number', 'ASC');
        }
    }
    
    /**
     * 금액 집계 정보 조회
     */
    public function get_amount_totals($params = []) {
        $this->db->select("
            SUM(CASE WHEN dr.is_excellent_product = 1 THEN dri.increase_decrease_amount ELSE 0 END) as jodalTotalAmount,
            SUM(CASE WHEN dr.is_excellent_product = 0 THEN dri.increase_decrease_amount ELSE 0 END) as masTotalAmount,
            SUM(dri.increase_decrease_amount) as totalAmount
        ");
        
        $this->db->from('delivery_request_items dri');
        $this->db->join('delivery_requests dr', 'dr.id = dri.delivery_request_id');
        $this->db->join('institutions i', 'i.id = dr.institution_id');
        $this->db->join('companies c', 'c.id = dr.company_id');
        $this->db->join('products p', 'p.id = dri.product_id');
        $this->db->join('product_categories pc', 'pc.id = p.category_id');
        
        // 동일한 필터 조건 적용
        $this->_apply_filters($params);
        
        $result = $this->db->get()->row_array();
        
        return [
            'jodalTotalAmount' => (float)($result['jodalTotalAmount'] ?? 0),
            'masTotalAmount' => (float)($result['masTotalAmount'] ?? 0),
            'totalAmount' => (float)($result['totalAmount'] ?? 0)
        ];
    }
    
    /**
     * 필터 옵션 조회
     */
    public function get_filter_options($params = []) {
        // 임시로 간단한 배열 반환 (테스트용)
        return [
            'filterPrdctClsfcNoNm' => ['컴퓨터용품', '사무용품', '기계장비'],
            'filterDminsttNm' => ['서울특별시', '부산광역시', '대구광역시'],
            'filterCorpNm' => ['삼성전자', 'LG전자', 'SK하이닉스']
        ];
    }
    
    /**
     * 필터 조건 적용
     */
    private function _apply_filters($params, $exclude = []) {
        // type 필터 (CSO, MAS 등)
        if (isset($params['types']) && is_array($params['types']) && !in_array('types', $exclude)) {
            $type_conditions = [];
            foreach ($params['types'] as $type) {
                if ($type === 'CSO') {
                    $type_conditions[] = 'dr.is_excellent_product = 1';
                } elseif ($type === 'MAS') {
                    $type_conditions[] = 'dr.is_excellent_product = 0';
                }
            }
            if (!empty($type_conditions)) {
                $this->db->where('(' . implode(' OR ', $type_conditions) . ')');
            }
        }
        
        // 우수제품 여부 필터 (기존 호환성 유지)
        if (isset($params['exclcProdctYn']) && !in_array('exclcProdctYn', $exclude)) {
            if ($params['exclcProdctYn'] === 'Y') {
                $this->db->where('dr.is_excellent_product', 1);
            } elseif ($params['exclcProdctYn'] === 'N') {
                $this->db->where('dr.is_excellent_product', 0);
            }
        }
        
        // 납품요구접수일자 필터
        if (isset($params['dlvrReqRcptDate']) && !in_array('dlvrReqRcptDate', $exclude)) {
            $this->db->where('dr.delivery_receipt_date', $params['dlvrReqRcptDate']);
        }
        
        // 수요기관명 필터
        if (isset($params['dminsttNm']) && !in_array('dminsttNm', $exclude)) {
            $this->db->where('i.institution_name', $params['dminsttNm']);
        }
        
        // 수요기관지역명 필터
        if (isset($params['dminsttRgnNm']) && !in_array('dminsttRgnNm', $exclude)) {
            $this->db->where('i.region_name', $params['dminsttRgnNm']);
        }
        
        // 업체명 필터
        if (isset($params['corpNm']) && !in_array('corpNm', $exclude)) {
            $this->db->where('c.company_name', $params['corpNm']);
        }
        
        // 납품요구건명 필터 (LIKE 검색)
        if (isset($params['dlvrReqNm']) && !in_array('dlvrReqNm', $exclude)) {
            $this->db->like('dr.delivery_request_name', $params['dlvrReqNm']);
        }
        
        // 품명 필터
        if (isset($params['prdctClsfcNoNm']) && !in_array('prdctClsfcNoNm', $exclude)) {
            $this->db->where('pc.category_name', $params['prdctClsfcNoNm']);
        }
        
        // 세부품명 필터
        if (isset($params['dtilPrdctClsfcNoNm']) && !in_array('dtilPrdctClsfcNoNm', $exclude)) {
            $this->db->where('pc.detail_category_name', $params['dtilPrdctClsfcNoNm']);
        }
        
        // 물품식별번호 필터
        if (isset($params['prdctIdntNo']) && !in_array('prdctIdntNo', $exclude)) {
            $this->db->where('p.product_code', $params['prdctIdntNo']);
        }
        
        // 물품규격명 필터 (LIKE 검색)
        if (isset($params['prdctIdntNoNm']) && !in_array('prdctIdntNoNm', $exclude)) {
            $this->db->like('p.product_name', $params['prdctIdntNoNm']);
        }
        
        // 증감수량 필터
        if (isset($params['incdecQty']) && !in_array('incdecQty', $exclude)) {
            $this->db->where('dri.increase_decrease_quantity', $params['incdecQty']);
        }
        
        // 물품단가 필터
        if (isset($params['prdctUprc']) && !in_array('prdctUprc', $exclude)) {
            $this->db->where('dri.unit_price', $params['prdctUprc']);
        }
        
        // 증감금액 필터
        if (isset($params['incdecAmt']) && !in_array('incdecAmt', $exclude)) {
            $this->db->where('dri.increase_decrease_amount', $params['incdecAmt']);
        }
        
        // 수요기관코드 필터
        if (isset($params['dminsttCd']) && !in_array('dminsttCd', $exclude)) {
            $this->db->where('i.institution_code', $params['dminsttCd']);
        }
        
        // 날짜 범위 필터
        if (isset($params['dateFrom']) && !in_array('dateFrom', $exclude)) {
            $this->db->where('dr.delivery_receipt_date >=', $params['dateFrom']);
        }
        
        if (isset($params['dateTo']) && !in_array('dateTo', $exclude)) {
            $this->db->where('dr.delivery_receipt_date <=', $params['dateTo']);
        }
        
        // 금액 범위 필터
        if (isset($params['amountFrom']) && !in_array('amountFrom', $exclude)) {
            $this->db->where('dri.increase_decrease_amount >=', $params['amountFrom']);
        }
        
        if (isset($params['amountTo']) && !in_array('amountTo', $exclude)) {
            $this->db->where('dri.increase_decrease_amount <=', $params['amountTo']);
        }
    }
    
    /**
     * 수요기관별 통계 조회
     */
    public function get_institution_statistics($params = []) {
        $this->db->select("
            i.institution_name,
            i.region_name,
            COUNT(DISTINCT dr.id) as delivery_count,
            SUM(dri.increase_decrease_amount) as total_amount,
            AVG(dri.increase_decrease_amount) as avg_amount
        ");
        
        $this->db->from('delivery_request_items dri');
        $this->db->join('delivery_requests dr', 'dr.id = dri.delivery_request_id');
        $this->db->join('institutions i', 'i.id = dr.institution_id');
        $this->db->join('companies c', 'c.id = dr.company_id');
        $this->db->join('products p', 'p.id = dri.product_id');
        $this->db->join('product_categories pc', 'pc.id = p.category_id');
        
        $this->_apply_filters($params);
        
        $this->db->group_by(['i.id', 'i.institution_name', 'i.region_name']);
        $this->db->order_by('total_amount', 'DESC');
        
        return $this->db->get()->result_array();
    }
    
    /**
     * 업체별 통계 조회
     */
    public function get_company_statistics($params = []) {
        $this->db->select("
            c.company_name,
            c.company_type,
            COUNT(DISTINCT dr.id) as delivery_count,
            SUM(dri.increase_decrease_amount) as total_amount,
            AVG(dri.increase_decrease_amount) as avg_amount
        ");
        
        $this->db->from('delivery_request_items dri');
        $this->db->join('delivery_requests dr', 'dr.id = dri.delivery_request_id');
        $this->db->join('companies c', 'c.id = dr.company_id');
        $this->db->join('institutions i', 'i.id = dr.institution_id');
        $this->db->join('products p', 'p.id = dri.product_id');
        $this->db->join('product_categories pc', 'pc.id = p.category_id');
        
        $this->_apply_filters($params);
        
        $this->db->group_by(['c.id', 'c.company_name', 'c.company_type']);
        $this->db->order_by('total_amount', 'DESC');
        
        return $this->db->get()->result_array();
    }
    
    /**
     * 품목별 통계 조회
     */
    public function get_product_statistics($params = []) {
        $this->db->select("
            pc.category_name,
            pc.detail_category_name,
            COUNT(dri.id) as item_count,
            SUM(dri.increase_decrease_amount) as total_amount,
            AVG(dri.increase_decrease_amount) as avg_amount,
            SUM(dri.increase_decrease_quantity) as total_quantity
        ");
        
        $this->db->from('delivery_request_items dri');
        $this->db->join('delivery_requests dr', 'dr.id = dri.delivery_request_id');
        $this->db->join('products p', 'p.id = dri.product_id');
        $this->db->join('product_categories pc', 'pc.id = p.category_id');
        $this->db->join('institutions i', 'i.id = dr.institution_id');
        $this->db->join('companies c', 'c.id = dr.company_id');
        
        $this->_apply_filters($params);
        
        $this->db->group_by(['pc.id', 'pc.category_name', 'pc.detail_category_name']);
        $this->db->order_by('total_amount', 'DESC');
        
        return $this->db->get()->result_array();
    }
} 
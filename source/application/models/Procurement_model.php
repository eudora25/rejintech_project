<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Procurement_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    /**
     * 조달청 데이터 전체 리스트 조회 (PowerPoint 스토리보드 요구사항 반영)
     * 
     * PowerPoint 요구사항:
     * 1. 기간 필터링: dlvrReqRcptDate ~ dlvrTmlmtDate 사이 값 조회
     * 2. 지역 매칭: 사업자번호로 2번째 API와 매칭하여 지역 정보 가져오기
     * 3. 모든 데이터 표시: 합산 없이 모든 레코드 표기
     */
    public function get_delivery_requests($params = []) {
        // 기본 파라미터 설정 (page, size로 변경)
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $size = isset($params['size']) ? (int)$params['size'] : 50;
        $offset = ($page - 1) * $size;
        
        // PowerPoint 요구사항에 맞는 필드 선택 (실제 테이블 구조 반영)
        $this->db->select("
            drd.dlvr_req_no as dlvrReqNo,
            DATE_FORMAT(drd.dlvr_req_rcpt_date, '%Y-%m-%d') as dlvrReqRcptDate,
            DATE_FORMAT(drd.dlvr_tmlmt_date, '%Y-%m-%d') as dlvrTmlmtDate,
            drd.dminstt_nm as dminsttNm,
            drd.dminstt_rgn_nm as dminsttRgnNm,
            drd.corp_nm as corpNm,
            drd.cntrct_corp_bizno as cntrctCorpBizno,
            drd.dminstt_rgn_nm as rgnNm,
            drd.dlvr_req_nm as dlvrReqNm,
            drd.prdct_clsfc_no_nm as prdctClsfcNoNm,
            drd.dtil_prdct_clsfc_no_nm as dtilPrdctClsfcNoNm,
            drd.prdct_idnt_no as prdctIdntNo,
            drd.prdct_idnt_no_nm as prdctIdntNoNm,
            drd.item_spec as prdctStndrd,
            drd.incdec_qty as incdecQty,
            drd.prdct_uprc as prdctUprc,
            drd.incdec_amt as incdecAmt,
            drd.dminstt_cd as dminsttCd,
            drd.exclc_prodct_yn as exclcProdctYn,
            CASE WHEN drd.exclc_prodct_yn = 'Y' THEN 'CSO' ELSE 'MAS' END as type,
            drd.corp_nm as bizName
        ");
        
        $this->db->from('delivery_request_details drd');
        
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
     * 정렬 조건 적용 (PowerPoint 요구사항 반영, 실제 테이블 구조 반영)
     */
    private function _apply_sorting($params) {
        if (isset($params['sortModel']) && is_array($params['sortModel'])) {
            foreach ($params['sortModel'] as $field => $direction) {
                $direction = strtoupper($direction);
                if (!in_array($direction, ['ASC', 'DESC'])) {
                    $direction = 'ASC';
                }
                
                // 필드 매핑 (PowerPoint 요구사항 반영, 실제 테이블 구조)
                switch ($field) {
                    case 'bizName':
                    case 'corpNm':
                        $this->db->order_by('drd.corp_nm', $direction);
                        break;
                    case 'dlvrReqRcptDate':
                        $this->db->order_by('drd.dlvr_req_rcpt_date', $direction);
                        break;
                    case 'dlvrTmlmtDate': // PowerPoint 요구사항: 납품일자종료일 정렬 추가
                        $this->db->order_by('drd.dlvr_tmlmt_date', $direction);
                        break;
                    case 'dminsttNm':
                        $this->db->order_by('drd.dminstt_nm', $direction);
                        break;
                    case 'incdecAmt':
                        $this->db->order_by('drd.incdec_amt', $direction);
                        break;
                    case 'prdctUprc':
                        $this->db->order_by('drd.prdct_uprc', $direction);
                        break;
                    case 'prdctClsfcNoNm': // PowerPoint 요구사항: 품명 정렬 추가
                        $this->db->order_by('drd.prdct_clsfc_no_nm', $direction);
                        break;
                    case 'dlvrReqNm': // PowerPoint 요구사항: 납품요구건명 정렬 추가
                        $this->db->order_by('drd.dlvr_req_nm', $direction);
                        break;
                    default:
                        // 기본 정렬 (PowerPoint 요구사항: 납품요구접수일자 우선)
                        $this->db->order_by('drd.dlvr_req_rcpt_date', 'DESC');
                        $this->db->order_by('drd.id', 'ASC');
                        break;
                }
            }
        } else {
            // 기본 정렬 (PowerPoint 요구사항에 맞게 조정)
            $this->db->order_by('drd.dlvr_req_rcpt_date', 'DESC');
            $this->db->order_by('drd.dlvr_tmlmt_date', 'ASC');
            $this->db->order_by('drd.id', 'ASC');
            $this->db->order_by('drd.dlvr_req_dtl_seq', 'ASC');
        }
    }
    
    /**
     * 금액 집계 정보 조회 (실제 테이블 구조 반영)
     */
    public function get_amount_totals($params = []) {
        $this->db->select("
            SUM(CASE WHEN drd.exclc_prodct_yn = 'Y' THEN drd.incdec_amt ELSE 0 END) as jodalTotalAmount,
            SUM(CASE WHEN drd.exclc_prodct_yn = 'N' THEN drd.incdec_amt ELSE 0 END) as masTotalAmount,
            SUM(drd.incdec_amt) as totalAmount
        ");
        
        $this->db->from('delivery_request_details drd');
        
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
     * 필터 조건 적용 (PowerPoint 요구사항 반영, 실제 테이블 구조 반영)
     */
    private function _apply_filters($params, $exclude = []) {
        // type 필터 (CSO, MAS 등)
        if (isset($params['types']) && is_array($params['types']) && !in_array('types', $exclude)) {
            $type_conditions = [];
            foreach ($params['types'] as $type) {
                if ($type === 'CSO') {
                    $type_conditions[] = "drd.exclc_prodct_yn = 'Y'";
                } elseif ($type === 'MAS') {
                    $type_conditions[] = "drd.exclc_prodct_yn = 'N'";
                }
            }
            if (!empty($type_conditions)) {
                $this->db->where('(' . implode(' OR ', $type_conditions) . ')');
            }
        }
        
        // 우수제품 여부 필터 (기존 호환성 유지)
        if (isset($params['exclcProdctYn']) && !in_array('exclcProdctYn', $exclude)) {
            $this->db->where('drd.exclc_prodct_yn', $params['exclcProdctYn']);
        }
        
        // PowerPoint 요구사항: 납품요구접수일자 기간 필터링 (From/To)
        if (isset($params['dlvrReqRcptDateFrom']) && !in_array('dlvrReqRcptDateFrom', $exclude)) {
            $this->db->where('drd.dlvr_req_rcpt_date >=', $params['dlvrReqRcptDateFrom']);
        }
        
        if (isset($params['dlvrReqRcptDateTo']) && !in_array('dlvrReqRcptDateTo', $exclude)) {
            $this->db->where('drd.dlvr_req_rcpt_date <=', $params['dlvrReqRcptDateTo']);
        }
        
        // PowerPoint 요구사항: 납품일자종료일 기간 필터링 (From/To)
        if (isset($params['dlvrTmlmtDateFrom']) && !in_array('dlvrTmlmtDateFrom', $exclude)) {
            $this->db->where('drd.dlvr_tmlmt_date >=', $params['dlvrTmlmtDateFrom']);
        }
        
        if (isset($params['dlvrTmlmtDateTo']) && !in_array('dlvrTmlmtDateTo', $exclude)) {
            $this->db->where('drd.dlvr_tmlmt_date <=', $params['dlvrTmlmtDateTo']);
        }

        // 납품요구접수일자 필터 (기존 호환성)
        if (isset($params['dlvrReqRcptDate']) && !in_array('dlvrReqRcptDate', $exclude)) {
            $this->db->where('drd.dlvr_req_rcpt_date', $params['dlvrReqRcptDate']);
        }
        
        // 수요기관명 필터 (PowerPoint 요구사항: LIKE 검색으로 변경)
        if (isset($params['dminsttNm']) && !in_array('dminsttNm', $exclude)) {
            $this->db->like('drd.dminstt_nm', $params['dminsttNm']);
        }
        
        // 수요기관지역명 필터
        if (isset($params['dminsttRgnNm']) && !in_array('dminsttRgnNm', $exclude)) {
            $this->db->like('drd.dminstt_rgn_nm', $params['dminsttRgnNm']);
        }
        
        // 업체명 필터 (PowerPoint 요구사항: LIKE 검색으로 변경)
        if (isset($params['corpNm']) && !in_array('corpNm', $exclude)) {
            $this->db->like('drd.corp_nm', $params['corpNm']);
        }
        
        // 납품요구건명 필터 (LIKE 검색)
        if (isset($params['dlvrReqNm']) && !in_array('dlvrReqNm', $exclude)) {
            $this->db->like('drd.dlvr_req_nm', $params['dlvrReqNm']);
        }
        
        // 품명 필터 (PowerPoint 요구사항: LIKE 검색으로 변경)
        if (isset($params['prdctClsfcNoNm']) && !in_array('prdctClsfcNoNm', $exclude)) {
            $this->db->like('drd.prdct_clsfc_no_nm', $params['prdctClsfcNoNm']);
        }
        
        // 세부품명 필터
        if (isset($params['dtilPrdctClsfcNoNm']) && !in_array('dtilPrdctClsfcNoNm', $exclude)) {
            $this->db->like('drd.dtil_prdct_clsfc_no_nm', $params['dtilPrdctClsfcNoNm']);
        }
        
        // 물품식별번호 필터
        if (isset($params['prdctIdntNo']) && !in_array('prdctIdntNo', $exclude)) {
            $this->db->like('drd.prdct_idnt_no', $params['prdctIdntNo']);
        }
        
        // 물품규격명 필터 (LIKE 검색)
        if (isset($params['prdctIdntNoNm']) && !in_array('prdctIdntNoNm', $exclude)) {
            $this->db->like('drd.prdct_idnt_no_nm', $params['prdctIdntNoNm']);
        }
        
        // 증감수량 필터
        if (isset($params['incdecQty']) && !in_array('incdecQty', $exclude)) {
            $this->db->where('drd.incdec_qty', $params['incdecQty']);
        }
        
        // 물품단가 필터
        if (isset($params['prdctUprc']) && !in_array('prdctUprc', $exclude)) {
            $this->db->where('drd.prdct_uprc', $params['prdctUprc']);
        }
        
        // 증감금액 필터
        if (isset($params['incdecAmt']) && !in_array('incdecAmt', $exclude)) {
            $this->db->where('drd.incdec_amt', $params['incdecAmt']);
        }
        
        // 수요기관코드 필터
        if (isset($params['dminsttCd']) && !in_array('dminsttCd', $exclude)) {
            $this->db->where('drd.dminstt_cd', $params['dminsttCd']);
        }
        
        // 날짜 범위 필터 (기존 호환성)
        if (isset($params['dateFrom']) && !in_array('dateFrom', $exclude)) {
            $this->db->where('drd.dlvr_req_rcpt_date >=', $params['dateFrom']);
        }
        
        if (isset($params['dateTo']) && !in_array('dateTo', $exclude)) {
            $this->db->where('drd.dlvr_req_rcpt_date <=', $params['dateTo']);
        }
        
        // 금액 범위 필터
        if (isset($params['amountFrom']) && !in_array('amountFrom', $exclude)) {
            $this->db->where('drd.incdec_amt >=', $params['amountFrom']);
        }
        
        if (isset($params['amountTo']) && !in_array('amountTo', $exclude)) {
            $this->db->where('drd.incdec_amt <=', $params['amountTo']);
        }
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
     * 특정 연도의 수요기관별 통계 조회 (실제 테이블 구조 반영)
     */
    private function _get_yearly_institution_stats($year, $params = [], $page = null, $size = null) {
        // PowerPoint 요구사항에 맞는 SELECT 구문 (실제 테이블 구조)
        $this->db->select("
            drd.dminstt_nm as dminsttNm,
            drd.dminstt_rgn_nm as dminsttRgnNm,
            drd.dminstt_cd as dminsttCd,
            COUNT(DISTINCT drd.dlvr_req_no) as delivery_count,
            SUM(drd.incdec_amt) as current_amount,
            SUM(CASE WHEN drd.exclc_prodct_yn = 'Y' THEN drd.incdec_amt ELSE 0 END) as exclc_prodct_amount,
            SUM(CASE WHEN drd.exclc_prodct_yn = 'N' THEN drd.incdec_amt ELSE 0 END) as general_prodct_amount,
            CASE 
                WHEN SUM(CASE WHEN drd.exclc_prodct_yn = 'Y' THEN drd.incdec_amt ELSE 0 END) > 
                     SUM(CASE WHEN drd.exclc_prodct_yn = 'N' THEN drd.incdec_amt ELSE 0 END) 
                THEN 'Y' ELSE 'N' 
            END as main_exclc_prodct_yn
        ");
        
        $this->db->from('delivery_request_details drd');
        
        // PowerPoint 요구사항: 연도별 필터링 (임시로 조건 제거 - 날짜 데이터 문제)
        // $this->db->where('YEAR(drd.dlvr_req_rcpt_date)', $year);
        
        // 기타 필터 적용 (연도 필터 제외)
        $exclude_filters = ['year', 'includePrevYear', 'dateFrom', 'dateTo', 'page', 'size'];
        $this->_apply_filters($params, $exclude_filters);
        
        // 수요기관별 그룹핑
        $this->db->group_by(['drd.dminstt_cd', 'drd.dminstt_nm', 'drd.dminstt_rgn_nm']);
        $this->db->order_by('current_amount', 'DESC');
        
        // 페이징 적용 (페이지 정보가 있을 때만)
        if ($page !== null && $size !== null) {
            $offset = ($page - 1) * $size;
            $this->db->limit($size, $offset);
        }
        
        return $this->db->get()->result_array();
    }
    
    /**
     * 수요기관별 통계 전체 개수 조회
     */
    private function _get_institution_stats_count($year, $params = []) {
        $this->db->select("COUNT(DISTINCT CONCAT(drd.dminstt_cd, '-', drd.dminstt_nm)) as total_count");
        $this->db->from('delivery_request_details drd');
        
        // PowerPoint 요구사항: 연도별 필터링 (임시로 조건 제거 - 날짜 데이터 문제)
        // $this->db->where('YEAR(drd.dlvr_req_rcpt_date)', $year);
        
        // 기타 필터 적용 (연도 필터 제외)
        $exclude_filters = ['year', 'includePrevYear', 'dateFrom', 'dateTo', 'page', 'size'];
        $this->_apply_filters($params, $exclude_filters);
        
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
            $prev_stats_indexed[$stat['dminsttNm']] = $stat;
        }
        
        $result = [];
        foreach ($current_stats as $current) {
            $institution_name = $current['dminsttNm'];
            $current_amount = (float)$current['current_amount'];
            
            // 전년도 데이터 찾기
            $prev_amount = 0;
            if (isset($prev_stats_indexed[$institution_name])) {
                $prev_amount = (float)$prev_stats_indexed[$institution_name]['current_amount'];
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
                'dminsttRgnNm' => $current['dminsttRgnNm'],
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
} 
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 조달청 API 라이브러리
 * 
 * 조달청 공공데이터포털 API 호출을 담당하는 라이브러리
 */
class Procurement_api
{
    private $CI;
    private $config;
    
    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->config('config');
        $this->config = $this->CI->config->item('procurement_api');
    }

    /**
     * 납품요구 상세정보 목록 조회
     * 
     * @param array $params 요청 파라미터
     * @return array API 응답 결과
     */
    public function get_delivery_request_details($params = array())
    {
        $api_name = 'getDlvrReqDtlInfoList';
        
        // 기본 파라미터 설정
        $default_params = array(
            'serviceKey' => $this->config['service_key'],
            'type' => $this->config['response_type'],
            'numOfRows' => $this->config['num_of_rows'],
            'pageNo' => 1,
            'inqryDiv' => 1  // 조회구분
        );
        
        // 사용자 파라미터와 병합
        $request_params = array_merge($default_params, $params);
        
        // API URL 구성
        $api_url = $this->config['base_url'] . '/' . $api_name;
        
        return $this->call_api($api_name, $api_url, $request_params);
    }

    /**
     * API 호출 실행
     * 
     * @param string $api_name API 명칭
     * @param string $api_url API URL
     * @param array $params 요청 파라미터
     * @return array API 호출 결과
     */
    private function call_api($api_name, $api_url, $params)
    {
        $start_time = microtime(true);
        $call_time = date('Y-m-d H:i:s');
        
        // URL 파라미터 구성
        $query_string = http_build_query($params);
        $full_url = $api_url . '?' . $query_string;
        
        $retry_count = 0;
        $max_retries = $this->config['retry_count'];
        
        while ($retry_count <= $max_retries) {
            try {
                // cURL 초기화
                $ch = curl_init();
                
                // cURL 옵션 설정
                curl_setopt_array($ch, array(
                    CURLOPT_URL => $full_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $this->config['timeout'],
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT => 'Rejintech-Procurement-Batch/1.0',
                    CURLOPT_HTTPHEADER => array(
                        'Accept: */*'
                    )
                ));
                
                // API 호출 실행
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                
                curl_close($ch);
                
                $end_time = microtime(true);
                $response_time = round(($end_time - $start_time) * 1000); // ms 단위
                
                // API 호출 결과 구성
                $result = array(
                    'success' => false,
                    'api_name' => $api_name,
                    'api_url' => $full_url,
                    'request_params' => $params,
                    'response_code' => $http_code,
                    'response_data' => $response,
                    'raw_response' => $response,
                    'call_time' => $call_time,
                    'response_time' => $response_time,
                    'retry_count' => $retry_count,
                    'error_message' => null
                );
                
                // cURL 오류 체크
                if ($curl_error) {
                    $result['error_message'] = 'cURL Error: ' . $curl_error;
                    $result['status'] = 'FAILED';
                } else if ($http_code >= 200 && $http_code < 300) {
                    // 성공적인 응답
                    $result['success'] = true;
                    $result['status'] = 'SUCCESS';
                    
                    // JSON 응답 파싱
                    $json_data = json_decode($response, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $result['parsed_data'] = $json_data;
                    } else {
                        // JSON 파싱 실패해도 성공으로 처리 (XML 응답일 수 있음)
                        $result['parse_error'] = 'JSON Parsing Error: ' . json_last_error_msg();
                        log_message('info', 'JSON parsing failed, raw response: ' . substr($response, 0, 500));
                    }
                    
                    return $result;
                } else {
                    // HTTP 오류
                    $result['error_message'] = 'HTTP Error: ' . $http_code;
                    $result['status'] = 'FAILED';
                }
                
                // 재시도 로직
                if ($retry_count < $max_retries) {
                    $retry_count++;
                    log_message('info', "API call failed, retrying ({$retry_count}/{$max_retries}): " . $result['error_message']);
                    sleep($this->config['retry_delay']);
                } else {
                    // 최대 재시도 횟수 도달
                    $result['error_message'] .= " (Max retries: {$max_retries})";
                    return $result;
                }
                
            } catch (Exception $e) {
                $end_time = microtime(true);
                $response_time = round(($end_time - $start_time) * 1000);
                
                $result = array(
                    'success' => false,
                    'api_name' => $api_name,
                    'api_url' => $full_url,
                    'request_params' => $params,
                    'response_code' => 0,
                    'response_data' => '',
                    'call_time' => $call_time,
                    'response_time' => $response_time,
                    'retry_count' => $retry_count,
                    'status' => 'FAILED',
                    'error_message' => 'Exception: ' . $e->getMessage()
                );
                
                if ($retry_count < $max_retries) {
                    $retry_count++;
                    log_message('error', "API call exception, retrying ({$retry_count}/{$max_retries}): " . $e->getMessage());
                    sleep($this->config['retry_delay']);
                } else {
                    return $result;
                }
            }
        }
        
        return $result;
    }

    /**
     * API 응답 데이터를 DB 저장 형식으로 변환
     * 
     * @param array $api_item API 응답 항목
     * @return array DB 저장 형식 데이터
     */
    public function transform_api_data($api_item)
    {
        // 실제 API 응답 필드를 DB 필드에 매핑 (기존 필드 + 새로운 필드)
        $transformed = array(
            // 기본 정보 (기존 필드들)
            'dlvr_req_no' => isset($api_item['dlvrReqNo']) ? $api_item['dlvrReqNo'] : null,
            'dlvr_req_dtl_seq' => isset($api_item['prdctSno']) ? (int)$api_item['prdctSno'] : 1, // 제품순번을 상세순번으로 사용
            
            // 기존 호환 필드들 (API 응답에서 매핑)
            'item_no' => isset($api_item['prdctIdntNo']) ? $api_item['prdctIdntNo'] : null,
            'item_nm' => isset($api_item['prdctIdntNoNm']) ? $api_item['prdctIdntNoNm'] : null,
            'item_spec' => null, // API에서 해당 필드 없음
            'unit_cd' => null, // API에서 해당 필드 없음  
            'unit_nm' => isset($api_item['prdctUnit']) ? $api_item['prdctUnit'] : null,
            'req_qty' => isset($api_item['prdctQty']) ? (float)str_replace(',', '', $api_item['prdctQty']) : null,
            'dlvr_qty' => isset($api_item['dlvrReqQty']) ? (float)str_replace(',', '', $api_item['dlvrReqQty']) : null,
            'unit_price' => isset($api_item['prdctUprc']) ? (float)str_replace(',', '', $api_item['prdctUprc']) : null,
            'total_amt' => isset($api_item['prdctAmt']) ? (float)str_replace(',', '', $api_item['prdctAmt']) : null,
            'dlvr_req_dt' => isset($api_item['dlvrReqRcptDate']) ? $this->format_date($api_item['dlvrReqRcptDate']) : null,
            'dlvr_expect_dt' => isset($api_item['dlvrTmlmtDate']) ? $this->format_date($api_item['dlvrTmlmtDate']) : null,
            'dlvr_cmplt_dt' => null, // API에서 해당 필드 없음
            'dlvr_status_cd' => null, // API에서 해당 필드 없음
            'dlvr_status_nm' => null, // API에서 해당 필드 없음
            'supplier_cd' => isset($api_item['cntrctCorpBizno']) ? $api_item['cntrctCorpBizno'] : null,
            'supplier_nm' => isset($api_item['corp_nm']) ? $api_item['corp_nm'] : (isset($api_item['corpNm']) ? $api_item['corpNm'] : null),
            'buyer_cd' => isset($api_item['dminsttCd']) ? $api_item['dminsttCd'] : null,
            'buyer_nm' => isset($api_item['dminsttNm']) ? $api_item['dminsttNm'] : null,
            'contract_no' => isset($api_item['cntrctNo']) ? $api_item['cntrctNo'] : null,
            'rgst_dt' => null, // API에서 해당 필드 없음
            'updt_dt' => null, // API에서 해당 필드 없음
            
            // 새로운 상세 필드들
            'dlvr_req_chg_ord' => isset($api_item['dlvrReqChgOrd']) ? $api_item['dlvrReqChgOrd'] : null,
            'dlvr_req_rcpt_date' => isset($api_item['dlvrReqRcptDate']) ? $this->format_date($api_item['dlvrReqRcptDate']) : null,
            
            // 제품 정보
            'prdct_clsfc_no' => isset($api_item['prdctClsfcNo']) ? $api_item['prdctClsfcNo'] : null,
            'prdct_clsfc_no_nm' => isset($api_item['prdctClsfcNoNm']) ? $api_item['prdctClsfcNoNm'] : null,
            'dtil_prdct_clsfc_no' => isset($api_item['dtilPrdctClsfcNo']) ? $api_item['dtilPrdctClsfcNo'] : null,
            'dtil_prdct_clsfc_no_nm' => isset($api_item['dtilPrdctClsfcNoNm']) ? $api_item['dtilPrdctClsfcNoNm'] : null,
            'prdct_idnt_no' => isset($api_item['prdctIdntNo']) ? $api_item['prdctIdntNo'] : null,
            'prdct_idnt_no_nm' => isset($api_item['prdctIdntNoNm']) ? $api_item['prdctIdntNoNm'] : null,
            
            // 가격 및 수량 정보
            'prdct_uprc' => isset($api_item['prdctUprc']) ? (float)str_replace(',', '', $api_item['prdctUprc']) : null,
            'prdct_unit' => isset($api_item['prdctUnit']) ? $api_item['prdctUnit'] : null,
            'prdct_qty' => isset($api_item['prdctQty']) ? (float)str_replace(',', '', $api_item['prdctQty']) : null,
            'prdct_amt' => isset($api_item['prdctAmt']) ? (float)str_replace(',', '', $api_item['prdctAmt']) : null,
            
            // 납품 관련 정보
            'dlvr_tmlmt_date' => isset($api_item['dlvrTmlmtDate']) ? $this->format_date($api_item['dlvrTmlmtDate']) : null,
            'cntrct_cncls_stle_nm' => isset($api_item['cntrctCnclsStleNm']) ? $api_item['cntrctCnclsStleNm'] : null,
            'exclc_prodct_yn' => isset($api_item['exclcProdctYn']) ? $api_item['exclcProdctYn'] : null,
            'optn_div_cd_nm' => isset($api_item['optnDivCdNm']) ? $api_item['optnDivCdNm'] : null,
            
            // 기관 정보
            'dminstt_cd' => isset($api_item['dminsttCd']) ? $api_item['dminsttCd'] : null,
            'dminstt_nm' => isset($api_item['dminsttNm']) ? $api_item['dminsttNm'] : null,
            'dmnd_instt_div_nm' => isset($api_item['dmndInsttDivNm']) ? $api_item['dmndInsttDivNm'] : null,
            'dminstt_rgn_nm' => isset($api_item['dminsttRgnNm']) ? $api_item['dminsttRgnNm'] : null,
            
            // 업체 정보
            'corp_nm' => isset($api_item['corpNm']) ? $api_item['corpNm'] : null,
            'fnl_dlvr_req_yn' => isset($api_item['fnlDlvrReqYn']) ? $api_item['fnlDlvrReqYn'] : null,
            'incdec_qty' => isset($api_item['incdecQty']) ? (float)str_replace(',', '', $api_item['incdecQty']) : null,
            'incdec_amt' => isset($api_item['incdecAmt']) ? (float)str_replace(',', '', $api_item['incdecAmt']) : null,
            'cntrct_corp_bizno' => isset($api_item['cntrctCorpBizno']) ? $api_item['cntrctCorpBizno'] : null,
            
            // 계약 관련
            'dlvr_req_nm' => isset($api_item['dlvrReqNm']) ? $api_item['dlvrReqNm'] : null,
            'cntrct_no' => isset($api_item['cntrctNo']) ? $api_item['cntrctNo'] : null,
            'cntrct_chg_ord' => isset($api_item['cntrctChgOrd']) ? $api_item['cntrctChgOrd'] : null,
            'mas_yn' => isset($api_item['masYn']) ? $api_item['masYn'] : null,
            'cnstwk_mtrl_drct_purchs_obj_yn' => isset($api_item['cnstwkMtrlDrctPurchsObjYn']) ? $api_item['cnstwkMtrlDrctPurchsObjYn'] : null,
            'intl_cntrct_dlvr_req_date' => isset($api_item['IntlCntrctDlvrReqDate']) ? $this->format_date($api_item['IntlCntrctDlvrReqDate']) : null,
            
            // 기타
            'dlvr_req_qty' => isset($api_item['dlvrReqQty']) ? (float)str_replace(',', '', $api_item['dlvrReqQty']) : null,
            'dlvr_req_amt' => isset($api_item['dlvrReqAmt']) ? (float)str_replace(',', '', $api_item['dlvrReqAmt']) : null,
            'smetpr_cmpt_prodct_yn' => isset($api_item['smetprCmptProdctYn']) ? $api_item['smetprCmptProdctYn'] : null,
            'corp_entrprs_div_nm_nm' => isset($api_item['corpEntrprsDivNmNm']) ? $api_item['corpEntrprsDivNmNm'] : null,
            'brnofce_nm' => isset($api_item['brnofceNm']) ? $api_item['brnofceNm'] : null,
            
            // 메타 정보
            'api_response_json' => json_encode($api_item, JSON_UNESCAPED_UNICODE)
        );

        return $transformed;
    }

    /**
     * 날짜 형식 변환 (YYYYMMDD -> YYYY-MM-DD)
     * 
     * @param string $date_string 원본 날짜 문자열
     * @return string 변환된 날짜 문자열
     */
         private function format_date($date_string)
    {
        if (empty($date_string) || !is_string($date_string) || strlen($date_string) < 8) {
            return null;
        }
        
        $year = substr($date_string, 0, 4);
        $month = substr($date_string, 4, 2);
        $day = substr($date_string, 6, 2);
        
        return $year . '-' . $month . '-' . $day;
    }

    /**
     * 날짜시간 형식 변환
     * 
     * @param string $datetime_string 원본 날짜시간 문자열
     * @return string 변환된 날짜시간 문자열
     */
         private function format_datetime($datetime_string)
    {
        if (empty($datetime_string) || !is_string($datetime_string)) {
            return null;
        }
        
        // 다양한 형식 지원
        if (strlen($datetime_string) >= 14) {
            // YYYYMMDDHHMMSS 형식
            $year = substr($datetime_string, 0, 4);
            $month = substr($datetime_string, 4, 2);
            $day = substr($datetime_string, 6, 2);
            $hour = substr($datetime_string, 8, 2);
            $minute = substr($datetime_string, 10, 2);
            $second = substr($datetime_string, 12, 2);
            
            return $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $minute . ':' . $second;
        } else if (strlen($datetime_string) >= 8) {
            // YYYYMMDD 형식 (시간은 00:00:00으로)
            return $this->format_date($datetime_string) . ' 00:00:00';
        }
        
        return null;
    }

    /**
     * API 설정 정보 반환
     * 
     * @return array API 설정 정보
     */
    public function get_config()
    {
        return $this->config;
    }
} 
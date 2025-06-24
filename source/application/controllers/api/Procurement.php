<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// JWT 토큰 검증용 라이브러리 로드
require_once FCPATH . 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Procurement extends CI_Controller {

    public function __construct() {
        parent::__construct();
        
        // JWT 설정 로드
        $this->config->load('jwt');
        $this->jwt_secret = $this->config->item('jwt_secret_key');
        $this->jwt_algorithm = $this->config->item('jwt_algorithm');
        
        $this->load->model('Procurement_model');
        $this->load->model('User_token_model');
        $this->load->model('Login_log_model');
        $this->load->helper('url');
        
        // CORS 헤더 설정
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Content-Type: application/json; charset=UTF-8');
        
        // OPTIONS 요청 처리
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * JWT 토큰 검증
     */
    private function verify_token() {
        $headers = $this->input->request_headers();
        
        if (!isset($headers['Authorization'])) {
            return $this->output_error('인증 토큰이 필요합니다.', 401);
        }
        
        $auth_header = $headers['Authorization'];
        if (!preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return $this->output_error('잘못된 토큰 형식입니다.', 401);
        }
        
        $token = $matches[1];
        
        try {
            // JWT 토큰 검증
            $decoded = JWT::decode($token, new Key($this->jwt_secret, $this->jwt_algorithm));
            
            // DB에서 토큰 유효성 검증
            $token_info = $this->User_token_model->verify_token($token);
            if (!$token_info) {
                return $this->output_error('유효하지 않거나 만료된 토큰입니다.', 401);
            }
            
            return $decoded;
            
        } catch (Exception $e) {
            return $this->output_error('토큰 검증 실패: ' . $e->getMessage(), 401);
        }
    }
    
    /**
     * API 호출 기록 저장
     */
    private function log_api_call($endpoint, $method, $user_id = null, $status = 'success', $error_message = null) {
        $client_ip = $this->input->ip_address();
        $user_agent = $this->input->user_agent();
        
        $log_data = [
            'endpoint' => $endpoint,
            'method' => $method,
            'user_id' => $user_id,
            'ip_address' => $client_ip,
            'user_agent' => $user_agent,
            'status' => $status,
            'error_message' => $error_message,
            'called_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('api_call_history', $log_data);
    }
    
    /**
     * 에러 응답 출력
     */
    private function output_error($message, $status_code = 400) {
        http_response_code($status_code);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * 성공 응답 출력
     */
    private function output_success($data, $message = '성공') {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * 조달청 데이터 전체 리스트 조회 API
     * POST /api/procurement/delivery-requests
     */
    public function delivery_requests() {
        if ($this->input->method() !== 'post') {
            return $this->output_error('POST 요청만 허용됩니다.', 405);
        }
        
        // 토큰 검증
        $decoded_token = $this->verify_token();
        if (!$decoded_token) return; // 에러는 verify_token에서 처리됨
        
        try {
            // JSON 요청 데이터 가져오기
            $json_input = file_get_contents('php://input');
            $request_data = json_decode($json_input, true);
            
            if ($request_data === null) {
                return $this->output_error('잘못된 JSON 형식입니다.', 400);
            }
            
            // 기본 구조 검증
            $params = [];
            
            // 페이징 파라미터 (page, size)
            $params['page'] = isset($request_data['page']) ? (int)$request_data['page'] : 1;
            $params['size'] = isset($request_data['size']) ? (int)$request_data['size'] : 50;
            
            // 페이지 크기 제한
            if ($params['size'] > 100) {
                $params['size'] = 100;
            }
            if ($params['page'] < 1) {
                $params['page'] = 1;
            }
            
            // 필터 모델 처리
            if (isset($request_data['filterModel']) && is_array($request_data['filterModel'])) {
                $filterModel = $request_data['filterModel'];
                
                // type 필터 (배열 형태)
                if (isset($filterModel['type']) && is_array($filterModel['type'])) {
                    $params['types'] = $filterModel['type'];
                }
                
                // 기타 필터들
                $filter_mappings = [
                    'exclcProdctYn' => 'exclcProdctYn',
                    'dlvrReqRcptDate' => 'dlvrReqRcptDate', 
                    'dminsttNm' => 'dminsttNm',
                    'dminsttRgnNm' => 'dminsttRgnNm',
                    'corpNm' => 'corpNm',
                    'dlvrReqNm' => 'dlvrReqNm',
                    'prdctClsfcNoNm' => 'prdctClsfcNoNm',
                    'dtilPrdctClsfcNoNm' => 'dtilPrdctClsfcNoNm',
                    'prdctIdntNo' => 'prdctIdntNo',
                    'prdctIdntNoNm' => 'prdctIdntNoNm',
                    'dminsttCd' => 'dminsttCd',
                    'dateFrom' => 'dateFrom',
                    'dateTo' => 'dateTo',
                    'amountFrom' => 'amountFrom',
                    'amountTo' => 'amountTo'
                ];
                
                foreach ($filter_mappings as $api_key => $db_key) {
                    if (isset($filterModel[$api_key]) && $filterModel[$api_key] !== '') {
                        if (is_array($filterModel[$api_key])) {
                            $params[$db_key] = $filterModel[$api_key];
                        } else {
                            $params[$db_key] = $filterModel[$api_key];
                        }
                    }
                }
            }
            
            // 정렬 모델 처리
            if (isset($request_data['sortModel']) && is_array($request_data['sortModel'])) {
                $sortModel = $request_data['sortModel'];
                $params['sortModel'] = $sortModel;
            }
            
            // 데이터 조회
            $result = $this->Procurement_model->get_delivery_requests($params);
            
            // API 호출 기록 (임시 제거)
            // $this->log_api_call('/api/procurement/delivery-requests', 'POST', $decoded_token->user_id);
            
            $this->output_success($result, '조달청 데이터 조회 성공');
            
        } catch (Exception $e) {
            // $this->log_api_call('/api/procurement/delivery-requests', 'POST', $decoded_token->user_id ?? null, 'error', $e->getMessage());
            return $this->output_error('데이터 조회 중 오류가 발생했습니다: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 수요기관별 통계 조회 API
     * GET /api/procurement/statistics/institutions
     */
    public function institution_statistics() {
        if ($this->input->method() !== 'get') {
            return $this->output_error('GET 요청만 허용됩니다.', 405);
        }
        
        // 토큰 검증
        $decoded_token = $this->verify_token();
        if (!$decoded_token) return;
        
        try {
            // 필터 파라미터 가져오기
            $params = [];
            $filter_params = [
                'exclcProdctYn', 'dlvrReqRcptDate', 'dminsttRgnNm', 
                'corpNm', 'prdctClsfcNoNm', 'dateFrom', 'dateTo', 'amountFrom', 'amountTo'
            ];
            
            foreach ($filter_params as $param) {
                $value = $this->input->get($param);
                if ($value !== null && $value !== '') {
                    $params[$param] = $value;
                }
            }
            
            $result = $this->Procurement_model->get_institution_statistics($params);
            
            // API 호출 기록 (임시 제거)
            // $this->log_api_call('/api/procurement/statistics/institutions', 'GET', $decoded_token->user_id);
            
            $this->output_success($result, '수요기관별 통계 조회 성공');
            
        } catch (Exception $e) {
            // $this->log_api_call('/api/procurement/statistics/institutions', 'GET', $decoded_token->user_id ?? null, 'error', $e->getMessage());
            return $this->output_error('통계 조회 중 오류가 발생했습니다: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 업체별 통계 조회 API
     * GET /api/procurement/statistics/companies
     */
    public function company_statistics() {
        if ($this->input->method() !== 'get') {
            return $this->output_error('GET 요청만 허용됩니다.', 405);
        }
        
        // 토큰 검증
        $decoded_token = $this->verify_token();
        if (!$decoded_token) return;
        
        try {
            // 필터 파라미터 가져오기
            $params = [];
            $filter_params = [
                'exclcProdctYn', 'dlvrReqRcptDate', 'dminsttNm', 'dminsttRgnNm', 
                'prdctClsfcNoNm', 'dateFrom', 'dateTo', 'amountFrom', 'amountTo'
            ];
            
            foreach ($filter_params as $param) {
                $value = $this->input->get($param);
                if ($value !== null && $value !== '') {
                    $params[$param] = $value;
                }
            }
            
            $result = $this->Procurement_model->get_company_statistics($params);
            
            // API 호출 기록 (임시 제거)
            // $this->log_api_call('/api/procurement/statistics/companies', 'GET', $decoded_token->user_id);
            
            $this->output_success($result, '업체별 통계 조회 성공');
            
        } catch (Exception $e) {
            // $this->log_api_call('/api/procurement/statistics/companies', 'GET', $decoded_token->user_id ?? null, 'error', $e->getMessage());
            return $this->output_error('통계 조회 중 오류가 발생했습니다: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 품목별 통계 조회 API
     * GET /api/procurement/statistics/products
     */
    public function product_statistics() {
        if ($this->input->method() !== 'get') {
            return $this->output_error('GET 요청만 허용됩니다.', 405);
        }
        
        // 토큰 검증
        $decoded_token = $this->verify_token();
        if (!$decoded_token) return;
        
        try {
            // 필터 파라미터 가져오기
            $params = [];
            $filter_params = [
                'exclcProdctYn', 'dlvrReqRcptDate', 'dminsttNm', 'dminsttRgnNm', 
                'corpNm', 'dateFrom', 'dateTo', 'amountFrom', 'amountTo'
            ];
            
            foreach ($filter_params as $param) {
                $value = $this->input->get($param);
                if ($value !== null && $value !== '') {
                    $params[$param] = $value;
                }
            }
            
            $result = $this->Procurement_model->get_product_statistics($params);
            
            // API 호출 기록 (임시 제거)
            // $this->log_api_call('/api/procurement/statistics/products', 'GET', $decoded_token->user_id);
            
            $this->output_success($result, '품목별 통계 조회 성공');
            
        } catch (Exception $e) {
            // $this->log_api_call('/api/procurement/statistics/products', 'GET', $decoded_token->user_id ?? null, 'error', $e->getMessage());
            return $this->output_error('통계 조회 중 오류가 발생했습니다: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 필터 옵션 조회 API
     * GET /api/procurement/filter-options
     */
    public function filter_options() {
        if ($this->input->method() !== 'get') {
            return $this->output_error('GET 요청만 허용됩니다.', 405);
        }
        
        // 토큰 검증
        $decoded_token = $this->verify_token();
        if (!$decoded_token) return;
        
        try {
            // 필터 조건 파라미터 가져오기
            $params = [];
            $filter_params = [
                'exclcProdctYn', 'dlvrReqRcptDate', 'dminsttRgnNm', 
                'dateFrom', 'dateTo', 'amountFrom', 'amountTo'
            ];
            
            foreach ($filter_params as $param) {
                $value = $this->input->get($param);
                if ($value !== null && $value !== '') {
                    $params[$param] = $value;
                }
            }
            
            $result = $this->Procurement_model->get_filter_options($params);
            
            // API 호출 기록 (임시 제거)
            // $this->log_api_call('/api/procurement/filter-options', 'GET', $decoded_token->user_id);
            
            $this->output_success($result, '필터 옵션 조회 성공');
            
        } catch (Exception $e) {
            // $this->log_api_call('/api/procurement/filter-options', 'GET', $decoded_token->user_id ?? null, 'error', $e->getMessage());
            return $this->output_error('필터 옵션 조회 중 오류가 발생했습니다: ' . $e->getMessage(), 500);
        }
    }
} 
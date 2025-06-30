<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// JWT 토큰 검증용 라이브러리 로드
require_once FCPATH . 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Procurement extends CI_Controller {

    protected $procurement_api;
    private $jwt_secret;
    private $jwt_algorithm;

    public function __construct() {
        parent::__construct();
        
        // JWT 설정 로드
        $this->config->load('jwt');
        $this->jwt_secret = $this->config->item('jwt_secret_key');
        $this->jwt_algorithm = $this->config->item('jwt_algorithm');
        
        // CORS 헤더 설정
        $this->load->helper('cors');
        set_cors_headers();
        
        // OPTIONS 요청 처리
        if ($this->input->method() === 'options') {
            $this->output->set_status_header(200);
            exit();
        }
        
        // JSON 응답을 위한 헤더 설정
        $this->output->set_content_type('application/json');
        
        // 모델 로드
        $this->load->model('Procurement_model');
        $this->load->model('User_token_model');
        $this->load->model('Login_log_model');
    }
    
    /**
     * JWT 토큰 검증
     */
    private function verify_jwt_token($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwt_secret, $this->jwt_algorithm));
            return (array) $decoded;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Authorization 헤더에서 토큰 추출
     */
    private function get_token_from_header()
    {
        $headers = $this->input->request_headers();
        
        if (isset($headers['Authorization'])) {
            $auth_header = $headers['Authorization'];
            // 중복된 'Bearer' 제거
            $auth_header = preg_replace('/^Bearer\s+Bearer\s+/i', 'Bearer ', $auth_header);
            if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
                return $matches[1];
            }
        }
        
        return false;
    }
    
    /**
     * API 호출 기록 저장
     */
    private function log_api_call($endpoint, $method, $user_id = null, $status = 'SUCCESS', $error_message = null) {
        $client_ip = $this->input->ip_address();
        $user_agent = $this->input->user_agent();
        
        $log_data = [
            'api_name' => $endpoint, // endpoint를 api_name으로 매핑
            'api_url' => $endpoint,  // endpoint를 api_url로도 사용
            'request_params' => json_encode(['method' => $method, 'user_id' => $user_id, 'ip_address' => $client_ip, 'user_agent' => $user_agent]),
            'response_code' => 200,  // 기본값 200
            'call_time' => date('Y-m-d H:i:s'),
            'status' => strtoupper($status) === 'SUCCESS' ? 'SUCCESS' : 'FAILED',
            'error_message' => $error_message
        ];
        
        $this->db->insert('api_call_history', $log_data);
    }
    
    /**
     * 에러 응답 출력
     */
    private function output_error($message, $status_code = 400) {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        $this->output
            ->set_content_type('application/json', 'utf-8')
            ->set_status_header($status_code)
            ->set_output(json_encode($response, JSON_UNESCAPED_UNICODE));
        
        // CodeIgniter에서는 exit() 대신 return 사용
        return;
    }
    
    /**
     * 성공 응답 출력
     */
    private function output_success($data, $message = '성공') {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ];
        
        $this->output
            ->set_content_type('application/json', 'utf-8')
            ->set_status_header(200)
            ->set_output(json_encode($response, JSON_UNESCAPED_UNICODE));
        
        // CodeIgniter에서는 exit() 대신 return 사용
        return;
    }
    
    /**
     * 납품 요구 목록을 조회합니다.
     */
    public function delivery_requests()
    {
        try {
            // 토큰 검증
            $token = $this->get_token_from_header();
            if (!$token) {
                throw new Exception('인증 토큰이 필요합니다.');
            }
            
            $decoded = $this->verify_jwt_token($token);
            if (!$decoded) {
                throw new Exception('유효하지 않은 토큰입니다.');
            }
            
            // 검색 조건 파라미터
            $params = array(
                'start_date' => $this->input->get('start_date'),
                'end_date' => $this->input->get('end_date'),
                'institution_id' => $this->input->get('institution_id'),
                'company_id' => $this->input->get('company_id'),
                'product_id' => $this->input->get('product_id'),
                'page' => $this->input->get('page', true) ?? 1,
                'limit' => $this->input->get('limit', true) ?? 10
            );
            
            // 납품 요구 목록 조회
            $this->load->model('Procurement_model');
            $result = $this->Procurement_model->get_delivery_requests($params);
            
            $response = array(
                'success' => true,
                'message' => '납품 요구 목록 조회 성공',
                'data' => array(
                    'total' => $result['total'],
                    'page' => (int)$params['page'],
                    'limit' => (int)$params['limit'],
                    'items' => $result['items']
                )
            );
            
            $this->output->set_status_header(200);
            $this->output->set_output(json_encode($response));
        } catch (Exception $e) {
            $this->output->set_status_header(401);
            $this->output->set_output(json_encode(array(
                'success' => false,
                'message' => $e->getMessage()
            )));
        }
    }
    
    /**
     * 디버그용 API (토큰 검증 없음) - 개발 환경에서만 사용
     * GET /api/procurement/debug-delivery-requests
     */
    public function debug_delivery_requests() {
        if ($this->input->method() !== 'get') {
            return $this->output_error('GET 요청만 허용됩니다.', 405);
        }
        
        try {
            // 간단한 테스트용 파라미터
            $params = [
                'page' => 1,
                'size' => 3  // 적은 수의 레코드만 조회
            ];
            
            // 데이터 조회
            $result = $this->Procurement_model->get_delivery_requests($params);
            
            // 디버그 정보 포함한 응답
            $debug_info = [
                'query_result_keys' => array_keys($result),
                'data_count' => count($result['data'] ?? []),
                'first_item_keys' => !empty($result['data']) ? array_keys($result['data'][0]) : [],
                'first_item_sample' => !empty($result['data']) ? $result['data'][0] : null
            ];
            
            $response_data = [
                'page' => $result['page'],
                'pageSize' => $result['size'],
                'total' => $result['total'],
                'totalAmount' => $result['totalAmount'],
                'jodalTotalAmount' => $result['jodalTotalAmount'] ?? 0,
                'masTotalAmount' => $result['masTotalAmount'] ?? 0,
                'items' => array_map(function($item) {
                    return [
                        'exclcProdctYn' => $item['exclcProdctYn'] ?? null,
                        'dlvrReqRcptDate' => $item['dlvrReqRcptDate'] ?? null,
                        'dminsttNm' => $item['dminsttNm'] ?? null,
                        'dminsttRgnNm' => $item['dminsttRgnNm'] ?? null,
                        'corpNm' => $item['corpNm'] ?? null,
                        'dlvrReqNm' => $item['dlvrReqNm'] ?? null,
                        'prdctClsfcNoNm' => $item['prdctClsfcNoNm'] ?? null,
                        'dtilPrdctClsfcNoNm' => $item['dtilPrdctClsfcNoNm'] ?? null,
                        'prdctIdntNo' => $item['prdctIdntNo'] ?? null,
                        'prdctIdntNoNm' => $item['prdctIdntNoNm'] ?? null,
                        'incdecQty' => $item['incdecQty'] ?? null,
                        'prdctUprc' => $item['prdctUprc'] ?? null,
                        'incdecAmt' => $item['incdecAmt'] ?? null,
                        'dminsttCd' => $item['dminsttCd'] ?? null,
                    ];
                }, $result['data']),
                'debug' => $debug_info
            ];
            
            $this->output_success($response_data, '디버그 조회 성공');
            
        } catch (Exception $e) {
            return $this->output_error('디버그 조회 실패: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 수요기관별 통계 조회 API (PowerPoint 3번째 슬라이드 요구사항 반영)
     * GET /api/procurement/statistics/institutions
     * 
     * PowerPoint 요구사항:
     * 1. 납품요구접수일자에서 연도만 필터링
     * 2. 수요기관명 기준 필터
     * 3. 우수제품여부 필터
     * 4. 증감금액 합산 표기
     * 5. 전년실적 대비 증감률(%) 계산
     * 
     * @OA\Get(
     *     path="/api/procurement/statistics/institutions",
     *     tags={"조달청"},
     *     summary="수요기관별 통계 조회 (PowerPoint 스토리보드 요구사항 반영)",
     *     description="수요기관별 납품 통계를 연도별로 조회하며, 전년 대비 증감률을 계산합니다.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="기준 연도 (YYYY) - PowerPoint 요구사항: 연도만 필터",
     *         required=false,
     *         @OA\Schema(type="integer", example=2025)
     *     ),
     *     @OA\Parameter(
     *         name="dminsttNm",
     *         in="query",
     *         description="수요기관명 (부분 검색 지원) - PowerPoint 요구사항",
     *         required=false,
     *         @OA\Schema(type="string", example="서울특별시")
     *     ),
     *     @OA\Parameter(
     *         name="exclcProdctYn",
     *         in="query",
     *         description="우수제품여부 (Y/N) - PowerPoint 요구사항",
     *         required=false,
     *         @OA\Schema(type="string", enum={"Y", "N"}, example="Y")
     *     ),
     *     @OA\Parameter(
     *         name="includePrevYear",
     *         in="query",
     *         description="전년 대비 증감률 계산 포함 여부 - PowerPoint 요구사항",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="corpNm",
     *         in="query",
     *         description="업체명 (추가 필터)",
     *         required=false,
     *         @OA\Schema(type="string", example="삼성전자")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="페이지 번호 (1부터 시작)",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="size",
     *         in="query",
     *         description="페이지 크기 (최대 100)",
     *         required=true,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="수요기관별 통계 조회 성공"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="page", type="integer", description="현재 페이지", example=1),
     *                 @OA\Property(property="size", type="integer", description="페이지 크기", example=10),
     *                 @OA\Property(property="total", type="integer", description="전체 데이터 수", example=150),
     *                 @OA\Property(property="year", type="integer", description="기준 연도", example=2025),
     *                 @OA\Property(property="totalAmount", type="number", description="전체 증감금액 합산", example=50000000000),
     *                 @OA\Property(property="prevYearAmount", type="number", description="전년 실적", example=45000000000),
     *                 @OA\Property(property="growthRate", type="number", description="전년 대비 증감률(%)", example=11.11),
     *                 @OA\Property(property="institutions", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="dminsttNm", type="string", description="수요기관명", example="서울특별시"),
     *                         @OA\Property(property="dminsttCd", type="string", description="수요기관코드", example="6110000"),
     *                         @OA\Property(property="currentAmount", type="number", description="당해연도 금액", example=5000000000),
     *                         @OA\Property(property="prevAmount", type="number", description="전년도 금액", example=4500000000),
     *                         @OA\Property(property="growthRate", type="number", description="증감률(%)", example=11.11),
     *                         @OA\Property(property="deliveryCount", type="integer", description="납품요구 건수", example=50),
     *                         @OA\Property(property="exclcProdctAmount", type="number", description="우수제품 금액", example=3000000000),
     *                         @OA\Property(property="generalProdctAmount", type="number", description="일반제품 금액", example=2000000000),
     *                         @OA\Property(property="exclcProdctYn", type="string", description="주요 우수제품 여부", example="Y")
     *                     )
     *                 ),
     *                 @OA\Property(property="appliedFilters", type="object",
     *                     @OA\Property(property="year", type="integer", description="적용된 연도 필터", example=2025),
     *                     @OA\Property(property="dminsttNm", type="string", description="적용된 수요기관명 필터", example="서울특별시"),
     *                     @OA\Property(property="exclcProdctYn", type="string", description="적용된 우수제품여부 필터", example="Y"),
     *                     @OA\Property(property="corpNm", type="string", description="적용된 업체명 필터", example="삼성전자")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="잘못된 요청",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="인증 실패",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function institution_statistics() {
        if ($this->input->method() !== 'get') {
            return $this->output_error('GET 요청만 허용됩니다.', 405);
        }
        
        // 토큰 검증
        $decoded_token = $this->verify_jwt_token($this->get_token_from_header());
        if (!$decoded_token) return;
        
        try {
            // 필수 파라미터 처리
            $params = [];
            
            // 필수 파라미터: page, size만 필수로 처리
            $page = $this->input->get('page');
            $size = $this->input->get('size');
            
            if (!$page || !$size) {
                return $this->output_error('page와 size는 필수 파라미터입니다.', 400);
            }
            
            $params['page'] = max(1, (int)$page);
            $params['size'] = max(1, min(100, (int)$size)); // 최대 100개로 제한
            
            // 연도 필터는 선택사항으로 변경 (기본값: 현재 연도)
            $year = $this->input->get('year');
            if ($year && $year !== '' && $year !== 'null') {
                $params['year'] = (int)$year;
            } else {
                $params['year'] = (int)date('Y');
            }
            
            // 선택적 필터 파라미터들
            $optional_filters = [
                'dminsttNm' => '수요기관명',
                'exclcProdctYn' => '우수제품여부',
                'corpNm' => '업체명',
                'includePrevYear' => '전년대비 포함여부'
            ];
            
            foreach ($optional_filters as $key => $description) {
                $value = $this->input->get($key);
                if ($value !== null && $value !== '' && $value !== 'null') {
                    if ($key === 'includePrevYear') {
                        $params[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $params[$key] = $value;
                    }
                }
            }
            
            // 전년 대비 계산은 기본적으로 포함
            if (!isset($params['includePrevYear'])) {
                $params['includePrevYear'] = true;
            }
            
            // 데이터 조회
            $result = $this->Procurement_model->get_institution_statistics($params);
            
            // PowerPoint 요구사항에 맞게 응답 구조 조정
            $response_data = [
                'page' => $params['page'],
                'size' => $params['size'],
                'total' => $result['total'] ?? 0,
                'year' => $params['year'],
                'totalAmount' => $result['totalAmount'] ?? 0,
                'prevYearAmount' => $result['prevYearAmount'] ?? 0,
                'growthRate' => $result['growthRate'] ?? 0,
                'institutions' => $result['institutions'] ?? [],
                'appliedFilters' => array_filter([
                    'year' => $params['year'] ?? null,
                    'dminsttNm' => $params['dminsttNm'] ?? null,
                    'exclcProdctYn' => $params['exclcProdctYn'] ?? null,
                    'corpNm' => $params['corpNm'] ?? null
                ], function($value) {
                    return $value !== null && $value !== '';
                })
            ];
            
            // API 호출 기록
            $this->log_api_call('/api/procurement/statistics/institutions', 'GET', $decoded_token['user_id']);
            
            $this->output_success($response_data, '수요기관별 통계 조회 성공');
            
        } catch (Exception $e) {
            $this->log_api_call('/api/procurement/statistics/institutions', 'GET', $decoded_token['user_id'] ?? null, 'error', $e->getMessage());
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
        $decoded_token = $this->verify_jwt_token($this->get_token_from_header());
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
            // $this->log_api_call('/api/procurement/statistics/companies', 'GET', $decoded_token['user_id']);
            
            $this->output_success($result, '업체별 통계 조회 성공');
            
        } catch (Exception $e) {
            // $this->log_api_call('/api/procurement/statistics/companies', 'GET', $decoded_token['user_id'] ?? null, 'error', $e->getMessage());
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
        $decoded_token = $this->verify_jwt_token($this->get_token_from_header());
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
            // $this->log_api_call('/api/procurement/statistics/products', 'GET', $decoded_token['user_id']);
            
            $this->output_success($result, '품목별 통계 조회 성공');
            
        } catch (Exception $e) {
            // $this->log_api_call('/api/procurement/statistics/products', 'GET', $decoded_token['user_id'] ?? null, 'error', $e->getMessage());
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
        $decoded_token = $this->verify_jwt_token($this->get_token_from_header());
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
            // $this->log_api_call('/api/procurement/filter-options', 'GET', $decoded_token['user_id']);
            
            $this->output_success($result, '필터 옵션 조회 성공');
            
        } catch (Exception $e) {
            // $this->log_api_call('/api/procurement/filter-options', 'GET', $decoded_token['user_id'] ?? null, 'error', $e->getMessage());
            return $this->output_error('필터 옵션 조회 중 오류가 발생했습니다: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 제품 목록을 반환합니다.
     */
    public function products()
    {
        try {
            // 토큰 검증
            $token = $this->get_token_from_header();
            if (!$token) {
                throw new Exception('인증 토큰이 필요합니다.');
            }
            
            $decoded = $this->verify_jwt_token($token);
            if (!$decoded) {
                throw new Exception('유효하지 않은 토큰입니다.');
            }
            
            // 제품 목록 조회
            $this->load->model('Procurement_model');
            $products = $this->Procurement_model->get_products();
            
            $response = array(
                'success' => true,
                'message' => '제품 목록 조회 성공',
                'data' => $products
            );
            
            $this->output->set_status_header(200);
            $this->output->set_output(json_encode($response));
        } catch (Exception $e) {
            $this->output->set_status_header(401);
            $this->output->set_output(json_encode(array(
                'success' => false,
                'message' => $e->getMessage()
            )));
        }
    }
} 
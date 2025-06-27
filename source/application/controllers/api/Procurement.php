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
     * 조달청 납품요구 데이터 조회 API (엑셀 스펙 준수)
     * GET /api/procurement/delivery-requests
     * 
     * 엑셀 파일 "요청 API 목록" 시트의 스펙을 정확히 반영한 API입니다.
     * 
     * @OA\Get(
     *     path="/api/procurement/delivery-requests",
     *     tags={"Procurement"},
     *     summary="납품요구 데이터 조회 (엑셀 스펙 준수)",
     *     description="조달청 납품요구 데이터를 조회합니다. 엑셀 파일의 API 스펙을 정확히 반영했습니다.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="startDate",
     *         in="query",
     *         description="납품요구접수일자 시작일 (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="endDate",
     *         in="query", 
     *         description="납품요구접수일자 종료일 (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="exclcProdctYn",
     *         in="query",
     *         description="구분(조달/마스/전체) - 우수제품여부 값이 'Y'이면 '조달', 'N'이면 '마스'",
     *         required=false,
     *         @OA\Schema(type="string", example="조달")
     *     ),
     *     @OA\Parameter(
     *         name="prdctClsfcNoNm",
     *         in="query",
     *         description="품명",
     *         required=false,
     *         @OA\Schema(type="string", example="영상감시장치")
     *     ),
     *     @OA\Parameter(
     *         name="dtilPrdctClsfcNoNm",
     *         in="query",
     *         description="세부품명",
     *         required=false,
     *         @OA\Schema(type="string", example="CCTV")
     *     ),
     *     @OA\Parameter(
     *         name="dminsttRgnNm",
     *         in="query",
     *         description="수요기관지역(대한민국의 행정구역명 고정값)",
     *         required=false,
     *         @OA\Schema(type="string", example="경기도")
     *     ),
     *     @OA\Parameter(
     *         name="dminsttNm",
     *         in="query",
     *         description="수요기관",
     *         required=false,
     *         @OA\Schema(type="string", example="전북특별자치도")
     *     ),
     *     @OA\Parameter(
     *         name="corpNm",
     *         in="query",
     *         description="계약업체",
     *         required=false,
     *         @OA\Schema(type="string", example="(주)지인테크")
     *     ),
     *     @OA\Parameter(
     *         name="dlvrReqNmSearch",
     *         in="query",
     *         description="사업명(사용자 입력 검색)",
     *         required=false,
     *         @OA\Schema(type="string", example="바라산휴양림팀 CCTV 조달 구매 계약")
     *     ),
     *     @OA\Parameter(
     *         name="corpNameSearch",
     *         in="query",
     *         description="계약업체명(사용자 입력 검색)",
     *         required=false,
     *         @OA\Schema(type="string", example="주식회사 비알인포텍")
     *     ),
     *     @OA\Parameter(
     *         name="prdctClsfcNoNmSearch",
     *         in="query",
     *         description="품명(사용자 입력 검색)",
     *         required=false,
     *         @OA\Schema(type="string", example="감시장치")
     *     ),
     *     @OA\Parameter(
     *         name="prdctIdntNoNmSearch",
     *         in="query",
     *         description="품목(사용자 입력 검색)",
     *         required=false,
     *         @OA\Schema(type="string", example="렌즈")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="페이지 번호",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         description="페이지당 데이터 수",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=50)
     *     ),
     *     @OA\Parameter(
     *         name="sortBy",
     *         in="query",
     *         description="정렬 기준 필드",
     *         required=false,
     *         @OA\Schema(type="string", example="dlvrReqRcptDate")
     *     ),
     *     @OA\Parameter(
     *         name="sortOrder",
     *         in="query",
     *         description="정렬 순서",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="조달청 데이터 조회 성공"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="page", type="integer", example=5),
     *                 @OA\Property(property="pageSize", type="integer", example=50),
     *                 @OA\Property(property="total", type="string", example="1"),
     *                 @OA\Property(property="totalAmount", type="number", example=2310708400),
     *                 @OA\Property(property="jodalTotalAmount", type="number", example=1503059300),
     *                 @OA\Property(property="masTotalAmount", type="number", example=807649100),
     *                 @OA\Property(property="items", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="exclcProdctYn", type="string", example="조달"),
     *                         @OA\Property(property="dlvrReqRcptDate", type="string", example="2024-01-05"),
     *                         @OA\Property(property="dminsttNm", type="string", example="경기도 양평군 환경사업소"),
     *                         @OA\Property(property="dminsttRgnNm", type="string", example="경기도 양평군"),
     *                         @OA\Property(property="corpNm", type="string", example="(주)지인테크"),
     *                         @OA\Property(property="dlvrReqNm", type="string", example="관급자재 (영상감시설비) 구입(지평 - 정보통신)"),
     *                         @OA\Property(property="prdctClsfcNoNm", type="string", example="영상감시장치"),
     *                         @OA\Property(property="dtilPrdctClsfcNoNm", type="string", example="CCTV"),
     *                         @OA\Property(property="prdctIdntNo", type="string", example="123456"),
     *                         @OA\Property(property="prdctIdntNoNm", type="string", example="렌즈"),
     *                         @OA\Property(property="incdecQty", type="integer", example=10),
     *                         @OA\Property(property="prdctUprc", type="number", example=-3516000),
     *                         @OA\Property(property="incdecAmt", type="number", example=1500000),
     *                         @OA\Property(property="dminsttCd", type="number", example=4170033)
     *                     )
     *                 ),
     *                 @OA\Property(property="filterOptions", type="object",
     *                     @OA\Property(property="prdctClsfcNoNms", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="value", type="string", example="영상감시장치"),
     *                             @OA\Property(property="label", type="string", example="영상감시장치")
     *                         )
     *                     ),
     *                     @OA\Property(property="dminsttNms", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="value", type="string", example="경기도청"),
     *                             @OA\Property(property="label", type="string", example="경기도청")
     *                         )
     *                     ),
     *                     @OA\Property(property="corpNms", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="value", type="string", example="(주)지인테크"),
     *                             @OA\Property(property="label", type="string", example="(주)지인테크")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", example="2025-06-23T10:30:00+00:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="파라미터 오류",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="잘못된 파라미터 입니다"),
     *             @OA\Property(property="timestamp", type="string", example="2025-06-23T10:30:00+00:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="서버 내부 오류",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="데이터를 불러오지 못했습니다"),
     *             @OA\Property(property="timestamp", type="string", example="2025-06-23T10:30:00+00:00")
     *         )
     *     )
     * )
     */
    public function delivery_requests() {
        if ($this->input->method() !== 'get') {
            return $this->output_error('GET 요청만 허용됩니다.', 405);
        }
        
        // 토큰 검증
        $decoded_token = $this->verify_token();
        if (!$decoded_token) {
            // verify_token 내부에서 이미 에러 응답 처리를 하므로 별도 처리 불필요
            return;
        }

        // GET 파라미터 수집
        $params = $this->input->get();

        // 파라미터 이름 변환 (pageSize -> size)
        if (isset($params['pageSize'])) {
            $params['size'] = $params['pageSize'];
            unset($params['pageSize']);
        }
        
        // 모델 호출 부분을 v2로 변경
        $data = $this->Procurement_model->get_delivery_requests($params);

        if ($data) {
            // API 호출 로그 기록 (성공)
            $this->log_api_call('GET /api/procurement/delivery-requests', 'GET', $decoded_token->user_id, 'SUCCESS');
            $this->output_success($data, '납품요구 데이터 조회 성공');
        } else {
            // API 호출 로그 기록 (실패)
            $this->log_api_call('GET /api/procurement/delivery-requests', 'GET', $decoded_token->user_id, 'FAILED', '데이터 조회 실패');
            $this->output_error('데이터를 조회하는 데 실패했습니다.', 500);
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
        $decoded_token = $this->verify_token();
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
            $this->log_api_call('/api/procurement/statistics/institutions', 'GET', $decoded_token->user_id);
            
            $this->output_success($response_data, '수요기관별 통계 조회 성공');
            
        } catch (Exception $e) {
            $this->log_api_call('/api/procurement/statistics/institutions', 'GET', $decoded_token->user_id ?? null, 'error', $e->getMessage());
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
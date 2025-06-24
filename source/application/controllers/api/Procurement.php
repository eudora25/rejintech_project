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
     * 조달청 데이터 전체 리스트 조회 API
     * GET /api/procurement/delivery-requests
     * Query Parameters로 모든 필터링 처리
     * 
     * PowerPoint 스토리보드 요구사항:
     * 1. 기간 필터링: dlvrReqRcptDate ~ dlvrTmlmtDate 사이 값 조회
     * 2. 지역 매칭: 사업자번호로 2번째 API와 매칭하여 지역 정보 가져오기
     * 3. 모든 데이터 표시: 합산 없이 모든 레코드 표기
     * 
     * @OA\Get(
     *     path="/api/procurement/delivery-requests",
     *     tags={"Procurement"},
     *     summary="납품요구 데이터 조회",
     *     description="조달청 납품요구 데이터를 조회합니다. 기간, 지역, 업체명 등 다양한 조건으로 필터링 가능합니다.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="dlvrReqRcptDateFrom",
     *         in="query",
     *         description="납품요구접수일자 시작일 (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="dlvrReqRcptDateTo",
     *         in="query", 
     *         description="납품요구접수일자 종료일 (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="dlvrTmlmtDateFrom",
     *         in="query",
     *         description="납품일자종료일 시작일 (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="dlvrTmlmtDateTo",
     *         in="query",
     *         description="납품일자종료일 종료일 (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="dminsttNm",
     *         in="query",
     *         description="수요기관명",
     *         required=false,
     *         @OA\Schema(type="string", example="서울특별시")
     *     ),
     *     @OA\Parameter(
     *         name="dminsttRgnNm",
     *         in="query",
     *         description="수요기관지역",
     *         required=false,
     *         @OA\Schema(type="string", example="서울")
     *     ),
     *     @OA\Parameter(
     *         name="corpNm",
     *         in="query",
     *         description="업체명",
     *         required=false,
     *         @OA\Schema(type="string", example="삼성전자")
     *     ),
     *     @OA\Parameter(
     *         name="prdctClsfcNoNm",
     *         in="query",
     *         description="품명",
     *         required=false,
     *         @OA\Schema(type="string", example="컴퓨터")
     *     ),
     *     @OA\Parameter(
     *         name="exclcProdctYn",
     *         in="query",
     *         description="우수제품여부 (Y/N)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"Y", "N"}, example="Y")
     *     ),
     *     @OA\Parameter(
     *         name="includeRegionMatch",
     *         in="query",
     *         description="지역 매칭 포함 여부 (사업자번호로 2번째 API와 매칭)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="페이지 번호",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="size",
     *         in="query",
     *         description="페이지 크기",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=50)
     *     ),
     *     @OA\Parameter(
     *         name="sortBy",
     *         in="query",
     *         description="정렬 기준 필드",
     *         required=false,
     *         @OA\Schema(type="string", enum={"dlvrReqRcptDate", "incdecAmt", "dminsttNm", "corpNm"}, example="dlvrReqRcptDate")
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
     *                 @OA\Property(property="page", type="integer", example=1),
     *                 @OA\Property(property="size", type="integer", example=50),
     *                 @OA\Property(property="total", type="integer", example=1000),
     *                 @OA\Property(property="totalAmount", type="number", example=50000000),
     *                 @OA\Property(property="items", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="dlvrReqRcptDate", type="string", example="2025-06-23"),
     *                         @OA\Property(property="dlvrTmlmtDate", type="string", example="2025-06-30"),
     *                         @OA\Property(property="dminsttNm", type="string", example="서울특별시"),
     *                         @OA\Property(property="dminsttRgnNm", type="string", example="서울"),
     *                         @OA\Property(property="corpNm", type="string", example="삼성전자"),
     *                         @OA\Property(property="rgnNm", type="string", example="경기"),
     *                         @OA\Property(property="dlvrReqNm", type="string", example="컴퓨터 납품"),
     *                         @OA\Property(property="prdctClsfcNoNm", type="string", example="컴퓨터"),
     *                         @OA\Property(property="prdctIdntNo", type="string", example="COMP001"),
     *                         @OA\Property(property="prdctIdntNoNm", type="string", example="노트북"),
     *                         @OA\Property(property="incdecQty", type="integer", example=10),
     *                         @OA\Property(property="incdecAmt", type="number", example=5000000),
     *                         @OA\Property(property="exclcProdctYn", type="string", example="Y")
     *                     )
     *                 )
     *             ),
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
        if (!$decoded_token) return; // 에러는 verify_token에서 처리됨
        
        try {
            // 기본 구조 초기화
            $params = [];
            
            // Query Parameters 처리
            $params['page'] = (int)($this->input->get('page') ?: 1);
            $params['size'] = (int)($this->input->get('size') ?: 50);
            
            // 페이지 크기 제한
            if ($params['size'] > 100) {
                $params['size'] = 100;
            }
            if ($params['page'] < 1) {
                $params['page'] = 1;
            }
            
            // PowerPoint 요구사항에 따른 필터 파라미터 처리
            $filter_mappings = [
                // 기간 필터링 (From/To 방식)
                'dlvrReqRcptDateFrom' => 'dlvrReqRcptDateFrom',
                'dlvrReqRcptDateTo' => 'dlvrReqRcptDateTo', 
                'dlvrTmlmtDateFrom' => 'dlvrTmlmtDateFrom',
                'dlvrTmlmtDateTo' => 'dlvrTmlmtDateTo',
                
                // 기본 필터들
                'dminsttNm' => 'dminsttNm',           // 수요기관명
                'dminsttRgnNm' => 'dminsttRgnNm',     // 수요기관지역
                'corpNm' => 'corpNm',                 // 업체명
                'dlvrReqNm' => 'dlvrReqNm',           // 납품요구건명
                'prdctClsfcNoNm' => 'prdctClsfcNoNm', // 품명
                'prdctIdntNo' => 'prdctIdntNo',       // 물품식별번호
                'exclcProdctYn' => 'exclcProdctYn',   // 우수제품여부
                
                // 지역 매칭 옵션
                'includeRegionMatch' => 'includeRegionMatch',
                
                // 기존 호환성
                'type' => 'types',
                'dateFrom' => 'dateFrom',
                'dateTo' => 'dateTo',
                'amountFrom' => 'amountFrom',
                'amountTo' => 'amountTo'
            ];
            
            foreach ($filter_mappings as $query_key => $param_key) {
                $value = $this->input->get($query_key);
                if ($value !== null && $value !== '') {
                    if ($query_key === 'type') {
                        // type은 배열로 변환 (단일 값이지만 모델에서 배열로 처리)
                        $params[$param_key] = [$value];
                    } elseif ($query_key === 'includeRegionMatch') {
                        // boolean 변환
                        $params[$param_key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $params[$param_key] = $value;
                    }
                }
            }
            
            // 정렬 처리
            $sortBy = $this->input->get('sortBy');
            $sortOrder = $this->input->get('sortOrder') ?: 'desc';
            
            if ($sortBy && in_array($sortBy, ['dlvrReqRcptDate', 'incdecAmt', 'dminsttNm', 'corpNm', 'dlvrTmlmtDate'])) {
                $params['sortModel'] = [$sortBy => $sortOrder];
            }
            
            // 데이터 조회
            $result = $this->Procurement_model->get_delivery_requests($params);
            
            // PowerPoint 요구사항에 맞게 응답 구조 조정
            $response_data = [
                'page' => $result['page'],
                'size' => $result['size'], 
                'total' => $result['total'],
                'totalAmount' => $result['totalAmount'], // 전체 금액
                'items' => $result['data'], // data -> items로 변경 (PowerPoint 구조에 맞게)
                
                // 통계 정보 (기존 호환성)
                'jodalTotalAmount' => $result['jodalTotalAmount'] ?? 0,
                'masTotalAmount' => $result['masTotalAmount'] ?? 0,
                
                // 필터 옵션 (기존 호환성)
                'filterOptions' => [
                    'prdctClsfcNoNm' => $result['filterPrdctClsfcNoNm'] ?? [],
                    'dminsttNm' => $result['filterDminsttNm'] ?? [],
                    'corpNm' => $result['filterCorpNm'] ?? []
                ]
            ];

            // API 호출 기록
            $this->log_api_call('/api/procurement/delivery-requests', 'GET', $decoded_token->user_id);
            
            $this->output_success($response_data, '조달청 데이터 조회 성공');
            
        } catch (Exception $e) {
            $this->log_api_call('/api/procurement/delivery-requests', 'GET', $decoded_token->user_id ?? null, 'error', $e->getMessage());
            return $this->output_error('데이터 조회 중 오류가 발생했습니다: ' . $e->getMessage(), 500);
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
     *         name="dminsttRgnNm",
     *         in="query",
     *         description="수요기관지역 - PowerPoint 요구사항",
     *         required=false,
     *         @OA\Schema(type="string", example="서울")
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
     *         name="prdctClsfcNoNm",
     *         in="query",
     *         description="품명 (추가 필터)",
     *         required=false,
     *         @OA\Schema(type="string", example="컴퓨터")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="수요기관별 통계 조회 성공"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="year", type="integer", description="기준 연도", example=2025),
     *                 @OA\Property(property="totalInstitutions", type="integer", description="전체 수요기관 수", example=150),
     *                 @OA\Property(property="totalAmount", type="number", description="전체 증감금액 합산", example=50000000000),
     *                 @OA\Property(property="prevYearAmount", type="number", description="전년 실적", example=45000000000),
     *                 @OA\Property(property="growthRate", type="number", description="전년 대비 증감률(%)", example=11.11),
     *                 @OA\Property(property="institutions", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="dminsttNm", type="string", description="수요기관명", example="서울특별시"),
     *                         @OA\Property(property="dminsttRgnNm", type="string", description="수요기관지역", example="서울"),
     *                         @OA\Property(property="dminsttCd", type="string", description="수요기관코드", example="ORG001"),
     *                         @OA\Property(property="currentAmount", type="number", description="당해년도 증감금액", example=5000000000),
     *                         @OA\Property(property="prevAmount", type="number", description="전년도 증감금액", example=4500000000),
     *                         @OA\Property(property="growthRate", type="number", description="증감률(%)", example=11.11),
     *                         @OA\Property(property="deliveryCount", type="integer", description="납품 건수", example=150),
     *                         @OA\Property(property="exclcProdctAmount", type="number", description="우수제품 금액", example=3000000000),
     *                         @OA\Property(property="generalProdctAmount", type="number", description="일반제품 금액", example=2000000000),
     *                         @OA\Property(property="exclcProdctYn", type="string", description="주요 제품 유형", example="Y")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", example="2025-06-24T10:30:00+00:00")
     *         )
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
            // PowerPoint 요구사항에 따른 파라미터 처리
            $params = [];
            
            // 페이징 파라미터 처리
            $page = $this->input->get('page');
            $size = $this->input->get('size');
            
            $params['page'] = $page ? max(1, (int)$page) : 1;
            $params['size'] = $size ? max(1, min(100, (int)$size)) : 10; // 최대 100개로 제한
            
            // 연도 필터 (PowerPoint 요구사항: 납품요구접수일자에서 연도만)
            $year = $this->input->get('year');
            if ($year) {
                $params['year'] = (int)$year;
            } else {
                // 기본값: 현재 연도
                $params['year'] = (int)date('Y');
            }
            
            // PowerPoint 요구사항 필터들
            $filter_mappings = [
                'dminsttNm' => 'dminsttNm',           // 수요기관명 기준 필터
                'dminsttRgnNm' => 'dminsttRgnNm',     // 수요기관지역
                'exclcProdctYn' => 'exclcProdctYn',   // 우수제품여부 필터
                'includePrevYear' => 'includePrevYear', // 전년 대비 증감률 계산 포함
                
                // 추가 필터들
                'corpNm' => 'corpNm',
                'prdctClsfcNoNm' => 'prdctClsfcNoNm',
                
                // 기존 호환성
                'dlvrReqRcptDate' => 'dlvrReqRcptDate',
                'dateFrom' => 'dateFrom',
                'dateTo' => 'dateTo',
                'amountFrom' => 'amountFrom',
                'amountTo' => 'amountTo'
            ];
            
            foreach ($filter_mappings as $query_key => $param_key) {
                $value = $this->input->get($query_key);
                if ($value !== null && $value !== '') {
                    if ($query_key === 'includePrevYear') {
                        $params[$param_key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $params[$param_key] = $value;
                    }
                }
            }
            
            // 기본적으로 전년 대비 계산 포함
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
                'totalInstitutions' => $result['total'] ?? 0,
                'totalAmount' => $result['totalAmount'] ?? 0,
                'prevYearAmount' => $result['prevYearAmount'] ?? 0,
                'growthRate' => $result['growthRate'] ?? 0,
                'institutions' => $result['institutions'] ?? [],
                
                // 필터 정보 (참고용)
                'filters' => [
                    'year' => $params['year'],
                    'dminsttNm' => $params['dminsttNm'] ?? null,
                    'dminsttRgnNm' => $params['dminsttRgnNm'] ?? null,
                    'exclcProdctYn' => $params['exclcProdctYn'] ?? null
                ]
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
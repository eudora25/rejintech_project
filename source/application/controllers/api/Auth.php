<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Composer autoload
require_once FCPATH . 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Auth API Controller
 * 
 * JWT 기반 인증 API 컨트롤러
 * 
 * @swagger: @OA\Tag(name="Authentication", description="사용자 인증 관련 API")
 */
class Auth extends CI_Controller
{
    private $jwt_secret;
    private $jwt_algorithm;
    private $jwt_expiration;
    private $jwt_issuer;
    private $jwt_audience;

    public function __construct()
    {
        parent::__construct();
        
        // JWT 설정 로드
        $this->config->load('jwt');
        $this->jwt_secret = $this->config->item('jwt_secret_key');
        $this->jwt_algorithm = $this->config->item('jwt_algorithm');
        $this->jwt_expiration = $this->config->item('jwt_expiration');
        $this->jwt_issuer = $this->config->item('jwt_issuer');
        $this->jwt_audience = $this->config->item('jwt_audience');
        
        // JWT 설정 검증
        if (empty($this->jwt_secret) || strlen($this->jwt_secret) < 32) {
            log_message('error', 'JWT 비밀키가 설정되지 않았거나 너무 짧습니다.');
            show_error('JWT 설정 오류: 비밀키를 확인하세요.');
        }
        
        // 모델 로드
        $this->load->model('User_model');
        $this->load->model('Login_log_model');
        $this->load->model('User_token_model');
        
        // 헬퍼 로드
        $this->load->helper('url');
        
        // JSON 응답을 위한 헤더 설정
        $this->output->set_content_type('application/json');
        
        // CORS 헤더 설정
        $this->output->set_header('Access-Control-Allow-Origin: *');
        $this->output->set_header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        $this->output->set_header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        // OPTIONS 요청 처리 (CORS preflight)
        if ($this->input->method() === 'options') {
            $this->output->set_status_header(200);
            return;
        }
    }

    /**
     * JWT 토큰 생성
     */
    private function generate_jwt_token($user_data)
    {
        $issued_at = time();
        $expiration = $issued_at + $this->jwt_expiration;
        
        $payload = array(
            'iss' => $this->jwt_issuer,     // 발급자
            'aud' => $this->jwt_audience,   // 대상
            'iat' => $issued_at,            // 발급 시간
            'exp' => $expiration,           // 만료 시간
            'user_id' => $user_data['id'],
            'username' => $user_data['username'],
            'email' => $user_data['email']
        );

        return JWT::encode($payload, $this->jwt_secret, $this->jwt_algorithm);
    }

    /**
     * JWT 토큰 검증
     */
    private function verify_jwt_token($token)
    {
        try {
            log_message('debug', 'JWT Verification - Token to verify: ' . substr($token, 0, 50) . '...');
            log_message('debug', 'JWT Verification - Secret key length: ' . strlen($this->jwt_secret));
            log_message('debug', 'JWT Verification - Algorithm: ' . $this->jwt_algorithm);
            
            $decoded = JWT::decode($token, new Key($this->jwt_secret, $this->jwt_algorithm));
            $decoded_array = (array) $decoded;
            
            log_message('debug', 'JWT Verification - Decode SUCCESS');
            log_message('debug', 'JWT Verification - Decoded data: ' . json_encode($decoded_array));
            
            return $decoded_array;
        } catch (Exception $e) {
            log_message('debug', 'JWT Verification - Decode FAILED: ' . $e->getMessage());
            log_message('debug', 'JWT Verification - Exception class: ' . get_class($e));
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
            if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
                return $matches[1];
            }
        }
        
        return false;
    }

    /**
     * 클라이언트 IP 주소 가져오기
     */
    private function get_client_ip()
    {
        // Proxy를 통한 접속 확인
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // 여러 IP가 있을 경우 첫 번째 IP 사용
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        
        return 'unknown';
    }

    /**
     * 사용자 에이전트 가져오기
     */
    private function get_user_agent()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
    }

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Authentication"},
     *     summary="사용자 로그인",
     *     description="사용자명과 패스워드로 로그인하여 JWT 토큰을 발급받습니다.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","password"},
     *             @OA\Property(property="username", type="string", example="admin", description="사용자명"),
     *             @OA\Property(property="password", type="string", example="admin123", description="패스워드")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="로그인 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="username", type="string", example="admin"),
     *                 @OA\Property(property="email", type="string", example="admin@example.com")
     *             ),
     *             @OA\Property(property="message", type="string", example="로그인 성공")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="잘못된 요청",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="사용자명과 패스워드를 입력해주세요.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="인증 실패",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="잘못된 사용자명 또는 패스워드입니다.")
     *         )
     *     )
     * )
     */
    public function login()
    {
        // POST 요청만 허용
        if ($this->input->method() !== 'post') {
            $this->output
                ->set_status_header(405)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'POST 요청만 허용됩니다.'
                ]));
            return;
        }

        // JSON 입력 데이터 받기
        $json_input = json_decode($this->input->raw_input_stream, true);
        
        if (!$json_input) {
            $this->output
                ->set_status_header(400)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => '잘못된 JSON 형식입니다.'
                ]));
            return;
        }

        $username = isset($json_input['username']) ? trim($json_input['username']) : '';
        $password = isset($json_input['password']) ? $json_input['password'] : '';

        // 클라이언트 정보 수집
        $client_ip = $this->get_client_ip();
        $user_agent = $this->get_user_agent();

        // 입력값 검증
        if (empty($username) || empty($password)) {
            // 로그인 실패 로그 저장 - 필수 필드 누락
            $this->Login_log_model->log_failed_login(
                $username, 
                $client_ip, 
                '사용자명 또는 패스워드 누락', 
                $user_agent
            );

            $this->output
                ->set_status_header(400)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => '사용자명과 패스워드를 입력해주세요.'
                ]));
            return;
        }

        // 사용자 인증
        $user = $this->User_model->authenticate($username, $password);

        if (!$user) {
            // 로그인 실패 로그 저장 - 인증 실패
            $this->Login_log_model->log_failed_login(
                $username, 
                $client_ip, 
                '잘못된 사용자명 또는 패스워드', 
                $user_agent
            );

            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => '잘못된 사용자명 또는 패스워드입니다.'
                ]));
            return;
        }

        // JWT 토큰 생성
        $token = $this->generate_jwt_token($user);

        // 토큰을 데이터베이스에 저장
        $token_saved = $this->User_token_model->save_token(
            $user['id'],
            $token,
            $client_ip,
            $user_agent,
            $this->jwt_expiration,
            'access'
        );

        if (!$token_saved) {
            log_message('error', 'Failed to save token to database for user: ' . $user['id']);
        }

        // 로그인 성공 로그 저장
        $this->Login_log_model->log_successful_login($user, $client_ip, $user_agent);

        // 성공 응답 (API 요구사항에 맞는 형식)
        $this->output
            ->set_status_header(200)
            ->set_output(json_encode([
                'success' => true,
                'message' => '로그인 성공',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email']
                    ],
                    'expires_in' => $this->jwt_expiration, // 토큰 만료 시간 (초)
                    'token_type' => 'Bearer'
                ],
                'timestamp' => date('c') // ISO8601 형식
            ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @OA\Post(
     *     path="/api/auth/verify",
     *     tags={"Authentication"},
     *     summary="토큰 검증",
     *     description="JWT 토큰의 유효성을 검증합니다.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="토큰 유효",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="username", type="string", example="admin"),
     *                 @OA\Property(property="email", type="string", example="admin@example.com")
     *             ),
     *             @OA\Property(property="message", type="string", example="토큰이 유효합니다.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="토큰 무효",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="유효하지 않은 토큰입니다.")
     *         )
     *     )
     * )
     */
    public function verify()
    {
        // 디버깅을 위한 헤더 정보 로그
        $headers = $this->input->request_headers();
        log_message('debug', 'Verify endpoint - Headers: ' . json_encode($headers));
        
        $token = $this->get_token_from_header();
        
        // 디버깅을 위한 토큰 정보 로그
        log_message('debug', 'Verify endpoint - Token extracted: ' . ($token ? 'YES' : 'NO'));
        if ($token) {
            log_message('debug', 'Verify endpoint - Token length: ' . strlen($token));
            log_message('debug', 'Verify endpoint - Token first 50 chars: ' . substr($token, 0, 50));
        }
        
        if (!$token) {
            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => '토큰이 제공되지 않았습니다.'
                ]));
            return;
        }

        $user_data = $this->verify_jwt_token($token);

        // 디버깅을 위한 검증 결과 로그
        log_message('debug', 'Verify endpoint - Token validation: ' . ($user_data ? 'SUCCESS' : 'FAILED'));
        if (!$user_data) {
            log_message('debug', 'Verify endpoint - JWT verification failed');
        }

        if (!$user_data) {
            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => '유효하지 않은 토큰입니다.'
                ]));
            return;
        }

        $this->output
            ->set_status_header(200)
            ->set_output(json_encode([
                'success' => true,
                'user' => [
                    'user_id' => $user_data['user_id'],
                    'username' => $user_data['username'],
                    'email' => $user_data['email'],
                    'iat' => $user_data['iat'],
                    'exp' => $user_data['exp']
                ],
                'message' => '토큰이 유효합니다.'
            ]));
    }

    /**
     * @OA\Get(
     *     path="/api/auth/profile",
     *     tags={"Authentication"},
     *     summary="사용자 프로필 조회",
     *     description="JWT 토큰을 사용하여 현재 로그인한 사용자의 프로필을 조회합니다.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="프로필 조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="username", type="string", example="admin"),
     *                 @OA\Property(property="email", type="string", example="admin@example.com"),
     *                 @OA\Property(property="created_at", type="string", example="2025-06-22 05:59:26"),
     *                 @OA\Property(property="updated_at", type="string", example="2025-06-22 05:59:26")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="인증 실패",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="유효하지 않은 토큰입니다.")
     *         )
     *     )
     * )
     */
    public function profile()
    {
        $token = $this->get_token_from_header();
        
        if (!$token) {
            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => '토큰이 제공되지 않았습니다.'
                ]));
            return;
        }

        $token_data = $this->verify_jwt_token($token);

        if (!$token_data) {
            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => '유효하지 않은 토큰입니다.'
                ]));
            return;
        }

        // 데이터베이스에서 최신 사용자 정보 조회
        $user = $this->User_model->get_user_by_id($token_data['user_id']);

        if (!$user) {
            $this->output
                ->set_status_header(404)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => '사용자를 찾을 수 없습니다.'
                ]));
            return;
        }

        // 패스워드 제외하고 응답
        unset($user['password']);

        $this->output
            ->set_status_header(200)
            ->set_output(json_encode([
                'success' => true,
                'user' => $user
            ]));
    }

    /**
     * @OA\Get(
     *     path="/api/auth/login-logs",
     *     tags={"Authentication"},
     *     summary="로그인 로그 조회",
     *     description="사용자의 로그인 로그를 조회합니다.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="사용자 ID (관리자만)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="조회 개수 제한",
     *         required=false,
     *         @OA\Schema(type="integer", example=50, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="offset",
     *         in="query",
     *         description="조회 시작 위치",
     *         required=false,
     *         @OA\Schema(type="integer", example=0)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="로그인 로그 조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="logs", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="username", type="string", example="admin"),
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="ip_address", type="string", example="192.168.1.100"),
     *                     @OA\Property(property="user_agent", type="string", example="Mozilla/5.0..."),
     *                     @OA\Property(property="login_status", type="string", example="success"),
     *                     @OA\Property(property="failure_reason", type="string", example=null),
     *                     @OA\Property(property="request_time", type="string", example="2025-06-23 10:30:00"),
     *                     @OA\Property(property="created_at", type="string", example="2025-06-23 10:30:00")
     *                 )
     *             ),
     *             @OA\Property(property="total", type="integer", example=100),
     *             @OA\Property(property="message", type="string", example="로그인 로그 조회 성공")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="인증 실패",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="유효하지 않은 토큰입니다.")
     *         )
     *     )
     * )
     */
    public function login_logs()
    {
        // GET 요청만 허용
        if ($this->input->method() !== 'get') {
            $this->output
                ->set_status_header(405)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'GET 요청만 허용됩니다.'
                ]));
            return;
        }

        // JWT 토큰 검증
        $token = $this->get_token_from_header();
        
        if (!$token) {
            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => '토큰이 제공되지 않았습니다.'
                ]));
            return;
        }

        $token_data = $this->verify_jwt_token($token);

        if (!$token_data) {
            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => '유효하지 않은 토큰입니다.'
                ]));
            return;
        }

        // 쿼리 파라미터 받기
        $user_id = $this->input->get('user_id');
        $limit = (int)$this->input->get('limit') ?: 50;
        $offset = (int)$this->input->get('offset') ?: 0;

        // 제한값 검증
        if ($limit > 100) {
            $limit = 100;
        }

        // 본인 로그만 조회 (관리자는 다른 사용자 로그도 조회 가능)
        if (!$user_id) {
            $user_id = $token_data['user_id'];
        } else {
            // 다른 사용자 로그 조회시 권한 확인 (여기서는 단순히 본인만 허용)
            if ($user_id != $token_data['user_id']) {
                $this->output
                    ->set_status_header(403)
                    ->set_output(json_encode([
                        'success' => false,
                        'message' => '다른 사용자의 로그인 로그는 조회할 수 없습니다.'
                    ]));
                return;
            }
        }

        // 로그인 로그 조회
        $logs = $this->Login_log_model->get_user_login_logs($user_id, $limit, $offset);

        $this->output
            ->set_status_header(200)
            ->set_output(json_encode([
                'success' => true,
                'message' => '로그인 로그 조회 성공',
                'data' => $logs,
                'pagination' => [
                    'total' => count($logs),
                    'limit' => $limit,
                    'offset' => $offset
                ],
                'timestamp' => date('c')
            ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @OA\Get(
     *     path="/api/auth/login-statistics",
     *     tags={"Authentication"},
     *     summary="로그인 통계 조회",
     *     description="로그인 통계를 조회합니다.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="시작 날짜 (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", example="2025-06-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="종료 날짜 (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", example="2025-06-30")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="로그인 통계 조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="statistics", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="login_date", type="string", example="2025-06-23"),
     *                     @OA\Property(property="total_attempts", type="integer", example=150),
     *                     @OA\Property(property="successful_logins", type="integer", example=140),
     *                     @OA\Property(property="failed_logins", type="integer", example=10)
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="로그인 통계 조회 성공")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="인증 실패",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="유효하지 않은 토큰입니다.")
     *         )
     *     )
     * )
     */
    public function login_statistics()
    {
        // GET 요청만 허용
        if ($this->input->method() !== 'get') {
            $this->output
                ->set_status_header(405)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'GET 요청만 허용됩니다.'
                ]));
            return;
        }

        // JWT 토큰 검증
        $token = $this->get_token_from_header();
        
        if (!$token) {
            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => '토큰이 제공되지 않았습니다.'
                ]));
            return;
        }

        $token_data = $this->verify_jwt_token($token);

        if (!$token_data) {
            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => '유효하지 않은 토큰입니다.'
                ]));
            return;
        }

        // 쿼리 파라미터 받기
        $date_from = $this->input->get('date_from');
        $date_to = $this->input->get('date_to');

        // 로그인 통계 조회
        $statistics = $this->Login_log_model->get_login_statistics($date_from, $date_to);

        $this->output
            ->set_status_header(200)
            ->set_output(json_encode([
                'success' => true,
                'message' => '로그인 통계 조회 성공',
                'data' => [
                    'statistics' => $statistics,
                    'period' => [
                        'from' => $date_from ?: date('Y-m-d', strtotime('-30 days')),
                        'to' => $date_to ?: date('Y-m-d')
                    ]
                ],
                'timestamp' => date('c')
            ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @OA\Post(
     *     path="/api/auth/check-login",
     *     tags={"Authentication"},
     *     summary="로그인 상태 확인",
     *     description="현재 토큰이 데이터베이스에 저장된 유효한 토큰인지 확인합니다.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="토큰 유효 (로그인 상태)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="is_logged_in", type="boolean", example=true),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="username", type="string", example="admin"),
     *                 @OA\Property(property="email", type="string", example="admin@example.com")
     *             ),
     *             @OA\Property(property="token_info", type="object",
     *                 @OA\Property(property="issued_at", type="string", example="2025-06-23 07:00:00"),
     *                 @OA\Property(property="expires_at", type="string", example="2025-06-23 08:00:00"),
     *                 @OA\Property(property="last_used_at", type="string", example="2025-06-23 07:30:00"),
     *                 @OA\Property(property="ip_address", type="string", example="192.168.1.100")
     *             ),
     *             @OA\Property(property="message", type="string", example="로그인 상태 확인됨")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="토큰 무효 (로그아웃 상태)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="is_logged_in", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="유효하지 않은 토큰입니다.")
     *         )
     *     )
     * )
     */
    public function check_login()
    {
        // POST 요청만 허용
        if ($this->input->method() !== 'post') {
            $this->output
                ->set_status_header(405)
                ->set_output(json_encode([
                    'success' => false,
                    'is_logged_in' => false,
                    'message' => 'POST 요청만 허용됩니다.'
                ]));
            return;
        }

        // JWT 토큰 가져오기
        $token = $this->get_token_from_header();
        
        if (!$token) {
            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'is_logged_in' => false,
                    'message' => '토큰이 제공되지 않았습니다.'
                ]));
            return;
        }

        // 1. JWT 토큰 자체 검증
        $jwt_payload = $this->verify_jwt_token($token);
        
        if (!$jwt_payload) {
            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'is_logged_in' => false,
                    'message' => 'JWT 토큰이 유효하지 않습니다.'
                ]));
            return;
        }

        // 2. 데이터베이스에서 토큰 검증
        $db_token_info = $this->User_token_model->verify_token($token);
        
        if (!$db_token_info) {
            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'is_logged_in' => false,
                    'message' => '데이터베이스에서 토큰을 찾을 수 없거나 만료되었습니다.'
                ]));
            return;
        }

        // 3. JWT와 DB 정보 일치 확인
        if ($jwt_payload['user_id'] != $db_token_info['user_id']) {
            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'is_logged_in' => false,
                    'message' => '토큰 정보가 일치하지 않습니다.'
                ]));
            return;
        }

        // 4. 성공 응답
        $this->output
            ->set_status_header(200)
            ->set_output(json_encode([
                'success' => true,
                'is_logged_in' => true,
                'user' => [
                    'id' => $db_token_info['user_id'],
                    'username' => $db_token_info['username'],
                    'email' => $db_token_info['email']
                ],
                'token_info' => [
                    'issued_at' => $db_token_info['issued_at'],
                    'expires_at' => $db_token_info['expires_at'],
                    'last_used_at' => $db_token_info['last_used_at'],
                    'ip_address' => $db_token_info['ip_address'],
                    'user_agent' => $db_token_info['user_agent']
                ],
                'message' => '로그인 상태 확인됨'
            ]));
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"Authentication"},
     *     summary="로그아웃",
     *     description="현재 토큰을 무효화하여 로그아웃 처리합니다.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="logout_all", type="boolean", example=false, description="모든 디바이스에서 로그아웃")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="로그아웃 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="로그아웃 완료")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="인증 실패",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="유효하지 않은 토큰입니다.")
     *         )
     *     )
     * )
     */
    public function logout()
    {
        // POST 요청만 허용
        if ($this->input->method() !== 'post') {
            $this->output
                ->set_status_header(405)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'POST 요청만 허용됩니다.'
                ]));
            return;
        }

        // JWT 토큰 가져오기
        $token = $this->get_token_from_header();
        
        if (!$token) {
            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => '토큰이 제공되지 않았습니다.'
                ]));
            return;
        }

        // JWT 토큰 검증
        $jwt_payload = $this->verify_jwt_token($token);
        
        if (!$jwt_payload) {
            $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => '유효하지 않은 토큰입니다.'
                ]));
            return;
        }

        // JSON 입력 데이터 받기
        $json_input = json_decode($this->input->raw_input_stream, true);
        $logout_all = isset($json_input['logout_all']) ? (bool)$json_input['logout_all'] : false;

        if ($logout_all) {
            // 모든 디바이스에서 로그아웃 (모든 토큰 무효화)
            $result = $this->User_token_model->invalidate_user_tokens($jwt_payload['user_id']);
            $message = $result ? '모든 디바이스에서 로그아웃 완료' : '로그아웃 처리 중 오류 발생';
        } else {
            // 현재 토큰만 무효화
            $result = $this->User_token_model->invalidate_token($token);
            $message = $result ? '로그아웃 완료' : '로그아웃 처리 중 오류 발생';
        }

        // 응답
        $this->output
            ->set_status_header(200)
            ->set_output(json_encode([
                'success' => $result,
                'message' => $message
            ]));
    }

    /**
     * @OA\Post(
     *     path="/api/auth/change-password",
     *     tags={"Authentication"},
     *     summary="비밀번호 변경",
     *     description="현재 로그인된 사용자의 비밀번호를 변경합니다.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password","new_password","confirm_password"},
     *             @OA\Property(property="current_password", type="string", example="admin123", description="현재 비밀번호"),
     *             @OA\Property(property="new_password", type="string", example="newPassword123", description="새 비밀번호 (최소 8자, 영문+숫자+특수문자 포함)"),
     *             @OA\Property(property="confirm_password", type="string", example="newPassword123", description="새 비밀번호 확인")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="비밀번호 변경 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="비밀번호가 성공적으로 변경되었습니다."),
     *             @OA\Property(property="timestamp", type="string", example="2025-06-24T12:00:00+00:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="잘못된 요청",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="모든 필드를 입력해주세요.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="인증 실패",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="현재 비밀번호가 일치하지 않습니다.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="비밀번호 정책 위반",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="새 비밀번호는 최소 8자 이상이어야 하며, 영문, 숫자, 특수문자를 포함해야 합니다.")
     *         )
     *     )
     * )
     */
    public function change_password()
    {
        try {
            // JWT 토큰 검증
            $token = $this->get_token_from_header();
            if (!$token) {
                $this->output->set_status_header(401);
                $this->output->set_output(json_encode([
                    'success' => false,
                    'message' => 'Authorization 헤더가 필요합니다.',
                    'timestamp' => date('c')
                ], JSON_UNESCAPED_UNICODE));
                return;
            }

            $decoded_token = $this->verify_jwt_token($token);
            if (!$decoded_token) {
                $this->output->set_status_header(401);
                $this->output->set_output(json_encode([
                    'success' => false,
                    'message' => '유효하지 않은 토큰입니다.',
                    'timestamp' => date('c')
                ], JSON_UNESCAPED_UNICODE));
                return;
            }

            // DB에서 토큰 검증
            $token_hash = hash('sha256', $token);
            $stored_token = $this->User_token_model->get_valid_token($token_hash);
            if (!$stored_token) {
                $this->output->set_status_header(401);
                $this->output->set_output(json_encode([
                    'success' => false,
                    'message' => '토큰이 무효화되었거나 만료되었습니다.',
                    'timestamp' => date('c')
                ], JSON_UNESCAPED_UNICODE));
                return;
            }

            // 요청 데이터 받기
            $input = json_decode($this->input->raw_input_stream, true);
            
            if (!$input) {
                $this->output->set_status_header(400);
                $this->output->set_output(json_encode([
                    'success' => false,
                    'message' => '잘못된 JSON 형식입니다.',
                    'timestamp' => date('c')
                ], JSON_UNESCAPED_UNICODE));
                return;
            }

            // 필수 필드 검증
            $required_fields = ['current_password', 'new_password', 'confirm_password'];
            foreach ($required_fields as $field) {
                if (empty($input[$field])) {
                    $this->output->set_status_header(400);
                    $this->output->set_output(json_encode([
                        'success' => false,
                        'message' => '모든 필드를 입력해주세요.',
                        'timestamp' => date('c')
                    ], JSON_UNESCAPED_UNICODE));
                    return;
                }
            }

            $current_password = $input['current_password'];
            $new_password = $input['new_password'];
            $confirm_password = $input['confirm_password'];

            // 새 비밀번호 확인 검증
            if ($new_password !== $confirm_password) {
                $this->output->set_status_header(400);
                $this->output->set_output(json_encode([
                    'success' => false,
                    'message' => '새 비밀번호와 확인 비밀번호가 일치하지 않습니다.',
                    'timestamp' => date('c')
                ], JSON_UNESCAPED_UNICODE));
                return;
            }

            // 새 비밀번호 정책 검증
            $password_validation = $this->validate_password($new_password);
            if (!$password_validation['valid']) {
                $this->output->set_status_header(422);
                $this->output->set_output(json_encode([
                    'success' => false,
                    'message' => $password_validation['message'],
                    'timestamp' => date('c')
                ], JSON_UNESCAPED_UNICODE));
                return;
            }

            // 현재 사용자 정보 조회 (패스워드 포함)
            $user_id = $decoded_token['user_id'];
            $this->db->where('id', $user_id);
            $query = $this->db->get('users');
            $user = $query->row_array();

            if (!$user) {
                $this->output->set_status_header(404);
                $this->output->set_output(json_encode([
                    'success' => false,
                    'message' => '사용자를 찾을 수 없습니다.',
                    'timestamp' => date('c')
                ], JSON_UNESCAPED_UNICODE));
                return;
            }

            // 현재 비밀번호 검증
            if (!password_verify($current_password, $user['password'])) {
                // 로그인 로그에 비밀번호 변경 실패 기록
                $this->Login_log_model->log_failed_login(
                    $user['username'],
                    $this->get_client_ip(),
                    '비밀번호 변경 시도 - 현재 비밀번호 불일치',
                    $this->get_user_agent()
                );

                $this->output->set_status_header(401);
                $this->output->set_output(json_encode([
                    'success' => false,
                    'message' => '현재 비밀번호가 일치하지 않습니다.',
                    'timestamp' => date('c')
                ], JSON_UNESCAPED_UNICODE));
                return;
            }

            // 현재 비밀번호와 새 비밀번호가 같은지 확인
            if ($current_password === $new_password) {
                $this->output->set_status_header(400);
                $this->output->set_output(json_encode([
                    'success' => false,
                    'message' => '새 비밀번호는 현재 비밀번호와 달라야 합니다.',
                    'timestamp' => date('c')
                ], JSON_UNESCAPED_UNICODE));
                return;
            }

            // 비밀번호 업데이트
            $update_data = [
                'password' => password_hash($new_password, PASSWORD_DEFAULT),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->db->where('id', $user_id);
            $result = $this->db->update('users', $update_data);

            if ($result) {
                // 성공 로그 기록 (비밀번호 변경 성공을 위한 커스텀 로그)
                $log_data = [
                    'username' => $user['username'],
                    'user_id' => $user['id'],
                    'ip_address' => $this->get_client_ip(),
                    'user_agent' => $this->get_user_agent(),
                    'login_status' => 'success',
                    'failure_reason' => '비밀번호 변경 성공'
                ];
                $this->Login_log_model->save_login_log($log_data);

                // 토큰 업데이트 (마지막 사용 시간)
                $this->User_token_model->update_last_used($token_hash);

                $this->output->set_status_header(200);
                $this->output->set_output(json_encode([
                    'success' => true,
                    'message' => '비밀번호가 성공적으로 변경되었습니다.',
                    'timestamp' => date('c')
                ], JSON_UNESCAPED_UNICODE));
            } else {
                $this->output->set_status_header(500);
                $this->output->set_output(json_encode([
                    'success' => false,
                    'message' => '비밀번호 변경 중 오류가 발생했습니다.',
                    'timestamp' => date('c')
                ], JSON_UNESCAPED_UNICODE));
            }

        } catch (Exception $e) {
            log_message('error', 'Password change error: ' . $e->getMessage());
            
            $this->output->set_status_header(500);
            $this->output->set_output(json_encode([
                'success' => false,
                'message' => '서버 오류가 발생했습니다.',
                'timestamp' => date('c')
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 비밀번호 정책 검증
     * 
     * @param string $password 검증할 비밀번호
     * @return array 검증 결과
     */
    private function validate_password($password)
    {
        // 최소 길이 검증
        if (strlen($password) < 8) {
            return [
                'valid' => false,
                'message' => '비밀번호는 최소 8자 이상이어야 합니다.'
            ];
        }

        // 최대 길이 검증
        if (strlen($password) > 50) {
            return [
                'valid' => false,
                'message' => '비밀번호는 최대 50자까지 가능합니다.'
            ];
        }

        // 영문 포함 검증
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return [
                'valid' => false,
                'message' => '비밀번호는 영문자를 포함해야 합니다.'
            ];
        }

        // 숫자 포함 검증
        if (!preg_match('/[0-9]/', $password)) {
            return [
                'valid' => false,
                'message' => '비밀번호는 숫자를 포함해야 합니다.'
            ];
        }

        // 특수문자 포함 검증
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            return [
                'valid' => false,
                'message' => '비밀번호는 특수문자를 포함해야 합니다.'
            ];
        }

        // 공백 검증
        if (preg_match('/\s/', $password)) {
            return [
                'valid' => false,
                'message' => '비밀번호에는 공백을 포함할 수 없습니다.'
            ];
        }

        return [
            'valid' => true,
            'message' => '비밀번호가 정책을 만족합니다.'
        ];
    }
} 
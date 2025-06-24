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
     *             @OA\Property(property="password", type="string", example="password", description="패스워드")
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

        $user_data = $this->verify_jwt_token($token);

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
} 
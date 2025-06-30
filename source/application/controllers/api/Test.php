<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * API 테스트 컨트롤러
 * Swagger UI 테스트용 간단한 API
 */
class Test extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        
        // JSON 응답 헤더 설정
        $this->output->set_content_type('application/json', 'utf-8');
        
        // CORS 헤더 설정 (개발 환경용)
        if (ENVIRONMENT === 'development') {
            $this->output->set_header('Access-Control-Allow-Origin: *');
            $this->output->set_header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            $this->output->set_header('Access-Control-Allow-Headers: Content-Type, Authorization');
        }
        
        // OPTIONS 요청 처리 (CORS preflight)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->output->set_status_header(200);
            $this->output->_display();
            exit();
        }
    }
    
    /**
     * API 연결 테스트
     * GET /api/test
     */
    public function index()
    {
        try {
            $response = [
                'status' => 'success',
                'message' => 'API 서버가 정상적으로 작동중입니다',
                'timestamp' => date('c'),
                'server_info' => [
                    'php_version' => PHP_VERSION,
                    'codeigniter_version' => CI_VERSION,
                    'environment' => ENVIRONMENT
                ]
            ];
            
            $this->output
                ->set_status_header(200)
                ->set_output(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
        } catch (Exception $e) {
            $this->_send_error_response(500, '서버 내부 오류가 발생했습니다', $e->getMessage());
        }
    }
    
    /**
     * 데이터베이스 연결 테스트
     * GET /api/test/database
     */
    public function database()
    {
        try {
            $this->load->database();
            
            if ($this->db->conn_id) {
                // 간단한 쿼리 테스트
                $query = $this->db->query("SELECT 1 as test");
                $result = $query->row_array();
                
                $response = [
                    'status' => 'success',
                    'message' => '데이터베이스 연결이 정상입니다',
                    'database_info' => [
                        'platform' => $this->db->platform(),
                        'version' => $this->db->version(),
                        'database' => $this->db->database
                    ],
                    'test_query_result' => $result,
                    'timestamp' => date('c')
                ];
                
                $this->output
                    ->set_status_header(200)
                    ->set_output(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                
            } else {
                $this->_send_error_response(500, '데이터베이스 연결 실패');
            }
            
        } catch (Exception $e) {
            $this->_send_error_response(500, '데이터베이스 연결 오류', $e->getMessage());
        }
    }
    
    /**
     * POST 요청 테스트
     * POST /api/test/echo
     */
    public function echo_post()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->_send_error_response(405, '허용되지 않는 메소드입니다');
            return;
        }
        
        try {
            // JSON 입력 데이터 파싱
            $input_data = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->_send_error_response(400, '잘못된 JSON 형식입니다');
                return;
            }
            
            $response = [
                'status' => 'success',
                'message' => '데이터를 성공적으로 받았습니다',
                'received_data' => $input_data,
                'timestamp' => date('c'),
                'method' => $_SERVER['REQUEST_METHOD']
            ];
            
            $this->output
                ->set_status_header(200)
                ->set_output(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
        } catch (Exception $e) {
            $this->_send_error_response(500, '요청 처리 중 오류가 발생했습니다', $e->getMessage());
        }
    }
    
    /**
     * 파라미터 테스트
     * GET /api/test/params?name=홍길동&age=30
     */
    public function params()
    {
        try {
            $name = $this->input->get('name');
            $age = $this->input->get('age');
            
            $response = [
                'status' => 'success',
                'message' => '파라미터를 성공적으로 받았습니다',
                'parameters' => [
                    'name' => $name ?: null,
                    'age' => $age ? (int)$age : null
                ],
                'all_get_params' => $this->input->get(),
                'timestamp' => date('c')
            ];
            
            $this->output
                ->set_status_header(200)
                ->set_output(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
        } catch (Exception $e) {
            $this->_send_error_response(500, '파라미터 처리 중 오류가 발생했습니다', $e->getMessage());
        }
    }
    
    /**
     * 오류 응답 전송
     */
    private function _send_error_response($code, $message, $detail = null)
    {
        $response = [
            'status' => 'error',
            'message' => $message,
            'code' => $code,
            'timestamp' => date('c')
        ];
        
        if ($detail && ENVIRONMENT === 'development') {
            $response['detail'] = $detail;
        }
        
        $this->output
            ->set_status_header($code)
            ->set_output(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
} 
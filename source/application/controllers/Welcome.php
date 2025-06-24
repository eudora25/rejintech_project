<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Welcome extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Rejintech 프로젝트 메인 페이지
	 * 
	 * 프로젝트 정보와 API 문서 링크를 제공합니다.
	 */
	public function index()
	{
		$data = [
			'title' => 'Rejintech 프로젝트',
			'project_info' => [
				'name' => 'Rejintech',
				'framework' => 'CodeIgniter 3.x',
				'database' => 'MariaDB',
				'web_server' => 'Nginx',
				'php_version' => PHP_VERSION,
				'environment' => ENVIRONMENT
			],
			'links' => [
				'swagger_ui' => base_url('swagger-ui/'),
				'api_test' => base_url('api/test'),
				'api_docs' => base_url('api/docs/openapi.json'),
				'database_test' => base_url('api/test/database')
			]
		];
		
		$this->load->view('welcome_message', $data);
	}
	
	/**
	 * 데이터베이스 연결 테스트
	 */
	public function database_test()
	{
		try {
			// 데이터베이스 연결 테스트
			if ($this->db->conn_id) {
				$query = $this->db->query("SELECT 1 as test, NOW() as current_time");
				$result = $query->row_array();
				
				$data = [
					'title' => '데이터베이스 연결 테스트',
					'status' => 'success',
					'message' => '데이터베이스 연결이 성공했습니다!',
					'test_result' => $result,
					'db_info' => [
						'platform' => $this->db->platform(),
						'version' => $this->db->version(),
						'database' => $this->db->database
					]
				];
			} else {
				$data = [
					'title' => '데이터베이스 연결 테스트',
					'status' => 'error',
					'message' => '데이터베이스 연결에 실패했습니다.'
				];
			}
		} catch (Exception $e) {
			$data = [
				'title' => '데이터베이스 연결 테스트',
				'status' => 'error',
				'message' => '오류 발생: ' . $e->getMessage()
			];
		}
		
		$this->load->view('database_test', $data);
	}
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * API 문서 컨트롤러
 * OpenAPI/Swagger 문서 제공
 */
class Api_docs extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        
        // CORS 헤더 설정
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        // OPTIONS 요청 처리 (CORS preflight)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * API 문서 메인 페이지 (Swagger UI로 리다이렉트)
     */
    public function index()
    {
        redirect('/swagger-ui/');
    }
    
    /**
     * OpenAPI JSON 스펙 제공
     * GET /api/docs/openapi.json
     */
    public function openapi_json()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $openapi_file = FCPATH . 'api/docs/openapi.json';
            
            if (file_exists($openapi_file)) {
                // 파일에서 읽기
                $content = file_get_contents($openapi_file);
                echo $content;
            } else {
                // 동적으로 생성
                echo $this->_generate_openapi_spec();
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'OpenAPI 스펙을 로드할 수 없습니다',
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Internal Server Error'
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * OpenAPI 스펙 동적 생성
     */
    public function generate()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            echo $this->_generate_openapi_spec();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'OpenAPI 스펙 생성 실패',
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Internal Server Error'
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * OpenAPI 3.0 스펙 생성
     */
    private function _generate_openapi_spec()
    {
        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Rejintech API',
                'description' => 'Rejintech 프로젝트 REST API 문서<br><br>이 API는 CodeIgniter 3.x + MariaDB + Docker 환경에서 구축된 RESTful 서비스입니다.',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'Rejintech Support',
                    'email' => 'admin@rejintech.com',
                    'url' => 'http://localhost'
                ],
                'license' => [
                    'name' => 'MIT',
                    'url' => 'https://opensource.org/licenses/MIT'
                ]
            ],
            'servers' => [
                [
                    'url' => 'http://localhost',
                    'description' => '개발 서버 (Docker)'
                ]
            ],
            'tags' => [
                [
                    'name' => 'test',
                    'description' => 'API 테스트 엔드포인트'
                ],
                [
                    'name' => 'users',
                    'description' => '사용자 관리 (예제)'
                ]
            ],
            'paths' => [
                '/api/test' => [
                    'get' => [
                        'tags' => ['test'],
                        'summary' => 'API 서버 상태 확인',
                        'description' => 'API 서버가 정상적으로 작동하는지 확인합니다.',
                        'responses' => [
                            '200' => [
                                'description' => '서버 정상 응답',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'status' => ['type' => 'string', 'example' => 'success'],
                                                'message' => ['type' => 'string', 'example' => 'API 서버가 정상적으로 작동중입니다'],
                                                'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                                                'server_info' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'php_version' => ['type' => 'string'],
                                                        'codeigniter_version' => ['type' => 'string'],
                                                        'environment' => ['type' => 'string']
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/api/test/database' => [
                    'get' => [
                        'tags' => ['test'],
                        'summary' => '데이터베이스 연결 테스트',
                        'description' => 'MariaDB 데이터베이스 연결 상태를 확인합니다.',
                        'responses' => [
                            '200' => [
                                'description' => '데이터베이스 연결 성공',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'status' => ['type' => 'string', 'example' => 'success'],
                                                'message' => ['type' => 'string', 'example' => '데이터베이스 연결이 정상입니다'],
                                                'database_info' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'platform' => ['type' => 'string'],
                                                        'version' => ['type' => 'string'],
                                                        'database' => ['type' => 'string']
                                                    ]
                                                ],
                                                'test_query_result' => ['type' => 'object'],
                                                'timestamp' => ['type' => 'string', 'format' => 'date-time']
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            '500' => [
                                'description' => '데이터베이스 연결 실패',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/Error']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/api/test/params' => [
                    'get' => [
                        'tags' => ['test'],
                        'summary' => 'GET 파라미터 테스트',
                        'description' => 'GET 요청의 파라미터 처리를 테스트합니다.',
                        'parameters' => [
                            [
                                'name' => 'name',
                                'in' => 'query',
                                'description' => '이름',
                                'required' => false,
                                'schema' => ['type' => 'string', 'example' => '홍길동']
                            ],
                            [
                                'name' => 'age',
                                'in' => 'query',
                                'description' => '나이',
                                'required' => false,
                                'schema' => ['type' => 'integer', 'example' => 30]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => '파라미터 처리 성공',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'status' => ['type' => 'string'],
                                                'message' => ['type' => 'string'],
                                                'parameters' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'name' => ['type' => 'string', 'nullable' => true],
                                                        'age' => ['type' => 'integer', 'nullable' => true]
                                                    ]
                                                ],
                                                'all_get_params' => ['type' => 'object'],
                                                'timestamp' => ['type' => 'string', 'format' => 'date-time']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/api/test/echo' => [
                    'post' => [
                        'tags' => ['test'],
                        'summary' => 'POST 요청 에코 테스트',
                        'description' => 'POST 요청으로 전송된 JSON 데이터를 그대로 반환합니다.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'message' => ['type' => 'string', 'example' => '안녕하세요'],
                                            'data' => ['type' => 'object', 'example' => ['key' => 'value']]
                                        ]
                                    ],
                                    'example' => [
                                        'message' => '테스트 메시지',
                                        'data' => [
                                            'name' => '홍길동',
                                            'email' => 'hong@example.com'
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => '요청 데이터 에코 성공',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'status' => ['type' => 'string'],
                                                'message' => ['type' => 'string'],
                                                'received_data' => ['type' => 'object'],
                                                'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                                                'method' => ['type' => 'string']
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            '400' => [
                                'description' => '잘못된 JSON 형식',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/Error']
                                    ]
                                ]
                            ],
                            '405' => [
                                'description' => '허용되지 않는 메소드',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/Error']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'components' => [
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string', 'example' => 'error'],
                            'message' => ['type' => 'string'],
                            'code' => ['type' => 'integer'],
                            'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                            'detail' => ['type' => 'string', 'description' => '개발 환경에서만 제공']
                        ],
                        'required' => ['status', 'message', 'code', 'timestamp']
                    ]
                ]
            ]
        ];
        
        return json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
} 
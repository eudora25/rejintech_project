# Swagger API 문서화 가이드

## 개요

이 문서는 Rejintech 프로젝트에 Swagger/OpenAPI를 통합하여 API 문서를 자동화하는 방법을 설명합니다.

## 통합 방법 선택

### 권장 방법: swagger-php + Swagger UI

**장점:**
- PHP 어노테이션으로 자동 문서 생성
- CodeIgniter와 완벽 통합
- 실시간 API 테스트 가능
- 코드와 문서 동기화

**단점:**
- 초기 설정 필요
- 어노테이션 학습 필요

## 설치 및 설정

### 1. Composer 설정 업데이트

`source/composer.json` 파일 수정:

```json
{
    "description": "The CodeIgniter framework with Swagger API documentation",
    "name": "codeigniter/framework",
    "type": "project",
    "homepage": "https://codeigniter.com",
    "license": "MIT",
    "require": {
        "php": ">=7.4",
        "zircote/swagger-php": "^4.0"
    },
    "require-dev": {
        "mikey179/vfsStream": "1.1.*"
    },
    "autoload": {
        "psr-4": {
            "App\\": "application/"
        }
    }
}
```

### 2. Composer 패키지 설치

컨테이너 내에서 실행:

```bash
# 웹 서버 컨테이너 접속
docker exec -it rejintech-workspace bash

# Composer 업데이트 및 패키지 설치
cd /var/www/html
composer update
composer install
```

### 3. Swagger UI 설정

#### A. 정적 파일 방식 (권장)

```bash
# Swagger UI 다운로드
cd /var/www/html
mkdir swagger-ui
cd swagger-ui
wget https://github.com/swagger-api/swagger-ui/archive/refs/tags/v5.0.0.tar.gz
tar -xzf v5.0.0.tar.gz --strip-components=2 swagger-ui-5.0.0/dist
rm v5.0.0.tar.gz
```

#### B. CDN 방식 (간단한 설정)

`source/swagger-ui/index.html` 파일 생성:

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Rejintech API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.0.0/swagger-ui.css" />
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.0.0/swagger-ui-bundle.js" crossorigin></script>
    <script>
        window.onload = () => {
            window.ui = SwaggerUIBundle({
                url: '/api/docs/openapi.json',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.presets.standalone,
                ],
                layout: "StandaloneLayout",
            });
        };
    </script>
</body>
</html>
```

### 4. CodeIgniter 설정

#### Swagger 라이브러리 생성

`source/application/libraries/Swagger_lib.php`:

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use OpenApi\Generator;
use OpenApi\Util;

class Swagger_lib
{
    private $CI;
    
    public function __construct()
    {
        $this->CI =& get_instance();
    }
    
    /**
     * OpenAPI 스펙 생성
     */
    public function generate_spec()
    {
        $openapi = Generator::scan([
            APPPATH . 'controllers',
            APPPATH . 'models'
        ]);
        
        return $openapi->toJson();
    }
    
    /**
     * OpenAPI 스펙을 파일로 저장
     */
    public function save_spec($filename = 'openapi.json')
    {
        $spec = $this->generate_spec();
        $path = FCPATH . 'api/docs/' . $filename;
        
        // 디렉토리 생성
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        file_put_contents($path, $spec);
        return $path;
    }
}
```

#### API 문서 생성 컨트롤러

`source/application/controllers/Api_docs.php`:

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @OA\Info(
 *     title="Rejintech API",
 *     version="1.0.0",
 *     description="Rejintech 프로젝트 API 문서",
 *     @OA\Contact(
 *         email="admin@rejintech.com"
 *     )
 * )
 * @OA\Server(
 *     url="http://localhost",
 *     description="개발 서버"
 * )
 * @OA\Server(
 *     url="https://api.rejintech.com",
 *     description="프로덕션 서버"
 * )
 */
class Api_docs extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('swagger_lib');
    }
    
    /**
     * OpenAPI JSON 스펙 출력
     */
    public function openapi_json()
    {
        header('Content-Type: application/json');
        echo $this->swagger_lib->generate_spec();
    }
    
    /**
     * Swagger UI 페이지
     */
    public function index()
    {
        // Swagger UI HTML 파일 로드
        $swagger_ui_path = FCPATH . 'swagger-ui/index.html';
        
        if (file_exists($swagger_ui_path)) {
            readfile($swagger_ui_path);
        } else {
            show_404();
        }
    }
    
    /**
     * API 스펙 생성 (개발용)
     */
    public function generate()
    {
        if (ENVIRONMENT !== 'development') {
            show_404();
            return;
        }
        
        $path = $this->swagger_lib->save_spec();
        echo "OpenAPI 스펙이 생성되었습니다: " . $path;
    }
}
```

#### 라우팅 설정

`source/application/config/routes.php`에 추가:

```php
// API 문서 라우팅
$route['api/docs'] = 'api_docs/index';
$route['api/docs/openapi.json'] = 'api_docs/openapi_json';
$route['api/docs/generate'] = 'api_docs/generate';
```

### 5. API 컨트롤러 예제

`source/application/controllers/api/Users.php`:

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 사용자 API 컨트롤러
 */
class Users extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('user_model');
        $this->load->helper('url');
        
        // JSON 응답 헤더 설정
        header('Content-Type: application/json');
    }
    
    /**
     * @OA\Get(
     *     path="/api/users",
     *     summary="사용자 목록 조회",
     *     description="등록된 모든 사용자 목록을 반환합니다",
     *     tags={"Users"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="페이지 번호",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="페이지당 항목 수",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="성공",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(ref="#/components/schemas/User")
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="page", type="integer"),
     *                 @OA\Property(property="limit", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="서버 오류",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function index()
    {
        try {
            $page = $this->input->get('page') ?: 1;
            $limit = $this->input->get('limit') ?: 10;
            
            $users = $this->user_model->get_users($page, $limit);
            $total = $this->user_model->count_users();
            
            $response = [
                'status' => 'success',
                'data' => $users,
                'pagination' => [
                    'page' => (int)$page,
                    'limit' => (int)$limit,
                    'total' => (int)$total
                ]
            ];
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * @OA\Post(
     *     path="/api/users",
     *     summary="새 사용자 생성",
     *     description="새로운 사용자를 생성합니다",
     *     tags={"Users"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email"},
     *             @OA\Property(property="name", type="string", example="홍길동"),
     *             @OA\Property(property="email", type="string", format="email", example="hong@example.com"),
     *             @OA\Property(property="phone", type="string", example="010-1234-5678")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="생성 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/User"),
     *             @OA\Property(property="message", type="string", example="사용자가 성공적으로 생성되었습니다")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="잘못된 요청",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function create()
    {
        try {
            // JSON 입력 데이터 파싱
            $input = json_decode(file_get_contents('php://input'), true);
            
            // 유효성 검사
            $this->load->library('form_validation');
            $this->form_validation->set_data($input);
            $this->form_validation->set_rules('name', '이름', 'required|trim');
            $this->form_validation->set_rules('email', '이메일', 'required|valid_email|is_unique[users.email]');
            
            if (!$this->form_validation->run()) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => '유효성 검사 실패',
                    'errors' => $this->form_validation->error_array()
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            
            $user_id = $this->user_model->create_user($input);
            $user = $this->user_model->get_user($user_id);
            
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'data' => $user,
                'message' => '사용자가 성공적으로 생성되었습니다'
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="사용자",
 *     description="사용자 정보",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="홍길동"),
 *     @OA\Property(property="email", type="string", format="email", example="hong@example.com"),
 *     @OA\Property(property="phone", type="string", example="010-1234-5678"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01 12:00:00"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01 12:00:00")
 * )
 */
```

### 6. 사용자 모델 예제

`source/application/models/User_model.php`:

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model
{
    private $table = 'users';
    
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }
    
    public function get_users($page = 1, $limit = 10)
    {
        $offset = ($page - 1) * $limit;
        
        return $this->db
            ->select('id, name, email, phone, created_at, updated_at')
            ->from($this->table)
            ->limit($limit, $offset)
            ->order_by('created_at', 'DESC')
            ->get()
            ->result_array();
    }
    
    public function count_users()
    {
        return $this->db->count_all($this->table);
    }
    
    public function get_user($id)
    {
        return $this->db
            ->select('id, name, email, phone, created_at, updated_at')
            ->from($this->table)
            ->where('id', $id)
            ->get()
            ->row_array();
    }
    
    public function create_user($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }
}
```

## URL Rewriting 설정

### 현재 .htaccess 파일 상태

**중요**: `source/.htaccess` 파일이 이미 존재합니다.

```apache
<IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteBase /
        RewriteCond $1 !^(index\.php|images|captcha|data|include|uploads|tests|robots\.txt)
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ /index.php/$1 [L]
</IfModule>
```

### Swagger 지원을 위한 .htaccess 업데이트

현재 설정은 CodeIgniter API에 적합하지만, Swagger UI 정적 파일 접근을 위해 다음과 같이 개선할 수 있습니다:

```apache
<IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteBase /
        RewriteCond $1 !^(index\.php|images|captcha|data|include|uploads|tests|robots\.txt|swagger-ui|api/docs)
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ /index.php/$1 [L]
</IfModule>
```

**변경사항**: `swagger-ui|api/docs` 경로를 제외 조건에 추가

### Apache vs Nginx 환경

| 구분 | Apache | Nginx (현재) |
|------|--------|--------------|
| **설정 파일** | `.htaccess` | `nginx.conf` |
| **현재 사용** | ❌ (호환성 목적) | ✅ |
| **성능** | 요청시마다 읽기 | 시작시 로드 |
| **설정 위치** | 웹 루트 | 서버 설정 |

**현재 상황**: Nginx 환경이므로 .htaccess는 사용되지 않지만, Apache 호환성을 위해 유지됩니다.

## Nginx 설정 업데이트

`images/ubuntu/conf/nginx.conf`에 API 라우팅 추가:

```nginx
server {
    listen 80;
    root /var/www/html;
    index index.php index.html index.htm;
    server_name localhost;

    # API 경로 처리
    location /api/ {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # Swagger UI 정적 파일
    location /swagger-ui/ {
        try_files $uri $uri/ =404;
    }
    
    # CodeIgniter URL rewriting
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP 파일 처리
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }

    error_log  /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;
}
```

## 사용법

### 1. API 문서 접속

- **Swagger UI**: http://localhost/api/docs
- **OpenAPI JSON**: http://localhost/api/docs/openapi.json

### 2. API 테스트

- **사용자 목록**: GET http://localhost/api/users
- **사용자 생성**: POST http://localhost/api/users

### 3. 문서 업데이트

개발 환경에서 API 스펙 재생성:
```
http://localhost/api/docs/generate
```

## 추가 기능

### 1. 인증 설정

JWT 토큰 기반 인증:

```php
/**
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */

/**
 * @OA\Get(
 *     security={{"bearerAuth":{}}},
 *     // ... 나머지 설정
 * )
 */
```

### 2. 환경별 서버 설정

```php
/**
 * @OA\Server(
 *     url="http://localhost",
 *     description="개발 서버"
 * )
 * @OA\Server(
 *     url="https://staging.rejintech.com",
 *     description="스테이징 서버"
 * )
 * @OA\Server(
 *     url="https://api.rejintech.com",
 *     description="프로덕션 서버"
 * )
 */
```

## 문제 해결

### 1. Composer 오류
```bash
# PHP 8.1 호환성 확인
composer require zircote/swagger-php:^4.0 --ignore-platform-reqs
```

### 2. 권한 오류
```bash
chmod -R 755 /var/www/html/swagger-ui/
chmod -R 755 /var/www/html/api/docs/
```

### 3. JSON 생성 오류
- 어노테이션 문법 확인
- PHP 네임스페이스 설정 확인
- OpenAPI 3.0 스펙 준수 확인

---

**문서 생성일**: $(date)
**Swagger/OpenAPI 버전**: 3.0.3
**작성자**: AI Assistant 
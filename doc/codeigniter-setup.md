# CodeIgniter 설정 가이드

## 개요

이 문서는 Rejintech 프로젝트에 추가된 CodeIgniter 프레임워크 설정과 사용법을 설명합니다.

## CodeIgniter 정보

- **프레임워크**: CodeIgniter 3.x
- **라이선스**: MIT
- **PHP 요구사항**: PHP 5.2.4 이상 (현재 환경: PHP 8.1)
- **위치**: `/var/www/html` (source 디렉토리)

## 디렉토리 구조

```
source/
├── application/                    # 애플리케이션 파일들
│   ├── cache/                     # 캐시 파일
│   ├── config/                    # 설정 파일들
│   │   ├── config.php             # 메인 설정 파일
│   │   ├── database.php           # 데이터베이스 설정
│   │   ├── routes.php             # 라우팅 설정
│   │   └── autoload.php           # 자동로드 설정
│   ├── controllers/               # 컨트롤러 클래스들
│   ├── core/                      # 코어 확장 클래스들
│   ├── helpers/                   # 헬퍼 함수들
│   ├── hooks/                     # 훅 파일들
│   ├── language/                  # 언어 파일들
│   ├── libraries/                 # 사용자 정의 라이브러리
│   ├── logs/                      # 로그 파일들
│   ├── models/                    # 모델 클래스들
│   ├── third_party/              # 서드파티 라이브러리
│   └── views/                     # 뷰 파일들
├── system/                        # CodeIgniter 시스템 파일들
├── user_guide/                    # 사용자 가이드
├── index.php                      # 메인 진입점
├── composer.json                  # Composer 설정
└── .htaccess                      # Apache Rewrite 규칙
```

## 주요 설정 파일

### 1. 메인 설정 (application/config/config.php)

#### 기본 URL 설정
```php
$config['base_url'] = 'http://localhost/';
```

#### 인덱스 파일 제거 (URL Rewriting)
```php
$config['index_page'] = '';  // 빈 문자열로 설정
```

#### 문자셋 설정
```php
$config['charset'] = 'UTF-8';
```

#### 언어 설정
```php
$config['language'] = 'korean';  // 한국어 사용 시
```

### 2. 데이터베이스 설정 (application/config/database.php)

현재 설정을 MariaDB에 맞게 수정:

```php
$db['default'] = array(
    'dsn'       => '',
    'hostname'  => 'rejintech-mariadb',  // Docker 컨테이너명
    'username'  => 'jintech',
    'password'  => 'jin2010!!',
    'database'  => 'jintech',
    'dbdriver'  => 'mysqli',
    'dbprefix'  => '',
    'pconnect'  => FALSE,
    'db_debug'  => (ENVIRONMENT !== 'production'),
    'cache_on'  => FALSE,
    'cachedir'  => '',
    'char_set'  => 'utf8mb4',
    'dbcollat'  => 'utf8mb4_unicode_ci',
    'swap_pre'  => '',
    'encrypt'   => FALSE,
    'compress'  => FALSE,
    'stricton'  => FALSE,
    'failover'  => array(),
    'save_queries' => TRUE
);
```

### 3. 자동로드 설정 (application/config/autoload.php)

자주 사용하는 라이브러리들을 자동로드:

```php
$autoload['libraries'] = array('database', 'session');
$autoload['helper'] = array('url', 'form', 'html');
```

### 4. 추가 기본 설정 (application/config/config.php)

개발 환경에 맞는 추가 설정들:

```php
// Composer 자동로드 활성화
$config['composer_autoload'] = 'vendor/autoload.php';

// 로그 설정 (개발 환경)
$config['log_threshold'] = 4;  // 모든 로그 활성화

// 세션 설정
$config['sess_driver'] = 'files';
$config['sess_cookie_name'] = 'ci_session';
$config['sess_expiration'] = 7200;
$config['sess_save_path'] = APPPATH . 'cache/sessions/';
$config['sess_match_ip'] = FALSE;
$config['sess_time_to_update'] = 300;
$config['sess_regenerate_destroy'] = FALSE;

// CSRF 설정 (API 제외)
$config['csrf_protection'] = FALSE;
$config['csrf_exclude_uris'] = array('api/.*');  // API는 CSRF 검사 제외
```

### 5. 라우팅 설정 (application/config/routes.php)

기본 컨트롤러 및 API 라우팅 설정:

```php
$route['default_controller'] = 'welcome';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// API 라우팅
$route['api/test'] = 'api/test/index';
$route['api/test/database'] = 'api/test/database';
$route['api/test/params'] = 'api/test/params';
$route['api/test/echo'] = 'api/test/echo_post';

// Swagger 문서 라우팅
$route['api/docs'] = 'api_docs/index';
$route['api/docs/openapi.json'] = 'api_docs/openapi_json';
$route['api/docs/generate'] = 'api_docs/generate';

// Welcome 컨트롤러 라우팅
$route['database-test'] = 'welcome/database_test';

// 사용자 API 라우팅 (예제)
$route['api/users'] = 'api/users/index';
$route['api/users/(:num)'] = 'api/users/get/$1';
```

## URL Rewriting 설정

### 현재 .htaccess 파일 (source/.htaccess)

**현재 상태**: 이미 CodeIgniter용 .htaccess 파일이 존재합니다.

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

#### .htaccess 설정 설명

- **RewriteEngine On**: URL 재작성 활성화
- **RewriteBase /**: 기본 경로 설정
- **제외 조건**: `index.php`, `images`, `captcha`, `data`, `include`, `uploads`, `tests`, `robots.txt` 파일/폴더는 직접 접근 허용
- **파일 존재 확인**: 실제 파일이나 디렉토리가 없는 경우에만 재작성 적용
- **재작성 규칙**: 모든 요청을 `index.php`로 전달

#### Swagger UI를 위한 .htaccess 개선

현재 설정에 Swagger 관련 경로를 추가하려면:

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

### Apache .htaccess vs Nginx 설정

**⚠️ 중요**: 현재 프로젝트는 Nginx 환경이므로 .htaccess 파일은 직접 사용되지 않습니다.

| 환경 | 설정 파일 | 상태 |
|------|-----------|------|
| **Apache** | `.htaccess` | ✅ 준비됨 (호환성 목적) |
| **Nginx** | `nginx.conf` | ✅ 현재 사용중 |

### Nginx 설정 (현재 환경)

`images/ubuntu/conf/nginx.conf` 파일에 CodeIgniter용 설정 추가:

```nginx
server {
    listen 80;
    root /var/www/html;
    index index.php index.html index.htm;
    server_name localhost;

    # CodeIgniter URL rewriting
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Remove index.php from URLs
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }

    # Handle other PHP files
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

## 개발 환경 설정

### 1. 환경 변수 설정

`index.php` 파일에서 개발 환경 설정:

```php
define('ENVIRONMENT', 'development');
```

### 2. 오류 보고 설정

개발 환경에서는 모든 오류를 표시:

```php
// config.php
$config['log_threshold'] = 4;  // 모든 로그 기록
```

### 3. 세션 설정

`application/config/config.php`에서 세션 설정:

```php
$config['sess_driver'] = 'files';
$config['sess_cookie_name'] = 'ci_session';
$config['sess_expiration'] = 7200;
$config['sess_save_path'] = APPPATH . 'cache/sessions/';
$config['sess_regenerate_destroy'] = FALSE;
```

## 기본 사용법

### 1. 컨트롤러 생성

`application/controllers/Welcome.php`:

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Welcome extends CI_Controller {

    public function index()
    {
        $this->load->view('welcome_message');
    }
    
    public function test_db()
    {
        $this->load->database();
        
        if ($this->db->conn_id) {
            echo "데이터베이스 연결 성공!";
        } else {
            echo "데이터베이스 연결 실패!";
        }
    }
}
```

### 2. 모델 생성

`application/models/User_model.php`:

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }
    
    public function get_users()
    {
        $query = $this->db->get('users');
        return $query->result();
    }
    
    public function insert_user($data)
    {
        return $this->db->insert('users', $data);
    }
}
```

### 3. 뷰 생성

`application/views/welcome_message.php`:

```php
<!DOCTYPE html>
<html>
<head>
    <title>Rejintech - CodeIgniter</title>
    <meta charset="UTF-8">
</head>
<body>
    <h1>CodeIgniter가 성공적으로 설치되었습니다!</h1>
    <p>환영합니다. Rejintech 프로젝트에 오신 것을 환영합니다.</p>
</body>
</html>
```

## 데이터베이스 연결 테스트

### 테스트 컨트롤러 생성

`application/controllers/Database_test.php`:

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Database_test extends CI_Controller {

    public function index()
    {
        $this->load->database();
        
        // 연결 테스트
        if ($this->db->conn_id) {
            echo "<h2>데이터베이스 연결 성공!</h2>";
            
            // 테이블 목록 조회
            $tables = $this->db->list_tables();
            echo "<h3>사용 가능한 테이블:</h3>";
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li>" . $table . "</li>";
            }
            echo "</ul>";
            
        } else {
            echo "<h2>데이터베이스 연결 실패!</h2>";
        }
    }
}
```

접속 URL: `http://localhost/database_test`

## 유용한 팁

### 1. 개발 도구

#### 프로파일러 활성화
```php
$this->output->enable_profiler(TRUE);
```

#### 쿼리 디버깅
```php
$this->db->last_query();  // 마지막 실행 쿼리 확인
```

### 2. 보안 설정

#### XSS 필터링
```php
$this->load->helper('security');
$clean_data = $this->security->xss_clean($input_data);
```

#### CSRF 보호
```php
// config.php
$config['csrf_protection'] = TRUE;
$config['csrf_token_name'] = 'csrf_token';
$config['csrf_cookie_name'] = 'csrf_cookie';
```

### 3. 캐시 설정

#### 출력 캐시
```php
$this->output->cache(60);  // 60분 캐시
```

## 문제 해결

### 1. 404 오류
- URL rewriting 설정 확인
- .htaccess 파일 존재 확인
- base_url 설정 확인

### 2. 데이터베이스 연결 오류
- 호스트명 확인 (rejintech-mariadb)
- 사용자 계정 및 비밀번호 확인
- 포트 연결 상태 확인

### 3. 파일 권한 오류
```bash
# 캐시 디렉토리 권한 설정
chmod -R 777 application/cache/
chmod -R 777 application/logs/
```

## 다음 단계

1. 사용자 인증 시스템 구축
2. 관리자 패널 개발
3. RESTful API 구현
4. 다국어 지원 설정
5. 테스트 환경 구축

---

**문서 생성일**: $(date)
**CodeIgniter 버전**: 3.x
**작성자**: AI Assistant 
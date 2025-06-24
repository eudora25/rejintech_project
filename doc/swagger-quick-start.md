# Swagger 빠른 시작 가이드

## 개요

이 가이드는 Rejintech 프로젝트에서 Swagger를 빠르게 시작하는 방법을 설명합니다.

## 즉시 사용 가능한 설정

### 1. 기본 Swagger UI 접속

현재 다음 파일들이 준비되어 있습니다:

✅ `source/swagger-ui/index.html` - Swagger UI 인터페이스  
✅ `source/api/docs/openapi.json` - 기본 API 스펙  
✅ `source/application/controllers/api/Test.php` - 테스트 API  
✅ `source/.htaccess` - Apache URL rewriting (호환성 목적)  

### 2. 바로 확인하기

**1단계: 컨테이너 시작**
```bash
docker-compose up -d
```

**2단계: Swagger UI 접속**
```
브라우저에서 http://localhost/swagger-ui/ 접속
```

**3단계: API 테스트**
```
http://localhost/api/test - 기본 API 테스트
http://localhost/api/test/database - 데이터베이스 연결 테스트
```

## 현재 제공되는 API

### 1. 기본 테스트 API
- **GET** `/api/test` - 서버 상태 확인
- **GET** `/api/test/database` - 데이터베이스 연결 테스트
- **GET** `/api/test/params` - 파라미터 테스트
- **POST** `/api/test/echo` - POST 데이터 에코 테스트

### 2. 사용자 API (문서 예제)
- **GET** `/api/users` - 사용자 목록 조회
- **POST** `/api/users` - 새 사용자 생성
- **GET** `/api/users/{id}` - 사용자 상세 조회

## 테스트 방법

### Swagger UI에서 직접 테스트

1. **http://localhost/swagger-ui/** 접속
2. 원하는 API 엔드포인트 클릭
3. "Try it out" 버튼 클릭
4. 필요한 파라미터 입력
5. "Execute" 버튼 클릭

### 커맨드라인에서 테스트

```bash
# 기본 API 테스트
curl http://localhost/api/test

# 데이터베이스 연결 테스트
curl http://localhost/api/test/database

# 파라미터 테스트
curl "http://localhost/api/test/params?name=홍길동&age=30"

# POST 테스트
curl -X POST http://localhost/api/test/echo \
  -H "Content-Type: application/json" \
  -d '{"name": "홍길동", "message": "안녕하세요"}'
```

## 다음 단계

### 1. 고급 설정 (선택사항)

더 고급 기능을 원한다면:

**swagger-php 라이브러리 설치**
```bash
# 컨테이너 접속
docker exec -it rejintech-workspace bash

# Composer 업데이트
cd /var/www/html
composer require zircote/swagger-php:^4.0
```

### 2. 자동 문서 생성 활성화

고급 설정을 완료하면:
- PHP 어노테이션으로 API 문서 자동 생성
- 코드와 문서 자동 동기화
- 더 풍부한 API 스펙 정의

자세한 내용은 [Swagger API 문서화 가이드](./swagger-integration.md)를 참고하세요.

### 3. 실제 API 개발

```php
// 예: source/application/controllers/api/Users.php
class Users extends CI_Controller 
{
    public function index() 
    {
        // 사용자 목록 API 구현
        $users = $this->user_model->get_all_users();
        
        $response = [
            'status' => 'success',
            'data' => $users
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}
```

### 4. OpenAPI 스펙 업데이트

`source/api/docs/openapi.json` 파일을 수정하여:
- 새로운 API 엔드포인트 추가
- 스키마 정의 업데이트
- 응답 예제 추가

## 문제 해결

### 1. Swagger UI가 로드되지 않는 경우

**원인**: 정적 파일 접근 권한 문제
```bash
# 권한 설정
docker exec -it rejintech-workspace bash
chmod -R 755 /var/www/html/swagger-ui/
```

### 2. API가 응답하지 않는 경우

**확인사항**:
1. 컨테이너 상태: `docker-compose ps`
2. Nginx 로그: `docker exec -it rejintech-workspace tail -f /var/log/nginx/error.log`
3. URL 확인: `http://localhost/api/test` (정확한 경로)

### 3. CORS 오류

개발 환경에서는 이미 CORS가 설정되어 있습니다. 프로덕션에서는 필요에 따라 제한하세요.

## 웹서버 설정 정보

### 현재 환경 (Nginx)
현재 설정으로도 Swagger가 작동하지만, 더 나은 URL을 원한다면 nginx.conf를 업데이트하세요:

### .htaccess 파일 (Apache 호환성)
**현재 상태**: `source/.htaccess` 파일이 존재하며 CodeIgniter URL rewriting을 지원합니다.

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond $1 !^(index\.php|images|...|swagger-ui|api/docs)
    RewriteRule ^(.*)$ /index.php/$1 [L]
</IfModule>
```

**역할**: Apache 환경으로 이전시 URL rewriting 자동 지원

```nginx
# API 문서 경로
location /docs {
    alias /var/www/html/swagger-ui;
    index index.html;
}

# API 엔드포인트
location /api/ {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## 요약

✅ **현재 상태**: 기본 Swagger UI와 테스트 API가 준비됨  
✅ **접속**: http://localhost/swagger-ui/  
✅ **테스트**: http://localhost/api/test  
⚠️ **다음**: 실제 비즈니스 API 개발 및 문서화  

더 자세한 정보는 [전체 Swagger 통합 가이드](./swagger-integration.md)를 참고하세요.

---

**빠른 시작 완료!** 🎉  
이제 Swagger UI에서 API를 테스트하고 개발을 시작할 수 있습니다. 
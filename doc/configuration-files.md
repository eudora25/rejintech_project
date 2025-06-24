# 설정 파일 상세 가이드

## 개요

이 문서는 Rejintech 프로젝트의 각 설정 파일에 대한 상세한 설명과 커스터마이징 방법을 안내합니다.

## Docker Compose 설정 (docker-compose.yml)

### 현재 설정

```yaml
version: '3.9'
services:
    ubuntu:
        container_name: rejintech-workspace
        build:
            context: ./images/ubuntu
            dockerfile: Dockerfile
        stdin_open: true
        tty: true
        volumes:
            - ./source:/var/www/html
            - ./images/ubuntu/conf/nginx.conf:/etc/nginx/conf.d/default.conf
        ports:
            - "80:80"
            - "443:443"
    db:
        image: mariadb:latest
        container_name: rejintech-mariadb
        restart: always
        environment:
            MYSQL_DATABASE: jintech
            MYSQL_USER: jintech
            MYSQL_PASSWORD: jin2010!!
            MYSQL_ROOT_PASSWORD: A77ila@
        volumes:
            - ./mariadb_data:/var/lib/mysql    
        ports:
            - "3306:3306"
```

### 설정 설명

- **version**: Docker Compose 파일 버전 (3.9)
- **services**: 두 개의 주요 서비스 정의
  - `ubuntu`: 웹 서버 컨테이너
  - `db`: MariaDB 데이터베이스 컨테이너

### 커스터마이징 옵션

1. **포트 변경**: 충돌 방지를 위해 포트 변경 가능
2. **환경 변수**: 데이터베이스 설정 변경
3. **볼륨 추가**: 추가 디렉토리 마운트

## Dockerfile 설정 (images/ubuntu/Dockerfile)

### 주요 구성 요소

```dockerfile
FROM ubuntu:20.04
```
- **베이스 이미지**: Ubuntu 20.04 LTS

### 패키지 설치

1. **기본 패키지**:
   - `software-properties-common`: PPA 저장소 관리
   - `nginx`: 웹 서버
   - `supervisor`: 프로세스 관리
   - `curl`, `vim`: 유틸리티

2. **PHP 8.1 및 확장 모듈**:
   - `php8.1-fpm`: FastCGI Process Manager
   - `php8.1-mysql`: MySQL/MariaDB 연결
   - `php8.1-gd`: 이미지 처리
   - `php8.1-curl`: HTTP 클라이언트
   - `php8.1-mbstring`: 멀티바이트 문자열 처리
   - `php8.1-zip`: ZIP 파일 처리
   - `php8.1-redis`: Redis 연결
   - `php8.1-memcache`: Memcache 연결

3. **Composer**: PHP 의존성 관리

### 포트 노출

- **80**: HTTP
- **443**: HTTPS

## Nginx 설정 (images/ubuntu/conf/nginx.conf)

### 현재 설정

```nginx
server {
    listen 80;
    root /var/www/html;
    index index.html index.htm index.php;
    server_name localhost;
    
    error_log  /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;
    
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }
}
```

### 설정 설명

- **listen 80**: HTTP 포트 리스닝
- **root**: 웹 루트 디렉토리
- **index**: 기본 인덱스 파일
- **server_name**: 서버 이름
- **location ~ \.php$**: PHP 파일 처리 규칙

### 커스터마이징 예시

#### HTTPS 설정 추가

```nginx
server {
    listen 443 ssl;
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    # ... 기타 설정
}
```

#### 정적 파일 캐싱

```nginx
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

## Supervisor 설정 (images/ubuntu/conf/supervisord.conf)

### 현재 설정

```ini
[supervisord]
user = root
nodaemon=true
logfile=/dev/null
logfile_maxbytes=0
pidfile=/run/supervisord.pid

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:php-fpm]
command=/bin/bash -c "mkdir -p /var/run/php && php-fpm8.1 --nodaemonize --fpm-config /etc/php/8.1/fpm/php-fpm.conf"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

### 설정 설명

- **supervisord**: 메인 프로세스 설정
- **program:nginx**: Nginx 웹 서버 프로세스
- **program:php-fpm**: PHP-FPM 프로세스

### 추가 프로세스 설정 예시

#### Cron 추가

```ini
[program:cron]
command=cron -f
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

#### Queue Worker 추가

```ini
[program:queue-worker]
command=php /var/www/html/artisan queue:work
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

## .htaccess 설정 (source/.htaccess)

### 현재 설정

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

### 설정 설명

이 .htaccess 파일은 Apache 웹서버용 CodeIgniter URL rewriting 규칙입니다.

#### 주요 구성 요소

- **RewriteEngine On**: URL 재작성 기능 활성화
- **RewriteBase /**: 재작성 규칙의 기본 경로 설정
- **RewriteCond**: 재작성 조건 설정
  - `$1 !^(index\.php|images|captcha|data|include|uploads|tests|robots\.txt)`: 특정 파일/폴더는 제외
  - `%{REQUEST_FILENAME} !-f`: 실제 파일이 존재하지 않는 경우
  - `%{REQUEST_FILENAME} !-d`: 실제 디렉토리가 존재하지 않는 경우
- **RewriteRule**: 모든 요청을 index.php로 전달

### 현재 환경에서의 역할

**⚠️ 중요**: 현재 프로젝트는 **Nginx** 환경에서 실행되므로 이 .htaccess 파일은 **직접적으로 사용되지 않습니다**.

#### Nginx vs Apache 차이점

| 기능 | Apache (.htaccess) | Nginx (nginx.conf) |
|------|-------------------|-------------------|
| **설정 파일** | `.htaccess` | `nginx.conf` |
| **적용 방식** | 디렉토리별 자동 적용 | 서버 설정에서 직접 설정 |
| **성능** | 요청시마다 파일 읽기 | 서버 시작시 한번 로드 |
| **현재 사용** | ❌ 미사용 | ✅ 사용중 |

#### 현재 Nginx 설정 (실제 사용중)

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    try_files $uri =404;
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param PATH_INFO $fastcgi_path_info;
}
```

### .htaccess 파일을 유지하는 이유

1. **호환성**: Apache 환경으로 이전할 가능성 대비
2. **개발 환경**: 로컬에서 Apache 사용시 필요
3. **배포 유연성**: 다양한 호스팅 환경 지원
4. **CodeIgniter 표준**: 프레임워크 표준 구성

### 추가 .htaccess 설정 옵션

#### 보안 강화

```apache
# PHP 설정 보호
<Files "*.php">
    Order Deny,Allow
    Deny from all
</Files>

<Files "index.php">
    Order Allow,Deny
    Allow from all
</Files>

# 민감한 파일 접근 차단
<Files ~ "^(\.htaccess|\.gitignore|composer\.json|composer\.lock)$">
    Order Allow,Deny
    Deny from all
</Files>
```

#### 캐싱 설정

```apache
# 정적 파일 캐싱
<FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg)$">
    ExpiresActive On
    ExpiresDefault "access plus 1 year"
    Header append Cache-Control "public"
</FilesMatch>
```

#### HTTPS 강제 리디렉션

```apache
# HTTPS 강제 전환 (프로덕션 환경)
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### API 경로 최적화

현재 .htaccess 설정으로 다음 URL들이 정상 작동합니다:

| 요청 URL | 실제 처리 |
|----------|-----------|
| `/api/test` | `/index.php/api/test` |
| `/api/users` | `/index.php/api/users` |
| `/swagger-ui/` | 정적 파일 직접 서빙 |

#### Swagger와 API 경로 고려사항

```apache
# Swagger UI 정적 파일 제외
RewriteCond $1 !^(index\.php|images|captcha|data|include|uploads|tests|robots\.txt|swagger-ui|api/docs)
```

### 문제 해결

#### 1. URL 라우팅 오류

**증상**: `/api/test` 접근시 404 오류
**해결**: 
- Nginx 환경에서는 nginx.conf 설정 확인
- Apache 환경에서는 mod_rewrite 모듈 활성화 확인

#### 2. 정적 파일 접근 불가

**증상**: CSS, JS 파일 로드 실패
**해결**:
```apache
# .htaccess에 제외 경로 추가
RewriteCond $1 !^(index\.php|assets|css|js|images|swagger-ui)
```

#### 3. API JSON 응답 오류

**증상**: API 응답이 HTML로 반환
**해결**: 컨트롤러에서 Content-Type 헤더 설정 확인

### 환경별 추천 설정

#### 개발 환경 (.htaccess)
```apache
# 오류 표시 활성화
php_flag display_errors On
php_value error_reporting "E_ALL"

# 개발용 캐시 비활성화
<FilesMatch "\.(css|js)$">
    ExpiresActive Off
    Header set Cache-Control "no-cache, no-store, must-revalidate"
</FilesMatch>
```

#### 프로덕션 환경 (.htaccess)
```apache
# 오류 표시 비활성화
php_flag display_errors Off
php_value error_reporting 0

# 보안 헤더
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

## 보안 권장사항

### 1. 비밀번호 보안

현재 설정에서 하드코딩된 비밀번호들:
- MySQL 사용자 비밀번호: `jin2010!!`
- MySQL Root 비밀번호: `A77ila@`

**권장사항**: 환경 변수 파일 (.env) 사용

```yaml
# docker-compose.yml
environment:
  MYSQL_PASSWORD: ${DB_PASSWORD}
  MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
```

### 2. 네트워크 보안

포트 노출 최소화:
```yaml
# 외부 접근이 필요없는 경우
# ports:
#   - "3306:3306"  # 이 라인 제거
```

### 3. 파일 권한

적절한 파일 권한 설정:
```bash
chmod 644 docker-compose.yml
chmod 600 .env  # 환경 변수 파일
```

## 성능 튜닝

### PHP-FPM 설정

`/etc/php/8.1/fpm/pool.d/www.conf` 파일 수정:
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
```

### Nginx 설정

버퍼 크기 조정:
```nginx
client_max_body_size 100M;
fastcgi_buffer_size 128k;
fastcgi_buffers 4 256k;
fastcgi_busy_buffers_size 256k;
```

## 로그 관리

### 로그 파일 위치

- **Nginx**: `/var/log/nginx/`
- **PHP-FPM**: `/var/log/php8.1-fpm.log`
- **MariaDB**: 컨테이너 로그 확인

### 로그 로테이션

`logrotate` 설정으로 로그 파일 크기 관리:
```bash
/var/log/nginx/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
}
```

---

**문서 업데이트일**: $(date)
**작성자**: AI Assistant 
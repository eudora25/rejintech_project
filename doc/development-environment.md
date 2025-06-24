# Rejintech 프로젝트 개발 환경 문서

## 개요

이 문서는 Rejintech 프로젝트의 개발 환경 구성과 실행 방법을 설명합니다.

## 시스템 아키텍처

본 프로젝트는 Docker 기반으로 구성된 웹 개발 환경입니다.

### 서비스 구성

#### 1. 웹 서버 컨테이너 (ubuntu)
- **컨테이너명**: `rejintech-workspace`
- **베이스 이미지**: Ubuntu 20.04
- **웹 서버**: Nginx
- **PHP 버전**: PHP 8.1 (PHP-FPM)
- **프레임워크**: CodeIgniter 3.x
- **프로세스 관리**: Supervisor

#### 2. 데이터베이스 컨테이너 (db)
- **컨테이너명**: `rejintech-mariadb`
- **이미지**: MariaDB (latest)
- **데이터베이스명**: `jintech`
- **사용자명**: `jintech`

## 포트 설정

| 서비스 | 포트 | 설명 |
|--------|------|------|
| Nginx | 80 | HTTP 웹 서버 |
| Nginx | 443 | HTTPS 웹 서버 |
| MariaDB | 3306 | 데이터베이스 서버 |

## 디렉토리 구조

```
rejintech_project/
├── docker-compose.yml          # Docker Compose 설정
├── images/
│   └── ubuntu/
│       ├── Dockerfile          # Ubuntu 컨테이너 빌드 설정
│       └── conf/
│           ├── nginx.conf      # Nginx 웹 서버 설정
│           └── supervisord.conf # Supervisor 프로세스 관리 설정
├── mariadb_data/              # MariaDB 데이터 저장소
├── source/                    # CodeIgniter 웹 애플리케이션
│   ├── .htaccess             # Apache URL rewriting (호환성)
│   ├── application/          # CodeIgniter 애플리케이션 파일
│   ├── system/              # CodeIgniter 시스템 파일
│   ├── swagger-ui/          # Swagger UI 인터페이스
│   ├── api/docs/            # API 스펙 문서
│   ├── index.php            # 메인 진입점
│   └── composer.json        # Composer 의존성 관리
└── doc/                       # 프로젝트 문서
```

## 개발 환경 설정

### PHP 8.1 확장 모듈

다음과 같은 PHP 확장 모듈이 설치되어 있습니다:
- php8.1-fpm
- php8.1-mysql (MySQL/MariaDB 연결)
- php8.1-curl
- php8.1-gd (이미지 처리)
- php8.1-mbstring (다국어 문자열 처리)
- php8.1-xml
- php8.1-zip
- php8.1-redis
- php8.1-memcache
- 기타 개발에 필요한 확장 모듈들

### Composer

PHP 의존성 관리를 위한 Composer가 설치되어 있습니다.

## 실행 방법

### 개발 환경 시작

```bash
docker-compose up -d
```

### 개발 환경 중지

```bash
docker-compose down
```

### 컨테이너 상태 확인

```bash
docker-compose ps
```

### 웹 서버 컨테이너 접속

```bash
docker exec -it rejintech-workspace bash
```

### 데이터베이스 컨테이너 접속

```bash
docker exec -it rejintech-mariadb mysql -u jintech -p
```

## 볼륨 마운트

- `./source` → `/var/www/html`: 웹 애플리케이션 소스 코드
- `./images/ubuntu/conf/nginx.conf` → `/etc/nginx/conf.d/default.conf`: Nginx 설정
- `./mariadb_data` → `/var/lib/mysql`: MariaDB 데이터 영구 저장

## 데이터베이스 정보

- **호스트**: localhost (또는 컨테이너 이름: rejintech-mariadb)
- **포트**: 3306
- **데이터베이스**: jintech
- **사용자명**: jintech
- **비밀번호**: jin2010!!
- **Root 비밀번호**: A77ila@

⚠️ **보안 주의사항**: 프로덕션 환경에서는 반드시 비밀번호를 변경하세요.

## 웹 애플리케이션 접속

브라우저에서 다음 URL로 접속할 수 있습니다:
- **메인 페이지**: http://localhost
- **CodeIgniter 환영 페이지**: http://localhost/index.php
- **Swagger API 문서**: http://localhost/swagger-ui/
- **API 테스트**: http://localhost/api/test
- **데이터베이스 연결 테스트**: http://localhost/api/test/database
- HTTPS: https://localhost (설정 필요)

## 로그 파일

- **Nginx 오류 로그**: `/var/log/nginx/error.log`
- **Nginx 접근 로그**: `/var/log/nginx/access.log`
- **Supervisor 로그**: stdout/stderr로 출력

## 다음 단계

1. **CodeIgniter 설정 완료**: 
   - 데이터베이스 연결 설정 (application/config/database.php)
   - 기본 URL 설정 (application/config/config.php)
   - URL Rewriting 활성화

2. **개발 진행**:
   - 컨트롤러, 모델, 뷰 개발
   - 사용자 인증 시스템 구축
   - 관리자 패널 개발

3. **추가 설정**:
   - SSL 인증서 설정 (HTTPS 사용 시)
   - 추가 서비스 설정 (Redis, Elasticsearch 등)
   - 프로덕션 환경 최적화

### CodeIgniter 관련 문서
- [CodeIgniter 설정 가이드](./codeigniter-setup.md) 참조

## 트러블슈팅

### 컨테이너가 시작되지 않는 경우
1. 포트 충돌 확인 (80, 443, 3306 포트)
2. Docker 및 Docker Compose 버전 확인
3. 로그 확인: `docker-compose logs`

### 데이터베이스 연결 오류
1. MariaDB 컨테이너 상태 확인
2. 데이터베이스 자격 증명 확인
3. 네트워크 연결 상태 확인

---

**문서 생성일**: $(date)
**작성자**: AI Assistant 
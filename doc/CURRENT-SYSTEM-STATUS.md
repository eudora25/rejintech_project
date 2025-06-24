# 현재 시스템 상태 문서

**📅 작성일**: 2025-06-23  
**📊 문서 버전**: v2.0  
**🎯 시스템 상태**: 완전 구축 완료

## 📋 시스템 개요

Rejintech 프로젝트는 Docker 기반의 현대적인 웹 애플리케이션으로, JWT 기반 인증 시스템, 로그인 로그 관리, 토큰 기반 세션 관리, 조달청 데이터 동기화 배치 시스템 등을 포함한 완전한 솔루션입니다.

### 🏗️ 기술 스택
- **컨테이너**: Docker & Docker Compose
- **웹 서버**: Nginx 1.18.0 + PHP 8.1-FPM
- **프레임워크**: CodeIgniter 3.x
- **데이터베이스**: MariaDB 10.5+
- **API 문서**: Swagger UI 4.15.5
- **인증**: JWT (Firebase PHP-JWT)
- **프로세스 관리**: Supervisor

---

## 🗄️ 데이터베이스 구조

### 현재 데이터베이스 상태
- **데이터베이스명**: `jintech`
- **총 테이블 수**: 6개
- **마지막 업데이트**: 2025-06-23 07:39:16

### 1. 사용자 관련 테이블

#### `users` 테이블
```sql
CREATE TABLE users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```
- **데이터 수**: 2개 (admin, testuser)
- **인덱스**: PRIMARY, username(UNIQUE), email(UNIQUE)

#### `user_tokens` 테이블 ⭐ **새로 추가**
```sql
CREATE TABLE user_tokens (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    token_type ENUM('access', 'refresh') NOT NULL DEFAULT 'access',
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    issued_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_token_hash (token_hash),
    INDEX idx_token_type (token_type),
    INDEX idx_is_active (is_active),
    INDEX idx_expires_at (expires_at),
    INDEX idx_issued_at (issued_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```
- **데이터 수**: 6개 (테스트 토큰들, 모두 무효화됨)
- **목적**: JWT 토큰 DB 저장 및 검증 시스템

#### `login_logs` 테이블 ⭐ **새로 추가**
```sql
CREATE TABLE login_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100),
    user_id INT(11),
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    login_status ENUM('success', 'failed') NOT NULL,
    failure_reason VARCHAR(255),
    request_time DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_user_id (user_id),
    INDEX idx_ip_address (ip_address),
    INDEX idx_login_status (login_status),
    INDEX idx_request_time (request_time)
);
```
- **데이터 수**: 19개 (실패 9개, 성공 10개)
- **목적**: 로그인 시도 이력 및 보안 감사

### 2. 조달청 배치 시스템 테이블

#### `batch_logs` 테이블
```sql
CREATE TABLE batch_logs (
    id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
    batch_name VARCHAR(100) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    status ENUM('RUNNING','SUCCESS','FAILED') NOT NULL,
    total_count INT(11),
    success_count INT(11),
    error_count INT(11),
    error_message TEXT,
    api_call_count INT(11),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_batch_name (batch_name),
    INDEX idx_start_time (start_time),
    INDEX idx_status (status)
);
```
- **데이터 수**: 2개 (배치 실행 이력)

#### `api_call_history` 테이블
```sql
CREATE TABLE api_call_history (
    id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
    api_name VARCHAR(100) NOT NULL,
    api_url VARCHAR(500) NOT NULL,
    request_params TEXT,
    response_code INT(11),
    response_data LONGTEXT,
    call_time DATETIME NOT NULL,
    response_time INT(11),
    batch_log_id BIGINT(20),
    status ENUM('SUCCESS','FAILED') NOT NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_api_name (api_name),
    INDEX idx_call_time (call_time),
    INDEX idx_batch_log_id (batch_log_id),
    
    FOREIGN KEY (batch_log_id) REFERENCES batch_logs(id)
);
```
- **데이터 수**: 20개 (API 호출 이력)

#### `delivery_request_details` 테이블
```sql
CREATE TABLE delivery_request_details (
    id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
    dlvr_req_no VARCHAR(50) NOT NULL,
    dlvr_req_dtl_seq INT(11) NOT NULL,
    -- ... (52개 필드, 조달청 API 데이터 구조 반영)
    data_sync_dt DATETIME,
    api_response_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_dlvr_req_dtl (dlvr_req_no, dlvr_req_dtl_seq),
    INDEX idx_dlvr_req_no (dlvr_req_no),
    INDEX idx_dlvr_req_dt (dlvr_req_dt),
    INDEX idx_supplier_cd (supplier_cd),
    INDEX idx_buyer_cd (buyer_cd),
    INDEX idx_data_sync_dt (data_sync_dt)
);
```
- **데이터 수**: 992개 (조달청 납품요청 상세 데이터)

---

## 🔐 인증 및 보안 시스템

### JWT 토큰 시스템

#### 1. 토큰 발급 (로그인)
```php
POST /api/auth/login
Content-Type: application/json

{
    "username": "admin",
    "password": "admin123"
}
```

**응답:**
```json
{
    "success": true,
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "user": {
        "id": "1",
        "username": "admin",
        "email": "admin@example.com"
    },
    "message": "로그인 성공",
    "expires_in": 3600
}
```

#### 2. 토큰 검증 및 DB 저장 시스템 ⭐ **새로 구현**
- **로그인 시**: JWT 토큰 생성 → DB에 해시값으로 저장
- **API 요청 시**: JWT 검증 + DB 토큰 존재 여부 확인
- **보안 강화**: 이중 검증 시스템으로 토큰 탈취 방지

#### 3. 로그인 상태 확인 API ⭐ **새로 구현**
```php
POST /api/auth/check-login
Authorization: Bearer {token}
```

**응답:**
```json
{
    "success": true,
    "is_logged_in": true,
    "user": {
        "id": "1",
        "username": "admin",
        "email": "admin@example.com"
    },
    "token_info": {
        "issued_at": "2025-06-23 07:00:00",
        "expires_at": "2025-06-23 08:00:00",
        "last_used_at": "2025-06-23 07:30:00",
        "ip_address": "192.168.1.100"
    },
    "message": "로그인 상태 확인됨"
}
```

#### 4. 로그아웃 API ⭐ **새로 구현**
```php
POST /api/auth/logout
Authorization: Bearer {token}
Content-Type: application/json

# 현재 토큰만 무효화
{}

# 모든 디바이스에서 로그아웃
{"logout_all": true}
```

### 로그인 로그 시스템 ⭐ **새로 구현**

#### 자동 로그 저장
- **모든 로그인 시도** 자동 기록
- **성공/실패 상태** 구분 저장
- **IP 주소, User-Agent** 정보 수집
- **실패 사유** 상세 기록

#### 로그인 로그 조회 API
```php
GET /api/auth/login-logs?user_id=1&limit=10&offset=0
Authorization: Bearer {token}
```

#### 로그인 통계 API
```php
GET /api/auth/login-statistics?date_from=2025-06-01&date_to=2025-06-23
Authorization: Bearer {token}
```

---

## 🌐 API 엔드포인트

### 인증 관련 API
| 메서드 | 엔드포인트 | 설명 | 상태 |
|--------|------------|------|------|
| POST | `/api/auth/login` | 사용자 로그인 | ✅ 구현완료 |
| POST | `/api/auth/verify` | JWT 토큰 검증 | ✅ 구현완료 |
| GET | `/api/auth/profile` | 사용자 프로필 조회 | ✅ 구현완료 |
| POST | `/api/auth/check-login` | 로그인 상태 확인 | ⭐ **신규 구현** |
| POST | `/api/auth/logout` | 로그아웃 | ⭐ **신규 구현** |
| GET | `/api/auth/login-logs` | 로그인 로그 조회 | ⭐ **신규 구현** |
| GET | `/api/auth/login-statistics` | 로그인 통계 조회 | ⭐ **신규 구현** |

### 테스트 및 기타 API
| 메서드 | 엔드포인트 | 설명 | 상태 |
|--------|------------|------|------|
| GET | `/api/test` | 서버 상태 확인 | ✅ 구현완료 |
| GET | `/api/test/database` | 데이터베이스 연결 테스트 | ✅ 구현완료 |
| GET | `/api/docs/openapi.json` | OpenAPI 3.0 스펙 | ✅ 구현완료 |

---

## 📊 Swagger UI 통합

### 완전한 API 문서화 ⭐ **업데이트됨**
- **URL**: http://localhost/swagger-ui/
- **OpenAPI 3.0 스펙** 기반
- **실시간 API 테스트** 가능
- **JWT 인증** 완전 지원

### 새로 추가된 Swagger 기능
1. **로그인 상태 확인 API** 문서화
2. **로그아웃 API** 문서화 (개별/전체 로그아웃 지원)
3. **인터랙티브 테스트** 환경
4. **캐시 버스팅** 기능으로 항상 최신 스펙 로드

### Swagger UI 인증 플로우
1. **로그인**: POST /api/auth/login으로 토큰 발급
2. **Authorize**: 상단 자물쇠 버튼으로 Bearer 토큰 설정
3. **API 테스트**: 모든 보호된 엔드포인트 테스트 가능
4. **로그아웃**: POST /api/auth/logout으로 토큰 무효화

---

## 🔄 배치 시스템

### 조달청 데이터 동기화
- **배치명**: `procurement_delivery_sync`
- **실행 주기**: 매일 자정 (crontab 설정됨)
- **데이터 소스**: 조달청 나라장터 공공데이터 API
- **마지막 실행**: 2025-06-23 00:31:00 (SUCCESS)
- **동기화된 데이터**: 992건

### 배치 실행 방법
```bash
# 수동 실행
cd /var/www/html && php index.php procurement_sync sync_delivery_requests

# crontab 설정 (이미 구성됨)
0 0 * * * cd /var/www/html && php index.php procurement_sync sync_delivery_requests >/dev/null 2>&1
```

---

## 📁 프로젝트 구조

```
rejintech_project/
├── docker-compose.yml              # Docker 서비스 정의
├── images/ubuntu/                   # Docker 이미지 설정
│   ├── conf/nginx.conf             # Nginx 설정
│   └── Dockerfile                  # Ubuntu 이미지 설정
├── source/                         # CodeIgniter 애플리케이션
│   ├── application/
│   │   ├── controllers/
│   │   │   ├── api/
│   │   │   │   ├── Auth.php        # ⭐ JWT 인증 + 로그인/로그아웃 API
│   │   │   │   └── Test.php        # API 테스트 컨트롤러
│   │   │   └── batch/
│   │   │       └── Procurement_sync.php # 조달청 배치
│   │   ├── models/
│   │   │   ├── User_model.php      # 사용자 모델
│   │   │   ├── Login_log_model.php # ⭐ 로그인 로그 모델
│   │   │   └── User_token_model.php # ⭐ 토큰 관리 모델
│   │   ├── config/
│   │   │   └── routes.php          # ⭐ 신규 API 라우팅 추가
│   │   └── libraries/
│   │       └── Procurement_api.php # 조달청 API 라이브러리
│   ├── swagger-ui/
│   │   └── index.html              # ⭐ 캐시 버스팅 기능 추가
│   └── api/docs/
│       └── openapi.json            # ⭐ 로그아웃 API 스펙 추가
├── mariadb_data/                   # 데이터베이스 영구 저장소
└── doc/                            # 프로젝트 문서
    ├── README.md                   # ⭐ 신규 API 정보 추가
    ├── login-logs-guide.md         # ⭐ 로그인 로그 가이드
    ├── token-system-guide.md       # ⭐ 토큰 시스템 가이드
    └── CURRENT-SYSTEM-STATUS.md    # ⭐ 이 문서
```

---

## 🛠️ 새로 구현된 핵심 기능

### 1. 토큰 기반 세션 관리 시스템
- **JWT + DB 이중 검증**: 보안성 극대화
- **다중 디바이스 지원**: 여러 기기에서 동시 로그인
- **토큰 무효화**: 개별/전체 로그아웃 지원
- **자동 만료 관리**: 만료된 토큰 자동 정리

### 2. 종합 로그인 모니터링 시스템
- **실시간 로그 수집**: 모든 로그인 시도 기록
- **보안 감사**: IP 추적, 실패 패턴 분석
- **통계 분석**: 일별/사용자별 로그인 통계
- **API 기반 조회**: 프로그래밍 방식 데이터 접근

### 3. Swagger UI 완전 통합
- **실시간 API 테스트**: 브라우저에서 직접 테스트
- **JWT 인증 지원**: 원클릭 인증 설정
- **최신 스펙 자동 로드**: 캐시 문제 해결
- **한국어 문서화**: 완전한 한국어 API 문서

---

## 📈 시스템 성능 및 최적화

### 데이터베이스 최적화
- **인덱스 최적화**: 모든 주요 쿼리에 인덱스 적용
- **외래키 제약**: 데이터 무결성 보장
- **파티셔닝 준비**: 대용량 데이터 처리 준비
- **쿼리 최적화**: N+1 문제 방지

### 보안 강화
- **암호화 저장**: 모든 패스워드 bcrypt 해시
- **토큰 해시**: JWT 토큰 SHA-256 해시 저장
- **SQL 인젝션 방지**: PDO 기반 쿼리
- **CORS 설정**: 크로스 도메인 보안

---

## 🎯 서비스 URL

### 메인 서비스
- **웹 애플리케이션**: http://localhost/
- **Swagger UI**: http://localhost/swagger-ui/
- **API 베이스**: http://localhost/api/

### 데이터베이스
- **호스트**: localhost:3306
- **데이터베이스**: jintech
- **사용자**: jintech / jin2010!!

---

## 📚 관련 문서

### 기술 문서
1. **[README.md](README.md)** - 프로젝트 개요 및 빠른 시작
2. **[PROJECT-SUMMARY.md](PROJECT-SUMMARY.md)** - 전체 작업 요약
3. **[development-environment.md](development-environment.md)** - 개발 환경 가이드

### 기능별 가이드
4. **[login-logs-guide.md](login-logs-guide.md)** - 로그인 로그 시스템
5. **[token-system-guide.md](token-system-guide.md)** - 토큰 관리 시스템
6. **[batch-execution-guide.md](batch-execution-guide.md)** - 배치 시스템

### API 문서
7. **[swagger-integration.md](swagger-integration.md)** - Swagger 통합
8. **[swagger-quick-start.md](swagger-quick-start.md)** - Swagger 빠른 시작

---

## 🎊 현재 상태 요약

### ✅ 완전 구현된 기능
- [x] Docker 기반 개발 환경
- [x] CodeIgniter 3.x 프레임워크
- [x] MariaDB 데이터베이스 (6개 테이블)
- [x] JWT 기반 인증 시스템
- [x] 토큰 DB 저장 및 검증 시스템
- [x] 로그인 로그 모니터링 시스템
- [x] 로그인 상태 확인 API
- [x] 로그아웃 API (개별/전체)
- [x] Swagger UI 완전 통합
- [x] 조달청 데이터 동기화 배치
- [x] 크론탭 자동 실행 설정

### 📊 시스템 데이터 현황
- **사용자**: 2명 (admin, testuser)
- **로그인 로그**: 19건 (성공 10건, 실패 9건)
- **토큰 이력**: 6건 (모두 무효화됨)
- **배치 실행**: 2회 (성공 2회)
- **API 호출**: 20건 (모두 성공)
- **조달청 데이터**: 992건 (동기화 완료)

### 🚀 즉시 사용 가능
- **개발 환경**: `docker-compose up -d`로 즉시 시작
- **API 테스트**: Swagger UI에서 실시간 테스트
- **인증 시스템**: 완전한 JWT 기반 보안
- **데이터 분석**: 로그인 패턴 및 통계 분석
- **배치 시스템**: 자동 데이터 동기화

---

**📝 문서 작성**: 2025-06-23  
**🔄 마지막 업데이트**: 시스템 전체 기능 구현 완료  
**👨‍💻 개발 상태**: Production Ready ✨ 
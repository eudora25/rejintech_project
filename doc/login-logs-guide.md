ㅁ# 로그인 로그 기능 가이드

## 개요

Rejintech 프로젝트에는 사용자의 로그인 활동을 추적하고 분석할 수 있는 종합적인 로그인 로그 시스템이 구현되어 있습니다. 이 시스템은 보안 모니터링, 사용자 행동 분석, 그리고 시스템 접근 이력 관리에 도움이 됩니다.

## 주요 기능

### 1. 자동 로그 수집
- **성공/실패 로그인 추적**: 모든 로그인 시도가 자동으로 기록됩니다
- **클라이언트 정보 수집**: IP 주소, User-Agent 등 클라이언트 환경 정보를 수집합니다
- **실시간 저장**: 로그인 시도와 동시에 데이터베이스에 저장됩니다

### 2. 상세한 로그 정보
각 로그 항목에는 다음 정보가 포함됩니다:

- `id`: 로그 고유 ID
- `username`: 로그인 시도 사용자명
- `user_id`: 성공시 사용자 ID (실패시 NULL)
- `ip_address`: 클라이언트 IP 주소 (IPv4/IPv6 지원)
- `user_agent`: 브라우저/클라이언트 정보
- `login_status`: 로그인 상태 ('success' 또는 'failed')
- `failure_reason`: 실패 사유 (성공시 NULL)
- `request_time`: 로그인 시도 시간
- `created_at`: 로그 생성 시간

### 3. API 엔드포인트

#### 로그인 로그 조회
```
GET /api/auth/login-logs
```

**파라미터:**
- `user_id` (선택): 조회할 사용자 ID (기본값: 현재 사용자)
- `limit` (선택): 조회 개수 제한 (기본값: 50, 최대: 100)
- `offset` (선택): 조회 시작 위치 (기본값: 0)

**인증:** Bearer 토큰 필요

**응답 예시:**
```json
{
    "success": true,
    "logs": [
        {
            "id": "7",
            "username": "admin",
            "user_id": "1",
            "ip_address": "192.168.65.1",
            "user_agent": "curl/8.7.1",
            "login_status": "success",
            "failure_reason": null,
            "request_time": "2025-06-23 06:52:35",
            "created_at": "2025-06-23 06:52:35"
        }
    ],
    "total": 1,
    "limit": 50,
    "offset": 0,
    "message": "로그인 로그 조회 성공"
}
```

#### 로그인 통계 조회
```
GET /api/auth/login-statistics
```

**파라미터:**
- `date_from` (선택): 시작 날짜 (Y-m-d 형식, 기본값: 30일 전)
- `date_to` (선택): 종료 날짜 (Y-m-d 형식, 기본값: 오늘)

**인증:** Bearer 토큰 필요

**응답 예시:**
```json
{
    "success": true,
    "statistics": [
        {
            "login_date": "2025-06-23",
            "total_attempts": "11",
            "successful_logins": "2",
            "failed_logins": "9"
        }
    ],
    "period": {
        "from": "2025-05-24",
        "to": "2025-06-23"
    },
    "message": "로그인 통계 조회 성공"
}
```

## 데이터베이스 구조

### login_logs 테이블

```sql
CREATE TABLE login_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NULL COMMENT '로그인 시도 사용자명',
    user_id INT(11) NULL COMMENT '사용자 ID (성공시만)',
    ip_address VARCHAR(45) NOT NULL COMMENT '요청 IP 주소 (IPv4/IPv6)',
    user_agent TEXT NULL COMMENT '사용자 에이전트',
    login_status ENUM('success', 'failed') NOT NULL DEFAULT 'failed' COMMENT '로그인 상태',
    failure_reason VARCHAR(255) NULL COMMENT '실패 사유',
    request_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '요청 시간',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '생성시간',
    
    INDEX idx_username (username),
    INDEX idx_user_id (user_id),
    INDEX idx_ip_address (ip_address),
    INDEX idx_login_status (login_status),
    INDEX idx_request_time (request_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 인덱스 설명
- `idx_username`: 사용자별 로그 조회 최적화
- `idx_user_id`: 사용자 ID별 로그 조회 최적화
- `idx_ip_address`: IP별 로그 조회 및 보안 분석 최적화
- `idx_login_status`: 성공/실패별 조회 최적화
- `idx_request_time`: 시간순 정렬 및 기간별 조회 최적화

## 보안 고려사항

### 1. 개인정보 보호
- 패스워드는 로그에 저장되지 않습니다
- IP 주소는 보안 목적으로만 사용되며 개인정보처리방침에 따라 관리됩니다

### 2. 접근 제어
- 사용자는 본인의 로그만 조회할 수 있습니다
- 관리자 권한이 있는 경우에만 다른 사용자의 로그 조회가 가능합니다

### 3. 로그 보존
- 로그 데이터는 보안 정책에 따라 일정 기간 보존됩니다
- 필요에 따라 로그 아카이빙 및 삭제 정책을 구현할 수 있습니다

## 사용 예시

### cURL을 사용한 테스트

1. **로그인 (성공)**:
```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

2. **로그인 (실패)**:
```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"wrongpassword"}'
```

3. **로그인 로그 조회**:
```bash
curl -X GET "http://localhost/api/auth/login-logs?limit=10" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

4. **로그인 통계 조회**:
```bash
curl -X GET "http://localhost/api/auth/login-statistics" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## 모델 클래스 사용법

### Login_log_model 메서드

#### 로그인 성공 로그 저장
```php
$this->Login_log_model->log_successful_login($user_data, $ip_address, $user_agent);
```

#### 로그인 실패 로그 저장
```php
$this->Login_log_model->log_failed_login($username, $ip_address, $failure_reason, $user_agent);
```

#### 사용자별 로그 조회
```php
$logs = $this->Login_log_model->get_user_login_logs($user_id, $limit, $offset);
```

#### IP별 로그 조회
```php
$logs = $this->Login_log_model->get_ip_login_logs($ip_address, $limit, $offset);
```

#### 최근 실패 횟수 조회
```php
$failed_count = $this->Login_log_model->get_recent_failed_attempts($ip_address, $minutes);
```

#### 로그인 통계 조회
```php
$statistics = $this->Login_log_model->get_login_statistics($date_from, $date_to);
```

## 로그 분석 및 모니터링

### 1. 이상 행위 탐지
- 단시간 내 다수의 실패 로그인 시도
- 비정상적인 IP 주소에서의 접근
- 일반적이지 않은 User-Agent 패턴

### 2. 사용자 행동 분석
- 로그인 시간 패턴 분석
- 접근 지역 분석 (IP 기반)
- 사용 빈도 분석

### 3. 보안 리포트
- 일별/주별/월별 로그인 통계
- 실패율 추이 분석
- 보안 이벤트 알림

## 확장 가능성

### 1. 추가 기능
- 지역 정보 추가 (IP 기반 지역 조회)
- 디바이스 정보 수집 및 분석
- 2FA 로그 추가
- 세션 관리 로그 연동

### 2. 성능 최적화
- 로그 파티셔닝 (월별/년별)
- 읽기 전용 복제본 활용
- 캐싱 전략 적용

### 3. 외부 연동
- SIEM 시스템 연동
- 슬랙/이메일 알림 연동
- 외부 위협 인텔리전스 연동

## 문제 해결

### 1. 로그가 저장되지 않는 경우
- 데이터베이스 연결 확인
- login_logs 테이블 존재 여부 확인
- 권한 설정 확인

### 2. API 응답이 느린 경우
- 인덱스 활용 확인
- 쿼리 최적화
- 로그 데이터 양 확인

### 3. JWT 토큰 오류
- 토큰 만료 시간 확인
- 비밀키 설정 확인
- 헤더 형식 확인

---

이 로그인 로그 시스템을 통해 보안을 강화하고 사용자 행동을 분석하여 더 나은 서비스를 제공할 수 있습니다. 
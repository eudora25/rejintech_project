# 토큰 시스템 가이드

## 개요

이 문서는 로그인 토큰의 데이터베이스 저장 및 검증 시스템에 대한 가이드입니다.

## 시스템 구조

### 1. 토큰 저장 시스템
- 로그인 시 JWT 토큰이 `user_tokens` 테이블에 저장됩니다
- 토큰은 해시값으로 저장되어 보안을 강화합니다
- 발급 시간, 만료 시간, IP 주소, User-Agent 등이 기록됩니다

### 2. 토큰 검증 시스템
- JWT 토큰 자체 검증
- 데이터베이스에서 토큰 존재 및 유효성 검증
- 토큰 정보 일치성 확인

## 데이터베이스 구조

### user_tokens 테이블

```sql
CREATE TABLE user_tokens (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL COMMENT '사용자 ID',
    token_hash VARCHAR(255) NOT NULL COMMENT '토큰 해시값',
    token_type ENUM('access', 'refresh') NOT NULL DEFAULT 'access',
    ip_address VARCHAR(45) NOT NULL COMMENT '발급 IP 주소',
    user_agent TEXT NULL COMMENT '사용자 에이전트',
    issued_at DATETIME NOT NULL COMMENT '발급 시간',
    expires_at DATETIME NOT NULL COMMENT '만료 시간',
    last_used_at DATETIME NULL COMMENT '마지막 사용 시간',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT '활성 상태',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- 인덱스
    INDEX idx_user_id (user_id),
    INDEX idx_token_hash (token_hash),
    INDEX idx_token_type (token_type),
    INDEX idx_is_active (is_active),
    INDEX idx_expires_at (expires_at),
    INDEX idx_issued_at (issued_at),
    
    -- 외래키
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## API 엔드포인트

### 1. 로그인 확인 API

**POST** `/api/auth/check-login`

로그인 상태를 확인하고 토큰의 유효성을 검증합니다.

#### 요청
```bash
curl -X POST http://localhost/api/auth/check-login \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 성공 응답 (200)
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
        "ip_address": "192.168.1.100",
        "user_agent": "curl/8.7.1"
    },
    "message": "로그인 상태 확인됨"
}
```

#### 실패 응답 (401)
```json
{
    "success": false,
    "is_logged_in": false,
    "message": "데이터베이스에서 토큰을 찾을 수 없거나 만료되었습니다."
}
```

### 2. 로그아웃 API

**POST** `/api/auth/logout`

현재 토큰을 무효화하거나 모든 디바이스에서 로그아웃합니다.

#### 기본 로그아웃 (현재 토큰만)
```bash
curl -X POST http://localhost/api/auth/logout \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

#### 모든 디바이스에서 로그아웃
```bash
curl -X POST http://localhost/api/auth/logout \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"logout_all": true}'
```

#### 성공 응답 (200)
```json
{
    "success": true,
    "message": "로그아웃 완료"
}
```

## 토큰 라이프사이클

### 1. 토큰 발급
1. 사용자 로그인 요청
2. 인증 성공 시 JWT 토큰 생성
3. 토큰 해시값과 메타데이터를 DB에 저장
4. 클라이언트에 토큰 반환

### 2. 토큰 검증
1. 클라이언트가 토큰과 함께 요청
2. JWT 토큰 구조 및 서명 검증
3. DB에서 토큰 해시 조회
4. 토큰 활성 상태 및 만료 시간 확인
5. 마지막 사용 시간 업데이트

### 3. 토큰 무효화
1. 로그아웃 요청 시 토큰의 `is_active`를 false로 설정
2. 만료된 토큰 정리 (배치 작업)

## 모델 클래스

### User_token_model 주요 메서드

```php
// 토큰 저장
$this->User_token_model->save_token($user_id, $token, $ip_address, $user_agent);

// 토큰 검증
$token_info = $this->User_token_model->verify_token($token);

// 토큰 무효화
$this->User_token_model->invalidate_token($token);

// 사용자의 모든 토큰 무효화
$this->User_token_model->invalidate_user_tokens($user_id);

// 만료된 토큰 정리
$this->User_token_model->cleanup_expired_tokens();
```

## 보안 고려사항

### 1. 토큰 해시 저장
- 실제 JWT 토큰이 아닌 SHA-256 해시값을 저장
- 데이터베이스 노출 시에도 실제 토큰은 보호됨

### 2. 토큰 만료 관리
- JWT 만료 시간과 DB 만료 시간 동기화
- 정기적인 만료 토큰 정리

### 3. 디바이스별 토큰 관리
- IP 주소 및 User-Agent 기록
- 의심스러운 로그인 감지 가능

### 4. 다중 토큰 지원
- 동일 사용자가 여러 디바이스에서 동시 로그인 가능
- 개별 또는 전체 토큰 무효화 지원

## 사용 예시

### JavaScript 클라이언트 예시

```javascript
// 로그인 상태 확인
async function checkLoginStatus() {
    const token = localStorage.getItem('auth_token');
    
    try {
        const response = await fetch('/api/auth/check-login', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const result = await response.json();
        
        if (result.success && result.is_logged_in) {
            console.log('로그인 상태:', result.user);
            return true;
        } else {
            console.log('로그아웃 상태');
            localStorage.removeItem('auth_token');
            return false;
        }
    } catch (error) {
        console.error('로그인 확인 실패:', error);
        return false;
    }
}

// 로그아웃
async function logout(logoutAll = false) {
    const token = localStorage.getItem('auth_token');
    
    try {
        const response = await fetch('/api/auth/logout', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ logout_all: logoutAll })
        });
        
        const result = await response.json();
        
        if (result.success) {
            localStorage.removeItem('auth_token');
            console.log('로그아웃 완료');
        }
        
        return result;
    } catch (error) {
        console.error('로그아웃 실패:', error);
        return { success: false, message: '네트워크 오류' };
    }
}
```

## 문제 해결

### 1. 토큰이 검증되지 않는 경우
- JWT 토큰 유효성 확인
- 데이터베이스 연결 상태 확인
- 토큰 만료 시간 확인

### 2. 성능 최적화
- 토큰 해시 인덱스 활용
- 만료된 토큰 정기 정리
- 적절한 토큰 만료 시간 설정

### 3. 로그 모니터링
- 로그인/로그아웃 로그 확인
- 비정상적인 토큰 사용 패턴 감지
- IP 주소별 접근 패턴 분석

## 관련 문서
- [로그인 로그 가이드](login-logs-guide.md)
- [JWT 인증 설정](jwt-configuration-guide.md)
- [API 문서](README.md) 
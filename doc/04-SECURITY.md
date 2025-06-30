# 보안 및 인증 문서

## 목차
1. [인증 시스템](#인증-시스템)
2. [보안 정책](#보안-정책)
3. [접근 제어](#접근-제어)
4. [로깅 및 감사](#로깅-및-감사)

## 인증 시스템

### JWT 토큰
- 알고리즘: HS256
- 토큰 유효기간: 1시간 (3600초)
- 리프레시 토큰: 7일 (604800초)
- 토큰 구조:
  ```json
  {
    "iss": "rejintech",
    "aud": "rejintech_users",
    "sub": "user_id",
    "iat": 1234567890,
    "exp": 1234571490
  }
  ```

### 토큰 관리
- 토큰 저장: 클라이언트 localStorage
- 토큰 갱신: 자동 갱신
- 토큰 폐기: 로그아웃 시 즉시
- 동시 접속: 최대 5개 세션

### 패스워드 정책
- 최소 길이: 12자
- 복잡도 요구사항:
  - 대문자 1개 이상
  - 소문자 1개 이상
  - 숫자 1개 이상
  - 특수문자 1개 이상
- 제한사항:
  - 연속된 문자 3개 이상 불가
  - 키보드 배열 연속 문자 불가
  - 이전 비밀번호 재사용 불가 (최근 5개)

## 보안 정책

### CORS 정책
```http
Access-Control-Allow-Origin: https://rejintech.com
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
Access-Control-Max-Age: 86400
```

### 보안 헤더
```http
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
Content-Security-Policy: default-src 'self'
Referrer-Policy: strict-origin-when-cross-origin
```

### SSL/TLS 설정
- 프로토콜: TLS 1.2, 1.3
- 암호화 스위트:
  ```
  ECDHE-ECDSA-AES128-GCM-SHA256
  ECDHE-RSA-AES128-GCM-SHA256
  ECDHE-ECDSA-AES256-GCM-SHA384
  ECDHE-RSA-AES256-GCM-SHA384
  ```
- 인증서: Let's Encrypt
- 자동 갱신: 매월 1일

## 접근 제어

### IP 기반 제한
- 관리자 페이지: 허용된 IP만 접근
- API 접근: 화이트리스트 기반
- 차단 정책:
  - 5회 연속 실패 시 15분 차단
  - 24시간 내 20회 실패 시 24시간 차단

### 역할 기반 접근 제어 (RBAC)
| 역할 | 권한 |
|------|------|
| 관리자 | 전체 권한 |
| 운영자 | 읽기/쓰기 권한 |
| 사용자 | 읽기 권한 |
| 게스트 | 제한된 읽기 권한 |

### API 접근 제어
- Rate Limiting:
  - 초당 요청 수 제한
  - IP별 제한
  - 토큰별 제한
- 요청 크기 제한:
  - Body: 10MB
  - File Upload: 50MB
  - JSON: 1MB

## 로깅 및 감사

### 로그인 로그
- 시도 일시
- IP 주소
- 사용자 에이전트
- 성공/실패 여부
- 실패 사유

### API 호출 로그
- 요청 시간
- 엔드포인트
- 요청 파라미터
- 응답 코드
- 처리 시간
- 사용자 정보

### 보안 감사
- 비정상 접근 시도
- 권한 변경 이력
- 중요 데이터 접근
- 설정 변경 이력

### 로그 보관
- 보관 기간: 1년
- 저장 위치: AWS CloudWatch
- 암호화: AES-256
- 접근 제어: IAM 기반 
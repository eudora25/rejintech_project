# API 및 인터페이스 문서

## 목차
1. [API 개요](#api-개요)
2. [인증 API](#인증-api)
3. [조달 API](#조달-api)
4. [통계 API](#통계-api)
5. [공통 사항](#공통-사항)

## API 개요

### 기본 정보
- 기본 URL: `https://api.rejintech.com`
- API 버전: v1
- 응답 형식: JSON
- 문자 인코딩: UTF-8

### 공통 헤더
```http
Content-Type: application/json
Authorization: Bearer <access_token>
```

### 응답 형식
```json
{
  "success": true|false,
  "message": "응답 메시지",
  "data": {
    // 응답 데이터
  },
  "timestamp": "2024-01-01T00:00:00Z"
}
```

## 인증 API

### 로그인
```http
POST /api/auth/login
```

**요청 본문**
```json
{
  "username": "user123",
  "password": "password123"
}
```

**응답**
```json
{
  "success": true,
  "message": "로그인 성공",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "id": 1,
      "username": "user123",
      "email": "user123@example.com"
    },
    "expires_in": 3600
  }
}
```

### 토큰 검증
```http
GET /api/auth/verify
```

### 로그아웃
```http
POST /api/auth/logout
```

### 비밀번호 변경
```http
POST /api/auth/change-password
```

## 조달 API

### 납품요구 목록 조회
```http
GET /api/procurement/delivery-requests
```

**필수 파라미터**
- start_date: 시작일 (YYYY-MM-DD)
- end_date: 종료일 (YYYY-MM-DD)

**선택 파라미터**
- exclcProdctYn: 우수제품여부 (Y/N)
- dminsttRgnNm: 수요기관지역
- dminsttNm: 수요기관명
- corpNm: 업체명
- page: 페이지 번호
- size: 페이지 크기

### 수요기관별 통계
```http
GET /api/procurement/statistics/institutions
```

### 업체별 통계
```http
GET /api/procurement/statistics/companies
```

### 품목별 통계
```http
GET /api/procurement/statistics/products
```

## 통계 API

### 기간별 실적 통계
```http
GET /api/statistics/period
```

### 지역별 실적 통계
```http
GET /api/statistics/region
```

### 업체별 실적 통계
```http
GET /api/statistics/company
```

## 공통 사항

### 에러 코드
| 코드 | 설명 |
|------|------|
| 400 | 잘못된 요청 |
| 401 | 인증 실패 |
| 403 | 권한 없음 |
| 404 | 리소스 없음 |
| 429 | 요청 횟수 초과 |
| 500 | 서버 오류 |

### 페이지네이션
- page: 페이지 번호 (1부터 시작)
- size: 페이지 크기 (기본값 20, 최대 100)
- total: 전체 데이터 수
- total_pages: 전체 페이지 수

### 정렬
- sort: 정렬 기준 필드
- order: 정렬 방향 (asc/desc)

### 필터링
- 날짜 형식: YYYY-MM-DD
- 검색어: URL 인코딩 필요
- 숫자 범위: min_*, max_* 형식

### CORS 정책
허용된 도메인:
- http://localhost:3000
- https://rejintech.com

### 요청 제한
- 초당 최대 요청 수: 10
- 일일 최대 요청 수: 10000
- 토큰당 최대 동시 접속: 5 
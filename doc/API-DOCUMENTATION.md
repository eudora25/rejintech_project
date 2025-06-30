# API 문서

## 목차
1. [인증 API](#인증-api)
   - [API 인증 정보](#api-인증-정보)
   - [로그인](#로그인)
   - [토큰 검증](#토큰-검증)
   - [로그아웃](#로그아웃)
   - [비밀번호 변경](#비밀번호-변경)
   - [사용자 프로필 조회](#사용자-프로필-조회)
2. [보안 정책](#보안-정책)
   - [CORS 정책](#cors-정책)
   - [보안 헤더](#보안-헤더)
   - [로그인 시도 제한](#로그인-시도-제한)
   - [패스워드 정책](#패스워드-정책)
3. [조달청 API](#조달청-api)
   - [수요기관별 통계 조회](#수요기관별-통계-조회)
   - [업체별 통계 조회](#업체별-통계-조회)
   - [품목별 통계 조회](#품목별-통계-조회)
   - [납품요구 목록 조회](#납품요구-목록-조회)

## 보안 정책

### CORS 정책
API는 다음 도메인에서만 접근이 가능합니다:
- http://localhost:3000 (개발 환경)
- https://rejintech.com (운영 환경)

### 보안 헤더
모든 API 응답에는 다음과 같은 보안 헤더가 포함됩니다:
```http
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
Content-Security-Policy: default-src 'self'; frame-ancestors 'none'
Referrer-Policy: strict-origin-when-cross-origin
```

### 로그인 시도 제한
보안을 위해 다음과 같은 로그인 시도 제한이 적용됩니다:
- 동일 IP에서 5회 연속 실패 시 15분간 로그인 시도 불가
- 잠금 해제까지 남은 시간이 응답에 포함됨
- 성공적인 로그인 시 실패 횟수 초기화

### 패스워드 정책
새로운 패스워드는 다음 조건을 모두 만족해야 합니다:
- 최소 12자 이상
- 최소 1개의 대문자 포함
- 최소 1개의 소문자 포함
- 최소 1개의 숫자 포함
- 최소 1개의 특수문자 포함 (!@#$%^&*()-_=+{};:,<.>)
- 3회 이상 연속된 문자 사용 불가 (예: 'aaa', '111')
- 키보드 상의 연속된 문자 사용 불가 (예: 'qwerty', 'asdfgh')

## API 인증 정보

모든 API 요청은 JWT(JSON Web Token) 기반의 인증이 필요합니다. 토큰은 로그인 API를 통해 발급받을 수 있습니다.

### 인증 헤더
```http
Authorization: Bearer <access_token>
```

### JWT 토큰 정보
- 발급자(iss): rejintech
- 대상(aud): rejintech_users
- 알고리즘: HS256
- 토큰 유효기간: 1시간 (3600초)
- 토큰에 포함된 정보:
  - user_id: 사용자 ID
  - username: 사용자명
  - email: 이메일
  - iat: 발급 시간
  - exp: 만료 시간

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

**성공 응답 (200 OK)**
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

**에러 응답**
- 400 Bad Request: 잘못된 요청
```json
{
  "success": false,
  "message": "사용자명과 패스워드를 입력해주세요."
}
```
- 401 Unauthorized: 인증 실패
```json
{
  "success": false,
  "message": "잘못된 사용자명 또는 패스워드입니다."
}
```
- 429 Too Many Requests: 로그인 시도 제한
```json
{
  "success": false,
  "message": "너무 많은 로그인 시도가 있었습니다. {n}분 후에 다시 시도해주세요."
}
```

### 토큰 검증
```http
GET /api/auth/verify
```

**인증**
- Bearer Token 인증 필요

**응답**
```json
{
  "success": true,
  "user": {
    "user_id": 1,
    "username": "user123",
    "email": "user123@example.com"
  }
}
```

**에러 응답**
- 401 Unauthorized: 유효하지 않은 토큰
```json
{
  "success": false,
  "message": "유효하지 않은 토큰입니다."
}
```

### 로그아웃
```http
POST /api/auth/logout
```

**인증**
- Bearer Token 인증 필요

**응답**
```json
{
  "success": true,
  "message": "로그아웃 성공"
}
```

### 비밀번호 변경
```http
POST /api/auth/change-password
```

**인증**
- Bearer Token 인증 필요

**요청 본문**
```json
{
  "current_password": "현재비밀번호",
  "new_password": "새비밀번호",
  "confirm_password": "새비밀번호확인"
}
```

**성공 응답 (200 OK)**
```json
{
  "success": true,
  "message": "비밀번호가 성공적으로 변경되었습니다."
}
```

**에러 응답**
- 400 Bad Request: 패스워드 정책 불만족
```json
{
  "success": false,
  "message": "새 비밀번호가 정책을 만족하지 않습니다.",
  "errors": [
    "비밀번호는 최소 12자 이상이어야 합니다.",
    "비밀번호는 최소 1개의 대문자를 포함해야 합니다.",
    // ... 기타 정책 위반 사항
  ]
}
```
- 401 Unauthorized: 현재 비밀번호 불일치
```json
{
  "success": false,
  "message": "현재 비밀번호가 일치하지 않습니다."
}
```

### 사용자 프로필 조회
```http
GET /api/auth/profile
```

**인증**
- Bearer Token 인증 필요

**응답**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "username": "user123",
    "email": "user123@example.com",
    "created_at": "2024-01-01T00:00:00Z"
  }
}
```

## 조달청 API

### 수요기관별 통계 조회

수요기관별 납품 통계를 연도별로 조회하며, 전년 대비 증감률을 계산합니다.

```http
GET /api/procurement/statistics/institutions
```

**인증**
- Bearer Token 인증 필요

**필수 파라미터**
| 파라미터 | 타입 | 설명 |
|----------|------|------|
| page | integer | 페이지 번호 (1부터 시작) |
| size | integer | 페이지 크기 (최대 100) |

**선택 파라미터**
| 파라미터 | 타입 | 설명 | 기본값 |
|----------|------|------|--------|
| year | integer | 기준 연도 (YYYY) | 현재 연도 |
| dminsttNm | string | 수요기관명 (부분 검색 지원) | - |
| exclcProdctYn | string | 우수제품여부 (Y/N) | - |
| includePrevYear | boolean | 전년 대비 증감률 계산 포함 여부 | true |
| corpNm | string | 업체명 (부분 검색 지원) | - |

**응답**
```json
{
  "success": true,
  "message": "수요기관별 통계 조회 성공",
  "data": {
    "page": 1,
    "size": 10,
    "total": 150,
    "year": 2025,
    "totalAmount": 50000000000,
    "prevYearAmount": 45000000000,
    "growthRate": 11.11,
    "institutions": [
      {
        "dminsttNm": "서울특별시",
        "dminsttCd": "6110000",
        "currentAmount": 5000000000,
        "prevAmount": 4500000000,
        "growthRate": 11.11,
        "deliveryCount": 50,
        "exclcProdctAmount": 3000000000,
        "generalProdctAmount": 2000000000,
        "exclcProdctYn": "Y"
      }
    ],
    "appliedFilters": {
      "year": 2025,
      "dminsttNm": "서울특별시",
      "exclcProdctYn": "Y",
      "corpNm": "삼성전자"
    }
  }
}
```

**응답 필드 설명**
| 필드 | 타입 | 설명 |
|------|------|------|
| page | integer | 현재 페이지 |
| size | integer | 페이지 크기 |
| total | integer | 전체 데이터 수 |
| year | integer | 기준 연도 |
| totalAmount | number | 전체 금액 합계 (모든 납품요구의 total_amount 합계) |
| prevYearAmount | number | 전년 실적 |
| growthRate | number | 전년 대비 증감률(%) |
| institutions | array | 수요기관별 통계 목록 |
| institutions[].dminsttNm | string | 수요기관명 |
| institutions[].dminsttCd | string | 수요기관코드 |
| institutions[].currentAmount | number | 당해연도 금액 |
| institutions[].prevAmount | number | 전년도 금액 |
| institutions[].growthRate | number | 증감률(%) |
| institutions[].deliveryCount | integer | 납품요구 건수 |
| institutions[].exclcProdctAmount | number | 우수제품 금액 |
| institutions[].generalProdctAmount | number | 일반제품 금액 |
| institutions[].exclcProdctYn | string | 주요 우수제품 여부 (Y/N) |
| appliedFilters | object | 적용된 필터 정보 |

**에러 응답**
```json
{
  "success": false,
  "message": "에러 메시지",
  "error": {
    "code": "ERROR_CODE",
    "details": "상세 에러 정보"
  }
}
```

**에러 코드**
| HTTP 상태 코드 | 설명 |
|----------------|------|
| 400 | 잘못된 요청 (필수 파라미터 누락 등) |
| 401 | 인증 실패 |
| 500 | 서버 오류 |

### 업체별 통계 조회

업체별 납품 통계를 조회합니다.

```http
GET /api/procurement/statistics/companies
```

**인증**
- Bearer Token 인증 필요

**선택 파라미터**
| 파라미터 | 타입 | 설명 |
|----------|------|------|
| exclcProdctYn | string | 우수제품여부 (Y/N) |
| dminsttNm | string | 수요기관명 |
| prdctClsfcNoNm | string | 품명 |

**응답**
```json
{
  "success": true,
  "message": "업체별 통계 조회 성공",
  "data": [
    {
      "company_name": "삼성전자",
      "company_type": "대기업",
      "delivery_count": 50,
      "total_amount": 5000000000,
      "avg_amount": 100000000
    }
  ]
}
```

### 품목별 통계 조회

품목별 납품 통계를 조회합니다.

```http
GET /api/procurement/statistics/products
```

**인증**
- Bearer Token 인증 필요

**선택 파라미터**
| 파라미터 | 타입 | 설명 |
|----------|------|------|
| exclcProdctYn | string | 우수제품여부 (Y/N) |
| dminsttNm | string | 수요기관명 |
| corpNm | string | 업체명 |

**응답**
```json
{
  "success": true,
  "message": "품목별 통계 조회 성공",
  "data": [
    {
      "category_name": "컴퓨터",
      "detail_category_name": "노트북",
      "item_count": 100,
      "total_amount": 5000000000,
      "avg_amount": 50000000,
      "total_quantity": 500
    }
  ]
}
```

### 납품요구 목록 조회

납품요구 목록을 조회합니다. 기간별, 지역별, 기관별, 업체별 등 다양한 조건으로 필터링이 가능합니다.

```http
GET /api/procurement/delivery-requests
```

**인증**
- Bearer Token 인증 필요

**필수 파라미터**
| 파라미터 | 타입 | 설명 | 기본값 | 제약사항 |
|----------|------|------|---------|-----------|
| start_date | string | 납품요구접수일자 시작일 (YYYY-MM-DD) | - | 유효한 날짜 형식 |
| end_date | string | 납품요구접수일자 종료일 (YYYY-MM-DD) | - | 유효한 날짜 형식 |

**선택 파라미터**
| 파라미터 | 타입 | 설명 | 기본값 | 제약사항 |
|----------|------|------|---------|-----------|
| exclcProdctYn | string | 우수제품여부 | - | 'Y' 또는 'N' |
| prdctClsfcNoNm | string | 품명 | - | 최대 100자 |
| dtilPrdctClsfcNoNm | string | 세부품명 | - | 최대 100자 |
| dminsttRgnNm | string | 수요기관지역 | - | 유효한 행정구역명 |
| dminsttNm | string | 수요기관명 | - | 최대 100자 |
| corpNm | string | 업체명 | - | 최대 100자 |
| page | integer | 페이지 번호 | 1 | 1 이상 |
| size | integer | 페이지당 데이터 수 | 20 | 1~100 |
| sort | string | 정렬 기준 필드 | dlvrReqRcptDate | 유효한 필드명 |
| order | string | 정렬 순서 | desc | 'asc' 또는 'desc' |

**응답 필드 설명**
| 필드 | 타입 | 설명 |
|------|------|------|
| page | integer | 현재 페이지 번호 |
| size | integer | 페이지당 데이터 수 |
| total | integer | 전체 데이터 수 |
| totalAmount | number | 전체 금액 합계 |
| jodalTotalAmount | number | 우수제품 금액 합계 |
| masTotalAmount | number | 일반제품 금액 합계 |
| items | array | 납품요구 목록 |
| items[].exclcProdctYn | string | 우수제품여부 (Y/N) |
| items[].dlvrReqRcptDate | string | 납품요구접수일자 |
| items[].dminsttNm | string | 수요기관명 |
| items[].dminsttRgnNm | string | 수요기관지역 |
| items[].corpNm | string | 업체명 |
| items[].dlvrReqNm | string | 납품요구명 |
| items[].prdctClsfcNoNm | string | 품명 |
| items[].dtilPrdctClsfcNoNm | string | 세부품명 |
| items[].prdctIdntNo | string | 식별번호 |
| items[].prdctIdntNoNm | string | 식별번호명 |
| items[].incdecQty | number | 수량 |
| items[].prdctUprc | number | 단가 |
| items[].incdecAmt | number | 금액 |
| items[].dminsttCd | string | 수요기관코드 |

**성공 응답 (200 OK)**
```json
{
  "success": true,
  "message": "납품요구 목록 조회 성공",
  "data": {
    "page": 1,
    "size": 20,
    "total": 215,
    "totalAmount": 1000000000,
    "jodalTotalAmount": 600000000,
    "masTotalAmount": 400000000,
    "items": [
      {
        "exclcProdctYn": "Y",
        "dlvrReqRcptDate": "2024-01-01",
        "dminsttNm": "수원시청",
        "dminsttRgnNm": "경기도",
        "corpNm": "(주)지인테크",
        "dlvrReqNm": "CCTV 설치",
        "prdctClsfcNoNm": "영상감시장치",
        "dtilPrdctClsfcNoNm": "CCTV",
        "prdctIdntNo": "23571113",
        "prdctIdntNoNm": "고해상도 CCTV",
        "incdecQty": 10,
        "prdctUprc": 5000000,
        "incdecAmt": 50000000,
        "dminsttCd": "3910000"
      }
    ]
  }
}
```

**에러 응답**
- 400 Bad Request: 잘못된 파라미터
```json
{
  "success": false,
  "message": "잘못된 요청입니다.",
  "errors": {
    "start_date": "시작일이 종료일보다 늦을 수 없습니다.",
    "size": "페이지당 데이터 수는 1에서 100 사이여야 합니다."
  }
}
```
- 401 Unauthorized: 인증 실패
```json
{
  "success": false,
  "message": "유효하지 않은 토큰입니다."
}
```

**지역 매칭 로직**
- 수요기관지역(dminsttRgnNm)은 행정구역 단위로 매칭됩니다.
- 상위 행정구역으로 검색 시 하위 행정구역을 포함합니다.
  - 예: "경기도" 검색 시 "수원시", "성남시" 등 경기도 내 모든 시군 포함
- 정확한 지역명을 입력해야 하며, 부분 검색은 지원하지 않습니다.

**기간 필터링**
- start_date와 end_date는 YYYY-MM-DD 형식으로 입력
- 시작일은 종료일보다 이후일 수 없음
- 최대 조회 기간은 1년으로 제한
- 날짜 범위는 납품요구접수일자(dlvrReqRcptDate) 기준으로 적용 
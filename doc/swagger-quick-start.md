# Swagger 빠른 시작 가이드

## 개요

이 가이드는 Rejintech 프로젝트에서 Swagger UI를 사용하여 API를 테스트하는 방법을 설명합니다.

## 📊 현재 구현 상태

### ✅ 완전히 구현된 기능
- **JWT 인증 시스템** - 완전 작동
- **조달청 데이터 API** - 전체 구현 완료
- **Swagger UI 통합** - 인터랙티브 API 테스트 환경

### 📁 준비된 파일들
✅ `source/swagger-ui/index.html` - Swagger UI 인터페이스  
✅ `source/api/docs/openapi.json` - 완전한 API 스펙  
✅ `source/application/controllers/api/Auth.php` - JWT 인증 API  
✅ `source/application/controllers/api/Procurement.php` - 조달청 데이터 API  

## 🚀 바로 시작하기

### 1단계: 서비스 실행
```bash
# 프로젝트 디렉토리로 이동
cd rejintech_project

# Docker 컨테이너 실행
docker-compose up -d

# 실행 상태 확인
docker-compose ps
```

### 2단계: Swagger UI 접속
```
브라우저에서 http://localhost/source/swagger-ui/ 접속
```

### 3단계: API 테스트
1. **로그인** → JWT 토큰 발급
2. **토큰 설정** → 상단 "Authorize" 버튼 클릭
3. **API 테스트** → 조달청 데이터 조회

## 🔐 인증 흐름

### 1. 로그인 (JWT 토큰 발급)
```bash
# API 방식
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin123"}'
```

**응답 예시:**
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "user": {
      "id": "1", 
      "username": "admin",
      "email": "admin@example.com"
    }
  },
  "message": "로그인 성공"
}
```

### 2. Swagger UI에서 토큰 설정
1. Swagger UI 상단의 **"Authorize"** 버튼 클릭
2. `bearerAuth` 필드에 토큰 입력 (앞에 "Bearer " 붙이지 마세요)
3. **"Authorize"** 버튼 클릭

### 3. 인증된 API 테스트
이제 🔒 자물쇠 표시가 있는 모든 API를 테스트할 수 있습니다.

## 📋 제공되는 API

### 🔐 인증 관련 API
- **POST** `/api/auth/login` - 로그인 (JWT 토큰 발급)
- **GET** `/api/auth/verify` - 토큰 검증
- **GET** `/api/auth/profile` - 사용자 프로필 조회
- **POST** `/api/auth/logout` - 로그아웃
- **POST** `/api/auth/change-password` - **비밀번호 변경** ⭐ **신규**
- **GET** `/api/auth/login-logs` - 로그인 로그 조회
- **GET** `/api/auth/login-statistics` - 로그인 통계

### 🏢 조달청 데이터 API (메인 기능)
- **GET** `/api/procurement/delivery-requests` - 📋 조달청 데이터 조회
- **GET** `/api/procurement/statistics/institutions` - 📊 수요기관별 통계
- **GET** `/api/procurement/statistics/companies` - 🏢 업체별 통계  
- **GET** `/api/procurement/statistics/products` - 📦 품목별 통계
- **GET** `/api/procurement/filter-options` - 🔍 필터 옵션 조회

### 🔧 테스트 API (인증 불필요)
- **GET** `/api/test` - 서버 상태 확인
- **GET** `/api/test/database` - 데이터베이스 연결 테스트

## 🎯 주요 API 테스트 가이드

### 조달청 데이터 조회
```
GET /api/procurement/delivery-requests
```

**주요 파라미터:**
- `page`: 페이지 번호 (기본값: 1)
- `size`: 페이지 크기 (기본값: 50, 최대: 100)
- `type`: 상품 유형 (CSO: 우수제품, MAS: 일반제품)
- `dminsttNm`: 수요기관명 (예: "서울특별시")
- `corpNm`: 업체명 (예: "삼성전자")
- `dateFrom`, `dateTo`: 날짜 범위 (YYYY-MM-DD)
- `amountFrom`, `amountTo`: 금액 범위
- `sortBy`: 정렬 필드 (bizName, dlvrReqRcptDate, incdecAmt 등)
- `sortOrder`: 정렬 순서 (asc, desc)

**테스트 예시:**
1. **기본 조회**: 파라미터 없이 실행
2. **페이징**: `page=2&size=20`
3. **필터링**: `type=CSO&dminsttNm=서울특별시`
4. **정렬**: `sortBy=bizName&sortOrder=asc`

### 통계 API 테스트
```
GET /api/procurement/statistics/institutions
GET /api/procurement/statistics/companies  
GET /api/procurement/statistics/products
```

각 통계 API는 해당 분야별 집계 정보를 제공합니다.

## 💡 커맨드라인 테스트

Swagger UI 외에도 curl로 직접 테스트 가능합니다:

```bash
# 1. 로그인 및 토큰 저장
TOKEN=$(curl -s -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin123"}' | \
  jq -r '.data.token')

# 2. 비밀번호 변경
curl -X POST "http://localhost/api/auth/change-password" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "current_password": "admin123",
    "new_password": "newPassword123!",
    "confirm_password": "newPassword123!"
  }' | jq

# 3. 조달청 데이터 조회
curl -X GET "http://localhost/api/procurement/delivery-requests?page=1&size=10&type=CSO" \
  -H "Authorization: Bearer $TOKEN" | jq

# 4. 수요기관별 통계
curl -X GET "http://localhost/api/procurement/statistics/institutions" \
  -H "Authorization: Bearer $TOKEN" | jq

# 5. 필터 옵션 조회
curl -X GET "http://localhost/api/procurement/filter-options" \
  -H "Authorization: Bearer $TOKEN" | jq
```

## 🆘 문제 해결

### 1. Swagger UI가 로드되지 않는 경우
```bash
# 컨테이너 상태 확인
docker-compose ps

# Nginx 로그 확인
docker exec -it rejintech-workspace tail -f /var/log/nginx/error.log
```

### 2. 인증 오류 (401 Unauthorized)
**확인사항:**
1. JWT 토큰이 올바르게 발급되었는지
2. Authorize 설정에서 토큰이 정확히 입력되었는지  
3. 토큰이 만료되지 않았는지 (1시간 유효)

```bash
# 토큰 검증
curl -X GET http://localhost/api/auth/verify \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 3. API 응답 오류
```bash
# 서버 로그 확인
docker-compose logs -f rejintech-workspace

# 데이터베이스 연결 확인
curl http://localhost/api/test/database
```

### 4. CORS 오류
현재 개발 환경에서는 CORS가 허용되도록 설정되어 있습니다.

## 📊 데이터 현황

### 현재 저장된 데이터
- **납품요구 항목**: 992건
- **수요기관**: 147개
- **계약업체**: 280개  
- **물품 종류**: 758개
- **총 금액**: ₩6,237,229,274

### 데이터 특성
- **우수제품(CSO)**: 70건 (7.06%)
- **일반제품(MAS)**: 922건 (92.94%)
- **주요 지역**: 서울, 경기, 부산 등
- **주요 품목**: 컴퓨터용품, 사무용품, 의료기기 등

## 🎯 실제 사용 시나리오

### 시나리오 1: 조달 관리자
1. **로그인** → admin/admin123
2. **전체 현황 파악** → `/api/procurement/delivery-requests?page=1&size=50`
3. **특정 기관 조회** → `dminsttNm=서울특별시`
4. **금액별 분석** → `/api/procurement/statistics/institutions`

### 시나리오 2: 업체 담당자
1. **로그인** 후 토큰 설정
2. **우수제품 조회** → `type=CSO`
3. **업체별 통계** → `/api/procurement/statistics/companies`
4. **특정 업체 검색** → `corpNm=삼성전자`

### 시나리오 3: 데이터 분석가
1. **필터 옵션 확인** → `/api/procurement/filter-options`
2. **기간별 분석** → `dateFrom=2024-01-01&dateTo=2024-12-31`
3. **금액 범위 분석** → `amountFrom=1000000&amountTo=10000000`
4. **품목별 통계** → `/api/procurement/statistics/products`

---

**🎉 Swagger UI 준비 완료!** 🚀  
이제 http://localhost/source/swagger-ui/에서 모든 API를 테스트할 수 있습니다. 
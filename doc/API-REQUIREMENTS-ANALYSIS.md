# 지인테크 API 요구사항 분석

**📅 작성일**: 2025-06-23  
**📊 문서 버전**: v1.0  
**🎯 목적**: 클라이언트 요구사항 분석 및 구현 계획

## 📋 요구사항 출처

**문서 링크**: [지인테크 API 정보 정리](https://docs.google.com/spreadsheets/d/1wPgjLUUWYBl7TceD62DNienDhdD9R8B5TzNj4ApBQRc/edit?gid=1362176856#gid=1362176856)

---

## 🎯 요구되는 API 목록

### 1. 📊 조달청 데이터 전체 리스트 API

#### 📝 기본 응답 구조
```json
{
    "page": 1,
    "pageSize": 20,
    "total": 992,
    "jodalTotalAmount": 1500000000,
    "masTotalAmount": 800000000,
    "totalAmount": 2300000000,
    "filterPrdctClsfcNoNm": ["품명1", "품명2", "..."],
    "filterDminsttNm": ["기관명1", "기관명2", "..."],
    "filterCorpNm": ["업체명1", "업체명2", "..."],
    "data": [...]
}
```

#### 🔍 데이터 필드 매핑
| 요구사항 필드 | 설명 | DB 필드 | 데이터 타입 |
|--------------|------|---------|------------|
| `exclcProdctYn` | 우수제품여부 (Y=조달, N=마스) | `exclc_prodct_yn` | CHAR(1) |
| `dlvrReqRcptDate` | 납품요구접수일자 (YYYY-MM-DD) | `dlvr_req_rcpt_date` | DATE |
| `dminsttNm` | 수요기관명 | `dminstt_nm` | VARCHAR(200) |
| `dminsttRgnNm` | 수요기관지역명 | `dminstt_rgn_nm` | VARCHAR(100) |
| `corpNm` | 업체명 | `corp_nm` | VARCHAR(200) |
| `dlvrReqNm` | 납품요구건명, 사업명 | `dlvr_req_nm` | TEXT |
| `prdctClsfcNoNm` | 품명 | `prdct_clsfc_no_nm` | VARCHAR(100) |
| `dtilPrdctClsfcNoNm` | 세부품명 | `dtil_prdct_clsfc_no_nm` | VARCHAR(100) |
| `prdctIdntNo` | 물품식별번호 | `prdct_idnt_no` | VARCHAR(20) |
| `prdctIdntNoNm` | 물품규격명, 품목 | `prdct_idnt_no_nm` | TEXT |
| `incdecQty` | 증감수량 | `incdec_qty` | DECIMAL(15,3) |
| `prdctUprc` | 물품단가 | `prdct_uprc` | DECIMAL(15,2) |
| `incdecAmt` | 증감금액 | `incdec_amt` | DECIMAL(15,2) |
| `dminsttCd` | 수요기관코드 | `dminstt_cd` | VARCHAR(20) |

#### 💰 금액 집계 로직
```sql
-- jodalTotalAmount: 우수제품(조달) 금액 합계
SELECT SUM(incdec_amt) FROM delivery_request_details 
WHERE exclc_prodct_yn = 'Y'

-- masTotalAmount: 일반제품(마스) 금액 합계  
SELECT SUM(incdec_amt) FROM delivery_request_details 
WHERE exclc_prodct_yn = 'N'

-- totalAmount: 전체 금액 합계
SELECT SUM(incdec_amt) FROM delivery_request_details
```

#### 🔧 필터 기능 구현
```sql
-- 품명별 유니크 값 (오름차순)
SELECT DISTINCT prdct_clsfc_no_nm FROM delivery_request_details 
ORDER BY prdct_clsfc_no_nm ASC

-- 수요기관명별 유니크 값 (오름차순)
SELECT DISTINCT dminstt_nm FROM delivery_request_details 
ORDER BY dminstt_nm ASC

-- 업체명별 유니크 값 (오름차순)
SELECT DISTINCT corp_nm FROM delivery_request_details 
ORDER BY corp_nm ASC
```

### 2. 👥 회원/접속로그 관련 API

#### 📊 로그인 로그 리스트
```json
{
    "page": 1,
    "pageSize": 20,
    "total": 19,
    "data": [
        {
            "loginAt": "2025-06-23T07:18:58+09:00",
            "username": "admin",
            "ip": "192.168.65.1",
            "userAgent": "curl/8.7.1"
        }
    ]
}
```

#### 🔄 기존 DB 필드 매핑
| 요구사항 필드 | DB 필드 | 변환 로직 |
|--------------|---------|----------|
| `loginAt` | `request_time` | ISO8601 형식으로 변환 |
| `username` | `username` | 그대로 사용 |
| `ip` | `ip_address` | IPv4/IPv6 지원 |
| `userAgent` | `user_agent` | 그대로 사용 |

### 3. 🔐 인증 관련 API

#### 회원가입 API
```json
// 요청
{
    "userId": "newuser123",
    "name": "홍길동"
}

// 응답
{
    "success": true,
    "message": "회원가입이 완료되었습니다",
    "user": {
        "userId": "newuser123",
        "name": "홍길동"
    }
}
```

#### 로그인 API (응답 형식 조정 필요)
```json
// 현재 응답
{
    "success": true,
    "token": "eyJ...",
    "user": {
        "id": "1",
        "username": "admin",
        "email": "admin@example.com"
    },
    "message": "로그인 성공",
    "expires_in": 3600
}

// 요구사항 응답
{
    "accessToken": "eyJ...",
    "user": {
        "email": "admin@example.com",
        "name": "관리자"
    }
}
```

#### 로그아웃 API (응답 형식 조정 필요)
```json
// 현재 응답
{
    "success": true,
    "message": "로그아웃되었습니다"
}

// 요구사항 응답  
{
    "success": true
}
```

---

## 🔍 현재 시스템 vs 요구사항 비교

### ✅ 이미 구현된 기능

#### 1. 데이터베이스 구조
- **조달청 데이터**: `delivery_request_details` 테이블 (992건)
- **로그인 로그**: `login_logs` 테이블 (19건)
- **사용자 관리**: `users` 테이블 (2명)
- **토큰 관리**: `user_tokens` 테이블 (6건)

#### 2. 기본 인증 시스템
- **JWT 기반 로그인/로그아웃**: 완전 구현
- **토큰 DB 저장/검증**: 이중 보안 시스템
- **로그인 로그 자동 저장**: IP, User-Agent 포함

#### 3. API 인프라
- **Swagger UI**: 완전 통합
- **Docker 환경**: Production Ready
- **라우팅 시스템**: CodeIgniter 기반

### 🔄 추가 구현이 필요한 API

#### 1. 조달청 데이터 조회 API ⭐ **신규 필요**
```php
GET /api/procurement/delivery-requests
```
- 페이징 지원
- 금액 집계 기능
- 필터링 기능
- 검색 기능

#### 2. 회원가입 API ⭐ **신규 필요**
```php
POST /api/auth/register
```
- 사용자 등록
- 중복 체크
- 유효성 검증

#### 3. 기존 API 응답 형식 조정 ⭐ **수정 필요**
- 로그인 API 응답 구조 변경
- 로그아웃 API 응답 단순화
- 로그인 로그 API ISO8601 형식 적용

---

## 🛠️ 구현 계획

### Phase 1: 데이터 조회 API (우선순위: 높음)
1. **조달청 데이터 조회 API** 구현
   - 기본 CRUD 컨트롤러 생성
   - 페이징 로직 구현
   - 금액 집계 로직 구현
   - 필터링 기능 추가

### Phase 2: 인증 시스템 확장 (우선순위: 중간)
2. **회원가입 API** 추가
   - 사용자 등록 로직
   - 중복 체크 기능
   - 입력 유효성 검증

### Phase 3: 응답 형식 통일 (우선순위: 낮음)
3. **기존 API 응답 형식 조정**
   - 로그인/로그아웃 API 수정
   - 로그인 로그 API 날짜 형식 변경
   - 일관된 응답 구조 적용

### Phase 4: 문서화 및 테스트
4. **Swagger 문서 업데이트**
   - 새로운 API 스펙 추가
   - 예시 데이터 작성
   - 테스트 케이스 추가

---

## 📊 예상 작업량

| 단계 | 예상 시간 | 복잡도 | 우선순위 |
|------|----------|--------|---------|
| 조달청 데이터 API | 4-6시간 | 중간 | 🔥 높음 |
| 회원가입 API | 2-3시간 | 낮음 | ⚡ 중간 |
| 응답 형식 조정 | 1-2시간 | 낮음 | 📝 낮음 |
| 문서화 및 테스트 | 2-3시간 | 낮음 | 📚 낮음 |

**총 예상 시간**: 9-14시간

---

## 🎯 다음 단계 제안

### 즉시 시작 가능한 작업
1. **조달청 데이터 조회 API 구현** - 가장 중요하고 복잡한 기능
2. **회원가입 API 추가** - 사용자 관리 완성
3. **기존 API 응답 형식 조정** - 클라이언트 요구사항 준수

### 구현 순서 권장사항
1. 🥇 **조달청 데이터 API** (핵심 비즈니스 로직)
2. 🥈 **회원가입 API** (사용자 관리 완성)
3. 🥉 **응답 형식 통일** (클라이언트 호환성)

---

**📝 문서 작성**: 2025-06-23  
**🔄 다음 업데이트**: API 구현 완료 후  
**👨‍💻 상태**: 요구사항 분석 완료, 구현 대기 중 
# 엑셀 스펙 기반 API 구현 완료

## 📋 개요

`doc_file/지인테크 API 정보 정리.xlsx` 파일의 "요청 API 목록" 시트에 정의된 스펙에 따라 `/api/procurement/delivery-requests` API를 구현했습니다.

**현재 데이터 현황**:
- 품목 분류: 91건
- 물품: 622건
- 수요기관: 100건
- 계약업체: 66건
- 계약: 119건
- 납품요구: 215건
- 납품항목: 1,026건

## 🔧 구현된 기능

### 1. 필터 검색 항목 (엑셀 스펙)

| 파라미터명 | 설명 | 타입 | 예시 |
|---|---|---|---|
| `startDate` | 납품요구접수일자 시작일 | string | 2024-01-01 |
| `endDate` | 납품요구접수일자 종료일 | string | 2024-01-31 |
| `exclcProdctYn` | 구분(조달/마스/전체) | string | 조달 |
| `prdctClsfcNoNm` | 품명 | string | 영상감시장치 |
| `dtilPrdctClsfcNoNm` | 세부품명 | string | CCTV |
| `dminsttRgnNm` | 수요기관지역 | string | 경기도 |
| `dminsttNm` | 수요기관 | string | 전북특별자치도 |
| `corpNm` | 계약업체 | string | (주)지인테크 |
| `dlvrReqNmSearch` | 사업명(사용자 입력 검색) | string | 바라산휴양림팀 CCTV 조달 구매 계약 |
| `corpNameSearch` | 계약업체명(사용자 입력 검색) | string | 주식회사 비알인포텍 |
| `prdctClsfcNoNmSearch` | 품명(사용자 입력 검색) | string | 감시장치 |
| `prdctIdntNoNmSearch` | 품목(사용자 입력 검색) | string | 렌즈 |
| `page` | 페이지 번호 | integer | 1 |
| `pageSize` | 페이지당 데이터 수 | integer | 50 |
| `sortBy` | 정렬 기준 필드 | string | dlvrReqRcptDate |
| `sortOrder` | 정렬 순서 | string | desc |

### 2. 결과 테이블 컬럼 (엑셀 스펙)

| 응답 필드명 | 설명(실제 UI) | 타입 | 예시 |
|---|---|---|---|
| `exclcProdctYn` | 구분 | string | 조달 |
| `dlvrReqRcptDate` | 납품요구일자 | string | 2024-01-05 |
| `dminsttNm` | 수요기관 | string | 경기도 양평군 환경사업소 |
| `dminsttRgnNm` | 수요기관지역 | string | 경기도 양평군 |
| `corpNm` | 계약업체 | string | (주)지인테크 |
| `dlvrReqNm` | 사업명 | string | 관급자재 (영상감시설비) 구입 |
| `prdctClsfcNoNm` | 품명 | string | 영상감시장치 |
| `dtilPrdctClsfcNoNm` | 세부분류명 | string | CCTV |
| `prdctIdntNo` | 품목식별번호 | string | 123456 |
| `prdctIdntNoNm` | 품목 | string | 렌즈 |
| `incdecQty` | 증감수량 | integer | 10 |
| `prdctUprc` | 물품단가 | BigInt | -3516000 |
| `incdecAmt` | 증감금액 | BigInt | 1500000 |
| `dminsttCd` | 수요기관코드 | BigInt | 4170033 |

### 3. 필터 옵션 항목 (엑셀 스펙)

```json
{
  "filterOptions": {
    "prdctClsfcNoNms": [
      {"value": "영상감시장치", "label": "영상감시장치"}
    ],
    "dminsttNms": [
      {"value": "경기도청", "label": "경기도청"}
    ],
    "corpNms": [
      {"value": "(주)지인테크", "label": "(주)지인테크"}
    ]
  }
}
```

### 4. 오류 응답 (엑셀 스펙)

| HTTP 코드 | 예시 메시지 | 설명 |
|---|---|---|
| 400 | `{"error": "잘못된 파라미터 입니다"}` | 파라미터 오류 등 |
| 500 | `{"error": "데이터를 불러오지 못했습니다"}` | 서버 내부 오류 등 |

## 📁 수정된 파일들

### 1. Controller: `source/application/controllers/api/Procurement.php`
- `delivery_requests()` 메서드 수정
- 엑셀 스펙에 맞는 파라미터 처리
- 응답 구조 변경 (`pageSize` 필드명 변경, 필터 옵션 구조 변경)
- 에러 처리 개선

### 2. Model: `source/application/models/Procurement_model.php`
- `_apply_filters()` 메서드 업데이트
- 엑셀 스펙의 새로운 필터 파라미터들 처리
- 검색 기능 추가 (사용자 입력 검색)

### 3. OpenAPI 문서: `source/api/docs/openapi.json`
- API 문서 업데이트
- 엑셀 스펙에 맞는 파라미터 정의
- 응답 예시 변경

## 🚀 API 사용 예시

### 기본 조회
```bash
GET /api/procurement/delivery-requests?page=1&pageSize=50
```

### 날짜 필터링
```bash
GET /api/procurement/delivery-requests?page=1&pageSize=50&startDate=2024-01-01&endDate=2024-01-31
```

### 구분 필터링 (조달만)
```bash
GET /api/procurement/delivery-requests?page=1&pageSize=50&exclcProdctYn=조달
```

### 품명 검색
```bash
GET /api/procurement/delivery-requests?page=1&pageSize=50&prdctClsfcNoNmSearch=감시장치
```

### 수요기관 검색
```bash
GET /api/procurement/delivery-requests?page=1&pageSize=50&dminsttNm=경기도
```

### 정렬 적용
```bash
GET /api/procurement/delivery-requests?page=1&pageSize=50&sortBy=dlvrReqRcptDate&sortOrder=desc
```

## 🧪 테스트

Python 테스트 스크립트가 생성되었습니다: `test_procurement_api.py`

### 테스트 실행
```bash
python3 test_procurement_api.py http://localhost
```

### 테스트 항목
1. **기본 조회 테스트**: 페이징, 기본 응답 구조 확인
2. **필터 조회 테스트**: 각종 필터 파라미터 동작 확인
3. **오류 응답 테스트**: 인증 오류, 파라미터 오류 처리 확인

## 📊 응답 예시

```json
{
  "success": true,
  "message": "조달청 데이터 조회 성공",
  "data": {
    "page": 1,
    "pageSize": 50,
    "total": "1",
    "totalAmount": 2310708400,
    "jodalTotalAmount": 1503059300,
    "masTotalAmount": 807649100,
    "items": [
      {
        "exclcProdctYn": "조달",
        "dlvrReqRcptDate": "2024-01-05",
        "dminsttNm": "경기도 양평군 환경사업소",
        "dminsttRgnNm": "경기도 양평군",
        "corpNm": "(주)지인테크",
        "dlvrReqNm": "관급자재 (영상감시설비) 구입(지평 - 정보통신)",
        "prdctClsfcNoNm": "영상감시장치",
        "dtilPrdctClsfcNoNm": "CCTV",
        "prdctIdntNo": "123456",
        "prdctIdntNoNm": "렌즈",
        "incdecQty": 10,
        "prdctUprc": -3516000,
        "incdecAmt": 1500000,
        "dminsttCd": 4170033
      }
    ],
    "filterOptions": {
      "prdctClsfcNoNms": [
        {"value": "영상감시장치", "label": "영상감시장치"}
      ],
      "dminsttNms": [
        {"value": "경기도청", "label": "경기도청"}
      ],
      "corpNms": [
        {"value": "(주)지인테크", "label": "(주)지인테크"}
      ]
    }
  },
  "timestamp": "2025-06-23T10:30:00+00:00"
}
```

## ✅ 구현 완료 사항

1. **✅ 엑셀 스펙 완전 준수**: 모든 파라미터와 응답 구조를 엑셀 스펙에 맞춰 구현
2. **✅ 필터링 기능**: 모든 검색 및 필터 파라미터 구현
3. **✅ 페이징**: `page`, `pageSize` 파라미터 지원
4. **✅ 정렬**: `sortBy`, `sortOrder` 파라미터 지원
5. **✅ 에러 처리**: 400, 500 에러 메시지를 엑셀 스펙에 맞춰 구현
6. **✅ API 문서**: OpenAPI 문서 업데이트
7. **✅ 테스트 스크립트**: 종합적인 API 테스트 도구 제공

## 🔄 호환성

기존 API 호출과의 호환성을 위해 이전 파라미터들도 계속 지원합니다:
- `dlvrReqRcptDateFrom`, `dlvrReqRcptDateTo`
- `size` (대신 `pageSize` 권장)
- 기타 기존 필터 파라미터들

이제 엑셀 스펙에 정확히 맞는 API가 구현되어 사용할 수 있습니다! 🎉 
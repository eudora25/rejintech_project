# 🔍 데이터베이스 비교 보고서

## 📋 비교 개요

**비교 일시**: 2025년 6월 28일  
**비교 대상**: 로컬 환경 vs AWS 서버 (52.78.104.83)  
**데이터베이스**: jintech (MariaDB)  

## 📊 테이블 구조 비교 결과

### ✅ 테이블 목록 일치
- **로컬 테이블 수**: 14개
- **서버 테이블 수**: 14개
- **공통 테이블**: 14개 (100% 일치)

#### 공통 테이블 목록
1. `api_call_history` - API 호출 기록
2. `batch_logs` - 배치 작업 로그
3. `companies` - 업체 정보
4. `contracts` - 계약 정보
5. `delivery_request_details` - 납품 요구 상세
6. `delivery_request_items` - 납품 요구 품목
7. `delivery_requests` - 납품 요구
8. `filtering_companies` - 필터링 업체
9. `institutions` - 기관 정보
10. `login_logs` - 로그인 기록
11. `product_categories` - 제품 카테고리
12. `products` - 제품 정보
13. `user_tokens` - 사용자 토큰
14. `users` - 사용자 정보

## 📈 데이터 수량 비교

| 테이블명 | 로컬 | 서버 | 차이 | 상태 |
|---------|------|------|------|------|
| **api_call_history** | 1,859 | 1,375 | +484 | 🟡 로컬이 많음 |
| **batch_logs** | 51 | 51 | 0 | ✅ 동일 |
| **companies** | 66 | 0 | +66 | 🔴 서버 데이터 없음 |
| **contracts** | 119 | 0 | +119 | 🔴 서버 데이터 없음 |
| **delivery_request_details** | 1,026 | 1,026 | 0 | ✅ 동일 |
| **delivery_request_items** | 947 | 0 | +947 | 🔴 서버 데이터 없음 |
| **delivery_requests** | 215 | 0 | +215 | 🔴 서버 데이터 없음 |
| **filtering_companies** | 471 | 471 | 0 | ✅ 동일 |
| **institutions** | 100 | 0 | +100 | 🔴 서버 데이터 없음 |
| **login_logs** | 65 | 60 | +5 | 🟡 로컬이 약간 많음 |
| **product_categories** | 92 | 0 | +92 | 🔴 서버 데이터 없음 |
| **products** | 622 | 0 | +622 | 🔴 서버 데이터 없음 |
| **user_tokens** | 49 | 43 | +6 | 🟡 로컬이 약간 많음 |
| **users** | 2 | 2 | 0 | ✅ 동일 |

## 🔧 구조적 차이점

### 📋 delivery_requests 테이블
**차이점**: `delivery_request_date` 컬럼의 인덱스 설정 차이
- **로컬**: MUL (Multiple) 인덱스 설정됨
- **서버**: 인덱스 없음

#### 로컬 인덱스 구조
```
- PRIMARY: id
- UNIQUE: delivery_request_number, delivery_request_change_order  
- INDEX: contract_id, institution_id, company_id
- INDEX: delivery_request_date (성능 최적화용)
- COMPOSITE: delivery_request_date + is_excellent_product
```

## 🚨 주요 발견사항

### 1. 서버 데이터 부족 문제
**심각도**: 🔴 높음
- **핵심 테이블들에 데이터가 없음**:
  - `companies` (66개 → 0개)
  - `contracts` (119개 → 0개) 
  - `delivery_requests` (215개 → 0개)
  - `delivery_request_items` (947개 → 0개)
  - `institutions` (100개 → 0개)
  - `product_categories` (92개 → 0개)
  - `products` (622개 → 0개)

### 2. 정상 동기화된 테이블
**상태**: ✅ 양호
- `batch_logs`: 완전 동일 (51개)
- `delivery_request_details`: 완전 동일 (1,026개)
- `filtering_companies`: 완전 동일 (471개)
- `users`: 완전 동일 (2개)

### 3. 활동 로그 차이
**상태**: 🟡 경미
- `api_call_history`: 로컬이 484개 더 많음 (최근 테스트로 인함)
- `login_logs`: 로컬이 5개 더 많음
- `user_tokens`: 로컬이 6개 더 많음

## 📝 권장 조치사항

### 🔥 즉시 조치 필요
1. **서버 데이터 동기화 실행**
   ```bash
   # 서버에서 배치 작업 수동 실행
   docker exec rejintech-workspace php /var/www/html/application/controllers/batch/data_normalization.php
   ```

2. **핵심 데이터 테이블 복구**
   - companies (업체 정보)
   - institutions (기관 정보)  
   - product_categories & products (제품 정보)
   - contracts (계약 정보)
   - delivery_requests & items (납품 요구)

### 🔧 성능 최적화
1. **서버 인덱스 추가**
   ```sql
   -- delivery_requests 테이블 성능 개선
   ALTER TABLE delivery_requests ADD INDEX idx_delivery_date (delivery_request_date);
   ALTER TABLE delivery_requests ADD INDEX idx_date_excellent (delivery_request_date, is_excellent_product);
   ```

### 📊 모니터링 강화
1. **배치 작업 상태 점검**
   - 매일 새벽 1시 배치 작업 정상 실행 여부 확인
   - 데이터 동기화 실패 시 즉시 알림 설정

2. **데이터 일관성 점검**
   - 주간 데이터베이스 구조 비교 스크립트 실행
   - 데이터 수량 차이 모니터링

## 🎯 결론

### 긴급 상황
🚨 **서버의 핵심 비즈니스 데이터가 누락되어 있어 즉시 복구가 필요합니다.**

### 원인 분석
- 배치 작업이 정상 실행되지 않았거나
- 데이터 동기화 과정에서 오류 발생
- 서버 배포 후 초기 데이터 설정 누락

### 복구 우선순위
1. **1순위**: companies, institutions (기준 데이터)
2. **2순위**: product_categories, products (제품 정보)
3. **3순위**: contracts, delivery_requests (거래 데이터)

---
**다음 단계**: 서버 데이터 동기화 및 배치 작업 점검 필요 
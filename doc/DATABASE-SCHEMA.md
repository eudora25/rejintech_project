# 데이터베이스 스키마 문서

## 정규화된 테이블 구조

### product_categories (제품 카테고리)
- `id` (PK): 카테고리 ID
- `category_code`: 카테고리 코드
- `category_name`: 카테고리 명
- `parent_id` (FK): 상위 카테고리 ID
- `level`: 카테고리 레벨
- `is_active`: 활성화 여부
- `created_at`: 생성일시
- `updated_at`: 수정일시

### products (제품)
- `id` (PK): 제품 ID
- `product_code`: 제품 코드
- `product_name`: 제품 명
- `category_id` (FK): 카테고리 ID
- `unit`: 단위
- `is_excellent_product`: 우수제품 여부
- `is_active`: 활성화 여부
- `created_at`: 생성일시
- `updated_at`: 수정일시

### institutions (기관)
- `id` (PK): 기관 ID
- `institution_code`: 기관 코드
- `institution_name`: 기관 명
- `region_code`: 지역 코드
- `region_name`: 지역 명
- `parent_region_code`: 상위 지역 코드
- `is_active`: 활성화 여부
- `created_at`: 생성일시
- `updated_at`: 수정일시

### companies (업체)
- `id` (PK): 업체 ID
- `business_number`: 사업자 번호
- `company_name`: 업체 명
- `company_type`: 업체 유형 (대기업/중소기업/소상공인)
- `is_active`: 활성화 여부
- `created_at`: 생성일시
- `updated_at`: 수정일시

### contracts (계약)
- `id` (PK): 계약 ID
- `contract_number`: 계약 번호
- `contract_change_order`: 계약 변경 순번
- `contract_type`: 계약 유형
- `is_mas`: MAS 여부
- `is_excellent_product`: 우수제품 여부
- `is_construction_material`: 건설자재 여부
- `contract_start_date`: 계약 시작일
- `contract_end_date`: 계약 종료일
- `is_active`: 활성화 여부
- `created_at`: 생성일시
- `updated_at`: 수정일시

### delivery_requests (납품 요구)
- `id` (PK): 납품 요구 ID
- `delivery_request_number`: 납품 요구 번호
- `delivery_request_change_order`: 납품 요구 변경 순번
- `delivery_request_date`: 납품 요구 일자
- `delivery_request_name`: 납품 요구 명
- `contract_id` (FK): 계약 ID
- `institution_id` (FK): 기관 ID
- `company_id` (FK): 업체 ID
- `total_amount`: 총 금액
- `is_excellent_product`: 우수제품 여부
- `created_at`: 생성일시
- `updated_at`: 수정일시

### delivery_request_items (납품 요구 품목)
- `id` (PK): 납품 요구 품목 ID
- `delivery_request_id` (FK): 납품 요구 ID
- `product_id` (FK): 제품 ID
- `sequence_number`: 상세 순번
- `quantity`: 수량
- `unit_price`: 단가
- `total_price`: 총 금액
- `is_excellent_product`: 우수제품 여부
- `created_at`: 생성일시
- `updated_at`: 수정일시

## 유니크 키 제약조건

### product_categories
- `uk_category_code`: (category_code)

### products
- `uk_product_code`: (product_code)

### institutions
- `uk_institution_code`: (institution_code)
- `uk_region_code`: (region_code)

### companies
- `uk_business_number`: (business_number)

### contracts
- `uk_contract`: (contract_number, contract_change_order)

### delivery_requests
- `uk_delivery_request`: (delivery_request_number, delivery_request_change_order)

### delivery_request_items
- `uk_delivery_request_item`: (delivery_request_id, product_id, sequence_number)

## 외래 키 제약조건

### product_categories
- `fk_category_parent`: parent_id → product_categories.id

### products
- `fk_product_category`: category_id → product_categories.id

### delivery_requests
- `fk_delivery_request_contract`: contract_id → contracts.id
- `fk_delivery_request_institution`: institution_id → institutions.id
- `fk_delivery_request_company`: company_id → companies.id

### delivery_request_items
- `fk_delivery_request_item_request`: delivery_request_id → delivery_requests.id
- `fk_delivery_request_item_product`: product_id → products.id

## 인덱스

### institutions
- `idx_region`: (region_code, parent_region_code)
- `idx_institution_name`: (institution_name)

### delivery_requests
- `idx_delivery_date`: (delivery_request_date)
- `idx_excellent_product`: (is_excellent_product)
- `idx_company`: (company_id)
- `idx_institution`: (institution_id)

### delivery_request_items
- `idx_product`: (product_id)
- `idx_excellent_product`: (is_excellent_product)

## 문자셋 및 콜레이션
모든 테이블은 다음 설정을 사용합니다:
- 문자셋: utf8mb4
- 콜레이션: utf8mb4_unicode_ci 
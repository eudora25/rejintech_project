# 🔄 데이터 처리 아키텍처 가이드

**📅 최종 업데이트**: 2025년 6월 25일
**📊 문서 버전**: v1.0
**🎯 목적**: 데이터의 흐름을 표준화하고, API 성능과 데이터 무결성을 보장하기 위한 아키텍처 정의

---

## 🏛️ 아키텍처 개요

본 프로젝트의 데이터 파이프라인은 **임시 저장(Staging) → 정규화(Normalization) → API 제공(Serving)**의 3단계로 구성됩니다. 이 구조는 외부 데이터의 변경에 유연하게 대응하고, API 서버의 부하를 최소화하며, 데이터의 일관성을 유지하는 것을 목표로 합니다.

![Data Flow Diagram](https://i.imgur.com/example.png)  <!-- 다이어그램 예시 URL -->

```mermaid
graph TD
    A[외부 데이터] -->|데이터 수집| B(Staging Table<br/>delivery_request_details_raw);
    B -->|주기적 배치 실행<br/>(run_normalization.php)| C{데이터 정규화};
    C --> D[Master Tables<br/>institutions, companies, products];
    C --> E[Transactional Tables<br/>delivery_requests, delivery_request_items];
    subgraph API Serving Layer
        F(API<br/>/api/procurement/*)
    end
    D -->|JOIN| F;
    E -->|JOIN| F;
    G[클라이언트] -->|API 요청| F;
```

---

## 📁 1단계: 임시 데이터 저장 (Staging)

### 📋 역할
- 외부(조달청)로부터 받은 원본 데이터를 **가공 없이 그대로 저장**하는 임시 창고 역할을 합니다.
- 이 단계의 테이블은 데이터 정규화 과정의 입력으로만 사용되며, API가 직접 접근해서는 안 됩니다.

### 📝 테이블 명세
- **`delivery_request_details_raw`** (또는 기존의 `delivery_request_details`):
  - **목적**: 원본 데이터 임시 저장
  - **API 접근**: **엄격히 금지 (Strictly Forbidden)**
  - **데이터 수명**: 배치 작업이 완료되면 데이터를 비우거나(Truncate), 로그 보관 정책에 따라 일정 기간 유지 후 삭제할 수 있습니다.

---

## ⚙️ 2단계: 데이터 정규화 (Normalization)

### 📋 역할
- Staging 테이블의 데이터를 읽어와 중복을 제거하고, 관계형 데이터베이스 원칙에 따라 데이터를 분리하여 최종 서비스 테이블에 저장합니다.
- 이 과정은 **주기적으로 실행되는 배치 스크립트**에 의해 수행됩니다.

### 📜 배치 스크립트
- **파일 위치**: `scripts/run_normalization.php`
- **주요 작업**:
  1. `delivery_request_details_raw` 테이블의 모든 데이터를 조회합니다.
  2. **`institutions`**: 수요기관 정보를 정규화하여 저장합니다. (기존 로직)
  3. **`companies`**: 계약업체 정보를 정규화하여 저장합니다. (기존 로직)
  4. **`products`**: 품목 정보를 정규화하여 저장합니다. (기존 로직)
  5. **`delivery_requests`**: 납품 요구의 고유 정보(기관, 업체, 사업명 등)를 저장합니다. **(신규)**
  6. **`delivery_request_items`**: 납품 요구에 포함된 개별 품목 정보(품목, 수량, 단가 등)를 `delivery_requests` 테이블과 1:N 관계로 저장합니다. **(신규)**
  7. 작업 완료 후 `delivery_request_details_raw` 테이블을 비웁니다.

---

## 🚀 3단계: API 제공 (Serving)

### 📋 역할
- 사용자에게 데이터를 제공하는 API 계층입니다.
- **성능과 안정성**을 위해, 반드시 2단계에서 생성된 **정규화된 테이블만**을 조회해야 합니다.

### ⚖️ 핵심 규칙
- **API는 Staging 테이블(`delivery_request_details_raw`)을 절대 직접 조회해서는 안 됩니다.**
- 모든 데이터 조회는 `institutions`, `companies`, `products`, `delivery_requests`, `delivery_request_items` 테이블 간의 `JOIN`을 통해 이루어져야 합니다.
- 이를 통해 `JOIN` 시 인덱스를 최대한 활용하여 조회 속도를 극대화하고, 데이터의 무결성을 보장합니다.

### 💻 API 수정 방향
- **`Procurement_model.php`** 의 모든 `get_*` 함수들은 `delivery_request_details_raw` 대신, 정규화된 테이블들을 `JOIN`하여 데이터를 조회하도록 수정되어야 합니다.

---

## ✅ 적용 기대 효과

1.  **성능 향상**: 거대한 단일 테이블 조회를 피하고, 인덱스가 설정된 정규화된 테이블을 `JOIN`함으로써 응답 속도가 크게 개선됩니다.
2.  **데이터 무결성**: 중복 데이터가 제거되고 데이터가 원자적으로 관리되어 일관성이 보장됩니다.
3.  **유지보수성**: 데이터 처리 로직(배치)과 제공 로직(API)이 명확히 분리되어 시스템의 이해와 유지보수가 용이해집니다.
4.  **확장성**: 향후 외부 데이터 소스가 변경되거나 추가되더라도 Staging 영역과 배치 스크립트만 수정하면 되므로 API 계층에 미치는 영향이 최소화됩니다. 
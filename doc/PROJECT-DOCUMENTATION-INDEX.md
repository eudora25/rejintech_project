# 📚 Rejintech 프로젝트 문서 통합 인덱스

**📅 최종 업데이트**: 2025년 6월 24일  
**📊 문서 버전**: v1.0  
**🎯 목적**: 프로젝트 내 모든 문서의 체계적 정리 및 빠른 접근

---

## 📂 문서 분류 체계

### 🏠 1. 프로젝트 개요 및 시작 가이드

#### 📋 핵심 문서
- **[README.md](README.md)** (8.5KB, 246줄)
  - 📌 **프로젝트 메인 가이드**
  - 프로젝트 개요, 기술 스택, 빠른 시작 방법
  - API 엔드포인트 목록, 데이터베이스 정보
  - 개발 환경 설정 및 문제 해결

- **[FINAL-PROJECT-STATUS.md](FINAL-PROJECT-STATUS.md)** (9.5KB, 301줄)
  - 📊 **프로젝트 완료 현황 상세 보고서**
  - 구현 완료된 기능 체크리스트
  - API 응답 예시 및 성능 지표
  - 보안 기능 및 배포 환경 정보

### 🔧 2. 기술 설정 및 구성

#### ⚙️ 시스템 설정
- **[configuration-files.md](configuration-files.md)** (11KB, 468줄)
  - 🔧 **모든 설정 파일 상세 가이드**
  - Docker Compose, Nginx, PHP, MariaDB 설정
  - JWT 설정, CodeIgniter 설정
  - 환경별 구성 방법

#### 📖 API 문서화
- **[swagger-quick-start.md](swagger-quick-start.md)** (7.3KB, 240줄)
  - 📚 **Swagger UI 빠른 시작 가이드**
  - OpenAPI 3.0 스펙 설명
  - 인터랙티브 API 테스트 방법
  - 문서 업데이트 프로세스

### 🏗️ 3. 시스템 설계 및 분석

#### 📊 요구사항 분석
- **[API-REQUIREMENTS-ANALYSIS.md](API-REQUIREMENTS-ANALYSIS.md)** (7.4KB, 273줄)
  - 📋 **API 요구사항 상세 분석**
  - PowerPoint 스토리보드 기반 분석
  - 필터링 요구사항 및 응답 구조
  - 성능 요구사항

#### 🗄️ 데이터베이스 설계
- **[API-DATABASE-DESIGN.md](API-DATABASE-DESIGN.md)** (17KB, 519줄)
  - 🏗️ **정규화된 데이터베이스 설계 문서**
  - 7개 테이블 상세 설계 (institutions, companies, etc.)
  - 관계 설계 및 인덱스 전략
  - 데이터 마이그레이션 계획

### 🔐 4. 보안 및 인증

#### 🛡️ 토큰 시스템
- **[token-system-guide.md](token-system-guide.md)** (7.1KB, 264줄)
  - 🔑 **JWT 토큰 저장 및 검증 시스템**
  - 이중 토큰 검증 구조
  - 토큰 생명주기 관리
  - 보안 모범 사례

#### 🔒 비밀번호 관리
- **[password-change-guide.md](password-change-guide.md)** (6.6KB, 248줄)
  - 🔐 **비밀번호 변경 API 가이드** ⭐ **신규**
  - 안전한 비밀번호 변경 프로세스
  - 입력 검증 및 보안 규칙
  - 에러 처리 및 로깅

### 📊 5. 모니터링 및 로깅

#### 📈 로그인 추적
- **[login-logs-guide.md](login-logs-guide.md)** (7.4KB, 259줄)
  - 📊 **로그인 로그 시스템 가이드**
  - 로그인 시도 추적 및 분석
  - IP, User-Agent 기록
  - 통계 및 모니터링 기능

#### ⏰ 배치 작업
- **[batch-execution-guide.md](batch-execution-guide.md)** (7.3KB, 307줄)
  - ⚡ **조달청 데이터 배치 처리 가이드**
  - 자동화된 데이터 동기화
  - 스케줄링 및 모니터링
  - 오류 처리 및 복구 방법

### 📋 6. 기획 및 스토리보드

#### 🎨 UI/UX 설계
- **[실적사이트 개발 (스토리보드).pptx](실적사이트 개발 (스토리보드).pptx)** (486KB, 1609줄)
  - 🎨 **프로젝트 스토리보드 및 UI 설계**
  - 화면 구성 및 사용자 플로우
  - API 요구사항 정의
  - 기능별 상세 스펙

---

## 📖 문서 읽기 순서 가이드

### 🚀 처음 시작하는 개발자
1. **[README.md](README.md)** - 프로젝트 전체 이해
2. **[configuration-files.md](configuration-files.md)** - 개발 환경 설정
3. **[swagger-quick-start.md](swagger-quick-start.md)** - API 테스트 방법

### 🏗️ 시스템 설계를 이해하려는 경우
1. **[API-REQUIREMENTS-ANALYSIS.md](API-REQUIREMENTS-ANALYSIS.md)** - 요구사항 파악
2. **[API-DATABASE-DESIGN.md](API-DATABASE-DESIGN.md)** - 데이터베이스 구조
3. **[실적사이트 개발 (스토리보드).pptx](실적사이트 개발 (스토리보드).pptx)** - UI/UX 설계

### 🔐 보안 관련 작업
1. **[token-system-guide.md](token-system-guide.md)** - 토큰 시스템 이해
2. **[password-change-guide.md](password-change-guide.md)** - 비밀번호 관리
3. **[login-logs-guide.md](login-logs-guide.md)** - 로그인 모니터링

### 🚀 운영 및 배포
1. **[batch-execution-guide.md](batch-execution-guide.md)** - 배치 작업 관리
2. **[FINAL-PROJECT-STATUS.md](FINAL-PROJECT-STATUS.md)** - 완료 현황 확인

---

## 📊 문서 통계

### 📁 파일 현황
- **총 문서 수**: 11개
- **총 용량**: 약 577KB
- **총 라인 수**: 4,324줄

### 📝 카테고리별 분포
- **프로젝트 개요**: 2개 (18%)
- **기술 설정**: 2개 (18%)
- **시스템 설계**: 2개 (18%)
- **보안/인증**: 2개 (18%)
- **모니터링/로깅**: 2개 (18%)
- **기획/스토리보드**: 1개 (10%)

### 🔄 최신 업데이트 현황
- **완전 최신**: 10개 문서 (91%)
- **업데이트 필요**: 1개 문서 (9%)

---

## 🛠️ 문서 유지보수 가이드

### 📝 문서 작성 규칙
1. **마크다운 표준**: GitHub Flavored Markdown 사용
2. **이모지 활용**: 가독성 향상을 위한 적절한 이모지 사용
3. **코드 블록**: 언어별 신택스 하이라이팅 적용
4. **표 형식**: 정보 정리는 표 형식 적극 활용

### 🔄 업데이트 프로세스
1. **기능 추가 시**: 관련 문서 동시 업데이트
2. **버전 관리**: 주요 변경사항 시 버전 번호 업데이트
3. **상호 참조**: 관련 문서 간 링크 연결 유지
4. **검토 과정**: 기술적 정확성 및 가독성 검토

### 📋 추후 추가 예정 문서
- **Performance Monitoring Guide** - 성능 모니터링 가이드
- **Security Audit Checklist** - 보안 감사 체크리스트
- **API Rate Limiting Guide** - API 요청 제한 가이드
- **Database Backup Strategy** - 데이터베이스 백업 전략

---

## 🚀 빠른 링크

### 🔧 개발 관련
- [개발 환경 설정](configuration-files.md#-개발-환경-설정)
- [API 테스트](swagger-quick-start.md#-api-테스트)
- [데이터베이스 설정](configuration-files.md#-mariadb-설정)

### 📊 운영 관련
- [배치 작업 실행](batch-execution-guide.md#-배치-실행-방법)
- [로그인 로그 확인](login-logs-guide.md#-로그인-로그-조회)
- [시스템 모니터링](FINAL-PROJECT-STATUS.md#-성능-지표)

### 🔐 보안 관련
- [JWT 토큰 관리](token-system-guide.md#-토큰-관리)
- [비밀번호 정책](password-change-guide.md#-비밀번호-정책)
- [보안 설정](configuration-files.md#-보안-설정)

---

**📞 문의 및 지원**: 이 문서에 대한 질문이나 개선 사항이 있으시면 프로젝트 관리자에게 연락해 주세요. 
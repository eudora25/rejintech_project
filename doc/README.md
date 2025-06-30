# Rejintech 프로젝트 📚

## 📋 프로젝트 개요

Rejintech 프로젝트는 조달청 데이터 관리 및 조회를 위한 REST API 시스템입니다. Docker 기반의 현대적인 웹 애플리케이션으로, JWT 기반 인증, 로그인 로그 관리, 토큰 기반 세션 관리, 그리고 조달청 데이터 조회 API가 완전히 구현되어 있습니다.

### 🚀 주요 기능
- **완전한 Docker 환경**: 개발부터 배포까지 일관된 환경
- **CodeIgniter 3.x**: 안정적이고 검증된 PHP 프레임워크
- **JWT 인증 시스템**: Firebase JWT 기반 완전 구현
- **Swagger UI 통합**: 인터랙티브 API 문서 및 테스트 환경
- **PHP 8.1 호환성**: 최신 PHP 버전 완전 지원
- **MariaDB 통합**: 고성능 데이터베이스 솔루션
- **조달청 데이터 API**: 완전 구현된 조회 및 통계 API

### 🏗️ 기술 스택
- **컨테이너화**: Docker & Docker Compose
- **웹 서버**: Nginx 1.18.0 + PHP 8.1-FPM
- **프레임워크**: CodeIgniter 3.x
- **데이터베이스**: MariaDB 10.5+
- **API 문서**: Swagger UI 4.15.5
- **인증**: JWT (Firebase PHP-JWT)
- **프로세스 관리**: Supervisor

## 🚀 빠른 시작

### 1. 전제 조건 확인
```bash
# 필수 소프트웨어 설치 확인
docker --version          # Docker 20.10+
docker-compose --version  # Docker Compose 1.25+
```

### 2. 프로젝트 실행
```bash
# 프로젝트 디렉토리로 이동
cd rejintech_project

# Docker 컨테이너 빌드 및 실행
docker-compose up -d

# 실행 상태 확인
docker-compose ps
```

### 3. 서비스 접속 및 테스트
- **🏠 메인 페이지**: http://localhost/
- **📖 Swagger UI**: http://localhost/source/swagger-ui/
- **🔌 API 테스트**: http://localhost/api/test
- **📊 데이터베이스 테스트**: http://localhost/api/test/database

### 4. 로그인 및 API 테스트
```bash
# 로그인 (JWT 토큰 발급)
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin123"}'

# 조달청 데이터 조회 (토큰 필요)
curl -X GET "http://localhost/api/procurement/delivery-requests?page=1&size=20" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## 📁 디렉토리 구조

```
rejintech_project/
├── docker-compose.yml           # Docker 서비스 정의
├── images/ubuntu/              # Docker 이미지 설정
├── source/                     # CodeIgniter 애플리케이션
│   ├── application/            # CI 애플리케이션 로직
│   │   ├── controllers/api/    # API 컨트롤러 (Auth, Procurement 등)
│   │   ├── models/            # 데이터 모델
│   │   └── config/            # 설정 파일 (JWT 등)
│   ├── system/                # CodeIgniter 시스템 파일
│   ├── swagger-ui/            # Swagger UI 인터페이스
│   └── api/docs/              # OpenAPI 스펙 파일
├── mariadb_data/              # 데이터베이스 영구 저장소
└── doc/                       # 프로젝트 문서
```

## 🎯 API 엔드포인트

### 🔐 인증 관련
| 메서드 | 엔드포인트 | 설명 |
|--------|------------|------|
| POST | `/api/auth/login` | 사용자 로그인 (JWT 토큰 발급) |
| GET | `/api/auth/verify` | JWT 토큰 검증 |
| GET | `/api/auth/profile` | 사용자 프로필 조회 |
| POST | `/api/auth/check-login` | 로그인 상태 확인 |
| POST | `/api/auth/logout` | 로그아웃 |
| **POST** | **`/api/auth/change-password`** | **비밀번호 변경** ⭐ |
| GET | `/api/auth/login-logs` | 로그인 로그 조회 |
| GET | `/api/auth/login-statistics` | 로그인 통계 조회 |

### 🏢 조달청 데이터
| 메서드 | 엔드포인트 | 설명 |
|--------|------------|------|
| GET | `/api/procurement/delivery-requests` | 조달청 데이터 전체 리스트 조회 |
| GET | `/api/procurement/statistics/institutions` | 수요기관별 통계 조회 |
| GET | `/api/procurement/statistics/companies` | 업체별 통계 조회 |
| GET | `/api/procurement/statistics/products` | 품목별 통계 조회 |
| GET | `/api/procurement/filter-options` | 필터 옵션 조회 |

### 🔧 테스트 API
| 메서드 | 엔드포인트 | 설명 |
|--------|------------|------|
| GET | `/api/test` | 서버 상태 확인 |
| GET | `/api/test/database` | 데이터베이스 연결 테스트 |
| GET | `/api/docs/openapi.json` | OpenAPI 3.0 스펙 |

## 🗄️ 데이터베이스 정보

### 접속 정보
- **호스트**: localhost:3306
- **데이터베이스**: jintech
- **사용자**: jintech / jin2010!!

### 주요 테이블
- **users** (2건) - 사용자 정보
- **user_tokens** (6건) - JWT 토큰 관리
- **login_logs** (19건) - 로그인 이력
- **delivery_requests** (437건) - 조달청 납품요구 메인
- **delivery_request_items** (992건) - 조달청 납품요구 상세
- **institutions** (147건) - 수요기관 마스터
- **companies** (280건) - 업체 마스터
- **products** (758건) - 물품 마스터

## 📚 문서 가이드

### 📂 통합 문서 인덱스
**📖 [PROJECT-DOCUMENTATION-INDEX.md](PROJECT-DOCUMENTATION-INDEX.md)** - **📚 전체 문서 통합 인덱스** ⭐ **신규 추가**
- 모든 문서의 체계적 분류 및 정리
- 문서 읽기 순서 가이드
- 빠른 링크 및 검색 지원

### 📋 핵심 문서
1. **[README.md](README.md)** - 🏠 **이 파일** (프로젝트 메인 가이드)
2. **[FINAL-PROJECT-STATUS.md](FINAL-PROJECT-STATUS.md)** - 📊 **완료 현황 상세 문서** ⭐

### 🔧 상세 가이드
3. **[configuration-files.md](configuration-files.md)** - 설정 파일 상세 가이드
4. **[swagger-quick-start.md](swagger-quick-start.md)** - Swagger 빠른 시작

### 📋 분석 및 설계
5. **[API-REQUIREMENTS-ANALYSIS.md](API-REQUIREMENTS-ANALYSIS.md)** - API 요구사항 분석
6. **[API-DATABASE-DESIGN.md](API-DATABASE-DESIGN.md)** - 데이터베이스 설계 문서

### ⏰ 배치 및 로그
7. **[batch-execution-guide.md](batch-execution-guide.md)** - 조달청 데이터 배치 가이드
8. **[login-logs-guide.md](login-logs-guide.md)** - 로그인 로그 시스템 가이드
9. **[token-system-guide.md](token-system-guide.md)** - 토큰 저장 및 검증 가이드

### 🔐 보안 가이드
10. **[password-change-guide.md](password-change-guide.md)** - **비밀번호 변경 API 가이드** ⭐ **신규**

> 💡 **팁**: 전체 문서 구조와 읽기 순서는 [통합 문서 인덱스](PROJECT-DOCUMENTATION-INDEX.md)를 참조하세요!

## 🔧 개발 환경 설정

### 컨테이너 관리
```bash
# 개발 모드로 컨테이너 실행 (실시간 로그)
docker-compose up

# 컨테이너 접속
docker exec -it rejintech-workspace bash
docker exec -it rejintech-mariadb mysql -u jintech -p

# 서비스 중지
docker-compose down
```

### API 테스트 예시
```bash
# 1. 로그인 (JWT 토큰 발급)
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
  }'

# 3. 조달청 데이터 조회
curl -X GET "http://localhost/api/procurement/delivery-requests?page=1&size=10&type=CSO" \
  -H "Authorization: Bearer $TOKEN"

# 4. 통계 조회
curl -X GET "http://localhost/api/procurement/statistics/institutions" \
  -H "Authorization: Bearer $TOKEN"
```

## 🆘 문제 해결

### 일반적인 문제들

#### 1. 포트 충돌
```bash
# 포트 사용 확인
sudo netstat -tulpn | grep -E ':80|:443|:3306'

# 포트 변경: docker-compose.yml 수정 후 재시작
docker-compose down && docker-compose up -d
```

#### 2. 인증 오류
```bash
# JWT 토큰 확인
curl -X GET http://localhost/api/auth/verify \
  -H "Authorization: Bearer YOUR_TOKEN"

# 로그인 로그 확인
curl -X GET http://localhost/api/auth/login-logs \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 3. 로그 확인
```bash
# 전체 로그
docker-compose logs -f

# 특정 서비스 로그
docker-compose logs -f rejintech-workspace
docker-compose logs -f rejintech-mariadb
```

## 📊 시스템 현황

### 🎯 **프로젝트 상태**: ✅ 완전 구현 완료
- **인증 시스템**: JWT 기반 완전 구현
- **조달청 API**: 전체 조회/통계 API 완료
- **데이터베이스**: 정규화된 구조 완성 (13개 테이블, 2,199건 데이터)
- **API 문서**: Swagger UI 완전 통합
- **보안**: 이중 토큰 검증 시스템

### 📈 **데이터 현황**
- **총 납품요구 금액**: ₩6,237,229,274
- **납품요구 항목**: 992건
- **수요기관**: 147개
- **계약업체**: 280개
- **우수제품 비율**: 7.06%

---

**🎉 현재 상태**: 완전한 개발 환경 구축 완료!  
**⭐ 추천**: [FINAL-PROJECT-STATUS.md](FINAL-PROJECT-STATUS.md)에서 상세 완료 현황을 확인하세요.

# 프로젝트 문서

## 문서 구조

### 1. 시스템 아키텍처 및 설계
- [시스템 아키텍처](01-SYSTEM-ARCHITECTURE.md)
  - 시스템 개요
  - 아키텍처 구성
  - 데이터 처리 아키텍처
  - 시스템 구성요소

### 2. API 및 인터페이스
- [API 문서](02-API-INTERFACE.md)
  - API 개요
  - 인증 API
  - 조달 API
  - 통계 API
  - 공통 사항

### 3. 데이터베이스
- [데이터베이스 설계](03-DATABASE.md)
  - 데이터베이스 개요
  - 테이블 구조
  - 인덱스 및 제약조건
  - 데이터베이스 운영

### 4. 보안 및 인증
- [보안 문서](04-SECURITY.md)
  - 인증 시스템
  - 보안 정책
  - 접근 제어
  - 로깅 및 감사

### 5. 배치 프로세스
- [배치 프로세스](05-BATCH-PROCESS.md)
  - 배치 프로세스 개요
  - 데이터 수집
  - 데이터 정규화
  - 오류 처리

### 6. 운영 및 배포
- [운영 및 배포](06-DEPLOYMENT.md)
  - 배포 환경
  - 배포 프로세스
  - 모니터링
  - 장애 대응

### 7. 테스트 및 모니터링
- [테스트 문서](07-TESTING.md)
  - 테스트 전략
  - 성능 테스트
  - 모니터링
  - 품질 관리

## 기타 문서
- [부하 테스트 보고서](load_test_report.md)
- [배치 비교 보고서](batch_comparison_report.md)
- [데이터베이스 비교 보고서](database_comparison_report.md)

## 문서 작성 가이드
1. 모든 문서는 마크다운 형식으로 작성
2. 각 문서는 목차를 포함
3. 코드 예제는 적절한 언어 하이라이팅 사용
4. 다이어그램은 Mermaid 형식 사용
5. 표는 마크다운 테이블 형식 사용

## 문서 업데이트 이력
| 날짜 | 버전 | 설명 | 작성자 |
|------|------|------|--------|
| 2024-03-29 | 1.0.0 | 문서 구조 개편 | 시스템 |
| 2024-03-29 | 1.0.1 | API 문서 업데이트 | 시스템 | 
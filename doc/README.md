# Rejintech 프로젝트 📚

## 📋 프로젝트 개요

Rejintech 프로젝트는 Docker 기반의 현대적인 웹 애플리케이션 개발 환경으로, CodeIgniter 프레임워크와 Swagger API 문서화가 통합된 완전한 개발 솔루션입니다.

### 🚀 주요 기능
- **완전한 Docker 환경**: 개발부터 배포까지 일관된 환경
- **CodeIgniter 3.x**: 안정적이고 검증된 PHP 프레임워크
- **JWT 인증 시스템**: Firebase JWT 기반 보안 인증 준비
- **Swagger UI 통합**: 인터랙티브 API 문서 및 테스트 환경
- **PHP 8.1 호환성**: 최신 PHP 버전 완전 지원
- **MariaDB 통합**: 고성능 데이터베이스 솔루션

### 🏗️ 기술 스택
- **컨테이너화**: Docker & Docker Compose
- **웹 서버**: Nginx 1.18.0 + PHP 8.1-FPM
- **프레임워크**: CodeIgniter 3.x
- **데이터베이스**: MariaDB 10.5+
- **API 문서**: Swagger UI 4.15.5
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
- **🔌 API 테스트**: http://localhost/api/test
- **📊 데이터베이스 테스트**: http://localhost/api/test/database
- **📖 Swagger UI**: http://localhost/swagger-ui/

### 4. 기본 기능 확인
```bash
# API 상태 확인
curl http://localhost/api/test

# 데이터베이스 연결 테스트
curl http://localhost/api/test/database
```

## 📁 디렉토리 구조

```
rejintech_project/
├── docker-compose.yml           # Docker 서비스 정의
├── images/ubuntu/              # Docker 이미지 설정
├── source/                     # CodeIgniter 애플리케이션
│   ├── application/            # CI 애플리케이션 로직
│   ├── system/                # CodeIgniter 시스템 파일
│   ├── swagger-ui/            # Swagger UI 인터페이스
│   └── api/docs/              # OpenAPI 스펙 파일
├── mariadb_data/              # 데이터베이스 영구 저장소
└── doc/                       # 종합 프로젝트 문서
```

## 🎯 주요 URL 및 엔드포인트

### 🌐 웹 인터페이스
| 서비스 | URL | 설명 |
|--------|-----|------|
| 메인 페이지 | `http://localhost/` | CodeIgniter 메인 대시보드 |
| Swagger UI | `http://localhost/swagger-ui/` | 인터랙티브 API 문서 |

### 🔌 API 엔드포인트
| 엔드포인트 | 메서드 | 설명 |
|------------|--------|------|
| `/api/test` | GET | 서버 상태 확인 |
| `/api/test/database` | GET | 데이터베이스 연결 테스트 |
| `/api/test/params` | GET | GET 파라미터 테스트 |
| `/api/test/echo` | POST | POST 데이터 에코 테스트 |
| `/api/auth/login` | POST | 사용자 로그인 (JWT 토큰 발급) |
| `/api/auth/verify` | POST | JWT 토큰 검증 |
| `/api/auth/profile` | GET | 사용자 프로필 조회 |
| `/api/auth/check-login` | POST | 로그인 상태 확인 |
| `/api/auth/logout` | POST | 로그아웃 |
| `/api/auth/login-logs` | GET | 로그인 로그 조회 |
| `/api/auth/login-statistics` | GET | 로그인 통계 조회 |
| `/api/docs/openapi.json` | GET | OpenAPI 3.0 스펙 |

### 🗄️ 데이터베이스 접속
- **호스트**: localhost:3306
- **데이터베이스**: jintech
- **사용자**: jintech / jin2010!!

## 📚 문서 가이드

### 📋 종합 문서
1. **[PROJECT-SUMMARY.md](PROJECT-SUMMARY.md)** - 📋 **전체 작업 요약** (이 문서 권장)
2. **[README.md](README.md)** - 🏠 프로젝트 개요 (이 파일)

### 🔧 상세 가이드
3. **[development-environment.md](development-environment.md)** - 개발 환경 구축 가이드
4. **[configuration-files.md](configuration-files.md)** - 설정 파일 상세 가이드
5. **[codeigniter-setup.md](codeigniter-setup.md)** - CodeIgniter 설정 가이드

### 🌐 API 문서화
6. **[swagger-integration.md](swagger-integration.md)** - Swagger 통합 가이드
7. **[swagger-quick-start.md](swagger-quick-start.md)** - Swagger 빠른 시작

### ⏰ 배치 작업
8. **[batch-execution-guide.md](batch-execution-guide.md)** - 조달청 데이터 동기화 배치 실행 및 crontab 설정

### 🔐 보안 및 로그
9. **[login-logs-guide.md](login-logs-guide.md)** - 로그인 로그 시스템 가이드 ⭐
10. **[token-system-guide.md](token-system-guide.md)** - 토큰 저장 및 검증 시스템 가이드 ⭐

### 📊 현재 상태 및 요구사항
11. **[CURRENT-SYSTEM-STATUS.md](CURRENT-SYSTEM-STATUS.md)** - **전체 시스템 현재 상태 종합 문서** ⭐ **신규**
12. **[API-REQUIREMENTS-ANALYSIS.md](API-REQUIREMENTS-ANALYSIS.md)** - **클라이언트 API 요구사항 분석** ⭐ **신규**
13. **[API-DATABASE-DESIGN.md](API-DATABASE-DESIGN.md)** - **API용 정규화된 데이터베이스 설계** ⭐ **신규**

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

## 🆘 문제 해결

### 일반적인 문제들

#### 1. 포트 충돌
```bash
# 포트 사용 확인
sudo netstat -tulpn | grep -E ':80|:443|:3306'

# 포트 변경: docker-compose.yml 수정 후 재시작
docker-compose down && docker-compose up -d
```

#### 2. 권한 문제
```bash
# 파일 권한 설정
docker exec -it rejintech-workspace chown -R www-data:www-data /var/www/html
docker exec -it rejintech-workspace chmod -R 777 /var/www/html/application/cache
```

#### 3. 로그 확인
```bash
# 전체 로그
docker-compose logs -f

# 특정 서비스 로그
docker-compose logs -f rejintech-workspace
docker-compose logs -f rejintech-mariadb
```

## 🎯 다음 단계

### 즉시 시작 가능한 작업
1. **실제 API 개발**: `/source/application/controllers/api/` 디렉토리에서 시작
2. **데이터베이스 설계**: MariaDB에 비즈니스 테이블 생성
3. **JWT 인증 구현**: 이미 설치된 Firebase JWT 라이브러리 활용
4. **프론트엔드 연동**: API를 호출하는 웹 또는 모바일 앱 개발

---

**🎉 현재 상태**: 완전한 개발 환경 구축 완료!  
**⭐ 추천**: [PROJECT-SUMMARY.md](PROJECT-SUMMARY.md)에서 전체 작업 내용을 확인하세요. 
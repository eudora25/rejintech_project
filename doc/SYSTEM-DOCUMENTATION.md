# 시스템 문서화

## 1. 시스템 아키텍처

시스템은 Docker 기반의 마이크로서비스 아키텍처로 구성되어 있습니다:

### 1.1 컨테이너 구성
- **Ubuntu 컨테이너** (rejintech-workspace)
  - PHP/CodeIgniter 애플리케이션 호스팅
  - Nginx 웹 서버
  - 포트 매핑: 80, 443
  
- **MariaDB 컨테이너** (rejintech-mariadb)
  - 데이터베이스 서버
  - 포트 매핑: 3306

### 1.2 볼륨 마운트
- 소스 코드: `./source:/var/www/html`
- Nginx 설정: `./images/ubuntu/conf/nginx.conf:/etc/nginx/conf.d/default.conf`
- MariaDB 데이터: `./mariadb_data:/var/lib/mysql`

## 2. 데이터베이스 구성

### 2.1 기본 설정
- 데이터베이스명: jintech
- 문자셋: utf8mb4
- 콜레이션: utf8mb4_unicode_ci

### 2.2 테이블 구조
1. **사용자 관련 테이블**
   - users: 사용자 정보
   - user_tokens: 사용자 토큰 정보
   - login_logs: 로그인 기록

2. **제품 관련 테이블**
   - product_categories: 제품 카테고리
   - products: 제품 정보

3. **기관/업체 관련 테이블**
   - institutions: 기관 정보
   - companies: 업체 정보
   - filtering_companies: 필터링된 업체 목록

4. **계약/납품 관련 테이블**
   - contracts: 계약 정보
   - delivery_requests: 납품 요구 정보
   - delivery_request_items: 납품 요구 품목 정보
   - delivery_request_details: 납품 요구 상세 정보

5. **시스템 관련 테이블**
   - api_call_history: API 호출 기록
   - batch_logs: 배치 작업 로그

## 3. 애플리케이션 설정

### 3.1 환경 설정
- 환경: development
- 디버그 모드: 활성화
- 기본 URL: http://localhost

### 3.2 JWT 설정
- 알고리즘: HS256
- 토큰 만료 시간: 3600초 (1시간)
- 리프레시 토큰 만료 시간: 604800초 (7일)
- 리프레시 토큰 기능: 비활성화

### 3.3 데이터베이스 연결 설정
- 호스트: rejintech-mariadb
- 사용자: jintech
- 데이터베이스: jintech
- 문자셋: utf8mb4
- 콜레이션: utf8mb4_unicode_ci

## 4. 보안 설정

### 4.1 데이터베이스 보안
- root 비밀번호: 설정됨 (프로덕션 환경에서 변경 필요)
- jintech 사용자: 해당 데이터베이스에 대한 모든 권한 보유

### 4.2 JWT 보안
- 시크릿 키: 개발용 키 사용 중 (프로덕션 환경에서 변경 필요)
- 토큰 발급자: rejintech
- 토큰 대상자: rejintech_users

## 5. 시스템 요구사항

### 5.1 하드웨어 요구사항
- 최소 4GB RAM
- 최소 20GB 저장공간

### 5.2 소프트웨어 요구사항
- Docker Engine 24.0 이상
- Docker Compose 2.0 이상
- 호스트 OS: Linux, macOS, Windows with WSL2 
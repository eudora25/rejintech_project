# PHP 8.4 버전 업데이트 가이드

## 📋 업데이트 개요
현재 프로젝트의 PHP 버전을 8.1에서 8.4.6으로 업데이트하여 AWS 환경과 동일하게 맞춥니다.

## 🔧 변경된 파일 목록

### 1. Docker 설정 파일
- `images/ubuntu/Dockerfile` - PHP 8.1 → 8.4 패키지 변경
- `images/ubuntu/conf/supervisord.conf` - PHP-FPM 8.4 경로 수정
- `images/ubuntu/conf/nginx.conf` - PHP-FPM 소켓 경로 수정

### 2. 스크립트 파일
- `scripts/update_php_version.sh` - 자동 업데이트 스크립트 (신규)

### 3. 문서 파일
- `aws-deployment-guide.md` - PHP 8.4 관련 내용 업데이트
- `PHP-8.4-UPDATE-GUIDE.md` - 이 가이드 문서 (신규)

## 🚀 업데이트 실행 방법

### 방법 1: 자동 스크립트 사용 (권장)
```bash
# 프로젝트 루트 디렉토리에서 실행
./scripts/update_php_version.sh
```

### 방법 2: 수동 업데이트
```bash
# 1. 기존 컨테이너 중지
docker-compose down --volumes

# 2. Docker 이미지 캐시 정리
docker system prune -f
docker rmi $(docker images -q rejintech*)

# 3. 새 이미지 빌드
docker-compose build --no-cache

# 4. 컨테이너 시작
docker-compose up -d

# 5. PHP 버전 확인
docker exec rejintech-workspace php -v
```

## ✅ 업데이트 후 확인 사항

### 1. PHP 버전 확인
```bash
docker exec rejintech-workspace php -v
# 출력 예시: PHP 8.4.6 (cli) (built: ...)
```

### 2. 웹서버 응답 확인
```bash
curl -I http://localhost
# HTTP/1.1 200 OK 또는 403 Forbidden 응답 확인
```

### 3. 데이터베이스 연결 확인
```bash
docker exec rejintech-workspace php -r "
require_once '/var/www/html/index.php';
\$CI =& get_instance();
\$CI->load->database();
echo \$CI->db->initialize() ? 'DB 연결 성공' : 'DB 연결 실패';
"
```

### 4. PHP 모듈 확인
```bash
docker exec rejintech-workspace php -m | grep -E "(mysql|curl|gd|mbstring|xml)"
```

### 5. 웹 브라우저에서 확인
- 메인 페이지: http://localhost
- API 문서: http://localhost/swagger-ui/

## 🔍 PHP 8.4의 주요 변화점

### 새로운 기능
- **Property Hooks**: 클래스 프로퍼티에 getter/setter 자동 생성
- **Asymmetric Visibility**: 프로퍼티의 읽기/쓰기 권한 분리
- **New Array Functions**: `array_find()`, `array_find_key()`, `array_any()`, `array_all()` 추가
- **Request Parameter Updates**: `$_GET`, `$_POST`, `$_COOKIE` 자동 deep-trimming

### 성능 개선
- JIT 컴파일러 향상
- 메모리 사용량 최적화
- 더 빠른 배열 처리

### Deprecated 기능
- `mysql_*` 함수들 (이미 제거됨, mysqli 사용)
- 일부 레거시 함수들

## 🛠 문제 해결

### 일반적인 문제들

#### 1. 컨테이너 빌드 실패
```bash
# 원인: 패키지 저장소 업데이트 필요
# 해결: Dockerfile에서 apt-get update 재실행

docker-compose build --no-cache --pull
```

#### 2. PHP 모듈 누락
```bash
# 확인
docker exec rejintech-workspace php -m

# 특정 모듈 설치 (컨테이너 내부에서)
docker exec -it rejintech-workspace bash
apt-get update
apt-get install php8.4-[module_name]
```

#### 3. Nginx 502 Bad Gateway
```bash
# PHP-FPM 상태 확인
docker exec rejintech-workspace service php8.4-fpm status

# 로그 확인
docker-compose logs nginx
docker-compose logs ubuntu
```

#### 4. 데이터베이스 연결 실패
```bash
# 데이터베이스 컨테이너 상태 확인
docker-compose logs db

# 연결 설정 확인
cat source/application/config/database.php
```

## 📊 성능 비교

### PHP 8.1 vs PHP 8.4
- **실행 속도**: 약 5-10% 향상
- **메모리 사용량**: 약 3-5% 감소
- **JIT 성능**: 15-20% 향상 (계산 집약적 작업)

### 벤치마크 테스트
```bash
# 간단한 성능 테스트
docker exec rejintech-workspace php -r "
\$start = microtime(true);
for (\$i = 0; \$i < 1000000; \$i++) {
    \$arr[] = \$i * 2;
}
echo '실행 시간: ' . (microtime(true) - \$start) . '초';
"
```

## 🔒 보안 강화 사항

### PHP 8.4의 보안 개선
- 더 엄격한 타입 체크
- 향상된 암호화 기능
- 보안 헤더 자동 설정

### 권장 보안 설정
```php
// php.ini 설정 (컨테이너 내부)
expose_php = Off
display_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
```

## 📝 롤백 가이드

### 문제 발생 시 PHP 8.1로 롤백
```bash
# 1. Git을 통한 롤백 (변경사항이 커밋된 경우)
git revert [commit_hash]

# 2. 수동 롤백
# Dockerfile에서 php8.4를 php8.1로 변경
# supervisord.conf에서 8.4를 8.1로 변경  
# nginx.conf에서 php8.4-fpm.sock을 php8.1-fpm.sock로 변경

# 3. 컨테이너 재빌드
docker-compose down --volumes
docker-compose build --no-cache
docker-compose up -d
```

## 🚀 AWS 배포 시 추가 고려사항

### 1. EC2 인스턴스 리소스
- PHP 8.4는 8.1보다 약간 더 많은 메모리 사용
- t3.medium 이상 권장 (기존과 동일)

### 2. 모니터링 강화
```bash
# 메모리 사용량 모니터링
docker stats rejintech-workspace

# CPU 사용량 확인
top -p $(docker inspect -f '{{.State.Pid}}' rejintech-workspace)
```

### 3. 로그 모니터링
```bash
# PHP 오류 로그 확인
docker exec rejintech-workspace tail -f /var/log/php8.4-fpm.log

# Nginx 오류 로그 확인
docker exec rejintech-workspace tail -f /var/log/nginx/error.log
```

## 📅 업데이트 체크리스트

- [ ] PHP 버전 확인 (8.4.6)
- [ ] 웹서버 응답 테스트
- [ ] 데이터베이스 연결 테스트
- [ ] 필수 PHP 모듈 확인
- [ ] Composer 동작 확인
- [ ] API 엔드포인트 테스트
- [ ] 배치 스크립트 테스트
- [ ] Swagger UI 접근 확인
- [ ] 로그 파일 정상 생성 확인
- [ ] 성능 테스트 (선택사항)

## 🎯 다음 단계

1. **개발 환경에서 충분히 테스트**
2. **AWS 스테이징 환경에 배포**
3. **운영 환경 배포 계획 수립**
4. **모니터링 및 알림 설정**
5. **백업 정책 재검토**

---

**⚠️ 중요 알림**: 운영 환경 배포 전에 반드시 스테이징 환경에서 충분한 테스트를 진행하세요! 
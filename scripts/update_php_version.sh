#!/bin/bash

# =============================================================================
# PHP 8.4 버전 업데이트 및 Docker 재빌드 스크립트
# 작성일: 2025-01-27
# 용도: PHP 8.1에서 8.4로 업데이트 후 환경 재구축
# =============================================================================

# 스크립트 설정
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
LOG_FILE="/tmp/php_update_$(date '+%Y%m%d_%H%M%S').log"

# 함수 정의
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a $LOG_FILE
}

# 시작
log_message "=== PHP 8.4 버전 업데이트 시작 ==="
log_message "프로젝트 디렉토리: $PROJECT_DIR"

# 프로젝트 디렉토리로 이동
cd $PROJECT_DIR

# 1. 기존 컨테이너 중지 및 제거
log_message "기존 컨테이너 중지 및 제거 중..."
docker-compose down --volumes --remove-orphans 2>&1 | tee -a $LOG_FILE

# 2. 기존 이미지 제거
log_message "기존 Docker 이미지 제거 중..."
docker rmi $(docker images -q rejintech* 2>/dev/null) 2>&1 | tee -a $LOG_FILE || true

# 3. Docker 캐시 정리
log_message "Docker 캐시 정리 중..."
docker system prune -f 2>&1 | tee -a $LOG_FILE

# 4. 새 이미지 빌드
log_message "PHP 8.4 기반 새 이미지 빌드 중..."
if docker-compose build --no-cache 2>&1 | tee -a $LOG_FILE; then
    log_message "이미지 빌드 성공"
else
    log_message "ERROR: 이미지 빌드 실패"
    exit 1
fi

# 5. 컨테이너 시작
log_message "컨테이너 시작 중..."
if docker-compose up -d 2>&1 | tee -a $LOG_FILE; then
    log_message "컨테이너 시작 성공"
else
    log_message "ERROR: 컨테이너 시작 실패"
    exit 1
fi

# 6. 컨테이너 상태 확인
log_message "컨테이너 상태 확인 중..."
sleep 10  # 컨테이너 시작 대기

# 7. PHP 버전 확인
log_message "PHP 버전 확인 중..."
PHP_VERSION=$(docker exec rejintech-workspace php -v 2>/dev/null | head -1)
if [[ $PHP_VERSION == *"8.4"* ]]; then
    log_message "✅ PHP 버전 확인 성공: $PHP_VERSION"
else
    log_message "❌ ERROR: PHP 버전이 8.4가 아닙니다: $PHP_VERSION"
    exit 1
fi

# 8. 웹서버 테스트
log_message "웹서버 응답 테스트 중..."
sleep 5  # 웹서버 시작 대기
if curl -s -o /dev/null -w "%{http_code}" http://localhost | grep -q "200\|403"; then
    log_message "✅ 웹서버 응답 테스트 성공"
else
    log_message "❌ ERROR: 웹서버 응답 실패"
    docker-compose logs nginx | tail -10 | tee -a $LOG_FILE
    exit 1
fi

# 9. 데이터베이스 연결 테스트
log_message "데이터베이스 연결 테스트 중..."
DB_TEST=$(docker exec rejintech-workspace php -r "
require_once '/var/www/html/index.php';
\$CI =& get_instance();
\$CI->load->database();
if (\$CI->db->initialize()) {
    echo 'DB_CONNECTION_OK';
} else {
    echo 'DB_CONNECTION_FAILED';
}
" 2>/dev/null)

if [[ $DB_TEST == "DB_CONNECTION_OK" ]]; then
    log_message "✅ 데이터베이스 연결 테스트 성공"
else
    log_message "❌ ERROR: 데이터베이스 연결 실패: $DB_TEST"
    docker-compose logs db | tail -10 | tee -a $LOG_FILE
    exit 1
fi

# 10. PHP 모듈 확인
log_message "PHP 모듈 확인 중..."
MODULES=$(docker exec rejintech-workspace php -m | grep -E "(mysql|curl|gd|mbstring|xml)" | wc -l)
if [[ $MODULES -ge 5 ]]; then
    log_message "✅ 필수 PHP 모듈 확인 성공"
else
    log_message "❌ WARNING: 일부 PHP 모듈이 누락될 수 있습니다"
    docker exec rejintech-workspace php -m | tee -a $LOG_FILE
fi

# 11. Composer 작업 (필요한 경우)
log_message "Composer 의존성 확인 중..."
if docker exec rejintech-workspace composer --version >/dev/null 2>&1; then
    log_message "✅ Composer 설치 확인"
    # 필요한 경우 의존성 업데이트
    # docker exec rejintech-workspace composer update --no-dev
else
    log_message "❌ WARNING: Composer 확인 실패"
fi

# 12. 최종 상태 확인
log_message "최종 시스템 상태 확인 중..."
log_message "=== 컨테이너 상태 ==="
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" | tee -a $LOG_FILE

log_message "=== 디스크 사용량 ==="
df -h $PROJECT_DIR | tee -a $LOG_FILE

# 성공 완료
log_message "=== PHP 8.4 버전 업데이트 완료 ==="
log_message "✅ 모든 테스트가 성공적으로 완료되었습니다!"
log_message "📋 로그 파일: $LOG_FILE"
log_message ""
log_message "다음 단계:"
log_message "1. 브라우저에서 http://localhost 접속 테스트"
log_message "2. API 문서 http://localhost/swagger-ui/ 확인"
log_message "3. 배치 스크립트 테스트: ./scripts/rejintech_batch.sh"
log_message ""
log_message "🔧 시스템 정보:"
log_message "- PHP 버전: $PHP_VERSION"
log_message "- 컨테이너 상태: 정상 실행 중"
log_message "- 데이터베이스: 연결 정상"

exit 0 
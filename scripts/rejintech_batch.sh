#!/bin/bash

# =============================================================================
# Rejintech 조달청 데이터 동기화 배치 스크립트
# 작성일: 2025-06-23
# 용도: crontab에서 자동 실행
# =============================================================================

# 스크립트 설정
SCRIPT_DIR="/Users/hoony/Desktop/dev-work/rejintech_project"  # 실제 프로젝트 경로로 수정 필요
LOG_DIR="/var/log/rejintech"
DATE=$(date '+%Y%m%d_%H%M%S')
LOG_FILE="$LOG_DIR/batch_$DATE.log"
LOCK_FILE="/tmp/rejintech_batch.lock"

# 함수 정의
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> $LOG_FILE
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

cleanup() {
    rm -f $LOCK_FILE
}

# 시그널 핸들러 설정
trap cleanup EXIT

# 시작
log_message "=== Rejintech 배치 시작 ==="

# 중복 실행 방지
if [ -f $LOCK_FILE ]; then
    log_message "ERROR: 배치가 이미 실행 중입니다. (Lock file: $LOCK_FILE)"
    exit 1
fi

# 락 파일 생성
touch $LOCK_FILE

# 로그 디렉토리 생성
mkdir -p $LOG_DIR

# 프로젝트 디렉토리로 이동
if [ ! -d "$SCRIPT_DIR" ]; then
    log_message "ERROR: 프로젝트 디렉토리를 찾을 수 없습니다: $SCRIPT_DIR"
    exit 1
fi

cd $SCRIPT_DIR
log_message "프로젝트 디렉토리로 이동: $SCRIPT_DIR"

# Docker 상태 확인
log_message "Docker 컨테이너 상태 확인 중..."
if ! docker ps | grep -q "rejintech-workspace"; then
    log_message "ERROR: rejintech-workspace 컨테이너가 실행되지 않았습니다."
    log_message "컨테이너 시작을 시도합니다..."
    
    # 컨테이너 시작 시도
    if docker-compose up -d; then
        log_message "컨테이너 시작 성공. 30초 대기 중..."
        sleep 30
    else
        log_message "ERROR: 컨테이너 시작 실패"
        exit 1
    fi
fi

# 데이터베이스 연결 확인
log_message "데이터베이스 연결 확인 중..."
if ! docker exec rejintech-workspace php -r "
require_once '/var/www/html/index.php';
\$CI =& get_instance();
\$CI->load->database();
if (\$CI->db->initialize()) {
    echo 'DB_OK';
} else {
    echo 'DB_ERROR';
}
" | grep -q "DB_OK"; then
    log_message "ERROR: 데이터베이스 연결 실패"
    exit 1
fi

log_message "데이터베이스 연결 확인 완료"

# 실행 시간 측정 시작
START_TIME=$(date +%s)
log_message "배치 실행 시작..."

# 배치 실행
docker exec rejintech-workspace php /var/www/html/index.php batch/procurement_sync sync_delivery_requests >> $LOG_FILE 2>&1
BATCH_EXIT_CODE=$?

# 실행 시간 계산
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

# 결과 확인
if [ $BATCH_EXIT_CODE -eq 0 ]; then
    log_message "배치 실행 성공 (실행 시간: ${DURATION}초)"
    
    # 실행 결과 통계 확인
    STATS=$(docker exec rejintech-workspace php -r "
    require_once '/var/www/html/index.php';
    \$CI =& get_instance();
    \$CI->load->database();
    \$log = \$CI->db->select('*')->from('batch_logs')->order_by('id', 'DESC')->limit(1)->get()->row_array();
    if (\$log) {
        echo \"성공: {\$log['success_count']}건, 오류: {\$log['error_count']}건, API호출: {\$log['api_call_count']}회\";
    }
    ")
    
    log_message "배치 결과: $STATS"
    
else
    log_message "ERROR: 배치 실행 실패 (Exit Code: $BATCH_EXIT_CODE, 실행 시간: ${DURATION}초)"
    
    # 실패 알림 (이메일 또는 Slack - 필요시 활성화)
    # echo "Rejintech 배치 실행 실패 - $(date)" | mail -s "배치 오류 알림" admin@company.com
    
    exit 1
fi

# 오래된 로그 파일 정리 (30일 이상)
log_message "오래된 로그 파일 정리 중..."
CLEANED_COUNT=$(find $LOG_DIR -name "batch_*.log" -mtime +30 -delete -print | wc -l)
if [ $CLEANED_COUNT -gt 0 ]; then
    log_message "정리된 로그 파일: ${CLEANED_COUNT}개"
fi

# 디스크 사용량 확인
DISK_USAGE=$(df -h $LOG_DIR | tail -1 | awk '{print $5}')
log_message "로그 디렉토리 디스크 사용량: $DISK_USAGE"

log_message "=== Rejintech 배치 완료 ==="

# 성공 종료
exit 0 
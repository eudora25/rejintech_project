#!/bin/bash

# =============================================================================
# Rejintech 조달청 데이터 동기화 배치 스크립트
# 작성일: 2025-06-23
# 용도: crontab에서 자동 실행
# =============================================================================

# 스크립트 설정
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
LOG_DIR="/var/log/rejintech"
DATE=$(date '+%Y%m%d_%H%M%S')
LOG_FILE="$LOG_DIR/batch_$DATE.log"

# 색상 정의
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 로그 함수
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

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

# 로그 디렉토리 생성
sudo mkdir -p $LOG_DIR
sudo chown $USER:$USER $LOG_DIR
sudo chmod 755 $LOG_DIR

# 프로젝트 디렉토리로 이동
cd $PROJECT_DIR

echo "=== Rejintech 배치 시작 - $(date) ===" >> $LOG_FILE

# Docker 컨테이너 상태 확인
if ! docker ps | grep -q "rejintech-workspace"; then
    echo "Error: rejintech-workspace 컨테이너가 실행되지 않았습니다." >> $LOG_FILE
    exit 1
fi

# 배치 종류 확인
BATCH_TYPE=$1
if [ -z "$BATCH_TYPE" ]; then
    echo "Error: 배치 종류를 지정해주세요." >> $LOG_FILE
    echo "사용법: $0 [normalize_delivery_data]" >> $LOG_FILE
    exit 1
fi

# 락 파일 설정
LOCK_FILE="/tmp/rejintech_${BATCH_TYPE}.lock"

if [ -f $LOCK_FILE ]; then
    echo "Error: 배치가 이미 실행 중입니다." >> $LOG_FILE
    exit 1
fi

# 락 파일 생성
touch $LOCK_FILE

# 시작 시간 기록
START_TIME=$(date +%s)

# 배치 실행
echo "배치 실행 시작... (타입: $BATCH_TYPE)" >> $LOG_FILE

case $BATCH_TYPE in
    "normalize_delivery_data")
        docker exec rejintech-workspace php /var/www/html/index.php batch/data_normalization normalize_delivery_data >> $LOG_FILE 2>&1
        ;;
    *)
        echo "Error: 알 수 없는 배치 종류입니다: $BATCH_TYPE" >> $LOG_FILE
        rm -f $LOCK_FILE
        exit 1
        ;;
esac

# 실행 결과 확인
RESULT=$?

# 종료 시간 기록
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

if [ $RESULT -eq 0 ]; then
    echo "배치 실행 성공 - 소요시간: ${DURATION}초 - $(date)" >> $LOG_FILE
else
    echo "배치 실행 실패 - 소요시간: ${DURATION}초 - $(date)" >> $LOG_FILE
fi

# 락 파일 제거
rm -f $LOCK_FILE

echo "=== Rejintech 배치 완료 - $(date) ===" >> $LOG_FILE

# 오래된 로그 파일 정리 (30일 이상)
find $LOG_DIR -name "batch_*.log" -mtime +30 -delete

exit $RESULT 
# 배치 실행 가이드

## 📋 개요

Rejintech 프로젝트의 조달청 납품요구 상세정보 동기화 배치 실행 가이드입니다. 이 문서는 수동 실행부터 crontab을 이용한 자동 실행까지 모든 방법을 다룹니다.

## 🚀 배치 정보

### 배치명
- **파일**: `source/application/controllers/batch/Procurement_sync.php`
- **메소드**: `sync_delivery_requests`
- **기능**: 조달청 종합쇼핑몰 납품요구 상세정보 API 데이터 동기화

### 처리 내용
- 조달청 API에서 납품요구 상세정보 조회
- 데이터 변환 및 검증
- 데이터베이스 저장 (신규/업데이트)
- 실행 로그 및 통계 기록

## 🔧 수동 실행 방법

### 1. Docker 컨테이너에서 직접 실행
```bash
# 프로젝트 디렉토리로 이동
cd /path/to/rejintech_project

# 배치 실행
docker exec -it rejintech-workspace php /var/www/html/index.php batch/procurement_sync sync_delivery_requests
```

### 2. 컨테이너 내부에서 실행
```bash
# 컨테이너 접속
docker exec -it rejintech-workspace bash

# 배치 실행
cd /var/www/html
php index.php batch/procurement_sync sync_delivery_requests
```

### 3. 실행 결과 확인
배치 실행 후 다음과 같은 로그가 출력됩니다:
```
[2025-06-23 00:31:00] [INFO] === 조달청 납품요구 상세정보 동기화 시작 ===
[2025-06-23 00:31:00] [INFO] 시작 시간: 2025-06-23 00:31:00
[2025-06-23 00:31:00] [INFO] 배치 로그 ID: 2
...
[2025-06-23 00:32:29] [INFO] 총 처리 건수: 992
[2025-06-23 00:32:29] [INFO] 성공: 992
[2025-06-23 00:32:29] [INFO] 오류: 0
```

## ⏰ Crontab 자동 실행 설정

### 1. 배치 실행 스크립트 생성

먼저 배치 실행을 위한 셸 스크립트를 생성합니다:

```bash
# 스크립트 파일 생성
sudo nano /usr/local/bin/rejintech_batch.sh
```

스크립트 내용:
```bash
#!/bin/bash

# 스크립트 설정
SCRIPT_DIR="/path/to/rejintech_project"
LOG_DIR="/var/log/rejintech"
DATE=$(date '+%Y%m%d_%H%M%S')
LOG_FILE="$LOG_DIR/batch_$DATE.log"

# 로그 디렉토리 생성
mkdir -p $LOG_DIR

# 프로젝트 디렉토리로 이동
cd $SCRIPT_DIR

echo "=== Rejintech 배치 시작 - $(date) ===" >> $LOG_FILE

# Docker 컨테이너 상태 확인
if ! docker ps | grep -q "rejintech-workspace"; then
    echo "Error: rejintech-workspace 컨테이너가 실행되지 않았습니다." >> $LOG_FILE
    exit 1
fi

# 배치 실행
echo "배치 실행 시작..." >> $LOG_FILE
docker exec rejintech-workspace php /var/www/html/index.php batch/procurement_sync sync_delivery_requests >> $LOG_FILE 2>&1

# 실행 결과 확인
if [ $? -eq 0 ]; then
    echo "배치 실행 성공 - $(date)" >> $LOG_FILE
else
    echo "배치 실행 실패 - $(date)" >> $LOG_FILE
    exit 1
fi

echo "=== Rejintech 배치 완료 - $(date) ===" >> $LOG_FILE

# 오래된 로그 파일 정리 (30일 이상)
find $LOG_DIR -name "batch_*.log" -mtime +30 -delete
```

### 2. 스크립트 권한 설정
```bash
# 실행 권한 부여
sudo chmod +x /usr/local/bin/rejintech_batch.sh

# 소유자 변경 (필요시)
sudo chown $USER:$USER /usr/local/bin/rejintech_batch.sh
```

### 3. Crontab 설정

#### crontab 편집
```bash
crontab -e
```

#### 스케줄 설정 예시

**매일 오전 2시 실행:**
```bash
0 2 * * * /usr/local/bin/rejintech_batch.sh
```

**매일 오전 2시, 오후 2시 실행:**
```bash
0 2,14 * * * /usr/local/bin/rejintech_batch.sh
```

**평일 오전 2시 실행:**
```bash
0 2 * * 1-5 /usr/local/bin/rejintech_batch.sh
```

**매시간 실행 (업무시간 9-18시):**
```bash
0 9-18 * * * /usr/local/bin/rejintech_batch.sh
```

#### Cron 표현식 설명
```
분(0-59) 시(0-23) 일(1-31) 월(1-12) 요일(0-7)
```

### 4. 환경 변수 설정 (필요시)
crontab에서 환경 변수가 필요한 경우:
```bash
# crontab 상단에 추가
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
SHELL=/bin/bash

# 배치 실행
0 2 * * * /usr/local/bin/rejintech_batch.sh
```

## 📊 모니터링 및 로그 관리

### 1. 실행 로그 확인
```bash
# 최신 배치 로그 확인
tail -f /var/log/rejintech/batch_$(date '+%Y%m%d')*.log

# 모든 배치 로그 목록
ls -la /var/log/rejintech/
```

### 2. 데이터베이스 로그 확인
```bash
# 배치 실행 이력 조회
docker exec -it rejintech-workspace php -r "
require_once '/var/www/html/index.php';
\$CI =& get_instance();
\$CI->load->database();
\$logs = \$CI->db->select('*')->from('batch_logs')->order_by('id', 'DESC')->limit(10)->get()->result_array();
foreach(\$logs as \$log) {
    echo \"ID: {\$log['id']}, 시작: {\$log['start_time']}, 상태: {\$log['status']}, 성공: {\$log['success_count']}건\n\";
}
"
```

### 3. cron 실행 로그 확인
```bash
# cron 로그 확인 (Ubuntu/Debian)
sudo tail -f /var/log/cron.log

# cron 로그 확인 (CentOS/RHEL)
sudo tail -f /var/log/cron
```

## 🚨 오류 처리 및 알림

### 1. 이메일 알림 설정
스크립트에 이메일 알림 추가:
```bash
# 스크립트 하단에 추가
if [ $? -ne 0 ]; then
    echo "배치 실행 실패" | mail -s "Rejintech 배치 오류 알림" admin@company.com
fi
```

### 2. Slack 알림 설정
```bash
# Slack Webhook을 이용한 알림
SLACK_WEBHOOK="https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK"

if [ $? -ne 0 ]; then
    curl -X POST -H 'Content-type: application/json' \
    --data '{"text":"🚨 Rejintech 배치 실행 실패"}' \
    $SLACK_WEBHOOK
fi
```

### 3. 일반적인 오류 해결

#### Docker 컨테이너 미실행
```bash
# 컨테이너 상태 확인
docker ps -a | grep rejintech

# 컨테이너 시작
docker-compose up -d
```

#### 권한 오류
```bash
# 스크립트 권한 확인
ls -la /usr/local/bin/rejintech_batch.sh

# 권한 재설정
sudo chmod +x /usr/local/bin/rejintech_batch.sh
```

#### 로그 디렉토리 권한 오류
```bash
# 로그 디렉토리 생성 및 권한 설정
sudo mkdir -p /var/log/rejintech
sudo chown $USER:$USER /var/log/rejintech
sudo chmod 755 /var/log/rejintech
```

## 📋 체크리스트

### 배치 설정 전 확인사항
- [ ] Docker 컨테이너 정상 실행 확인
- [ ] 데이터베이스 연결 확인
- [ ] API 인증 정보 확인
- [ ] 로그 디렉토리 생성 및 권한 설정
- [ ] 배치 스크립트 생성 및 권한 설정

### 정기 점검사항
- [ ] 배치 실행 로그 확인
- [ ] 데이터 동기화 상태 확인
- [ ] 디스크 용량 확인 (로그 파일)
- [ ] API 응답 상태 확인
- [ ] 데이터베이스 성능 모니터링

## 🔧 고급 설정

### 1. 배치 실행 시간 최적화
```bash
# 시스템 부하가 적은 시간대 선택
# 새벽 2-4시 권장
0 2 * * * /usr/local/bin/rejintech_batch.sh
```

### 2. 병렬 처리 방지
```bash
# 스크립트에 락 파일 추가
LOCK_FILE="/tmp/rejintech_batch.lock"

if [ -f $LOCK_FILE ]; then
    echo "배치가 이미 실행 중입니다." >> $LOG_FILE
    exit 1
fi

touch $LOCK_FILE
# ... 배치 실행 ...
rm -f $LOCK_FILE
```

### 3. 성능 모니터링
```bash
# 실행 시간 측정
START_TIME=$(date +%s)
# ... 배치 실행 ...
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))
echo "실행 시간: ${DURATION}초" >> $LOG_FILE
```

## 📞 지원 및 문의

배치 실행 관련 문제가 발생하면:
1. 로그 파일 확인
2. 데이터베이스 상태 확인
3. Docker 컨테이너 상태 확인
4. API 연결 상태 확인

---

**작성일**: 2025-06-23  
**버전**: 1.0  
**담당자**: Rejintech 개발팀 
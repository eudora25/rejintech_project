# AWS 인스턴스 배포 가이드 - Rejintech 프로젝트

## 1. AWS 인스턴스 생성 및 기본 설정

### 1.1 AWS EC2 인스턴스 생성
```bash
# 권장 스펙:
# - AMI: Amazon Linux 2023 (AL2023)
# - 인스턴스 타입: t3.medium 이상 (2 vCPU, 4GB RAM)
# - 스토리지: 30GB 이상
# - 보안 그룹: HTTP(80), HTTPS(443), SSH(22), MySQL(3306) 포트 오픈
```

### 1.2 보안 그룹 설정
```bash
# 인바운드 규칙:
# SSH (22) - 내 IP
# HTTP (80) - 0.0.0.0/0
# HTTPS (443) - 0.0.0.0/0  
# MySQL (3306) - 보안그룹 내부만 (옵션)
# 사용자 정의 TCP (8080) - 0.0.0.0/0 (Swagger UI용)
```

### 1.3 Elastic IP 할당 (권장)
```bash
# AWS 콘솔에서 Elastic IP 생성 후 인스턴스에 연결
# 고정 IP를 통해 도메인 설정 및 접근 편의성 확보
```

## 2. 인스턴스 초기 설정

### 2.1 시스템 업데이트 및 기본 패키지 설치
```bash
# 시스템 업데이트
sudo dnf update -y

# 기본 패키지 설치
sudo dnf install -y git wget curl vim htop

# Docker 설치
sudo dnf install -y docker
sudo systemctl start docker
sudo systemctl enable docker
sudo usermod -a -G docker ec2-user

# Docker Compose 설치
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# 로그아웃 후 재로그인 (docker 그룹 적용)
exit
```

### 2.2 프로젝트 디렉토리 구조 생성
```bash
# 프로젝트 루트 디렉토리 생성
sudo mkdir -p /opt/rejintech
sudo chown ec2-user:ec2-user /opt/rejintech
cd /opt/rejintech

# 로그 디렉토리 생성
sudo mkdir -p /var/log/rejintech
sudo chown ec2-user:ec2-user /var/log/rejintech
```

## 3. 프로젝트 파일 배포

### 3.1 프로젝트 파일 업로드
```bash
# 방법 1: Git 클론 (권장)
cd /opt/rejintech
git clone [YOUR_REPOSITORY_URL] .

# 방법 2: SCP로 파일 전송
# 로컬에서 실행:
# scp -r -i your-key.pem ./rejintech_project/* ec2-user@[EC2_IP]:/opt/rejintech/

# 방법 3: 압축 파일로 전송
# 로컬에서: tar -czf rejintech.tar.gz rejintech_project/
# scp -i your-key.pem rejintech.tar.gz ec2-user@[EC2_IP]:/opt/rejintech/
# 서버에서: tar -xzf rejintech.tar.gz
```

### 3.2 파일 권한 설정
```bash
cd /opt/rejintech

# 디렉토리 권한 설정
sudo chown -R ec2-user:ec2-user .
chmod -R 755 .

# 스크립트 실행 권한 설정
chmod +x scripts/*.sh

# MariaDB 데이터 디렉토리 권한 설정
sudo chown -R 999:999 mariadb_data/
chmod -R 755 mariadb_data/
```

## 4. 환경별 설정 파일 수정

### 4.1 Docker Compose 설정 수정
```bash
vim docker-compose.yml

# 수정할 내용:
# - APP_URL을 실제 도메인 또는 IP로 변경
# - JWT_SECRET_KEY를 보안성 높은 키로 변경
# - 환경 변수 APP_ENV를 'production'으로 변경 (운영환경의 경우)
```

```yaml
# docker-compose.yml 수정 예시
services:
  ubuntu:
    environment:
      JWT_SECRET_KEY: "your_very_secure_random_jwt_key_here_change_this"
      APP_ENV: "production"  # 또는 "staging"
      APP_DEBUG: "false"
      APP_URL: "http://your-domain.com"  # 또는 "http://your-ec2-ip"
```

### 4.2 CodeIgniter 설정 수정
```bash
# 베이스 URL 설정
vim source/application/config/config.php

# 수정할 부분:
# $config['base_url'] = 'http://your-domain.com/';  # 또는 EC2 IP
```

### 4.3 데이터베이스 비밀번호 변경 (보안 강화)
```bash
vim docker-compose.yml

# 데이터베이스 비밀번호를 보안성 높은 것으로 변경
# MYSQL_PASSWORD: "new_secure_password"
# MYSQL_ROOT_PASSWORD: "new_secure_root_password"

# 해당 비밀번호를 database.php에도 반영
vim source/application/config/database.php
```

## 5. Docker 환경 구축

### 5.1 Docker 이미지 빌드 및 컨테이너 실행
```bash
cd /opt/rejintech

# Docker Compose로 서비스 시작
docker-compose up -d

# 컨테이너 상태 확인
docker ps

# 로그 확인
docker-compose logs -f
```

### 5.2 컨테이너 헬스체크
```bash
# 웹서버 접근 테스트
curl http://localhost

# 데이터베이스 연결 테스트
docker exec rejintech-workspace php -r "
require_once '/var/www/html/index.php';
\$CI =& get_instance();
\$CI->load->database();
if (\$CI->db->initialize()) {
    echo 'Database connection successful\n';
} else {
    echo 'Database connection failed\n';
}
"
```

## 6. 시스템 서비스 설정

### 6.1 Docker 자동 시작 설정
```bash
# 시스템 부팅 시 Docker 자동 시작
sudo systemctl enable docker

# Docker Compose 서비스 자동 시작을 위한 systemd 서비스 생성
sudo vim /etc/systemd/system/rejintech.service
```

```ini
[Unit]
Description=Rejintech Docker Compose Service
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=/opt/rejintech
ExecStart=/usr/local/bin/docker-compose up -d
ExecStop=/usr/local/bin/docker-compose down
TimeoutStartSec=0

[Install]
WantedBy=multi-user.target
```

```bash
# 서비스 활성화
sudo systemctl daemon-reload
sudo systemctl enable rejintech.service
sudo systemctl start rejintech.service
```

### 6.2 배치 스크립트 설정
```bash
# 배치 스크립트 경로 수정
vim scripts/rejintech_batch.sh

# 수정할 부분:
# SCRIPT_DIR="/opt/rejintech"  # AWS 서버 경로로 변경
```

### 6.3 Crontab 설정
```bash
# crontab 편집
crontab -e

# 다음 라인 추가 (매일 오전 2시 실행)
0 2 * * * /opt/rejintech/scripts/rejintech_batch.sh

# crontab 확인
crontab -l
```

## 7. 보안 설정

### 7.1 방화벽 설정 (선택사항)
```bash
# firewalld 설치 및 설정 (Amazon Linux 2023)
sudo dnf install -y firewalld
sudo systemctl start firewalld
sudo systemctl enable firewalld

# 필요한 포트 허용
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-port=8080/tcp  # Swagger UI
sudo firewall-cmd --reload
```

### 7.2 SSL 인증서 설정 (Let's Encrypt - 도메인 보유 시)
```bash
# Certbot 설치
sudo dnf install -y certbot

# 인증서 발급 (도메인 보유 시)
sudo certbot certonly --standalone -d your-domain.com

# Nginx 설정에 SSL 추가 (images/ubuntu/conf/nginx.conf 수정 필요)
```

### 7.3 데이터베이스 보안 설정
```bash
# MariaDB 컨테이너 접속하여 보안 설정
docker exec -it rejintech-mariadb mysql -u root -p

# MySQL 보안 설정
# - 불필요한 사용자 제거
# - root 계정 원격 접속 제한
# - 테스트 데이터베이스 제거
```

## 8. 모니터링 및 로그 관리

### 8.1 로그 로테이션 설정
```bash
# logrotate 설정 생성
sudo vim /etc/logrotate.d/rejintech
```

```bash
/var/log/rejintech/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0644 ec2-user ec2-user
    postrotate
        systemctl reload rejintech > /dev/null 2>&1 || true
    endscript
}
```

### 8.2 디스크 사용량 모니터링
```bash
# 디스크 사용량 확인 스크립트 생성
vim /opt/rejintech/scripts/monitor_disk.sh
```

```bash
#!/bin/bash
THRESHOLD=80
USAGE=$(df /opt/rejintech | tail -1 | awk '{print $5}' | sed 's/%//')

if [ $USAGE -gt $THRESHOLD ]; then
    echo "WARNING: Disk usage is ${USAGE}% on /opt/rejintech"
    # 이메일 알림 또는 로그 기록
fi
```

### 8.3 애플리케이션 헬스체크 스크립트
```bash
vim /opt/rejintech/scripts/health_check.sh
```

```bash
#!/bin/bash
# 웹서버 응답 확인
if curl -s http://localhost > /dev/null; then
    echo "Web server is running"
else
    echo "Web server is down - restarting containers"
    cd /opt/rejintech && docker-compose restart
fi

# 데이터베이스 연결 확인
DB_STATUS=$(docker exec rejintech-workspace php -r "
require_once '/var/www/html/index.php';
\$CI =& get_instance();
\$CI->load->database();
echo \$CI->db->initialize() ? 'OK' : 'ERROR';
" 2>/dev/null)

if [ "$DB_STATUS" = "OK" ]; then
    echo "Database connection is OK"
else
    echo "Database connection failed - restarting database container"
    docker-compose restart db
fi
```

## 9. 백업 설정

### 9.1 데이터베이스 백업 스크립트
```bash
vim /opt/rejintech/scripts/backup_db.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/opt/rejintech/backups"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# 데이터베이스 백업
docker exec rejintech-mariadb mysqldump -u root -pA77ila@ jintech > $BACKUP_DIR/jintech_$DATE.sql

# 7일 이상 된 백업 파일 삭제
find $BACKUP_DIR -name "jintech_*.sql" -mtime +7 -delete

echo "Database backup completed: $BACKUP_DIR/jintech_$DATE.sql"
```

### 9.2 백업 자동화
```bash
# crontab에 백업 작업 추가
crontab -e

# 매일 오전 1시 데이터베이스 백업
0 1 * * * /opt/rejintech/scripts/backup_db.sh
```

## 10. 접속 확인 및 테스트

### 10.1 웹 애플리케이션 접속 확인
```bash
# 브라우저에서 접속:
# http://[EC2_PUBLIC_IP]
# http://[your-domain.com]

# API 문서 접속:
# http://[EC2_PUBLIC_IP]/swagger-ui/
```

### 10.2 API 테스트
```bash
# 기본 API 테스트
curl -X GET "http://[EC2_PUBLIC_IP]/api/test"

# 인증 테스트 (로그인)
curl -X POST "http://[EC2_PUBLIC_IP]/api/auth/login" \
     -H "Content-Type: application/json" \
     -d '{"username":"your_username","password":"your_password"}'
```

## 11. 성능 최적화

### 11.1 PHP-FPM 튜닝 (컨테이너 내부)
```bash
# 컨테이너 접속
docker exec -it rejintech-workspace bash

# PHP-FPM 설정 수정
vim /etc/php/8.4/fpm/pool.d/www.conf

# 권장 설정:
# pm.max_children = 20
# pm.start_servers = 4
# pm.min_spare_servers = 2
# pm.max_spare_servers = 6
```

### 11.2 MariaDB 튜닝
```bash
# MariaDB 설정 파일 생성
vim /opt/rejintech/mysql-config/my.cnf
```

```ini
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
max_connections = 200
query_cache_size = 64M
table_open_cache = 2000
```

```bash
# docker-compose.yml에 설정 파일 마운트 추가
volumes:
  - ./mysql-config/my.cnf:/etc/mysql/conf.d/custom.cnf
```

## 12. 장애 대응 가이드

### 12.1 일반적인 문제 해결
```bash
# 컨테이너 재시작
docker-compose restart

# 컨테이너 로그 확인
docker-compose logs -f [service_name]

# 디스크 용량 확인
df -h

# 메모리 사용량 확인
free -h

# 프로세스 확인
top
```

### 12.2 데이터베이스 복구
```bash
# 백업에서 복원
docker exec -i rejintech-mariadb mysql -u root -pA77ila@ jintech < /opt/rejintech/backups/jintech_[DATE].sql
```

## 13. 업데이트 가이드

### 13.1 애플리케이션 업데이트
```bash
cd /opt/rejintech

# Git에서 최신 코드 받기
git pull origin main

# 컨테이너 재빌드
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### 13.2 PHP 8.4 버전 업데이트 (자동화 스크립트)
```bash
# PHP 8.4 버전으로 자동 업데이트 스크립트 실행
cd /opt/rejintech
./scripts/update_php_version.sh

# 스크립트가 자동으로 수행하는 작업:
# 1. 기존 컨테이너 중지 및 제거
# 2. PHP 8.1 → 8.4 업데이트
# 3. Docker 이미지 재빌드
# 4. 컨테이너 재시작
# 5. PHP 버전 및 연결 테스트
# 6. 웹서버, 데이터베이스 상태 확인
```

### 13.3 시스템 업데이트
```bash
# 시스템 패키지 업데이트
sudo dnf update -y

# Docker 업데이트
sudo dnf update docker

# 업데이트 후 재시작
sudo reboot
```

---

## 체크리스트

- [ ] AWS EC2 인스턴스 생성 및 보안 그룹 설정
- [ ] Elastic IP 할당
- [ ] Docker 및 Docker Compose 설치
- [ ] 프로젝트 파일 업로드 및 권한 설정
- [ ] 환경 설정 파일 수정 (도메인, 비밀번호 등)
- [ ] Docker 컨테이너 빌드 및 실행
- [ ] 자동 시작 서비스 설정
- [ ] 배치 스크립트 및 Crontab 설정
- [ ] 보안 설정 (방화벽, SSL 등)
- [ ] 백업 스크립트 설정
- [ ] 모니터링 및 로그 관리 설정
- [ ] 웹 애플리케이션 접속 확인
- [ ] API 테스트
- [ ] 성능 튜닝

이 가이드를 따라 설정하면 AWS 인스턴스에서 Rejintech 프로젝트가 정상적으로 동작할 것입니다. 
# 서버 접근 가이드

## 서버 정보

- **서버 IP**: 52.78.104.83
- **리전**: ap-northeast-2 (서울)
- **인스턴스 유형**: t2.micro
- **운영체제**: Amazon Linux 2
- **사용자**: ec2-user

## SSH 접속 정보

### 키 파일 위치 및 정보
```bash
# 키 파일 경로
./keys/jiintech-web-key.pem

# 키 파일 상세 정보
- 파일명: jiintech-web-key.pem
- 위치: /Users/hoony/Desktop/dev-work/rejintech_project/keys/jiintech-web-key.pem
- 용도: AWS EC2 인스턴스 SSH 접속
- 필수 권한 설정: chmod 400 ./keys/jiintech-web-key.pem
```

### 키 파일 사용 시 주의사항
1. 키 파일은 절대로 외부에 유출되지 않도록 관리
2. 권한은 반드시 400으로 설정 (소유자만 읽기 가능)
3. Git 등 버전 관리 시스템에 키 파일이 포함되지 않도록 주의
4. 키 파일 백업 시 안전한 장소에 별도 보관

### 접속 명령어
```bash
# 프로젝트 루트 디렉토리에서
ssh -i ./keys/jiintech-web-key.pem ec2-user@52.78.104.83
```

## 서버 디렉토리 구조

### 웹 루트 디렉토리
```
/usr/share/nginx/html/           # 실제 웹 루트 디렉토리
├── application/                 # 애플리케이션 소스
│   ├── controllers/            # 컨트롤러 파일들
│   │   └── batch/             # 배치 프로세스 파일들
│   ├── models/                # 모델 파일들
│   └── libraries/            # 라이브러리 파일들
├── system/                     # CodeIgniter 시스템 파일들
└── vendor/                     # Composer 의존성 패키지들
```

### 로그 디렉토리
```
/usr/share/nginx/html/application/logs/  # 애플리케이션 로그
/var/log/nginx/                         # Nginx 로그
```

### 설정 파일 위치
```
/etc/nginx/conf.d/              # Nginx 설정 파일
/usr/share/nginx/html/.env      # 환경 설정 파일
```

## 배치 파일 위치

- 데이터 정규화: `/usr/share/nginx/html/application/controllers/batch/data_normalization.php`
- 공공데이터 동기화: `/usr/share/nginx/html/application/controllers/batch/Data_sync_process.php`
- 조달청 데이터 동기화: `/usr/share/nginx/html/application/controllers/batch/Procurement_sync.php`

## 권한 설정

```bash
# 키 파일 권한 설정
chmod 400 ./keys/jiintech-web-key.pem

# 웹 디렉토리 권한
sudo chown -R ec2-user:nginx /usr/share/nginx/html
sudo chmod -R 755 /usr/share/nginx/html
```

## 주의사항

1. 키 파일(.pem)은 절대로 공개되지 않도록 주의
2. 서버 접속 후에는 반드시 작업 완료 후 로그아웃
3. 배치 작업 실행 시 항상 로그 확인

## 로그 확인

```bash
# 배치 작업 로그
tail -f /usr/share/nginx/html/application/logs/batch.log

# Nginx 에러 로그
tail -f /var/log/nginx/error.log
```

## 데이터베이스 접속 정보

- **호스트**: localhost
- **포트**: 3306
- **데이터베이스**: rejintech
- **사용자**: rejintech_user

## 문제 해결

서버 접속이 안될 경우:
1. 키 파일 권한 확인 (400)
2. 보안 그룹 인바운드 규칙 확인 (SSH: 22번 포트)
3. 인스턴스 상태 확인

## 유용한 명령어

```bash
# 서버 상태 확인
systemctl status nginx
systemctl status mariadb

# PHP 프로세스 확인
ps aux | grep php

# 디스크 사용량 확인
df -h

# 배치 실행 명령어
cd /usr/share/nginx/html && php index.php batch/data_sync_process start
``` 
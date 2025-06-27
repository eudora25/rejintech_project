# 배치 실행 가이드

## 1. 개요

이 문서는 납품요구 데이터 정규화 배치 작업의 실행 방법과 crontab 설정 방법을 설명합니다.

## 2. 배치 작업 종류

### 2.1 납품요구 데이터 정규화 배치
- **목적**: 조달청 납품요구 데이터를 정규화된 형태로 변환
- **실행 주기**: 매일 새벽 3시 권장
- **소요 시간**: 약 1초 미만
- **영향 받는 테이블**:
  - product_categories
  - products
  - institutions
  - companies
  - contracts
  - delivery_requests
  - delivery_request_items

## 3. 배치 실행 방법

### 3.1 수동 실행
```bash
# Docker 컨테이너 내부에서 실행
docker exec rejintech-workspace php /var/www/html/index.php batch/data_normalization normalize_delivery_data

# 또는 스크립트를 통한 실행
./scripts/rejintech_batch.sh normalize_delivery_data
```

### 3.2 Crontab 설정

1. **root 권한으로 crontab 편집**
```bash
sudo crontab -e
```

2. **다음 내용 추가**
```bash
# 납품요구 데이터 정규화 배치 - 매일 새벽 3시 실행
0 3 * * * /usr/local/bin/docker exec rejintech-workspace php /var/www/html/index.php batch/data_normalization normalize_delivery_data >> /var/log/rejintech/batch.log 2>&1
```

## 4. 로그 확인

### 4.1 애플리케이션 로그
- 경로: `source/application/logs/log-yyyy-mm-dd.php`
- 포맷: `[날짜 시간] [로그레벨] 메시지`

### 4.2 시스템 로그
- 경로: `/var/log/rejintech/batch.log`
- 배치 실행 결과 및 시스템 에러 확인 가능

## 5. 오류 처리

### 5.1 일반적인 오류 해결 방법
1. 로그 파일 확인
2. 데이터베이스 연결 상태 확인
3. Docker 컨테이너 상태 확인

### 5.2 주요 오류 코드
- `ERROR 1`: 데이터베이스 연결 실패
- `ERROR 2`: 테이블 초기화 실패
- `ERROR 3`: 데이터 변환 실패

## 6. 모니터링

### 6.1 실행 결과 확인
```sql
-- 최근 데이터 동기화 시간 확인
SELECT MAX(data_sync_date) FROM delivery_requests;

-- 테이블별 데이터 건수 확인
SELECT COUNT(*) FROM delivery_requests;
SELECT COUNT(*) FROM delivery_request_items;
```

### 6.2 성능 모니터링
- 실행 시간이 1초 이상 소요될 경우 성능 최적화 검토 필요
- 데이터베이스 인덱스 상태 주기적 확인

## 7. 유지보수

### 7.1 정기적인 점검 사항
- 로그 파일 크기 관리
- 데이터베이스 백업 상태 확인
- crontab 설정 상태 확인

### 7.2 문제 발생시 연락처
- 시스템 관리자: [연락처 정보 추가 필요]
- 기술 지원: [연락처 정보 추가 필요]
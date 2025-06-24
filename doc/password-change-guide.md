# 비밀번호 변경 API 가이드

## 📋 개요

Rejintech 프로젝트에 새로 추가된 비밀번호 변경 API에 대한 상세 가이드입니다.

## 🔒 API 정보

### 엔드포인트
```
POST /api/auth/change-password
```

### 인증 방식
- **JWT Bearer Token** 필수
- 로그인 후 발급받은 토큰을 Authorization 헤더에 포함

### 요청 형식
```json
{
  "current_password": "현재비밀번호",
  "new_password": "새비밀번호123!",
  "confirm_password": "새비밀번호123!"
}
```

## 🛡️ 비밀번호 정책

### 필수 조건
- **최소 길이**: 8자 이상
- **포함 필수**: 영문자, 숫자, 특수문자 각각 최소 1개

### 허용 특수문자
```
! @ # $ % ^ & * ( ) _ + - = [ ] { } | ; : , . < > ?
```

### 비밀번호 예시
✅ **올바른 예시:**
- `MyPassword123!`
- `SecurePass2024@`
- `StrongPwd#99`

❌ **잘못된 예시:**
- `password` (숫자, 특수문자 없음)
- `12345678` (영문자, 특수문자 없음)
- `Pass123` (8자 미만)

## 🚀 사용 방법

### 1. Swagger UI를 통한 테스트

1. **브라우저에서 접속**
   ```
   http://localhost/source/swagger-ui/
   ```

2. **로그인 및 토큰 발급**
   - `POST /api/auth/login` 실행
   - 응답에서 JWT 토큰 복사

3. **토큰 설정**
   - Swagger UI 상단의 **"Authorize"** 버튼 클릭
   - `bearerAuth` 필드에 토큰 붙여넣기
   - **"Authorize"** 클릭

4. **비밀번호 변경**
   - `POST /api/auth/change-password` 선택
   - 요청 본문에 비밀번호 정보 입력
   - **"Execute"** 클릭

### 2. cURL을 통한 테스트

```bash
# 1. 로그인하여 토큰 발급
TOKEN=$(curl -s -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin123"}' | \
  jq -r '.data.token')

# 2. 비밀번호 변경
curl -X POST "http://localhost/api/auth/change-password" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "current_password": "admin123",
    "new_password": "newPassword123!",
    "confirm_password": "newPassword123!"
  }'
```

### 3. JavaScript/Fetch를 통한 호출

```javascript
// 토큰이 이미 있다고 가정
const token = 'your_jwt_token_here';

const changePassword = async () => {
  try {
    const response = await fetch('/api/auth/change-password', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({
        current_password: 'admin123',
        new_password: 'newPassword123!',
        confirm_password: 'newPassword123!'
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      console.log('비밀번호 변경 성공:', result.message);
    } else {
      console.error('비밀번호 변경 실패:', result.message);
    }
  } catch (error) {
    console.error('요청 오류:', error);
  }
};
```

## 📤 응답 형식

### 성공 응답 (200 OK)
```json
{
  "success": true,
  "message": "비밀번호가 성공적으로 변경되었습니다.",
  "timestamp": "2025-06-24T12:00:00+00:00"
}
```

### 실패 응답

#### 현재 비밀번호 불일치 (401 Unauthorized)
```json
{
  "success": false,
  "message": "현재 비밀번호가 일치하지 않습니다.",
  "timestamp": "2025-06-24T12:00:00+00:00"
}
```

#### 비밀번호 정책 위반 (422 Unprocessable Entity)
```json
{
  "success": false,
  "message": "새 비밀번호는 최소 8자 이상이며, 영문자, 숫자, 특수문자를 각각 포함해야 합니다.",
  "timestamp": "2025-06-24T12:00:00+00:00"
}
```

#### 필수 필드 누락 (400 Bad Request)
```json
{
  "success": false,
  "message": "모든 필드를 입력해주세요.",
  "timestamp": "2025-06-24T12:00:00+00:00"
}
```

#### 인증 실패 (401 Unauthorized)
```json
{
  "success": false,
  "message": "유효하지 않은 토큰입니다.",
  "timestamp": "2025-06-24T12:00:00+00:00"
}
```

## 🔐 보안 기능

### 이중 토큰 검증
1. **JWT 토큰 검증**: 토큰 형식 및 서명 확인
2. **DB 토큰 검증**: 데이터베이스에 저장된 유효한 토큰인지 확인

### 비밀번호 검증
1. **현재 비밀번호 확인**: DB의 해시된 비밀번호와 비교
2. **새 비밀번호 정책 검증**: 강력한 비밀번호 정책 적용
3. **비밀번호 확인**: new_password와 confirm_password 일치 확인

### 로그 기록
- 비밀번호 변경 시도는 모두 로그에 기록됩니다
- 성공/실패 여부와 IP 주소, 사용자 정보 포함

## 🆘 문제 해결

### Q1: "현재 비밀번호가 일치하지 않습니다" 오류
**해결 방법:**
1. 현재 비밀번호를 정확히 입력했는지 확인
2. 대소문자 및 특수문자 확인
3. 계정이 잠금 상태가 아닌지 확인

### Q2: "새 비밀번호가 정책에 맞지 않습니다" 오류
**해결 방법:**
1. 8자 이상인지 확인
2. 영문자(대소문자), 숫자, 특수문자 각각 포함 확인
3. 허용되지 않는 특수문자 사용 여부 확인

### Q3: "유효하지 않은 토큰입니다" 오류
**해결 방법:**
1. 로그인하여 새 토큰 발급
2. 토큰이 만료되지 않았는지 확인 (1시간 유효)
3. Authorization 헤더 형식 확인: `Bearer your_token`

### Q4: 토큰은 유효하지만 API 호출 실패
**해결 방법:**
1. 요청 본문이 올바른 JSON 형식인지 확인
2. Content-Type 헤더가 `application/json`인지 확인
3. 서버 로그 확인: `docker-compose logs -f rejintech-workspace`

## 🧪 테스트 시나리오

### 시나리오 1: 정상적인 비밀번호 변경
1. 로그인 → 토큰 발급
2. 현재 비밀번호로 change-password API 호출
3. 성공 응답 확인
4. 새 비밀번호로 재로그인 확인

### 시나리오 2: 잘못된 현재 비밀번호
1. 로그인 → 토큰 발급
2. 틀린 현재 비밀번호로 API 호출
3. 401 오류 응답 확인

### 시나리오 3: 약한 새 비밀번호
1. 로그인 → 토큰 발급
2. 정책에 맞지 않는 새 비밀번호로 API 호출
3. 422 오류 응답 확인

### 시나리오 4: 토큰 없이 API 호출
1. Authorization 헤더 없이 API 호출
2. 401 오류 응답 확인

## 📚 관련 문서

- **[메인 가이드](README.md)** - 프로젝트 전체 가이드
- **[Swagger 가이드](swagger-quick-start.md)** - API 테스트 가이드
- **[로그인 로그 가이드](login-logs-guide.md)** - 로그인 이력 관리
- **[토큰 시스템 가이드](token-system-guide.md)** - JWT 토큰 관리

---

**🔒 보안은 선택이 아닌 필수입니다!**  
정기적인 비밀번호 변경으로 계정 보안을 유지하세요. 🛡️ 
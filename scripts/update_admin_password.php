<?php
// 데이터베이스 연결 설정
$db_host = '127.0.0.1'; // 로컬에서 실행하므로 localhost 또는 IP 직접 사용
$db_user = 'jintech';
$db_pass = 'jin2010!!';
$db_name = 'jintech';
$db_port = '3306';

// PDO를 사용하여 데이터베이스 연결
try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("데이터베이스 연결 실패: " . $e->getMessage());
}

// 업데이트할 사용자 정보
$username_to_update = 'admin';
$plain_password = 'admin123';

// 비밀번호 해시 생성
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

try {
    // 사용자가 존재하는지 확인
    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt_check->execute(['username' => $username_to_update]);
    $user = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 비밀번호 업데이트
        $stmt_update = $pdo->prepare("UPDATE users SET password = :password WHERE username = :username");
        $stmt_update->execute([
            'password' => $hashed_password,
            'username' => $username_to_update
        ]);
        
        if ($stmt_update->rowCount() > 0) {
            echo "사용자 '{$username_to_update}'의 비밀번호가 성공적으로 업데이트되었습니다.\n";
            echo "새로운 해시: " . $hashed_password . "\n";
        } else {
            echo "사용자 '{$username_to_update}'의 비밀번호는 이미 최신 상태이거나, 업데이트에 실패했습니다.\n";
        }
    } else {
        // 사용자가 존재하지 않으면 새로 생성
        $stmt_create = $pdo->prepare("INSERT INTO users (username, password, email, created_at, updated_at) VALUES (:username, :password, :email, NOW(), NOW())");
        $stmt_create->execute([
            'username' => $username_to_update,
            'password' => $hashed_password,
            'email' => 'admin@example.com' // 기본 이메일
        ]);
        echo "사용자 '{$username_to_update}'가 존재하지 않아 새로 생성했습니다.\n";
        echo "새로운 해시: " . $hashed_password . "\n";
    }

} catch (Exception $e) {
    echo "오류 발생: " . $e->getMessage() . "\n";
} 
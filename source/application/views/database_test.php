<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $title : '데이터베이스 테스트'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: #2d3748;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .content {
            padding: 40px;
        }
        
        .status-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .status-card.success {
            border-left: 4px solid #38a169;
            background: #f0fff4;
        }
        
        .status-card.error {
            border-left: 4px solid #e53e3e;
            background: #fff5f5;
        }
        
        .status-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .status-icon.success {
            color: #38a169;
        }
        
        .status-icon.error {
            color: #e53e3e;
        }
        
        .status-message {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .status-message.success {
            color: #22543d;
        }
        
        .status-message.error {
            color: #742a2a;
        }
        
        .info-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .info-section h3 {
            color: #2d3748;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-label {
            font-weight: 600;
            color: #4a5568;
        }
        
        .info-value {
            color: #2d3748;
            font-family: monospace;
            background: #edf2f7;
            padding: 2px 8px;
            border-radius: 4px;
        }
        
        .back-link {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #718096;
            border-top: 1px solid #e2e8f0;
        }
        
        .error-details {
            background: #fed7d7;
            color: #742a2a;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            font-family: monospace;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .content {
                padding: 20px;
            }
            
            .info-item {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo isset($title) ? $title : '데이터베이스 테스트'; ?></h1>
            <p>MariaDB 연결 상태 확인</p>
        </div>
        
        <div class="content">
            <!-- 연결 상태 -->
            <div class="status-card <?php echo isset($status) ? $status : 'error'; ?>">
                <div class="status-icon <?php echo isset($status) ? $status : 'error'; ?>">
                    <?php if(isset($status) && $status === 'success'): ?>
                        ✅
                    <?php else: ?>
                        ❌
                    <?php endif; ?>
                </div>
                <div class="status-message <?php echo isset($status) ? $status : 'error'; ?>">
                    <?php echo isset($message) ? $message : '알 수 없는 오류가 발생했습니다.'; ?>
                </div>
                
                <?php if(isset($status) && $status === 'error' && isset($message)): ?>
                    <div class="error-details">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if(isset($status) && $status === 'success' && isset($test_result)): ?>
            <!-- 테스트 결과 -->
            <div class="info-section">
                <h3>🧪 테스트 결과</h3>
                <?php foreach($test_result as $key => $value): ?>
                    <div class="info-item">
                        <span class="info-label"><?php echo ucfirst($key); ?>:</span>
                        <span class="info-value"><?php echo htmlspecialchars($value); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if(isset($db_info)): ?>
            <!-- 데이터베이스 정보 -->
            <div class="info-section">
                <h3>🗄️ 데이터베이스 정보</h3>
                <div class="info-item">
                    <span class="info-label">플랫폼:</span>
                    <span class="info-value"><?php echo isset($db_info['platform']) ? $db_info['platform'] : 'N/A'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">버전:</span>
                    <span class="info-value"><?php echo isset($db_info['version']) ? $db_info['version'] : 'N/A'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">데이터베이스:</span>
                    <span class="info-value"><?php echo isset($db_info['database']) ? $db_info['database'] : 'N/A'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">테스트 시간:</span>
                    <span class="info-value"><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 연결 설정 정보 -->
            <div class="info-section">
                <h3>⚙️ 연결 설정</h3>
                <div class="info-item">
                    <span class="info-label">호스트:</span>
                    <span class="info-value">rejintech-mariadb</span>
                </div>
                <div class="info-item">
                    <span class="info-label">포트:</span>
                    <span class="info-value">3306</span>
                </div>
                <div class="info-item">
                    <span class="info-label">사용자:</span>
                    <span class="info-value">jintech</span>
                </div>
                <div class="info-item">
                    <span class="info-label">데이터베이스:</span>
                    <span class="info-value">jintech</span>
                </div>
                <div class="info-item">
                    <span class="info-label">문자 인코딩:</span>
                    <span class="info-value">utf8mb4</span>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="<?php echo base_url(); ?>" class="back-link">← 메인 페이지로 돌아가기</a>
            </div>
        </div>
        
        <div class="footer">
            <p>CodeIgniter <?php echo CI_VERSION; ?> | <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html> 
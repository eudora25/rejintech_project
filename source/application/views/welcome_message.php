<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?><!DOCTYPE html>
<html lang="ko">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo isset($title) ? $title : 'Rejintech 프로젝트'; ?></title>
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
			max-width: 1200px;
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
			font-size: 2.5rem;
			margin-bottom: 10px;
		}
		
		.header p {
			font-size: 1.1rem;
			opacity: 0.9;
		}
		
		.content {
			padding: 40px;
		}
		
		.grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 30px;
			margin-bottom: 40px;
		}
		
		.card {
			background: #f8f9fa;
			border-radius: 8px;
			padding: 25px;
			border-left: 4px solid #667eea;
		}
		
		.card h3 {
			color: #2d3748;
			margin-bottom: 15px;
			font-size: 1.3rem;
		}
		
		.info-item {
			display: flex;
			justify-content: space-between;
			margin-bottom: 8px;
			padding: 5px 0;
			border-bottom: 1px solid #e2e8f0;
		}
		
		.info-label {
			font-weight: 600;
			color: #4a5568;
		}
		
		.info-value {
			color: #2d3748;
			font-family: monospace;
		}
		
		.links-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
			gap: 20px;
			margin-top: 30px;
		}
		
		.link-card {
			background: white;
			border: 2px solid #e2e8f0;
			border-radius: 8px;
			padding: 20px;
			text-align: center;
			transition: all 0.3s ease;
			text-decoration: none;
			color: inherit;
		}
		
		.link-card:hover {
			border-color: #667eea;
			transform: translateY(-5px);
			box-shadow: 0 10px 25px rgba(0,0,0,0.1);
		}
		
		.link-card h4 {
			color: #2d3748;
			margin-bottom: 10px;
			font-size: 1.1rem;
		}
		
		.link-card p {
			color: #718096;
			font-size: 0.9rem;
		}
		
		.status {
			display: inline-block;
			padding: 5px 15px;
			border-radius: 20px;
			font-size: 0.9rem;
			font-weight: 600;
		}
		
		.status.success {
			background: #c6f6d5;
			color: #22543d;
		}
		
		.status.development {
			background: #fed7d7;
			color: #742a2a;
		}
		
		.footer {
			background: #f8f9fa;
			padding: 20px;
			text-align: center;
			color: #718096;
			border-top: 1px solid #e2e8f0;
		}
		
		@media (max-width: 768px) {
			.header h1 {
				font-size: 2rem;
			}
			
			.content {
				padding: 20px;
			}
			
			.grid {
				grid-template-columns: 1fr;
			}
		}
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<h1><?php echo isset($title) ? $title : 'Rejintech 프로젝트'; ?></h1>
			<p>CodeIgniter + Docker + Swagger API 개발 환경</p>
			<?php if(isset($project_info['environment'])): ?>
			<span class="status <?php echo $project_info['environment'] === 'development' ? 'development' : 'success'; ?>">
				<?php echo strtoupper($project_info['environment']); ?> 환경
			</span>
			<?php endif; ?>
		</div>
		
		<div class="content">
			<div class="grid">
				<!-- 프로젝트 정보 -->
				<div class="card">
					<h3>🚀 프로젝트 정보</h3>
					<?php if(isset($project_info)): ?>
						<div class="info-item">
							<span class="info-label">프로젝트명:</span>
							<span class="info-value"><?php echo $project_info['name']; ?></span>
						</div>
						<div class="info-item">
							<span class="info-label">프레임워크:</span>
							<span class="info-value"><?php echo $project_info['framework']; ?></span>
						</div>
						<div class="info-item">
							<span class="info-label">데이터베이스:</span>
							<span class="info-value"><?php echo $project_info['database']; ?></span>
						</div>
						<div class="info-item">
							<span class="info-label">웹 서버:</span>
							<span class="info-value"><?php echo $project_info['web_server']; ?></span>
						</div>
						<div class="info-item">
							<span class="info-label">PHP 버전:</span>
							<span class="info-value"><?php echo $project_info['php_version']; ?></span>
						</div>
					<?php endif; ?>
				</div>
				
				<!-- 시스템 상태 -->
				<div class="card">
					<h3>⚡ 시스템 상태</h3>
					<div class="info-item">
						<span class="info-label">CodeIgniter:</span>
						<span class="info-value status success">구동중</span>
					</div>
					<div class="info-item">
						<span class="info-label">URL Rewriting:</span>
						<span class="info-value status success">활성화</span>
					</div>
					<div class="info-item">
						<span class="info-label">Swagger UI:</span>
						<span class="info-value status success">준비됨</span>
					</div>
					<div class="info-item">
						<span class="info-label">API 테스트:</span>
						<span class="info-value status success">가능</span>
					</div>
					<div class="info-item">
						<span class="info-label">서버 시간:</span>
						<span class="info-value"><?php echo date('Y-m-d H:i:s'); ?></span>
					</div>
				</div>
			</div>
			
			<!-- 빠른 링크 -->
			<h3 style="margin-bottom: 20px; color: #2d3748; text-align: center;">🔗 빠른 링크</h3>
			<div class="links-grid">
				<?php if(isset($links)): ?>
					<a href="<?php echo $links['swagger_ui']; ?>" class="link-card" target="_blank">
						<h4>📚 Swagger UI</h4>
						<p>API 문서 및 테스트 인터페이스</p>
					</a>
					
					<a href="<?php echo $links['api_test']; ?>" class="link-card" target="_blank">
						<h4>🧪 API 테스트</h4>
						<p>기본 API 서버 연결 테스트</p>
					</a>
					
					<a href="<?php echo $links['database_test']; ?>" class="link-card" target="_blank">
						<h4>🗄️ DB 연결 테스트</h4>
						<p>MariaDB 데이터베이스 연결 확인</p>
					</a>
					
					<a href="<?php echo $links['api_docs']; ?>" class="link-card" target="_blank">
						<h4>📄 OpenAPI Spec</h4>
						<p>API 명세서 JSON 파일</p>
					</a>
				<?php endif; ?>
			</div>
			
			<div style="margin-top: 40px; padding: 20px; background: #f0f8ff; border-radius: 8px; text-align: center;">
				<h4 style="color: #2d3748; margin-bottom: 10px;">🎉 개발 환경 준비 완료!</h4>
				<p style="color: #4a5568;">
					CodeIgniter + Docker + Swagger 환경이 성공적으로 설정되었습니다.<br>
					위의 링크들을 통해 각 기능을 테스트해보세요.
				</p>
			</div>
		</div>
		
		<div class="footer">
			<p>&copy; <?php echo date('Y'); ?> Rejintech 프로젝트. CodeIgniter <?php echo CI_VERSION; ?> 기반.</p>
		</div>
	</div>
</body>
</html>
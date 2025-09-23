<?php
/**
 * 登出处理文件 - 美观版本
 * 清除所有登录状态，显示友好的登出成功页面
 */

session_start();
$_SESSION = [];
session_destroy();

setcookie(session_name(), '', time() - 42000, '/');

isset($_SERVER['PHP_AUTH_USER']) && header('HTTP/1.1 401 Unauthorized');

header('Cache-Control: no-cache');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登出成功 - 泰格网盘</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #007bff;
            --primary-dark: #0056b3;
            --success-color: #28a745;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .logout-container {
            background: white;
            padding: 60px 50px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            text-align: center;
            max-width: 500px;
            width: 100%;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--success-color), #20c997);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease-out;
        }
        
        .success-icon i {
            font-size: 40px;
            color: white;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        h1 {
            color: var(--dark-color);
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 15px;
            line-height: 1.3;
        }
        
        .description {
            color: var(--text-muted);
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 40px;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            cursor: pointer;
            font-size: 15px;
            min-width: 140px;
            justify-content: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            box-shadow: var(--shadow-sm);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        
        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, var(--text-muted), #495057);
            color: white;
            box-shadow: var(--shadow-sm);
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        @media (max-width: 480px) {
            .logout-container {
                padding: 40px 30px;
                margin: 10px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .button-group {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 250px;
            }
        }
        
        /* 额外的动画效果 */
        .logout-container > * {
            animation: fadeIn 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h1>登出成功</h1>
        <p class="description">
            您已成功退出泰格网盘系统。<br>
            您的登录会话已完全清除，现在可以安全地关闭浏览器。
        </p>
        
        <div class="button-group">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home"></i>
                返回首页
            </a>
            
            <a href="login.php" class="btn btn-outline">
                <i class="fas fa-sign-in-alt"></i>
                重新登录
            </a>
            
            <a href="webdav.php" class="btn btn-secondary">
                <i class="fas fa-folder-open"></i>
                WebDAV服务
            </a>
        </div>
    </div>

    <script>
        // 防止页面被缓存
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // 防止后退按钮导致重新认证
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
        
        // 添加按钮点击动画
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                // 创建涟漪效果
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
    </script>
</body>
</html>
<?php
/**
 * WebDAV服务器入口文件
 * 软件介绍页面及登录跳板
 */

// 开启会话
session_start();

// 检查是否已安装
if (!file_exists(__DIR__ . '/install.lock')) {
    header('Location: install.php');
    exit;
}

// 加载配置
$config = require 'config.php';

// 连接数据库
try {
    $dbPath = __DIR__ . '/' . $config['db_file'];
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('数据库连接失败：' . $e->getMessage());
}

// 检查是否已登录
$isLoggedIn = isset($_SESSION['user_id']);

// 处理登录
$loginError = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // 登录成功
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'];
            
            // 重定向到当前页面（刷新）
            header('Location: admin/');
            exit;
        } else {
            $loginError = '用户名或密码错误';
        }
    } else {
        $loginError = '请输入用户名和密码';
    }
}

// 处理退出登录 - 使用统一登出页面
if (($_GET['action'] ?? '') === 'logout') {
    header('Location: logout.php');
    exit;
}

// 获取WebDAV访问URL
$protocol = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$webdavUrl = $protocol . '://' . $host . rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/') . '/webdav.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - WebDAV服务器</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .login-header i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        .login-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .login-form {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 16px;
        }

        .form-input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-input.error {
            border-color: #e74c3c;
            background: #fdf2f2;
        }

        .error-message {
            color: #e74c3c;
            font-size: 13px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            animation: shake 0.3s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .login-button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .login-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .login-button:active:not(:disabled) {
            transform: translateY(0);
        }

        .login-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .additional-links {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e1e5e9;
        }

        .additional-links a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .additional-links a:hover {
            color: #764ba2;
        }

        .back-to-home {
            text-align: center;
            margin-top: 20px;
        }

        .back-to-home a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s ease;
        }

        .back-to-home a:hover {
            color: #667eea;
        }

        /* 响应式设计 */
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .login-container {
                margin: 0;
                border-radius: 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }

            .login-header {
                padding: 30px 20px;
            }

            .login-form {
                padding: 30px 20px;
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .login-header h1 {
                font-size: 22px;
            }

            .login-header i {
                font-size: 40px;
            }
        }

        @media (max-width: 375px) {
            .login-header {
                padding: 25px 15px;
            }

            .login-form {
                padding: 25px 15px;
            }

            .form-input {
                padding: 12px 12px 12px 40px;
                font-size: 16px; /* 防止iOS缩放 */
            }

            .input-wrapper i {
                left: 12px;
                font-size: 14px;
            }
        }



        /* 无障碍支持 */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* 焦点样式 */
        .form-input:focus-visible {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }

        .login-button:focus-visible {
            outline: 2px solid white;
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <?php if ($isLoggedIn): ?>
            <div class="login-header">
                <i class="fas fa-check-circle"></i>
                <h1>已登录</h1>
                <p>欢迎回来，<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>！</p>
            </div>
            <div class="login-form">
                <div class="additional-links">
                    <?php if (($_SESSION['is_admin'] ?? 0) == 1): ?>
                        <a href="admin/">
                            <i class="fas fa-cog"></i> 管理后台
                        </a>
                    <?php endif; ?>
                    <a href="login.php?action=logout">
                        <i class="fas fa-sign-out-alt"></i> 退出登录
                    </a>
                </div>
                
                <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 12px;">
                    <h3 style="margin-bottom: 15px; color: #333;">
                        <i class="fas fa-link"></i> WebDAV访问地址
                    </h3>
                    <div style="background: white; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 14px; word-break: break-all; margin-bottom: 10px;">
                        <?php echo htmlspecialchars($webdavUrl); ?>
                    </div>
                    <button type="button" onclick="copyWebdavUrl()" style="width: 100%; padding: 10px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px;">
                        <i class="fas fa-copy"></i> 复制地址
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="login-header">
                <i class="fas fa-cloud"></i>
                <h1>WebDAV登录</h1>
                <p>安全访问您的云端存储</p>
            </div>
            
            <div class="login-form">
                <form method="post" action="" id="loginForm">
                    <div class="form-group">
                        <label for="username">用户名</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   class="form-input" 
                                   placeholder="请输入用户名"
                                   required 
                                   autocomplete="username">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">密码</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="form-input" 
                                   placeholder="请输入密码"
                                   required 
                                   autocomplete="current-password">
                        </div>
                    </div>
                    
                    <?php if (!empty($loginError)): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($loginError); ?>
                        </div>
                    <?php endif; ?>
                    
                    <input type="hidden" name="action" value="login">
                    <button type="submit" class="login-button" id="loginButton">
                        <span class="button-text">登录</span>
                        <div class="loading-spinner"></div>
                    </button>
                </form>
                
                <div class="additional-links">
                    <a href="../">
                        <i class="fas fa-home"></i> 返回首页
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function copyWebdavUrl() {
            const urlText = document.querySelector('.login-form > div[style*="background: #f8f9fa"] div[style*="font-family: monospace"]').textContent.trim();
            const button = event.target;
            const originalText = button.innerHTML;
            
            navigator.clipboard.writeText(urlText).then(() => {
                button.innerHTML = '<i class="fas fa-check"></i> 已复制';
                button.style.background = '#28a745';
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.style.background = '#667eea';
                }, 2000);
            }).catch(err => {
                console.error('无法复制URL: ', err);
                alert('复制失败，请手动选择URL并复制。');
            });
        }

        // 表单提交动画
        const loginForm = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');
        const buttonText = loginButton?.querySelector('.button-text');
        const loadingSpinner = loginButton?.querySelector('.loading-spinner');

        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                if (buttonText && loadingSpinner) {
                    buttonText.style.display = 'none';
                    loadingSpinner.style.display = 'block';
                    loginButton.disabled = true;
                }
            });
        }

        // 输入框焦点效果
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
        });


    </script>
</body>
</html>
<?php
/**
 * 统一登出处理文件
 * 清除WebDAV和后台管理的所有登录状态
 */

// 启动会话
session_start();

// 清除所有会话数据
$_SESSION = array();

// 销毁会话
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 强制清除HTTP基本认证
// 发送401状态码强制浏览器清除认证缓存
if (isset($_SERVER['PHP_AUTH_USER']) || isset($_SERVER['HTTP_AUTHORIZATION'])) {
    // 对于WebDAV基本认证，需要发送401来清除浏览器缓存
    header('WWW-Authenticate: Basic realm="WebDAV Server"');
    header('HTTP/1.1 401 Unauthorized');
    
    // 但是立即重定向到登出成功页面，避免用户看到401页面
    header('Refresh: 0; url=logout.php?cleared=1');
    exit;
}

// 处理认证清除后的重定向
if (isset($_GET['cleared'])) {
    // 认证已清除，显示成功页面
    // 清除可能的认证缓存
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // 显示登出成功页面
    // 继续执行下面的HTML代码
} else {
    // 第一次访问，需要清除认证
    // 强制清除HTTP基本认证
    
    // 设置不同的realm来确保浏览器清除缓存
    header('WWW-Authenticate: Basic realm="Logged Out ' . time() . '"');
    header('HTTP/1.1 401 Unauthorized');
    
    // 添加JavaScript来进一步确保清除
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>正在清除认证...</title>
    <meta http-equiv="refresh" content="1;url=logout.php?cleared=1">
    <script>
        // 尝试通过AJAX请求来清除认证
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "webdav.php", true);
        xhr.setRequestHeader("Authorization", "Basic " + btoa("logout:logout"));
        xhr.send();
        
        // 重定向到成功页面
        setTimeout(function() {
            window.location.href = "logout.php?cleared=1";
        }, 500);
    </script>
</head>
<body>
    <p>正在清除登录状态，请稍候...</p>
</body>
</html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>已安全退出 - WebDAV系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .logout-container {
            background: white;
            padding: 50px 40px;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 450px;
            width: 100%;
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .success-icon {
            font-size: 72px;
            color: #4CAF50;
            margin-bottom: 20px;
            animation: bounce 0.6s ease-in-out;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 28px;
            font-weight: 600;
        }
        
        .description {
            color: #666;
            margin-bottom: 35px;
            line-height: 1.6;
            font-size: 16px;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-block;
            padding: 14px 28px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            min-width: 150px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
            box-shadow: 0 4px 15px rgba(40,167,69,0.3);
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40,167,69,0.4);
        }
        
        .btn-outline {
            background: transparent;
            color: #007bff;
            border: 2px solid #007bff;
        }
        
        .btn-outline:hover {
            background: #007bff;
            color: white;
            transform: translateY(-2px);
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .logout-container {
                padding: 40px 25px;
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
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="success-icon">✅</div>
        <h1>已安全退出</h1>
        <p class="description">您已成功退出WebDAV文件管理和后台管理系统。<br>您的登录会话已完全清除。</p>
        
        <div class="button-group">
            <a href="webdav.php" class="btn btn-primary">重新登录WebDAV</a>
            <a href="admin/" class="btn btn-secondary">后台管理</a>
            <a href="login.php" class="btn btn-outline">返回首页</a>
        </div>
    </div>

    <script>
        // 额外清除浏览器认证缓存
        if (window.history.replaceState) {
            window.history.replaceState(null, null, 'logout.php');
        }
        
        // 防止后退按钮重新认证
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
    </script>
</body>
</html>
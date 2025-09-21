<?php
/**
 * WebDAV服务器安装程序
 * 兼容PHP 7.3及以下版本
 */

// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 安装锁定文件路径
$lockFile = __DIR__ . '/install.lock';

// 检查是否已安装
if (file_exists($lockFile)) {
    die('系统已安装，如需重新安装，请删除install.lock文件');
}

// 检查PDO SQLite扩展
if (!extension_loaded('pdo_sqlite')) {
    die('请安装PDO SQLite扩展');
}

// 检查目录权限
$checkDirs = ['db', 'storage'];
foreach ($checkDirs as $dir) {
    $dirPath = __DIR__ . '/' . $dir;
    if (!is_dir($dirPath)) {
        if (!mkdir($dirPath, 0755, true)) {
            die('无法创建目录：' . $dir . '，请检查权限');
        }
    } elseif (!is_writable($dirPath)) {
        die('目录不可写：' . $dir . '，请检查权限');
    }
}

// 处理表单提交
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证表单数据
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirmPassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    $baseDir = isset($_POST['base_dir']) ? trim($_POST['base_dir']) : '';
    
    // 验证用户名
    if (empty($username)) {
        $errors[] = '管理员用户名不能为空';
    } elseif (strlen($username) < 3) {
        $errors[] = '管理员用户名至少需要3个字符';
    }
    
    // 验证密码
    if (empty($password)) {
        $errors[] = '管理员密码不能为空';
    } elseif (strlen($password) < 6) {
        $errors[] = '管理员密码至少需要6个字符';
    } elseif ($password !== $confirmPassword) {
        $errors[] = '两次输入的密码不一致';
    }
    
    // 验证基础目录
    if (empty($baseDir)) {
        // 生成随机基础目录名
        $baseDir = 'storage/' . generateRandomString(16);
    }
    
    // 如果没有错误，执行安装
    if (empty($errors)) {
        try {
            // 创建随机数据库文件名
            $dbFileName = 'db/' . generateRandomString(12) . '.db';
            $dbPath = __DIR__ . '/' . $dbFileName;
            
            // 创建数据库连接
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 创建用户表
            $pdo->exec('CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                is_admin INTEGER DEFAULT 0,
                access_dir TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )');
            
            // 创建管理员账号
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password, is_admin) VALUES (?, ?, 1)');
            $stmt->execute([$username, $hashedPassword]);
            
            // 确保基础目录存在
            $fullBaseDir = __DIR__ . '/' . $baseDir;
            if (!is_dir($fullBaseDir)) {
                mkdir($fullBaseDir, 0755, true);
            }
            
            // 创建管理员目录
            $adminDir = $fullBaseDir . '/' . $username;
            if (!is_dir($adminDir)) {
                mkdir($adminDir, 0755, true);
            }
            
            // 创建配置文件
            $configContent = "<?php\n/**\n * WebDAV服务器配置文件\n */\n\n// WebDAV服务器配置\nreturn [\n    // 基础目录（相对于webdav.php文件的位置）\n    'base_dir' => '{$baseDir}',\n    \n    // 数据库配置\n    'db_file' => '{$dbFileName}',\n    \n    // 认证域名称\n    'realm' => 'My WebDAV Server',\n    \n    // 是否启用调试模式（显示详细错误信息）\n    'debug' => false,\n    \n    // 最大上传文件大小（字节）\n    'max_upload_size' => 100 * 1024 * 1024, // 100MB\n    \n    // 允许的HTTP方法\n    'allowed_methods' => [\n        'OPTIONS', 'GET', 'HEAD', 'PUT', 'DELETE', \n        'MKCOL', 'COPY', 'MOVE', 'PROPFIND', 'PROPPATCH'\n    ]\n];\n?>";
            
            file_put_contents(__DIR__ . '/config.php', $configContent);
            
            // 创建安装锁定文件
            file_put_contents($lockFile, date('Y-m-d H:i:s'));
            
            $success = true;
        } catch (Exception $e) {
            $errors[] = '安装失败：' . $e->getMessage();
        }
    }
}

// 生成随机字符串函数
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// 生成随机基础目录名（默认值）
$defaultBaseDir = 'storage/' . generateRandomString(16);

// HTML输出
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebDAV服务器安装</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .error {
            color: #e74c3c;
            background-color: #fadbd8;
            padding: 10px;
            border-radius: 3px;
            margin-bottom: 20px;
        }
        .success {
            color: #27ae60;
            background-color: #d4efdf;
            padding: 10px;
            border-radius: 3px;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
        }
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 3px;
            cursor: pointer;
        }
        button:hover {
            background-color: #2980b9;
        }
        .note {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>WebDAV服务器安装</h1>
        
        <?php if ($success): ?>
            <div class="success">
                <p>安装成功！您现在可以：</p>
                <ul>
                    <li><a href="webdav.php">访问WebDAV服务</a></li>
                    <li><a href="admin/">登录管理后台</a></li>
                </ul>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <h2>管理员账号设置</h2>
                <div>
                    <label for="username">管理员用户名：</label>
                    <input type="text" id="username" name="username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required>
                </div>
                
                <div>
                    <label for="password">管理员密码：</label>
                    <input type="password" id="password" name="password" required>
                    <p class="note">密码至少需要6个字符</p>
                </div>
                
                <div>
                    <label for="confirm_password">确认密码：</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <h2>系统设置</h2>
                <div>
                    <label for="base_dir">基础目录：</label>
                    <input type="text" id="base_dir" name="base_dir" value="<?php echo htmlspecialchars($defaultBaseDir); ?>">
                    <p class="note">文件将存储在此目录中，留空将使用随机生成的目录名</p>
                </div>
                
                <div>
                    <button type="submit">安装</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
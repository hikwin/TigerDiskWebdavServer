<?php
/**
 * WebDAV服务器管理后台 - 现代化版本
 * 兼容PHP 7.3及以下版本
 */

// 开启会话
session_start();

// 加载配置
$config = require __DIR__ . '/../config.php';

// 检查是否已安装
if (!file_exists(__DIR__ . '/../install.lock')) {
    header('Location: ../install.php');
    exit;
}

// 连接数据库
try {
    $dbPath = __DIR__ . '/../' . $config['db_file'];
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 检查并添加access_dir字段
    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN access_dir TEXT DEFAULT NULL');
    } catch (Exception $e) {
        // 字段已存在，忽略错误
    }
} catch (PDOException $e) {
    die('数据库连接失败：' . $e->getMessage());
}



// 处理退出登录
if (($_GET['action'] ?? '') === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 检查是否已登录
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = $isLoggedIn && ($_SESSION['is_admin'] ?? 0) == 1;

// 如果未登录，跳转到登录页面
if (!$isLoggedIn) {
    header('Location: ../login.php');
    exit;
}

// 消息处理
$messages = [];

// 从session中获取消息
$messageTypes = ['user_message', 'profile_message'];
foreach ($messageTypes as $type) {
    if (isset($_SESSION[$type])) {
        $messages[] = [
            'text' => $_SESSION[$type],
            'type' => strpos($_SESSION[$type], '错误') === 0 ? 'error' : 'success'
        ];
        unset($_SESSION[$type]);
    }
}

// 处理用户管理操作（仅管理员）
if ($isAdmin) {
    // 添加用户 - 改为AJAX处理
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'add_user') {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $accessDir = isset($_POST['access_dir']) ? trim($_POST['access_dir']) : '';
        
        $response = ['success' => false, 'message' => ''];
        
        if (!empty($username) && !empty($password)) {
            try {
                // 检查用户名是否已存在
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
                $stmt->execute([$username]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $response['message'] = '用户名已存在';
                } else {
                    // 添加新用户
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    if (empty($accessDir)) {
                        $accessDir = $username;
                    }
                    
                    $stmt = $pdo->prepare('INSERT INTO users (username, password, is_admin, access_dir) VALUES (?, ?, 0, ?)');
                    $stmt->execute([$username, $hashedPassword, $accessDir]);
                    
                    
                    $response['success'] = true;
                    $response['message'] = '用户添加成功';
                }
            } catch (Exception $e) {
                $response['message'] = '错误：' . $e->getMessage();
            }
        } else {
            $response['message'] = '用户名和密码不能为空';
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // 批量添加用户
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'batch_add_users') {
        $usernames = isset($_POST['usernames']) ? json_decode($_POST['usernames'], true) : [];
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        
        if (!empty($usernames) && !empty($password)) {
            try {
                $addedCount = 0;
                $failedCount = 0;
                $failedDetails = []; // 存储失败用户及原因
                $duplicateUsers = [];
                $pdo->beginTransaction();
                
                foreach ($usernames as $username) {
                    $username = trim($username);
                    if (empty($username)) continue;
                    
                    // 检查用户名是否已存在
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
                    $stmt->execute([$username]);
                    $count = $stmt->fetchColumn();
                    
                    if ($count > 0) {
                        $failedDetails[] = [
                            'username' => $username,
                            'reason' => '用户名已存在'
                        ];
                        $duplicateUsers[] = $username;
                        $failedCount++;
                        continue;
                    }
                    
                    try {
                        // 添加新用户
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $accessDir = $username;
                        
                        $stmt = $pdo->prepare('INSERT INTO users (username, password, is_admin, access_dir) VALUES (?, ?, 0, ?)');
                        $stmt->execute([$username, $hashedPassword, $accessDir]);
                        
                        
                        $addedCount++;
                    } catch (Exception $e) {
                        $failedDetails[] = [
                            'username' => $username,
                            'reason' => $e->getMessage()
                        ];
                        $failedCount++;
                    }
                }
                
                $pdo->commit();
                
                // 构建响应数据
                $response = [
                    'success' => $addedCount > 0,
                    'added' => $addedCount,
                    'failed' => $failedCount,
                    'failedDetails' => $failedDetails,
                    'duplicates' => $duplicateUsers,
                    'total' => count($usernames)
                ];
                
                if ($addedCount > 0 && $failedCount > 0) {
                    $response['message'] = "成功添加 {$addedCount} 个用户，{$failedCount} 个失败";
                } elseif ($addedCount > 0) {
                    $response['message'] = "成功批量添加 {$addedCount} 个用户";
                } elseif ($failedCount > 0) {
                    $response['message'] = "全部添加失败，共 {$failedCount} 个";
                }
                
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
                $failedDetails = [];
                foreach ($usernames as $username) {
                    $failedDetails[] = [
                        'username' => trim($username),
                        'reason' => '系统错误：' . $e->getMessage()
                    ];
                }
                
                $response = [
                    'success' => false,
                    'message' => '系统错误：' . $e->getMessage(),
                    'added' => 0,
                    'failed' => count($usernames),
                    'failedDetails' => $failedDetails,
                    'duplicates' => []
                ];
                
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        } else {
            $response = [
                'success' => false,
                'message' => '用户名列表和密码不能为空',
                'added' => 0,
                'failed' => 0,
                'failedDetails' => [],
                'duplicates' => []
            ];
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }
    
    // 删除用户
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        
        if ($userId > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND is_admin = 0');
                $stmt->execute([$userId]);
                $_SESSION['user_message'] = '用户删除成功';
            } catch (Exception $e) {
                $_SESSION['user_message'] = '错误：' . $e->getMessage();
            }
        }
        
        header('Location: index.php#users');
        exit;
    }
    
    // 修改用户密码
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'change_user_password') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $newPassword = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
        
        if ($userId > 0 && !empty($newPassword)) {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ? AND is_admin = 0');
                $stmt->execute([$hashedPassword, $userId]);
                $_SESSION['user_message'] = '密码修改成功';
            } catch (Exception $e) {
                $_SESSION['user_message'] = '错误：' . $e->getMessage();
            }
        }
        
        header('Location: index.php#users');
        exit;
    }
    
    // 修改用户访问目录
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'change_access_dir') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $accessDir = isset($_POST['access_dir']) ? trim($_POST['access_dir']) : '';
        
        if ($userId > 0) {
            try {
                if (empty($accessDir)) {
                    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    $accessDir = $user['username'];
                }
                
                $stmt = $pdo->prepare('UPDATE users SET access_dir = ? WHERE id = ? AND is_admin = 0');
                $stmt->execute([$accessDir, $userId]);
                $_SESSION['user_message'] = '访问目录修改成功';
            } catch (Exception $e) {
                $_SESSION['user_message'] = '错误：' . $e->getMessage();
            }
        }
        
        header('Location: index.php#users');
        exit;
    }
}

// 处理个人信息修改
if ($isLoggedIn) {
    // 修改密码
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'change_own_password') {
        $currentPassword = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
        $newPassword = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
        $confirmPassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
        
        if (!empty($currentPassword) && !empty($newPassword) && !empty($confirmPassword)) {
            if ($newPassword !== $confirmPassword) {
                $_SESSION['profile_message'] = '错误：两次输入的新密码不一致';
            } else {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($currentPassword, $user['password'])) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                    $_SESSION['profile_message'] = '密码修改成功';
                } else {
                    $_SESSION['profile_message'] = '错误：当前密码不正确';
                }
            }
        } else {
            $_SESSION['profile_message'] = '错误：所有密码字段都必须填写';
        }
        
        header('Location: index.php#account');
        exit;
    }
    
    // 修改用户名
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'change_username') {
        $newUsername = isset($_POST['new_username']) ? trim($_POST['new_username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        
        if (!empty($newUsername) && !empty($password)) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id != ?');
                $stmt->execute([$newUsername, $_SESSION['user_id']]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $_SESSION['profile_message'] = '错误：用户名已存在';
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
                    $stmt->execute([$newUsername, $_SESSION['user_id']]);
                    $_SESSION['username'] = $newUsername;
                    $_SESSION['profile_message'] = '用户名修改成功';
                }
            } else {
                $_SESSION['profile_message'] = '错误：密码不正确';
            }
        } else {
            $_SESSION['profile_message'] = '错误：新用户名和密码不能为空';
        }
        
        header('Location: index.php#account');
        exit;
    }
}

// 获取数据
$users = [];
$totalUsers = 0;
$currentPage = max(1, intval($_GET['page'] ?? 1));
$perPage = max(1, intval($_GET['per_page'] ?? 10));

if ($isAdmin) {
    // 获取总数
    $countStmt = $pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 0');
    $totalUsers = $countStmt->fetchColumn();
    
    // 计算分页
    $totalPages = max(1, ceil($totalUsers / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;
    
    // 获取用户列表
    $stmt = $pdo->prepare('SELECT * FROM users WHERE is_admin = 0 ORDER BY created_at DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取当前用户信息
$currentUser = null;
if ($isLoggedIn) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 生成WebDAV URL
$webdavUrl = '';
if ($isLoggedIn) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    // 获取当前脚本所在目录的相对路径，然后从admin目录退回到父目录
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $baseDir = dirname($scriptDir); // 从admin目录退回到webdav.php所在的目录
    
    // 构建webdav.php的完整URL路径
    $webdavPath = rtrim($baseDir, '/') . '/webdav.php';
    
    $webdavUrl = $protocol . '://' . $host . $webdavPath;
    // 替换反斜杠为空字符串
    $webdavUrl = str_replace('\\', '', $webdavUrl);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebDAV服务器管理后台</title>
    <link rel="stylesheet" href="../assets/css/modern-admin.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%232563eb'%3E%3Cpath d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z'/%3E%3C/svg%3E">
</head>
<body>
    <div class="container">
            <!-- 管理后台 -->
            <div class="header">
                <div>
                    <h1>WebDAV 管理后台</h1>
                    <p style="margin: 0; color: var(--gray-600);">
                        欢迎回来，<strong><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></strong>
                    </p>
                </div>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <a href="?action=logout" class="btn btn-secondary">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                            <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
                        </svg>
                        退出登录
                    </a>
                </div>
            </div>

            <!-- 消息显示 -->
            <?php foreach ($messages as $message): ?>
                <div class="message <?php echo $message['type']; ?>">
                    <?php echo htmlspecialchars($message['text']); ?>
                </div>
            <?php endforeach; ?>

            <!-- 标签页导航 -->
            <div class="tabs">
                <button class="tab active" data-tab="account">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 0.5rem;">
                        <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                    </svg>
                    账号设置
                </button>
                
                <?php if ($isAdmin): ?>
                    <button class="tab" data-tab="users">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 0.5rem;">
                            <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                            <path fill-rule="evenodd" d="M5.216 14A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216z"/>
                            <path d="M4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>
                        </svg>
                        用户管理
                    </button>
                <?php endif; ?>
                
                <button class="tab" data-tab="webdav">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 0.5rem;">
                        <path d="M4.406 1.342A5.526 5.526 0 0 1 8 0c2.692 0 4.944 1.959 4.954 4.467.002.391.003.792.003 1.202A5.526 5.526 0 0 1 8 15a5.526 5.526 0 0 1-4.594-2.458C2.128 11.445 1.5 9.773 1.5 8c0-1.773.628-3.445 1.906-4.542l.797.797C3.32 4.645 2.5 6.202 2.5 8c0 1.798.82 3.355 2.094 4.345l.797.797z"/>
                        <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                    </svg>
                    WebDAV信息
                </button>
            </div>

            <!-- 账号设置 -->
            <div id="account" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">修改用户名</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="change_username">
                            
                            <div class="form-group">
                                <label class="form-label" for="new_username">新用户名</label>
                                <input type="text" id="new_username" name="new_username" class="form-input" required 
                                       placeholder="请输入新的用户名">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="password_confirm">当前密码（确认）</label>
                                <input type="password" id="password_confirm" name="password" class="form-input" required 
                                       placeholder="请输入当前密码进行确认">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">修改用户名</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">修改密码</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="change_own_password">
                            
                            <div class="form-group">
                                <label class="form-label" for="current_password">当前密码</label>
                                <input type="password" id="current_password" name="current_password" class="form-input" required 
                                       placeholder="请输入当前密码">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="new_password">新密码</label>
                                <input type="password" id="new_password" name="new_password" class="form-input" required 
                                       placeholder="请输入新密码">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="confirm_password">确认新密码</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-input" required 
                                       placeholder="请再次输入新密码">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">修改密码</button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($isAdmin): ?>
                <!-- 用户管理 -->
                <div id="users" class="tab-content">
                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 class="card-title">用户管理</h2>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('addUserModal').classList.add('active')">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 0.5rem;">
                                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                </svg>
                                添加用户
                            </button>
                            <button type="button" class="btn btn-success" onclick="document.getElementById('batchAddUserModal').classList.add('active')">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 0.5rem;">
                                    <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                                    <path fill-rule="evenodd" d="M5.216 14A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216z"/>
                                    <path d="M4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>
                                </svg>
                                批量添加用户
                            </button>
                        </div>
                    </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>用户名</th>
                                            <th>访问目录</th>
                                            <th class="hide-mobile">创建时间</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['id']; ?></td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['access_dir'] ?? $user['username']); ?></td>
                                                <td class="hide-mobile"><?php echo $user['created_at']; ?></td>
                                                <td>
                                                    <div style="display: flex; gap: 0.5rem;">
                                                        <button type="button" class="btn btn-sm btn-secondary" 
                                                                onclick="showChangePasswordForm(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                            修改密码
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-secondary"
                                                                onclick="showChangeAccessDirForm(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['access_dir'] ?: $user['username']); ?>')">
                                                            修改目录
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger"
                                                                onclick="showDeleteUserForm(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                            删除
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- 分页 -->
                            <?php if ($totalPages > 1): ?>
                                <div style="margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        显示 <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalUsers); ?> 条，共 <?php echo $totalUsers; ?> 条
                                    </div>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <?php if ($currentPage > 1): ?>
                                            <a href="?page=<?php echo $currentPage - 1; ?>#users" class="btn btn-sm btn-secondary">上一页</a>
                                        <?php endif; ?>
                                        
                                        <span>第 <?php echo $currentPage; ?> 页 / 共 <?php echo $totalPages; ?> 页</span>
                                        
                                        <?php if ($currentPage < $totalPages): ?>
                                            <a href="?page=<?php echo $currentPage + 1; ?>#users" class="btn btn-sm btn-secondary">下一页</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- WebDAV信息 -->
            <div id="webdav" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">WebDAV 连接信息</h2>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <tr>
                                    <th>服务器地址</th>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($webdavUrl); ?>" target="_blank" rel="noopener noreferrer" style="color: var(--primary-color); text-decoration: none;">
                                            <code id="webdav-url"><?php echo htmlspecialchars($webdavUrl); ?></code>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-secondary copy-btn" data-target="webdav-url" style="margin-left: 0.5rem;">
                                            复制
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <th>用户名</th>
                                    <td>
                                        <code id="webdav-username"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></code>
                                        <button type="button" class="btn btn-sm btn-secondary copy-btn" data-target="webdav-username" style="margin-left: 0.5rem;">
                                            复制
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <th>密码</th>
                                    <td><em>使用您的登录密码</em></td>
                                </tr>
                                <tr>
                                    <th>用户身份</th>
                                    <td>
                                        <?php if ($isAdmin): ?>
                                            <span class="badge badge-admin">管理员</span>
                                        <?php else: ?>
                                            <span class="badge badge-user">普通用户</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>访问目录</th>
                                    <td>
                                        <?php if ($isAdmin): ?>
                                            <span class="badge badge-full-access">所有目录</span>
                                            <small style="display: block; margin-top: 0.25rem; color: var(--gray-600);">
                                                管理员可访问所有用户的目录
                                            </small>
                                        <?php else: ?>
                                            <code><?php echo htmlspecialchars($currentUser['access_dir'] ?? ($_SESSION['username'] ?? '')); ?></code>
                                            <small style="display: block; margin-top: 0.25rem; color: var(--gray-600);">
                                                仅能访问此目录下的文件
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div style="margin-top: 1rem; padding: 1rem; background: var(--gray-50); border-radius: var(--radius-md);">
                            <h3 style="margin-top: 0; font-size: 1rem;">使用说明</h3>
                            <p style="margin-bottom: 0.5rem;">
                                您可以使用任何支持WebDAV协议的客户端连接到服务器，推荐以下常用软件：
                            </p>
                            <ul style="margin: 0; padding-left: 1.5rem;">
                                <li><strong>Windows：</strong>文件资源管理器、RaiDrive（推荐）</li>
                                <li><strong>macOS：</strong>Finder</li>
                                <li><strong>Linux：</strong>Nautilus、Dolphin、RaiDrive等</li>
                                <li><strong>笔记软件：</strong>Joplin、思源笔记、Obsidian（插件支持WebDAV同步）</li>
                            </ul>
                            <p style="margin-top: 0.5rem; margin-bottom: 0; font-size: 0.875rem; color: var(--gray-600);">
                                💡 <strong>推荐：</strong>RaiDrive 是Windows平台下功能强大的WebDAV客户端，支持挂载为本地磁盘，操作更便捷。
                            </p>
                        </div>
                    </div>
                </div>
            </div>
    </div>

    <!-- 添加用户模态框 -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">添加新用户</h3>
                <button type="button" class="modal-close" onclick="this.closest('.modal').classList.remove('active')">&times;</button>
            </div>
            <form id="addUserForm" onsubmit="addUser(event)">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-group">
                    <label class="form-label" for="add_username">用户名</label>
                    <input type="text" id="add_username" name="username" class="form-input" required 
                           placeholder="请输入用户名">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="add_password">密码</label>
                    <input type="password" id="add_password" name="password" class="form-input" required 
                           placeholder="请输入密码">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="add_access_dir">访问目录（可选）</label>
                    <input type="text" id="add_access_dir" name="access_dir" class="form-input" 
                           placeholder="留空则使用用户名作为目录名">
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" 
                            onclick="this.closest('.modal').classList.remove('active')">取消</button>
                    <button type="submit" class="btn btn-primary">添加用户</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 修改密码模态框 -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">修改用户密码</h3>
                <button type="button" class="modal-close" onclick="this.closest('.modal').classList.remove('active')">&times;</button>
            </div>
            <form method="post" action="">
                <input type="hidden" name="action" value="change_user_password">
                <input type="hidden" id="changePasswordUserId" name="user_id" value="">
                
                <p>正在修改用户：<strong id="changePasswordUsername"></strong> 的密码</p>
                
                <div class="form-group">
                    <label class="form-label" for="changePasswordNew">新密码</label>
                    <input type="password" id="changePasswordNew" name="new_password" class="form-input" required 
                           placeholder="请输入新密码">
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" 
                            onclick="this.closest('.modal').classList.remove('active')">取消</button>
                    <button type="submit" class="btn btn-primary">修改密码</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 修改访问目录模态框 -->
    <div id="changeAccessDirModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">修改访问目录</h3>
                <button type="button" class="modal-close" onclick="this.closest('.modal').classList.remove('active')">&times;</button>
            </div>
            <form method="post" action="">
                <input type="hidden" name="action" value="change_access_dir">
                <input type="hidden" id="changeAccessDirUserId" name="user_id" value="">
                
                <p>正在修改用户：<strong id="changeAccessDirUsername"></strong> 的访问目录</p>
                
                <div class="form-group">
                    <label class="form-label" for="newAccessDir">新目录名</label>
                    <input type="text" id="newAccessDir" name="access_dir" class="form-input" required 
                           placeholder="请输入新的目录名">
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" 
                            onclick="this.closest('.modal').classList.remove('active')">取消</button>
                    <button type="submit" class="btn btn-primary">修改目录</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 删除用户模态框 -->
    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">确认删除</h3>
                <button type="button" class="modal-close" onclick="this.closest('.modal').classList.remove('active')">&times;</button>
            </div>
            <form method="post" action="">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" id="deleteUserId" name="user_id" value="">
                
                <p>确定要删除用户：<strong id="deleteUsername"></strong> 吗？</p>
                <p style="color: var(--error-color); margin-top: 0.5rem;">
                    此操作不可撤销，用户的数据目录不会被删除。
                </p>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" 
                            onclick="this.closest('.modal').classList.remove('active')">取消</button>
                    <button type="submit" class="btn btn-danger">确认删除</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 批量添加用户模态框 -->
        <div class="modal" id="batchAddUserModal">
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h3>批量添加用户</h3>
                    <button type="button" class="modal-close" onclick="closeBatchAddUserModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="batchAddUserForm" onsubmit="batchAddUser(event)" style="margin: 0;">
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="batchUsernames" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--gray-700);">用户名列表</label>
                            <textarea id="batchUsernames" name="usernames" rows="6" 
                                style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.875rem; line-height: 1.5; resize: vertical; min-height: 120px;"
                                placeholder="每行输入一个用户名&#10;例如：&#10;user1&#10;user2&#10;user3" required></textarea>
                            <div style="margin-top: 0.375rem; font-size: 0.8125rem; color: var(--gray-600); line-height: 1.4;">
                                <i class="fas fa-info-circle" style="margin-right: 0.25rem;"></i>
                                每个用户名独占一行，将自动创建与用户名相同的访问目录
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 2rem;">
                            <label for="batchPassword" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--gray-700);">统一密码</label>
                            <input type="password" id="batchPassword" name="password" required
                                style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.875rem;">
                            <div style="margin-top: 0.375rem; font-size: 0.8125rem; color: var(--gray-600); line-height: 1.4;">
                                <i class="fas fa-shield-alt" style="margin-right: 0.25rem;"></i>
                                所有批量创建的用户将使用此统一密码，建议设置6位以上
                            </div>
                        </div>
                        <div class="form-actions" style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 0;">
                            <button type="button" class="btn btn-secondary" onclick="closeBatchAddUserModal()" 
                                style="min-width: 80px; padding: 0.625rem 1.25rem; font-size: 0.875rem;">取消</button>
                            <button type="submit" class="btn btn-primary" 
                                style="min-width: 100px; padding: 0.625rem 1.25rem; font-size: 0.875rem; background: var(--primary-color); border-color: var(--primary-color);">
                                <i class="fas fa-users" style="margin-right: 0.375rem;"></i>
                                批量添加
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <script src="../assets/js/modern-admin.js"></script>
    <script>
        // 复制功能
        document.querySelectorAll('.copy-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const target = document.getElementById(this.dataset.target);
                if (target) {
                    navigator.clipboard.writeText(target.textContent).then(() => {
                        const originalText = this.textContent;
                        this.textContent = '已复制';
                        setTimeout(() => {
                            this.textContent = originalText;
                        }, 2000);
                    });
                }
            });
        });

        // 关闭批量添加用户模态框
        function closeBatchAddUserModal() {
            document.getElementById('batchAddUserModal').classList.remove('active');
            document.getElementById('batchAddUserForm').reset();
        }

        // 添加用户（AJAX请求）
        function addUser(event) {
            event.preventDefault();
            
            const form = document.getElementById('addUserForm');
            const formData = new FormData(form);
            const username = formData.get('username');
            const password = formData.get('password');
            
            if (!username || !password) {
                showToast('用户名和密码不能为空', 'error');
                return;
            }
            
            if (password.length < 6) {
                showToast('密码长度至少为6位', 'error');
                return;
            }
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    document.getElementById('addUserModal').classList.remove('active');
                    form.reset();
                    // 延迟刷新页面以显示新用户
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('网络错误，请重试', 'error');
            });
        }

        // 显示批量添加失败确认框
        function showBatchAddFailureDialog(data) {
            const modal = document.createElement('div');
            modal.className = 'modal active';
            modal.style.zIndex = '1000';
            
            let content = `
                <div class="modal-content" style="max-width: 600px; max-height: 80vh; overflow-y: auto;">
                    <div class="modal-header">
                        <h3 style="margin: 0; color: var(--error-color);">
                            <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>
                            批量添加结果
                        </h3>
                        <button type="button" class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                    </div>
                    <div class="modal-body" style="padding: 1.5rem;">
            `;
            
            if (data.added > 0) {
                content += `
                    <div style="margin-bottom: 1.5rem; padding: 1rem; background: var(--success-bg); border-left: 4px solid var(--success-color); border-radius: var(--radius-sm);">
                        <h4 style="margin: 0 0 0.5rem 0; color: var(--success-color);">
                            <i class="fas fa-check-circle"></i> 成功添加 ${data.added} 个用户
                        </h4>
                    </div>
                `;
            }
            
            if (data.failed > 0) {
                content += `
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="margin: 0 0 1rem 0; color: var(--error-color);">
                            <i class="fas fa-times-circle"></i> 添加失败 ${data.failed} 个用户
                        </h4>
                        <div style="max-height: 300px; overflow-y: auto; border: 1px solid var(--gray-300); border-radius: var(--radius-sm);">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead style="background: var(--gray-100);">
                                    <tr>
                                        <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--gray-300);">用户名</th>
                                        <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--gray-300);">失败原因</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                data.failedDetails.forEach(detail => {
                    content += `
                        <tr style="border-bottom: 1px solid var(--gray-200);">
                            <td style="padding: 0.75rem; font-family: monospace; font-weight: 600; color: var(--gray-700);">${detail.username}</td>
                            <td style="padding: 0.75rem; color: var(--error-color);">${detail.reason}</td>
                        </tr>
                    `;
                });
                
                content += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            }
            
            content += `
                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                            <button type="button" class="btn btn-primary" onclick="this.closest('.modal').remove()">
                                <i class="fas fa-check" style="margin-right: 0.375rem;"></i>确定
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            modal.innerHTML = content;
            document.body.appendChild(modal);
        }

        // 批量添加用户
        function batchAddUser(event) {
            event.preventDefault();
            
            const formData = new FormData(document.getElementById('batchAddUserForm'));
            const usernames = formData.get('usernames').trim().split('\n').filter(name => name.trim());
            const password = formData.get('password');
            
            if (usernames.length === 0) {
                showToast('请输入至少一个用户名', 'error');
                return;
            }
            
            if (password.length < 6) {
                showToast('密码长度至少为6位', 'error');
                return;
            }
            
            // 不再禁用按钮或更改状态，保持按钮初始状态
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'batch_add_users',
                    usernames: JSON.stringify(usernames),
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.failed === 0) {
                    // 全部成功
                    showToast(data.message, 'success');
                    closeBatchAddUserModal();
                    setTimeout(() => location.reload(), 1000);
                } else if (data.failed > 0) {
                    // 有失败的情况，显示确认框（不关闭模态框，让用户可以修改后重新提交）
                    showBatchAddFailureDialog(data);
                    
                    // 只有在全部成功时才关闭模态框和刷新
                    if (data.added > 0 && data.failed === 0) {
                        closeBatchAddUserModal();
                        setTimeout(() => location.reload(), 1000);
                    }
                } else {
                    // 全部失败，显示确认框，保持模态框打开
                    showBatchAddFailureDialog(data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('网络错误，请重试', 'error');
            });
            // 移除finally中的按钮状态恢复代码
        }
        
        // 显示toast提示
        function showToast(message, type = 'info') {
            // 优先使用modern-admin.js的showNotification
            if (typeof ModernAdmin !== 'undefined' && ModernAdmin.showNotification) {
                ModernAdmin.showNotification(message, type);
            } else {
                // 降级方案
                const notification = document.createElement('div');
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    padding: 12px 16px;
                    border-radius: 8px;
                    color: white;
                    font-size: 14px;
                    font-weight: 500;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    background: ${type === 'success' ? '#52c41a' : type === 'error' ? '#ff4d4f' : '#1890ff'};
                    animation: slideIn 0.3s ease-out;
                `;
                notification.textContent = message;
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 3000);
            }
        }

        // 模态框函数
        function showChangePasswordForm(userId, username) {
            document.getElementById('changePasswordUserId').value = userId;
            document.getElementById('changePasswordUsername').textContent = username;
            document.getElementById('changePasswordModal').classList.add('active');
        }

        function showChangeAccessDirForm(userId, username, currentDir) {
            document.getElementById('changeAccessDirUserId').value = userId;
            document.getElementById('changeAccessDirUsername').textContent = username;
            document.getElementById('newAccessDir').value = currentDir;
            document.getElementById('changeAccessDirModal').classList.add('active');
        }

        function showDeleteUserForm(userId, username) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUsername').textContent = username;
            document.getElementById('deleteUserModal').classList.add('active');
        }
    </script>
</body>
</html>
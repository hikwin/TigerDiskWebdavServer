<?php
/**
 * 纯PHP WebDAV服务器 - 数据库集成版本
 */

// 添加会话管理
session_start();

class WebDAVServer {
    private $baseDir;
    private $realm = 'WebDAV Server';
    private $authenticated = false;
    private $pdo = null;
    private $config = [];
    private $currentUser = null;
    private $userDir = null;
    
    public function __construct($baseDir = './webdav', $users = [], $realm = 'WebDAV Server') {
        // 加载配置
        $this->config = require __DIR__ . '/config.php';
        
        // 设置基础目录
        $this->baseDir = isset($this->config['base_dir']) ? rtrim($this->config['base_dir'], '/') . '/' : rtrim($baseDir, '/') . '/';
        $this->realm = $realm;
        
        // 确保基础目录存在
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0755, true);
        }
        
        // 连接数据库
        try {
            if (isset($this->config['db_file'])) {
                $dbPath = __DIR__ . '/' . $this->config['db_file'];
                if (file_exists($dbPath)) {
                    $this->pdo = new PDO('sqlite:' . $dbPath);
                    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                }
            }
        } catch (PDOException $e) {
            // 数据库连接失败，继续使用配置文件中的用户
        }
        
        // 初始化当前用户目录
        $this->userDir = null;
    }
    
    public function handle() {
        try {
            // 处理文件上传请求
            if (isset($_POST['upload']) && isset($_FILES['file'])) {
                $this->handleFileUpload();
                return;
            }
            
            // 处理文件下载请求
            if (isset($_GET['download'])) {
                $filePath = $_GET['download'];
                $realPath = $this->getRealPath($filePath);
                
                if (!file_exists($realPath) || is_dir($realPath)) {
                    $this->sendResponse(404, 'File not found');
                    return;
                }
                
                $this->sendFile($realPath);
                return;
            }
            
            // 处理文件大小检查请求
            if (isset($_GET['check_size'])) {
                $filePath = $_GET['check_size'];
                $realPath = $this->getRealPath($filePath);
                
                if (!file_exists($realPath) || is_dir($realPath)) {
                    echo json_encode(['error' => 'File not found']);
                    return;
                }
                
                echo json_encode(['size' => filesize($realPath)]);
                return;
            }
            
            // 处理文件预览请求
            if (isset($_GET['preview'])) {
                $filePath = $_GET['preview'];
                $realPath = $this->getRealPath($filePath);
                
                if (!file_exists($realPath) || is_dir($realPath)) {
                    $this->sendResponse(404, 'File not found');
                    return;
                }
                
                // 检查文件大小（限制200KB）
                $fileSize = filesize($realPath);
                if ($fileSize > 200 * 1024) {
                    $this->sendResponse(413, 'File too large for preview');
                    return;
                }
                
                // 读取文件内容
                $content = file_get_contents($realPath);
                if ($content === false) {
                    $this->sendResponse(500, 'Failed to read file');
                    return;
                }
                
                // 设置适当的响应头
                header('Content-Type: text/plain; charset=utf-8');
                header('Cache-Control: no-cache');
                echo $content;
                return;
            }
            
            // 处理文件删除请求
            if (isset($_POST['delete'])) {
                $filePath = $_POST['delete'];
                $realPath = $this->getRealPath($filePath);
                
                if (!file_exists($realPath)) {
                    echo json_encode(['success' => false, 'message' => '文件不存在']);
                    return;
                }
                
                try {
                    if (is_dir($realPath)) {
                        $this->removeDirectory($realPath);
                    } else {
                        unlink($realPath);
                    }
                    echo json_encode(['success' => true, 'message' => '文件删除成功']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => '删除文件失败: ' . $e->getMessage()]);
                }
                return;
            }
            
            // 处理登出请求 - 现在重定向到统一登出页面
            if (isset($_GET['logout'])) {
                header('Location: ./logout.php');
                exit;
            }
            
            // 处理身份验证 - 修复版本
            if (!$this->authenticateFixed()) {
                $this->sendUnauthorized();
                return;
            }
            
            $method = $_SERVER['REQUEST_METHOD'];
            $path = $this->getPath();
            
            switch ($method) {
                case 'OPTIONS':
                    $this->handleOptions();
                    break;
                case 'GET':
                    $this->handleGet($path);
                    break;
                case 'HEAD':
                    $this->handleHead($path);
                    break;
                case 'PUT':
                    $this->handlePut($path);
                    break;
                case 'DELETE':
                    $this->handleDelete($path);
                    break;
                case 'MKCOL':
                    $this->handleMkcol($path);
                    break;
                case 'COPY':
                    $this->handleCopy($path);
                    break;
                case 'MOVE':
                    $this->handleMove($path);
                    break;
                case 'PROPFIND':
                    $this->handlePropfind($path);
                    break;
                case 'PROPPATCH':
                    $this->handleProppatch($path);
                    break;
                default:
                    $this->sendResponse(405, 'Method Not Allowed');
            }
        } catch (Exception $e) {
            $this->sendResponse(500, 'Internal Server Error: ' . $e->getMessage());
        }
    }
    
    private function authenticateFixed() {
        // 如果没有安装，允许匿名访问
        if (!file_exists(__DIR__ . '/install.lock')) {
            return true;
        }
        
        // 获取认证信息
        $username = null;
        $password = null;
        
        // 方法1: 标准PHP_AUTH
        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            $username = $_SERVER['PHP_AUTH_USER'];
            $password = $_SERVER['PHP_AUTH_PW'];
        }
        // 方法2: HTTP_AUTHORIZATION头
        elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (preg_match('/Basic\s+(.*)$/i', $auth, $matches)) {
                list($username, $password) = explode(':', base64_decode($matches[1]));
            }
        }
        // 方法3: REDIRECT_HTTP_AUTHORIZATION头
        elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            if (preg_match('/Basic\s+(.*)$/i', $auth, $matches)) {
                list($username, $password) = explode(':', base64_decode($matches[1]));
            }
        }
        // 方法4: Apache环境下通过SetEnvIf设置的HTTP_AUTHORIZATION
        elseif (isset($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Basic\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            list($username, $password) = explode(':', base64_decode($matches[1]));
        }
        
        if ($username && $password) {
            // 优先使用数据库验证
            if ($this->pdo) {
                try {
                    $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
                    $stmt->execute([$username]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user && password_verify($password, $user['password'])) {
                        $this->currentUser = $user;
                        $this->userDir = $user['access_dir'] ?: $username;
                        $this->authenticated = true;
                        return true;
                    }
                } catch (Exception $e) {
                    // 数据库验证失败，尝试配置文件验证
                }
            }
            
            // 回退到配置文件验证
            if (isset($this->config['users'][$username]) && $this->config['users'][$username] === $password) {
                $this->currentUser = ['username' => $username];
                $this->userDir = $username;
                $this->authenticated = true;
                return true;
            }
        }
        
        return false;
    }
    
    private function sendUnauthorized() {
        header('WWW-Authenticate: Basic realm="' . $this->realm . '"');
        header('HTTP/1.1 401 Unauthorized');
        echo '<!DOCTYPE html><html><head><title>401 Unauthorized</title></head><body><h1>401 Unauthorized</h1><p>请输入正确的用户名和密码</p></body></html>';
        exit;
    }
    

    
    // 其余方法保持不变...
    private function getPath() {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = urldecode($path);
        $path = ltrim($path, '/');
        
        // 获取当前脚本的路径
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        $scriptDir = dirname($scriptPath);
        $scriptName = basename($scriptPath);
        
        // 构建webdav.php的完整路径
        $webdavPath = trim($scriptDir . '/' . $scriptName, '/');
        
        // 如果请求的是webdav.php本身，返回空字符串以访问根目录
        if ($path === $webdavPath || $path === $scriptName || $path === '') {
            return '';
        }
        
        // 如果路径以webdav.php/开头，去掉前缀
        $prefix = $webdavPath . '/';
        if (strpos($path, $prefix) === 0) {
            return substr($path, strlen($prefix));
        }
        
        // 检查是否直接访问webdav.php
        if (strpos($path, $scriptName . '/') === 0) {
            return substr($path, strlen($scriptName) + 1);
        }
        
        // 检查是否部署在根目录
        if ($scriptDir === '/') {
            return $path;
        }
        
        return $path;
    }
    
    private function getRealPath($path) {
        // 如果没有安装，使用基础目录
        if (!file_exists(__DIR__ . '/install.lock')) {
            return $this->baseDir . ltrim($path, '/');
        }
        
        // 检查当前用户是否为管理员
        $isAdmin = false;
        if ($this->currentUser && isset($this->currentUser['is_admin'])) {
            $isAdmin = (bool)$this->currentUser['is_admin'];
        }
        
        // 管理员用户不受目录限制，可以访问所有路径
        if ($isAdmin) {
            return $this->baseDir . ltrim($path, '/');
        }
        
        // 普通用户受目录限制
        if (!$this->userDir) {
            return $this->baseDir . ltrim($path, '/');
        }
        
        // 构建用户限制目录路径
        $userBasePath = $this->baseDir . $this->userDir;
        
        // 确保用户目录存在
        if (!is_dir($userBasePath)) {
            mkdir($userBasePath, 0755, true);
        }
        
        // 限制用户只能访问其指定目录
        $relativePath = ltrim($path, '/');
        return $userBasePath . '/' . $relativePath;
    }
    
    private function handleOptions() {
        header('Allow: OPTIONS, GET, HEAD, PUT, DELETE, MKCOL, COPY, MOVE, PROPFIND, PROPPATCH');
        header('DAV: 1, 2');
        $this->sendResponse(200, 'OK');
    }
    
    private function handleGet($path) {
        $realPath = $this->getRealPath($path);
        
        if (!file_exists($realPath)) {
            $this->sendResponse(404, 'Not Found');
            return;
        }
        
        if (is_dir($realPath)) {
            $this->sendDirectoryListing($realPath, $path);
        } else {
            $this->sendFile($realPath);
        }
    }
    
    private function handleHead($path) {
        $realPath = $this->getRealPath($path);
        
        if (!file_exists($realPath)) {
            $this->sendResponse(404, 'Not Found');
            return;
        }
        
        $this->setFileHeaders($realPath);
        $this->sendResponse(200, 'OK');
    }
    
    private function handlePut($path) {
        $realPath = $this->getRealPath($path);
        $dir = dirname($realPath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $input = fopen('php://input', 'rb');
        $output = fopen($realPath, 'wb');
        
        if ($input && $output) {
            stream_copy_to_stream($input, $output);
            fclose($input);
            fclose($output);
            $this->sendResponse(201, 'Created');
        } else {
            $this->sendResponse(500, 'Failed to write file');
        }
    }
    
    private function handleDelete($path) {
        $realPath = $this->getRealPath($path);
        
        if (!file_exists($realPath)) {
            $this->sendResponse(404, 'Not Found');
            return;
        }
        
        if (is_dir($realPath)) {
            $this->removeDirectory($realPath);
        } else {
            unlink($realPath);
        }
        
        $this->sendResponse(204, 'No Content');
    }
    
    private function handleMkcol($path) {
        $realPath = $this->getRealPath($path);
        
        if (file_exists($realPath)) {
            $this->sendResponse(405, 'Method Not Allowed');
            return;
        }
        
        if (mkdir($realPath, 0755, true)) {
            $this->sendResponse(201, 'Created');
        } else {
            $this->sendResponse(409, 'Conflict');
        }
    }
    
    private function handleFileUpload() {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => '文件上传失败']);
            return;
        }
        
        $uploadedFile = $_FILES['file'];
        $fileName = basename($uploadedFile['name']);
        $tmpName = $uploadedFile['tmp_name'];
        
        // 获取当前目录路径
        $currentPath = $this->getPath();
        $realDir = $this->getRealPath($currentPath);
        
        // 确保目录存在
        if (!is_dir($realDir)) {
            mkdir($realDir, 0755, true);
        }
        
        $targetPath = $realDir . '/' . $fileName;
        
        // 检查文件是否已存在
        if (file_exists($targetPath)) {
            echo json_encode(['success' => false, 'message' => '文件已存在']);
            return;
        }
        
        // 移动上传的文件
        if (move_uploaded_file($tmpName, $targetPath)) {
            // 设置文件权限
            chmod($targetPath, 0644);
            echo json_encode(['success' => true, 'message' => '文件上传成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '文件保存失败']);
        }
    }
    
    private function handleCopy($path) {
        $source = $this->getRealPath($path);
        $destination = $this->getRealPath($_SERVER['HTTP_DESTINATION'] ?? '');
        
        if (!file_exists($source) || !$destination) {
            $this->sendResponse(404, 'Not Found');
            return;
        }
        
        if (file_exists($destination) && !isset($_SERVER['HTTP_OVERWRITE']) || $_SERVER['HTTP_OVERWRITE'] !== 'T') {
            $this->sendResponse(412, 'Precondition Failed');
            return;
        }
        
        if ($this->copy($source, $destination)) {
            $this->sendResponse(file_exists($destination) ? 204 : 201, file_exists($destination) ? 'No Content' : 'Created');
        } else {
            $this->sendResponse(500, 'Internal Server Error');
        }
    }
    
    private function handleMove($path) {
        $source = $this->getRealPath($path);
        $destination = $this->getRealPath($_SERVER['HTTP_DESTINATION'] ?? '');
        
        if (!file_exists($source) || !$destination) {
            $this->sendResponse(404, 'Not Found');
            return;
        }
        
        if (file_exists($destination) && !isset($_SERVER['HTTP_OVERWRITE']) || $_SERVER['HTTP_OVERWRITE'] !== 'T') {
            $this->sendResponse(412, 'Precondition Failed');
            return;
        }
        
        if (rename($source, $destination)) {
            $this->sendResponse(file_exists($destination) ? 204 : 201, file_exists($destination) ? 'No Content' : 'Created');
        } else {
            $this->sendResponse(500, 'Internal Server Error');
        }
    }
    
    private function handlePropfind($path) {
        $realPath = $this->getRealPath($path);
        $depth = $_SERVER['HTTP_DEPTH'] ?? '1';
        
        if (!file_exists($realPath)) {
            $this->sendResponse(404, 'Not Found');
            return;
        }
        
        $xml = $this->generatePropfindResponse($realPath, $path, $depth);
        
        header('Content-Type: application/xml; charset=utf-8');
        $this->sendResponse(207, 'Multi-Status', $xml);
    }
    
    private function handleProppatch($path) {
        $xml = '<?xml version="1.0" encoding="utf-8"?>
<d:multistatus xmlns:d="DAV:">
  <d:response>
    <d:href>' . htmlspecialchars($_SERVER['REQUEST_URI']) . '</d:href>
    <d:propstat>
      <d:prop/>
      <d:status>HTTP/1.1 200 OK</d:status>
    </d:propstat>
  </d:response>
</d:multistatus>';
        
        header('Content-Type: application/xml; charset=utf-8');
        $this->sendResponse(207, 'Multi-Status', $xml);
    }
    
    private function sendFile($realPath) {
        // 安全检查：确保文件在允许的目录内
        $realPath = realpath($realPath);
        $baseDir = realpath($this->baseDir);
        
        if (!$realPath || !$baseDir || strpos($realPath, $baseDir) !== 0) {
            $this->sendResponse(403, 'Forbidden - File access denied');
            return;
        }
        
        // 确保文件存在且可读
        if (!file_exists($realPath) || !is_file($realPath)) {
            $this->sendResponse(404, 'File Not Found');
            return;
        }
        
        if (!is_readable($realPath)) {
            $this->sendResponse(403, 'Forbidden - File not readable');
            return;
        }
        
        $this->setFileHeaders($realPath);
        readfile($realPath);
    }
    
    private function setFileHeaders($realPath) {
        $fileSize = filesize($realPath);
        $mimeType = $this->getMimeType($realPath);
        $lastModified = gmdate('D, d M Y H:i:s T', filemtime($realPath));
        $etag = md5_file($realPath);
        $filename = basename($realPath);
        
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Last-Modified: ' . $lastModified);
        header('ETag: "' . $etag . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
    }
    
    private function sendDirectoryListing($realPath, $webPath) {
        // 引入文件列表模块
        require_once __DIR__ . '/file_list.php';
        
        // 使用FileListModule生成文件列表
        $html = FileListModule::generateFileListHTML($realPath, $webPath, '', $this->currentUser);
        
        $this->sendResponse(200, 'OK', $html);
    }
    
    private function generatePropfindResponse($realPath, $webPath, $depth) {
        $xml = '<?xml version="1.0" encoding="utf-8"?>
<d:multistatus xmlns:d="DAV:">
';
        
        $items = [$realPath];
        if ($depth === '1' && is_dir($realPath)) {
            $items = array_merge($items, glob($realPath . '/*'));
        }
        
        // 获取webdav-access的基础URL（不包含index.php）
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        $basePath = dirname($scriptPath);
        $basePath = rtrim($basePath, '/') . '/';
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $webdavBase = rtrim($protocol . '://' . $host . $basePath, '/');
        
        foreach ($items as $item) {
            $itemWebPath = str_replace($this->baseDir, '', $item);
            $itemWebPath = ltrim($itemWebPath, '/');
            $href = $webdavBase . '/' . $itemWebPath;
            
            $isDir = is_dir($item);
            $size = $isDir ? 0 : filesize($item);
            $mtime = filemtime($item);
            
            $xml .= '  <d:response>
    <d:href>' . htmlspecialchars($href) . '</d:href>
    <d:propstat>
      <d:prop>
        <d:displayname>' . htmlspecialchars(basename($item)) . '</d:displayname>
        <d:getcontentlength>' . $size . '</d:getcontentlength>
        <d:getcontenttype>' . ($isDir ? 'httpd/unix-directory' : $this->getMimeType($item)) . '</d:getcontenttype>
        <d:resourcetype>' . ($isDir ? '<d:collection/>' : '') . '</d:resourcetype>
        <d:getlastmodified>' . gmdate('D, d M Y H:i:s T', $mtime) . '</d:getlastmodified>
        <d:creationdate>' . gmdate('Y-m-d\TH:i:s\Z', $mtime) . '</d:creationdate>
        <d:supportedlock>
          <d:lockentry>
            <d:lockscope><d:exclusive/></d:lockscope>
            <d:locktype><d:write/></d:locktype>
          </d:lockentry>
          <d:lockentry>
            <d:lockscope><d:shared/></d:lockscope>
            <d:locktype><d:write/></d:locktype>
          </d:lockentry>
        </d:supportedlock>
      </d:prop>
      <d:status>HTTP/1.1 200 OK</d:status>
    </d:propstat>
  </d:response>
';
        }
        
        $xml .= '</d:multistatus>';
        return $xml;
    }
    
    private function copy($source, $destination) {
        if (is_dir($source)) {
            if (!is_dir($destination)) {
                mkdir($destination, 0755, true);
            }
            
            $files = scandir($source);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                
                $src = $source . '/' . $file;
                $dst = $destination . '/' . $file;
                
                if (is_dir($src)) {
                    $this->copy($src, $dst);
                } else {
                    copy($src, $dst);
                }
            }
            return true;
        } else {
            return copy($source, $destination);
        }
    }
    
    private function removeDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dir);
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    private function getMimeType($path) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);
        return $mimeType ?: 'application/octet-stream';
    }
    
    private function sendResponse($code, $message, $body = '') {
        http_response_code($code);
        if ($body) {
            echo $body;
        }
        exit;
    }
}

// 使用配置
$config = require __DIR__ . '../config.php';
$webdav = new WebDAVServer($config['base_dir'], $config['users']);
$webdav->handle();
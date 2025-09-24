<?php
/**
 * 文件列表显示模块
 * 用于WebDAV文件浏览功能的模块化实现
 */

class FileListModule {
    
    /**
     * 格式化文件大小
     */
    public static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * 获取文件MIME类型
     */
    public static function getMimeType($path) {
        if (!file_exists($path)) {
            return 'application/octet-stream';
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);
        return $mimeType ?: 'application/octet-stream';
    }

    /**
     * 获取文件类型图标
     */
    public static function getFileIcon($filename, $mimetype = null) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // 定义文件类型映射
        $fileTypes = [
            // 图片类型
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico', 'tiff', 'tif'],
            // 文档类型
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods', 'odp'],
            // 文本类型
            'text' => ['txt', 'md', 'csv', 'json', 'xml', 'html', 'htm', 'css', 'js', 'php', 'py', 'java', 'c', 'cpp', 'h', 'sql', 'log'],
            // 压缩文件类型
            'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'tgz', 'tar.gz'],
            // 可执行程序类型
            'executable' => ['exe', 'msi', 'deb', 'rpm', 'dmg', 'pkg', 'apk', 'app']
        ];

        // 根据扩展名判断类型
        foreach ($fileTypes as $type => $extensions) {
            if (in_array($extension, $extensions)) {
                switch ($type) {
                    case 'image':
                        return '🖼️';
                    case 'document':
                        return '📄';
                    case 'text':
                        return '📝';
                    case 'archive':
                        return '📦';
                    case 'executable':
                        return '⚙️';
                    default:
                        return '📄';
                }
            }
        }

        // 根据MIME类型判断
        if ($mimetype) {
            if (strpos($mimetype, 'image/') === 0) {
                return '🖼️';
            } elseif (strpos($mimetype, 'text/') === 0) {
                return '📝';
            } elseif (strpos($mimetype, 'application/pdf') === 0) {
                return '📄';
            } elseif (strpos($mimetype, 'application/zip') === 0 || 
                      strpos($mimetype, 'application/x-rar') === 0 ||
                      strpos($mimetype, 'application/x-7z') === 0) {
                return '📦';
            }
        }

        // 默认图标
        return '📄';
    }

    /**
     * 判断是否为图片文件
     */
    public static function isImageFile($filename, $mimetype = null) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico', 'tiff', 'tif'];
        
        if (in_array($extension, $imageExtensions)) {
            return true;
        }
        
        if ($mimetype && strpos($mimetype, 'image/') === 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 判断是否为文本文件
     */
    public static function isTextFile($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $textExtensions = [
            'txt', 'md', 'markdown', 'text',
            'json', 'xml', 'yaml', 'yml',
            'html', 'htm', 'css', 'js', 'ts', 'php', 'py', 'java', 'c', 'cpp', 'h', 'hpp', 'cs', 'go', 'rs', 'rb', 'sh', 'bat', 'cmd', 'ps1',
            'sql', 'log', 'conf', 'config', 'ini', 'cfg',
            'csv', 'tsv', 'diff', 'patch'
        ];
        
        return in_array($extension, $textExtensions);
    }
    
    /**
     * 生成面包屑导航
     */
    public static function generateBreadcrumb($currentPath, $baseUrl) {
        $html = '';
        
        if ($currentPath !== '') {
            $parts = explode('/', $currentPath);
            $pathBuild = '';
            foreach ($parts as $part) {
                if ($pathBuild === '') {
                    $pathBuild = $part;
                } else {
                    $pathBuild .= '/' . $part;
                }
                $html .= '<span class="separator">›</span>
                    <a href="' . htmlspecialchars($baseUrl . '/' . $pathBuild) . '">' . htmlspecialchars($part) . '</a>';
            }
        }
        
        return $html;
    }
    
    /**
     * 获取目录和文件列表
     */
    public static function getFileList($realPath) {
        if (!is_dir($realPath)) {
            return ['directories' => [], 'files' => []];
        }
        
        $files = scandir($realPath);
        $directories = [];
        $filesList = [];
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $fileRealPath = $realPath . '/' . $file;
            if (is_dir($fileRealPath)) {
                $directories[] = [
                    'name' => $file,
                    'path' => $fileRealPath,
                    'mtime' => filemtime($fileRealPath)
                ];
            } else {
                $filesList[] = [
                    'name' => $file,
                    'path' => $fileRealPath,
                    'size' => filesize($fileRealPath),
                    'mtime' => filemtime($fileRealPath)
                ];
            }
        }
        
        // 排序：目录在前，文件在后，按名称排序
        usort($directories, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        usort($filesList, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        
        return [
            'directories' => $directories,
            'files' => $filesList
        ];
    }
    
    /**
     * 生成文件列表HTML
     */
    public static function generateFileListHTML($realPath, $webPath, $baseUrl = '', $currentUser = null) {
        $fileList = self::getFileList($realPath);
        $directories = $fileList['directories'];
        $files = $fileList['files'];
        
        // 获取当前目录名和路径
        $currentDirName = $webPath ? htmlspecialchars(basename($webPath)) : 'WebDAV文件浏览';
        $currentPathDisplay = $webPath ? '路径: /' . htmlspecialchars($webPath) : '根目录';
        $username = $currentUser ? htmlspecialchars($currentUser['username'] ?? '用户') : '用户';
        
        // 动态检测当前脚本路径，支持子目录安装
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($scriptPath);
        
        // 正确处理根目录和子目录的情况
        if ($basePath === '/' || $basePath === '\\') {
            $webdavBaseUrl = '/' . basename($scriptPath); // 根目录情况
            $baseUrl = ''; // 根目录情况，CSS/JS路径使用相对路径
        } else {
            $webdavBaseUrl = $basePath . '/' . basename($scriptPath); // 子目录情况
            $baseUrl = $basePath; // 子目录情况，CSS/JS路径使用子目录路径
        }
        
        // 构建面包屑导航
        $breadcrumbItems = [];
        if ($webPath !== '') {
            $parts = explode('/', $webPath);
            $pathBuild = '';
            foreach ($parts as $part) {
                if ($pathBuild === '') {
                    $pathBuild = $part;
                } else {
                    $pathBuild .= '/' . $part;
                }
                $breadcrumbItems[] = ['name' => $part, 'path' => $pathBuild];
            }
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
            <meta name="theme-color" content="#0066cc">
            <title><?php echo $currentDirName; ?> - WebDAV文件浏览</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.css">
            <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/file-list.css">
            <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/file-list-modal.css">
        </head>
        <body>
            <header class="header">
                <div class="header-content">
                    <div class="title-section">
                        <h1>文件浏览器</h1>
                        <button type="button" class="upload-btn" onclick="openUploadModal()">上传文件</button>
                    </div>
                    <div class="user-info">
                        <span>👤 <?php echo $username; ?></span>
                        <a href="<?php echo substr($_SERVER["REQUEST_URI"], 0, strlen($_SERVER["SCRIPT_NAME"])); ?>?logout" class="logout-btn" onclick="return confirm('确定要退出吗？')">登出</a>
                    </div>
                </div>
            </header>
            
            <nav class="breadcrumb">
                <div class="breadcrumb-content">
                    <a href="<?php echo htmlspecialchars($webdavBaseUrl); ?>/">🏠 根目录</a>
                    <?php
                    foreach ($breadcrumbItems as $item) {
                        echo '<span class="separator">›</span>';
                        echo '<a href="' . htmlspecialchars($webdavBaseUrl . '/' . $item['path'] . '/') . '">' . htmlspecialchars($item['name']) . '</a>';
                    }
                    ?>
                </div>
            </nav>
            
            <main class="main-container">
                <div class="file-list">
                    <?php if (empty($directories) && empty($files)): ?>
                        <div class="empty-state">
                            <div class="icon">📁</div>
                            <div class="text">此目录为空</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($directories as $dir): ?>
                            <a href="<?php echo htmlspecialchars($webdavBaseUrl . '/' . ($webPath ? $webPath . '/' : '') . $dir['name'] . ''); ?>" class="file-item">
                                <div class="file-icon">📁</div>
                                <div class="file-info">
                                    <div class="file-name"><?php echo htmlspecialchars($dir['name']); ?></div>
                                    <div class="file-meta">
                                        <span class="file-date"><?php echo date('Y-m-d H:i', $dir['mtime']); ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        
                        <?php foreach ($files as $file): ?>
                            <?php 
                            $isImage = self::isImageFile($file['name']); 
                            $fileIcon = self::getFileIcon($file['name']);
                            $downloadUrl = '?download=' . urlencode($webPath ? $webPath . '/' . $file['name'] : $file['name']);
                            ?>
                            <div class="file-item">
                                <div class="file-icon"><?php echo $fileIcon; ?></div>
                                <div class="file-info">
                                    <?php 
                                    $isTextFile = self::isTextFile($file['name']);
                                    $fileNameDisplay = htmlspecialchars($file['name']);
                                    
                                    if ($isImage): ?>
                                        <a href="<?php echo htmlspecialchars($downloadUrl); ?>" 
                                           class="file-name image-link" 
                                           data-fancybox="gallery" 
                                           data-caption="<?php echo $fileNameDisplay; ?>">
                                            <?php echo $fileNameDisplay; ?>
                                        </a>
                                    <?php elseif ($isTextFile): ?>
                                        <a href="javascript:void(0)" 
                                           class="file-name" 
                                           onclick="previewTextFile('<?php echo htmlspecialchars($webPath ? $webPath . '/' . $file['name'] : $file['name']); ?>')"
                                           style="cursor: pointer; color: #0366d6;">
                                            <?php echo $fileNameDisplay; ?>
                                        </a>
                                    <?php else: ?>
                                        <div class="file-name"><?php echo $fileNameDisplay; ?></div>
                                    <?php endif; ?>
                                    <div class="file-meta">
                                        <span class="file-size"><?php echo self::formatBytes($file['size']); ?></span>
                                        <span class="file-date"><?php echo date('Y-m-d H:i', $file['mtime']); ?></span>
                                    </div>
                                </div>
                                <div class="file-actions">
                                    <a href="<?php echo htmlspecialchars($downloadUrl); ?>" 
                                       class="download-btn" 
                                       download="<?php echo htmlspecialchars($file['name']); ?>">下载</a>
                                    <button type="button" 
                                            class="delete-btn" 
                                            onclick="confirmDelete('<?php echo htmlspecialchars($webPath ? $webPath . '/' . $file['name'] : $file['name']); ?>')">删除</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
            
            <!-- 上传文件模态框 -->
            <div id="uploadModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>上传文件</h3>
                        <span class="close" onclick="resetUploadModal()">&times;</span>
                    </div>
                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="file" name="file" id="fileInput" class="file-input">
                        <div class="modal-buttons">
                            <button type="submit" class="btn btn-primary">上传</button>
                            <button type="button" class="btn btn-secondary" onclick="resetUploadModal()">取消</button>
                        </div>
                        <div id="uploadProgress" class="progress-container">
                            <div class="progress-bar">
                                <div id="progressBar" class="progress-fill"></div>
                            </div>
                            <div id="progressText" class="progress-text">0%</div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 删除确认模态框 -->
            <div id="deleteModal" class="modal">
                <div class="modal-content delete-modal">
                    <div class="delete-modal-header">
                        <div class="warning-icon">⚠️</div>
                        <h3 class="delete-title">确认删除</h3>
                    </div>
                    <div class="delete-modal-body">
                        <p class="delete-message">
                            确定要删除文件 <strong id="deleteFileName" class="delete-filename"></strong> 吗？
                        </p>
                        <p class="delete-warning">
                            此操作不可撤销，文件将被永久删除！
                        </p>
                    </div>
                    <div class="delete-modal-footer">
                        <button type="button" class="btn btn-danger" onclick="deleteFile()">确认删除</button>
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">取消</button>
                    </div>
                </div>
            </div>
            
            <!-- 文本预览模态框 -->
            <div id="textPreviewModal" class="modal preview-modal">
                <div class="modal-content preview-content">
                    <div class="preview-header">
                        <h3 id="previewFileName" class="preview-title"></h3>
                        <div class="preview-actions">
                            <button type="button" id="downloadPreviewBtn" class="btn btn-primary">下载</button>
                            <button type="button" class="btn btn-secondary" onclick="closeTextPreview()">关闭</button>
                        </div>
                    </div>
                    <div class="preview-body">
                        <div id="previewContent" class="preview-text"></div>
                    </div>
                </div>
            </div>
            

            
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/9.1.6/marked.min.js"></script>
            <script src="<?php echo $baseUrl; ?>/assets/js/file-list.js"></script>

                

        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
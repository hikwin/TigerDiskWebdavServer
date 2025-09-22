<?php
// 泰格网盘首页 - TigerDisk Modern Landing Page
// 开启会话用于检测登录状态
session_start();

// 检查是否已安装
if (!file_exists(__DIR__ . '/install.lock')) {
    header('Location: install.php');
    exit;
}

// 检测登录状态
$isLoggedIn = isset($_SESSION['user_id']);
$username = $isLoggedIn ? $_SESSION['username'] : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="泰格网盘 - 基于WebDAV的现代化文件管理系统，支持多用户、权限管理、在线预览等功能">
    <meta name="keywords" content="泰格网盘, WebDAV, 文件管理, 在线存储, 多用户系统">
    <meta name="author" content="TigerDisk Team">
    <title>泰格网盘 - TigerDisk | 现代化WebDAV文件管理系统</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='%23007bff'/%3E%3Ctext x='50' y='65' text-anchor='middle' fill='white' font-size='50' font-family='Arial'%3ETD%3C/text%3E%3C/svg%3E">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* CSS Reset & Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-color: #007bff;
            --primary-dark: #0056b3;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --border-color: #dee2e6;
            --text-muted: #6c757d;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            transition: var(--transition);
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            margin-right: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .nav-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
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
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        /* Hero Section */
        .hero {
            padding: 8rem 2rem 4rem;
            text-align: center;
            color: white;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .hero-title {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-subtitle {
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            margin-bottom: 2rem;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 3rem;
        }
        
        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.1rem;
        }
        
        /* Features Section */
        .features {
            background: white;
            padding: 4rem 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 3rem;
            color: var(--dark-color);
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .feature-card {
            background: white;
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: white;
            font-size: 1.5rem;
        }
        
        .feature-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }
        
        .feature-description {
            color: var(--text-muted);
            line-height: 1.6;
        }
        
        /* WebDAV Highlight */
        .webdav-highlight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 2rem;
            border-radius: var(--border-radius);
            margin: 2rem 0;
            text-align: center;
        }
        
        .webdav-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .webdav-description {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        /* Footer */
        .footer {
            background: var(--dark-color);
            color: white;
            padding: 2rem 0;
            text-align: center;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .footer-link {
            color: white;
            text-decoration: none;
            opacity: 0.8;
            transition: var(--transition);
        }
        
        .footer-link:hover {
            opacity: 1;
        }
        
        .copyright {
            opacity: 0.6;
            font-size: 0.9rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-container {
                padding: 0 1rem;
            }
            
            .hero {
                padding: 6rem 1rem 3rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .features {
                padding: 3rem 1rem;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .feature-card {
                padding: 2rem;
            }
            
            .footer-links {
                flex-direction: column;
                gap: 1rem;
            }
        }
        
        /* Animations */
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
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Loading State */
        .loading {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .loaded {
            opacity: 1;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="logo">
                <div class="logo-icon">
                    <svg width="40" height="40" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <!-- 老虎头轮廓 -->
                        <circle cx="50" cy="50" r="45" fill="#ff6b35"/>
                        <!-- 耳朵 -->
                        <ellipse cx="25" cy="25" rx="15" ry="20" fill="#ff6b35"/>
                        <ellipse cx="75" cy="25" rx="15" ry="20" fill="#ff6b35"/>
                        <!-- 耳朵内部 -->
                        <ellipse cx="25" cy="30" rx="8" ry="12" fill="#ff8c42"/>
                        <ellipse cx="75" cy="30" rx="8" ry="12" fill="#ff8c42"/>
                        <!-- 脸部 -->
                        <circle cx="50" cy="55" r="35" fill="#ff8c42"/>
                        <!-- 眼睛 -->
                        <circle cx="35" cy="45" r="6" fill="#2c1810"/>
                        <circle cx="65" cy="45" r="6" fill="#2c1810"/>
                        <!-- 眼睛高光 -->
                        <circle cx="37" cy="43" r="2" fill="#fff"/>
                        <circle cx="67" cy="43" r="2" fill="#fff"/>
                        <!-- 鼻子 -->
                        <ellipse cx="50" cy="60" rx="8" ry="6" fill="#2c1810"/>
                        <!-- 嘴巴 -->
                        <path d="M 35 70 Q 50 80 65 70" stroke="#2c1810" stroke-width="3" fill="none"/>
                        <!-- 胡须 -->
                        <line x1="15" y1="55" x2="35" y2="58" stroke="#2c1810" stroke-width="2"/>
                        <line x1="15" y1="65" x2="35" y2="62" stroke="#2c1810" stroke-width="2"/>
                        <line x1="85" y1="55" x2="65" y2="58" stroke="#2c1810" stroke-width="2"/>
                        <line x1="85" y1="65" x2="65" y2="62" stroke="#2c1810" stroke-width="2"/>
                        <!-- 额头条纹 -->
                        <path d="M 30 25 L 40 35" stroke="#2c1810" stroke-width="3"/>
                        <path d="M 60 25 L 70 35" stroke="#2c1810" stroke-width="3"/>
                        <path d="M 45 20 L 50 30" stroke="#2c1810" stroke-width="3"/>
                        <path d="M 55 20 L 50 30" stroke="#2c1810" stroke-width="3"/>
                    </svg>
                </div>
                泰格网盘
            </a>
            
            <div class="nav-buttons">
                <?php if ($isLoggedIn): ?>
                    <div class="user-info">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <a href="admin/" class="btn btn-primary">
                        <i class="fas fa-cog"></i>
                        后台管理
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        登录
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <h1 class="hero-title fade-in-up">泰格网盘</h1>
        <p class="hero-subtitle fade-in-up">
            基于WebDAV的现代化文件管理系统，为您提供安全、高效、便捷的文件存储与共享解决方案
        </p>
        
        <div class="hero-buttons fade-in-up">
            <a href="https://github.com/hikwin/TigerDiskWebdavServer" class="btn btn-primary btn-large" target="_blank">
                <i class="fab fa-github"></i>
                源码下载
            </a>
            <a href="webdav.php" class="btn btn-primary btn-large">
                        <i class="fas fa-folder-open"></i>
                        WebDAV服务
                    </a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <h2 class="section-title">核心功能特性</h2>
            
            <div class="features-grid">
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="feature-title">多用户管理</h3>
                    <p class="feature-description">
                        支持多用户管理，灵活的权限管理系统，管理员可轻松创建和管理用户账户及存储空间。
                    </p>
                </div>
                
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="feature-title">安全存储</h3>
                    <p class="feature-description">
                        采用先进加密技术保护用户数据，支持HTTPS安全传输，确保文件存储和访问的安全性。
                    </p>
                </div>
                
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h3 class="feature-title">WebDAV支持</h3>
                    <p class="feature-description">
                        完整支持WebDAV协议，可无缝对接Windows、macOS、Linux等系统的文件管理器。
                    </p>
                </div>
                
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3 class="feature-title">跨平台兼容</h3>
                    <p class="feature-description">
                        支持PC、手机、平板等多种设备访问，响应式设计确保在各种屏幕尺寸下都有最佳体验。
                    </p>
                </div>
            </div>
            
            <!-- WebDAV Highlight -->
            <div class="webdav-highlight fade-in-up">
                <h3 class="webdav-title">
                    <i class="fas fa-rocket"></i>
                    专业WebDAV服务支持
                </h3>
                <p class="webdav-description">
                    泰格网盘（TigerDisk）提供完整的WebDAV协议支持，让您可以在任何支持WebDAV的客户端中直接访问和管理文件，
                    包括RaiDrive、Joplin、Obsidian、思源笔记以及各种移动应用，例如一木清单、ES文件管理器等。
                </p>
                <a href="webdav.php" class="btn btn-primary btn-large" style="background: white; color: #667eea;">
                    <i class="fas fa-external-link-alt"></i>
                    立即体验WebDAV
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="https://github.com/hikwin/TigerDiskWebdavServer" class="footer-link" target="_blank">
                    <i class="fab fa-github"></i> GitHub
                </a>
                <a href="webdav.php" class="footer-link">
                    <i class="fas fa-folder"></i> WebDAV服务
                </a>
                <a href="login.php" class="footer-link">
                    <i class="fas fa-sign-in-alt"></i> 用户登录
                </a>
                <a href="logout.php" class="footer-link">
                    <i class="fas fa-sign-out-alt"></i> 退出登录
                </a>
            </div>
            <p class="copyright">
                &copy; 2024 泰格网盘(TigerDisk). 基于WebDAV的现代化文件管理系统. 
                开源项目，遵循MIT许可证.
            </p>
        </div>
    </footer>

    <script>
        // 页面加载动画
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.fade-in-up');
            elements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '0';
                    element.style.transform = 'translateY(30px)';
                    element.style.transition = 'all 0.6s ease-out';
                    
                    setTimeout(() => {
                        element.style.opacity = '1';
                        element.style.transform = 'translateY(0)';
                    }, 100 * index);
                }, 100);
            });
        });

        // 平滑滚动
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // 导航栏滚动效果
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                navbar.style.boxShadow = 'none';
            }
        });
    </script>
</body>
</html>
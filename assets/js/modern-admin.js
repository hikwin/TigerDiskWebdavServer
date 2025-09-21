/**
 * WebDAV Admin Modern JavaScript
 * 现代化后台管理界面交互逻辑
 */

class WebDAVAdmin {
    constructor() {
        this.init();
    }

    init() {
        this.setupTabs();
        this.setupModals();
        this.setupFormValidation();
        this.setupCopyButtons();
        this.setupResponsiveMenu();
        this.setupNotifications();
        this.setupLoadingStates();
    }

    /**
     * 标签页切换
     */
    setupTabs() {
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                const targetTab = e.target.dataset.tab;
                
                // 移除所有活动状态
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // 添加活动状态
                e.target.classList.add('active');
                const targetContent = document.getElementById(targetTab);
                if (targetContent) {
                    targetContent.classList.add('active');
                }

                // 更新URL hash
                window.location.hash = targetTab;
            });
        });

        // 根据URL hash初始化标签页
        const hash = window.location.hash.slice(1);
        if (hash) {
            const targetTab = document.querySelector(`[data-tab="${hash}"]`);
            if (targetTab) {
                targetTab.click();
            }
        }
    }

    /**
     * 模态框管理
     */
    setupModals() {
        const modals = document.querySelectorAll('.modal');
        
        // 关闭模态框
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-close') || 
                e.target.classList.contains('modal')) {
                this.closeModal(e.target.closest('.modal'));
            }
        });

        // ESC键关闭模态框
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.active');
                if (openModal) {
                    this.closeModal(openModal);
                }
            }
        });
    }

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    closeModal(modal) {
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    /**
     * 表单验证
     */
    setupFormValidation() {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });

        // 实时验证
        document.addEventListener('input', (e) => {
            if (e.target.classList.contains('form-input')) {
                this.validateField(e.target);
            }
        });
    }

    validateForm(form) {
        const inputs = form.querySelectorAll('.form-input[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });

        return isValid;
    }

    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';

        // 基础验证
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = '此字段为必填项';
        }

        // 密码验证
        if (field.type === 'password' && field.name === 'password' && value.length < 6) {
            isValid = false;
            errorMessage = '密码长度至少6位';
        }

        // 用户名验证
        if (field.name === 'username' && value && !/^[a-zA-Z0-9_-]+$/.test(value)) {
            isValid = false;
            errorMessage = '用户名只能包含字母、数字、下划线和连字符';
        }

        // 更新UI状态
        if (isValid) {
            field.classList.remove('error');
            this.hideFieldError(field);
        } else {
            field.classList.add('error');
            this.showFieldError(field, errorMessage);
        }

        return isValid;
    }

    showFieldError(field, message) {
        let errorElement = field.parentNode.querySelector('.field-error');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'field-error text-sm text-red-600 mt-1';
            field.parentNode.appendChild(errorElement);
        }
        errorElement.textContent = message;
    }

    hideFieldError(field) {
        const errorElement = field.parentNode.querySelector('.field-error');
        if (errorElement) {
            errorElement.remove();
        }
    }

    /**
     * 复制按钮功能
     */
    setupCopyButtons() {
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('copy-btn')) {
                const targetId = e.target.dataset.target;
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    this.copyToClipboard(targetElement.textContent || targetElement.value);
                    this.showNotification('已复制到剪贴板', 'success');
                }
            }
        });
    }

    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
        } catch (err) {
            // 降级方案
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        }
    }

    /**
     * 响应式菜单
     */
    setupResponsiveMenu() {
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebar = document.querySelector('.sidebar');

        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });

            // 点击外部关闭菜单
            document.addEventListener('click', (e) => {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            });
        }
    }

    /**
     * 通知系统
     */
    setupNotifications() {
        // 自动隐藏通知
        const notifications = document.querySelectorAll('.message');
        notifications.forEach(notification => {
            setTimeout(() => {
                notification.style.transition = 'opacity 0.3s ease';
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        });
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `message ${type}`;
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
            animation: slideIn 0.3s ease-out;
            max-width: 300px;
            word-wrap: break-word;
            background: ${type === 'success' ? '#52c41a' : type === 'error' ? '#ff4d4f' : type === 'warning' ? '#faad14' : '#1890ff'};
        `;
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <span style="margin-right: 12px;">${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="background: none; border: none; color: white; cursor: pointer; font-size: 18px; padding: 0; line-height: 1;">&times;</button>
            </div>
        `;
        
        // 添加动画样式
        if (!document.getElementById('notification-animations')) {
            const style = document.createElement('style');
            style.id = 'notification-animations';
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    /**
     * 加载状态
     */
    setupLoadingStates() {
        document.addEventListener('submit', (e) => {
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            
            if (submitBtn) {
                const originalText = submitBtn.textContent;
                submitBtn.innerHTML = '<span class="loading mr-2"></span>处理中...';
                submitBtn.disabled = true;
                
                // 恢复按钮状态（如果需要）
                setTimeout(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            }
        });
    }

    /**
     * 用户管理功能
     */
    showChangePasswordForm(userId, username) {
        const modal = document.getElementById('changePasswordModal');
        if (modal) {
            modal.querySelector('#changePasswordUserId').value = userId;
            modal.querySelector('#changePasswordUsername').textContent = username;
            this.openModal('changePasswordModal');
        }
    }

    showChangeAccessDirForm(userId, username, currentDir) {
        const modal = document.getElementById('changeAccessDirModal');
        if (modal) {
            modal.querySelector('#changeAccessDirUserId').value = userId;
            modal.querySelector('#changeAccessDirUsername').textContent = username;
            modal.querySelector('#newAccessDir').value = currentDir;
            this.openModal('changeAccessDirModal');
        }
    }

    showDeleteUserForm(userId, username) {
        const modal = document.getElementById('deleteUserModal');
        if (modal) {
            modal.querySelector('#deleteUserId').value = userId;
            modal.querySelector('#deleteUsername').textContent = username;
            this.openModal('deleteUserModal');
        }
    }

    /**
     * 分页功能
     */
    goToPage(page) {
        const url = new URL(window.location);
        url.searchParams.set('page', page);
        window.location.href = url.toString();
    }

    changePerPage(perPage) {
        const url = new URL(window.location);
        url.searchParams.set('per_page', perPage);
        url.searchParams.set('page', 1); // 重置到第一页
        window.location.href = url.toString();
    }

    /**
     * 搜索功能
     */
    searchUsers(query) {
        const rows = document.querySelectorAll('#usersTable tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const isVisible = text.includes(query.toLowerCase());
            row.style.display = isVisible ? '' : 'none';
        });
    }

    /**
     * 主题切换
     */
    toggleTheme() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        // 更新主题切换按钮图标
        const themeIcon = document.querySelector('.theme-toggle svg');
        if (themeIcon) {
            themeIcon.innerHTML = newTheme === 'light' 
                ? '<path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.591-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.591z"/>'
                : '<path d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z"/>';
        }
    }

    /**
     * 工具方法
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('zh-CN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// 初始化应用
document.addEventListener('DOMContentLoaded', () => {
    window.webDAVAdmin = new WebDAVAdmin();
});

// 全局函数（用于向后兼容）
function showChangePasswordForm(userId, username) {
    webDAVAdmin.showChangePasswordForm(userId, username);
}

function showChangeAccessDirForm(userId, username, currentDir) {
    webDAVAdmin.showChangeAccessDirForm(userId, username, currentDir);
}

function showDeleteUserForm(userId, username) {
    webDAVAdmin.showDeleteUserForm(userId, username);
}

function goToPage(page) {
    webDAVAdmin.goToPage(page);
}

function changePerPage(perPage) {
    webDAVAdmin.changePerPage(perPage);
}
/**
 * File List Module JavaScript
 * 文件列表模块JavaScript功能
 */

// 移动端优化
if ('ontouchstart' in window) {
    document.body.classList.add('touch-device');
}

// 防止iOS缩放
document.addEventListener('gesturestart', function(e) {
    e.preventDefault();
});

// 优化滚动性能
document.querySelectorAll('.file-item').forEach(function(item) {
    item.addEventListener('touchstart', function() {
        this.style.transform = 'scale(0.98)';
    });
    item.addEventListener('touchend', function() {
        this.style.transform = 'scale(1)';
    });
});

// 初始化Fancybox图片预览
$('[data-fancybox="gallery"]').fancybox({
    buttons: [
        'zoom',
        'slideShow',
        'fullScreen',
        'download',
        'close'
    ],
    loop: false,
    protect: true,
    animationEffect: 'zoom-in-out',
    transitionEffect: 'slide',
    toolbar: true,
    smallBtn: 'auto',
    iframe: {
        preload: false
    }
});

// Toast提示函数
function showToast(message, type = 'success', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    // 触发显示动画
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    // 自动隐藏
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, duration);
}

// 删除文件相关变量和函数
let currentDeleteFile = null;

function confirmDelete(filename) {
    currentDeleteFile = filename;
    // 只显示文件名，不包含路径
    const displayName = filename.split('/').pop();
    document.getElementById('deleteFileName').textContent = displayName;
    document.getElementById('deleteModal').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    currentDeleteFile = null;
}

function deleteFile() {
    if (!currentDeleteFile) return;

    const formData = new FormData();
    formData.append('delete', currentDeleteFile);

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('文件删除成功！', 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast('删除失败：' + (data.message || '未知错误'), 'error');
        }
    })
    .catch(error => {
        showToast('删除失败：' + error.message, 'error');
    })
    .finally(() => {
        closeDeleteModal();
    });
}

// 点击模态框外部关闭
window.onclick = function(event) {
    if (event.target === document.getElementById('deleteModal')) {
        closeDeleteModal();
    }
    if (event.target === document.getElementById('uploadModal')) {
        document.getElementById('uploadModal').style.display = 'none';
    }
};

// 文本预览功能
function previewTextFile(filename) {
    // 检查文件大小
    fetch('?check_size=' + encodeURIComponent(filename))
        .then(response => response.json())
        .then(data => {
            if (data.size > 200 * 1024) {
                showToast('文件过大，无法预览', 'warning');
                return;
            }
                
            // 加载文件内容
            fetch('?preview=' + encodeURIComponent(filename))
                .then(response => response.text())
                .then(content => {
                    const fileName = filename.split('/').pop();
                    const fileExtension = fileName.split('.').pop().toLowerCase();
                    
                    document.getElementById('previewFileName').textContent = fileName;
                    document.getElementById('downloadPreviewBtn').onclick = function() {
                        window.location.href = 'download.php?file=' + encodeURIComponent(filename);
                    };
                    
                    const contentDiv = document.getElementById('previewContent');
                    
                    if (fileExtension === 'md') {
                        // 使用marked.js渲染Markdown
                        contentDiv.innerHTML = marked.parse(content);
                    } else {
                        // 普通文本，转义HTML
                        contentDiv.innerHTML = '<pre style="white-space: pre-wrap; font-family: monospace; background: #f8f9fa; padding: 15px; border-radius: 4px;">' + 
                            escapeHtml(content) + '</pre>';
                    }
                    
                    document.getElementById('textPreviewModal').style.display = 'block';
                })
                .catch(error => {
                    showToast('预览失败：' + error.message, 'error');
                });
        })
        .catch(error => {
            showToast('检查文件大小失败：' + error.message, 'error');
        });
}

function closeTextPreview() {
    document.getElementById('textPreviewModal').style.display = 'none';
}

// HTML转义函数
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// 点击模态框外部关闭文本预览
window.onclick = function(event) {
    if (event.target === document.getElementById('deleteModal')) {
        closeDeleteModal();
    }
    if (event.target === document.getElementById('uploadModal')) {
        document.getElementById('uploadModal').style.display = 'none';
    }
    if (event.target === document.getElementById('textPreviewModal')) {
        closeTextPreview();
    }
};

// 文件上传功能
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('fileInput');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('请选择要上传的文件');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('upload', '1');
    
    const progressDiv = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    
    progressDiv.style.display = 'block';
    
    const xhr = new XMLHttpRequest();
    
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percentComplete = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percentComplete + '%';
            progressText.textContent = percentComplete + '%';
        }
    });
    
    xhr.addEventListener('load', function() {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
                alert('文件上传成功！');
                location.reload(); // 刷新页面显示新文件
            } else {
                alert('上传失败：' + response.message);
            }
        } else {
            alert('上传失败，请重试');
        }
        document.getElementById('uploadModal').style.display = 'none';
    });
    
    xhr.addEventListener('error', function() {
        alert('上传过程中发生错误');
        document.getElementById('uploadModal').style.display = 'none';
    });
    
    xhr.open('POST', '');
    xhr.send(formData);
});

// 点击模态框外部关闭
window.addEventListener('click', function(event) {
    const modal = document.getElementById('uploadModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});
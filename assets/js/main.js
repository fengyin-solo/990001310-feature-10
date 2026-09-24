/**
 * 社区便民留言板 - 前端脚本
 */
document.addEventListener('DOMContentLoaded', function() {
    // 滚动信息复制实现无缝滚动
    const scrollContent = document.getElementById('scrollContent');
    if (scrollContent) {
        scrollContent.innerHTML += scrollContent.innerHTML;
    }
});

/**
 * 切换收藏状态
 */
function toggleFavorite(event, btn) {
    event.preventDefault();
    event.stopPropagation();

    const messageId = btn.dataset.messageId;
    if (!messageId) return;

    const icon = btn.querySelector('.favorite-icon');
    const text = btn.querySelector('.favorite-text');
    const originalIcon = icon.textContent;
    const originalText = text.textContent;
    const originalClass = btn.className;

    btn.disabled = true;

    const formData = new FormData();
    formData.append('message_id', messageId);
    formData.append('action', 'toggle');

    fetch('api/favorite.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.code === 0) {
            if (result.data.favorited) {
                btn.classList.add('favorited');
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-warning');
                icon.textContent = '⭐';
                text.textContent = '已收藏';
                showToast(result.msg, 'success');
            } else {
                btn.classList.remove('favorited');
                btn.classList.remove('btn-warning');
                btn.classList.add('btn-secondary');
                icon.textContent = '☆';
                text.textContent = '收藏';
                showToast(result.msg, 'info');

                if (window.location.pathname.includes('favorites.php')) {
                    const card = btn.closest('.message-card');
                    if (card) {
                        card.style.transition = 'all 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(-100px)';
                        setTimeout(() => {
                            card.remove();
                            updateFavoritesStats();
                            checkEmptyState();
                        }, 300);
                    }
                }
            }
        } else {
            showToast(result.msg || '操作失败', 'error');
            icon.textContent = originalIcon;
            text.textContent = originalText;
            btn.className = originalClass;
        }
    })
    .catch(error => {
        console.error('收藏操作失败:', error);
        showToast('网络错误，请稍后重试', 'error');
        icon.textContent = originalIcon;
        text.textContent = originalText;
        btn.className = originalClass;
    })
    .finally(() => {
        btn.disabled = false;
    });
}

/**
 * 更新收藏页面统计数据
 */
function updateFavoritesStats() {
    const statNumbers = document.querySelectorAll('.favorites-stats .stat-number');
    statNumbers.forEach(el => {
        const current = parseInt(el.textContent) || 0;
        if (current > 0) {
            el.textContent = current - 1;
        }
    });

    const subtitle = document.querySelector('.page-subtitle');
    if (subtitle) {
        const match = subtitle.textContent.match(/\d+/);
        if (match) {
            const current = parseInt(match[0]) || 0;
            subtitle.textContent = `共收藏 ${Math.max(0, current - 1)} 条留言`;
        }
    }
}

/**
 * 检查收藏页面是否为空
 */
function checkEmptyState() {
    const list = document.querySelector('.message-list');
    if (!list) return;

    const cards = list.querySelectorAll('.message-card');
    if (cards.length === 0) {
        const container = document.querySelector('.message-list-section .container');
        if (container) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">⭐</div>
                    <p>暂无收藏的留言</p>
                    <a href="index.php" class="btn btn-primary">去浏览留言</a>
                </div>
            `;
        }
    }
}

/**
 * 显示提示消息
 */
function showToast(message, type = 'info') {
    const existingToast = document.querySelector('.toast-message');
    if (existingToast) {
        existingToast.remove();
    }

    const toast = document.createElement('div');
    toast.className = `toast-message toast-${type}`;
    toast.textContent = message;

    const styles = {
        position: 'fixed',
        top: '80px',
        left: '50%',
        transform: 'translateX(-50%)',
        padding: '12px 24px',
        borderRadius: '8px',
        color: '#fff',
        fontSize: '0.9rem',
        zIndex: '9999',
        boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
        animation: 'slideDown 0.3s ease',
        maxWidth: '90%',
        textAlign: 'center'
    };

    const typeColors = {
        success: 'background: #10b981',
        error: 'background: #ef4444',
        info: 'background: #3b82f6',
        warning: 'background: #f59e0b'
    };

    Object.assign(toast.style, styles);
    toast.style.cssText += ';' + (typeColors[type] || typeColors.info);

    const styleSheet = document.createElement('style');
    styleSheet.textContent = `
        @keyframes slideDown {
            from { opacity: 0; transform: translate(-50%, -20px); }
            to { opacity: 1; transform: translate(-50%, 0); }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    `;
    document.head.appendChild(styleSheet);

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s ease forwards';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 2000);
}

/**
 * 打开举报弹窗
 */
function openReportModal(messageId) {
    const modal = document.getElementById('reportModal');
    if (!modal) return;

    document.getElementById('reportMessageId').value = messageId;
    document.getElementById('reportForm').reset();
    document.getElementById('reportDescCount').textContent = '0';
    modal.style.display = 'flex';
}

/**
 * 关闭举报弹窗
 */
function closeReportModal() {
    const modal = document.getElementById('reportModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * 初始化举报表单
 */
function initReportForm() {
    const form = document.getElementById('reportForm');
    if (!form) return;

    const descInput = document.getElementById('reportDescription');
    const descCount = document.getElementById('reportDescCount');

    if (descInput && descCount) {
        descInput.addEventListener('input', function() {
            descCount.textContent = this.value.length;
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        submitReport();
    });

    document.getElementById('reportModal').addEventListener('click', function(e) {
        if (e.target === this) closeReportModal();
    });
}

/**
 * 提交举报
 */
function submitReport() {
    const form = document.getElementById('reportForm');
    if (!form) return;

    const submitBtn = document.getElementById('reportSubmitBtn');
    const messageId = document.getElementById('reportMessageId').value;
    const reportType = form.querySelector('input[name="report_type"]:checked');
    const description = document.getElementById('reportDescription').value;

    if (!reportType) {
        showToast('请选择举报类型', 'warning');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = '提交中...';

    const formData = new FormData();
    formData.append('message_id', messageId);
    formData.append('report_type', reportType.value);
    formData.append('description', description);
    formData.append('action', 'submit');

    fetch('api/report.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.code === 0) {
            showToast(result.msg, 'success');
            closeReportModal();
            // 服务端状态已变化（待处理面板出现、按钮置灰），整页同步
            setTimeout(() => location.reload(), 600);
        } else {
            // 并发提交或失败重试导致服务端已有记录时，以服务端为准，刷新同步
            if (result.msg && result.msg.indexOf('已经举报') !== -1) {
                showToast(result.msg, 'warning');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(result.msg || '举报失败', 'error');
            }
        }
    })
    .catch(error => {
        console.error('举报提交失败:', error);
        // 网络失败时不重复自动提交，交由用户手动重试；
        // 若首次其实已落地，服务端唯一约束会阻止重复记录
        showToast('网络错误，请稍后重试', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = '提交举报';
    });
}

/* ========== 举报证据补充与撤回 ========== */

/**
 * 打开补充证据弹窗
 */
function openEvidenceModal(reportId) {
    const modal = document.getElementById('evidenceModal');
    if (!modal) return;
    document.getElementById('evidenceReportId').value = reportId;
    document.getElementById('evidenceFiles').value = '';
    modal.style.display = 'flex';
}

/**
 * 关闭补充证据弹窗
 */
function closeEvidenceModal() {
    const modal = document.getElementById('evidenceModal');
    if (modal) modal.style.display = 'none';
}

/**
 * 初始化补充证据表单
 */
function initEvidenceForm() {
    const form = document.getElementById('evidenceForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        submitEvidence();
    });

    const modal = document.getElementById('evidenceModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeEvidenceModal();
        });
    }
}

/**
 * 上传补充证据
 */
function submitEvidence() {
    const panel = document.getElementById('myReportPanel');
    const reportId = document.getElementById('evidenceReportId').value;
    const fileInput = document.getElementById('evidenceFiles');
    const submitBtn = document.getElementById('evidenceSubmitBtn');

    if (!fileInput.files || fileInput.files.length === 0) {
        showToast('请先选择要补充的证据图片', 'warning');
        return;
    }

    const maxTotal = 6;
    const existCount = panel ? parseInt(panel.dataset.evidenceCount || '0', 10) : 0;
    const maxSize = 5 * 1024 * 1024;
    const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    for (let i = 0; i < fileInput.files.length; i++) {
        const f = fileInput.files[i];
        if (allowed.indexOf(f.type) === -1) {
            showToast('仅支持 JPG、PNG、GIF、WebP 格式的图片', 'error');
            return;
        }
        if (f.size > maxSize) {
            showToast('单张图片不能超过 5MB：' + f.name, 'error');
            return;
        }
    }

    if (existCount + fileInput.files.length > maxTotal) {
        showToast('每条举报最多 ' + maxTotal + ' 张证据（当前已有 ' + existCount + ' 张）', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_evidence');
    // 证据接口需要 message_id 作为入口校验（与举报接口保持一致）
    const reportBtn = document.querySelector('.report-btn');
    if (reportBtn && reportBtn.dataset.messageId) {
        formData.append('message_id', reportBtn.dataset.messageId);
    }
    formData.append('report_id', reportId);
    for (let i = 0; i < fileInput.files.length; i++) {
        formData.append('evidence[]', fileInput.files[i]);
    }

    submitBtn.disabled = true;
    submitBtn.textContent = '上传中...';

    fetch('api/report.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.code === 0) {
            showToast(result.msg, 'success');
            closeEvidenceModal();
            // 刷新页面以呈现最新证据列表与数量（服务端渲染，保证状态一致）
            setTimeout(() => location.reload(), 600);
        } else {
            // 越权 / 已被管理员处理 / 数量超限等，均以服务端状态为准
            showToast(result.msg || '证据补充失败', 'error');
        }
    })
    .catch(error => {
        console.error('证据补充失败:', error);
        showToast('网络错误，请稍后重试', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = '上传证据';
    });
}

/**
 * 撤回本人举报（仅待处理状态）
 */
function withdrawMyReport(reportId) {
    if (!confirm('确定撤回这条举报吗？撤回后记录将被清除，您可以重新发起举报。')) return;

    const reportBtn = document.querySelector('.report-btn');
    const messageId = reportBtn ? reportBtn.dataset.messageId : '';

    const formData = new FormData();
    formData.append('action', 'withdraw');
    formData.append('report_id', reportId);
    if (messageId) formData.append('message_id', messageId);

    // 防重复点击：并发撤回以先落地的一次为准
    const actionBtns = document.querySelectorAll('.my-report-actions .btn');
    actionBtns.forEach(btn => { btn.disabled = true; });

    fetch('api/report.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.code === 0) {
            showToast(result.msg, 'success');
            // 举报记录已物理删除：按钮恢复为可举报、面板消失、后台待处理数同步减少
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(result.msg || '撤回失败', 'error');
            actionBtns.forEach(btn => { btn.disabled = false; });
        }
    })
    .catch(error => {
        console.error('撤回举报失败:', error);
        showToast('网络错误，请稍后重试', 'error');
        actionBtns.forEach(btn => { btn.disabled = false; });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initReportForm();
    initEvidenceForm();
});

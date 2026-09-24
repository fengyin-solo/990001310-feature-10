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
 * 举报入口：未举报打开发起弹窗，已举报打开"我的举报"弹窗
 */
function openReportEntry(messageId) {
    const btn = document.querySelector('.report-btn[data-message-id="' + messageId + '"]');
    if (btn) btn.disabled = true;

    fetch('api/report.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=my_report&message_id=' + encodeURIComponent(messageId)
    })
    .then(response => response.json())
    .then(result => {
        if (result.code === 0 && result.data.report) {
            openMyReportModal(messageId, result.data.report);
        } else {
            openReportModal(messageId);
        }
    })
    .catch(error => {
        console.error('举报状态查询失败:', error);
        showToast('网络错误，请稍后重试', 'error');
    })
    .finally(() => {
        if (btn) btn.disabled = false;
    });
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
    const evidenceInput = document.getElementById('reportEvidence');
    if (evidenceInput) evidenceInput.value = '';
    renderEvidencePreview('reportEvidencePreview', []);
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

    const evidenceInput = document.getElementById('reportEvidence');
    if (evidenceInput) {
        evidenceInput.addEventListener('change', function() {
            validateEvidenceFiles(this, 0);
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        submitReport();
    });

    document.getElementById('reportModal').addEventListener('click', function(e) {
        if (e.target === this) closeReportModal();
    });

    const myModal = document.getElementById('myReportModal');
    if (myModal) {
        myModal.addEventListener('click', function(e) {
            if (e.target === this) closeMyReportModal();
        });
    }
}

/**
 * 校验待上传证据图片数量与大小
 * @param {HTMLInputElement} input 文件输入框
 * @param {number} existingCount 已有证据数量
 * @returns {boolean}
 */
function validateEvidenceFiles(input, existingCount) {
    const limit = 6;
    const files = Array.from(input.files || []);
    if (existingCount + files.length > limit) {
        showToast('证据图片最多' + limit + '张', 'warning');
        input.value = '';
        renderEvidencePreview(input.id + 'Preview', input.files);
        return false;
    }
    const oversized = files.find(f => f.size > 5 * 1024 * 1024);
    if (oversized) {
        showToast('单张图片不能超过5MB', 'warning');
        input.value = '';
        renderEvidencePreview(input.id + 'Preview', input.files);
        return false;
    }
    renderEvidencePreview(input.id + 'Preview', input.files);
    return true;
}

/**
 * 渲染本地待上传图片预览
 */
function renderEvidencePreview(containerId, files) {
    const box = document.getElementById(containerId);
    if (!box) return;
    box.innerHTML = '';
    Array.from(files || []).forEach(file => {
        const item = document.createElement('div');
        item.className = 'evidence-thumb';
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.alt = file.name || '证据图片';
        item.appendChild(img);
        box.appendChild(item);
    });
}

/**
 * 提交举报（失败重试不会重复生成记录，由服务端幂等保证）
 */
function submitReport() {
    const form = document.getElementById('reportForm');
    if (!form) return;

    const submitBtn = document.getElementById('reportSubmitBtn');
    const messageId = document.getElementById('reportMessageId').value;
    const reportType = form.querySelector('input[name="report_type"]:checked');
    const description = document.getElementById('reportDescription').value;
    const evidenceInput = document.getElementById('reportEvidence');

    if (!reportType) {
        showToast('请选择举报类型', 'warning');
        return;
    }

    if (evidenceInput && evidenceInput.files && evidenceInput.files.length > 6) {
        showToast('证据图片最多6张', 'warning');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = '提交中...';

    const formData = new FormData(form);
    formData.append('action', 'submit');
    // FormData 已包含 message_id / report_type / description / evidence[]

    fetch('api/report.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.code === 0) {
            showToast(result.msg, 'success');
            closeReportModal();
            if (result.data && result.data.report) {
                updateReportButton(messageId, result.data.report);
                openMyReportModal(messageId, result.data.report);
            }
        } else {
            showToast(result.msg || '举报失败', 'error');
        }
    })
    .catch(error => {
        console.error('举报提交失败:', error);
        showToast('网络错误，请稍后重试', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = '提交举报';
    });
}

/**
 * 当前打开的"我的举报"数据
 */
let currentMyReport = null;

/**
 * 打开"我的举报"弹窗
 */
function openMyReportModal(messageId, report) {
    const modal = document.getElementById('myReportModal');
    if (!modal) return;
    currentMyReport = report;
    document.getElementById('myReportBody').innerHTML = renderMyReportHtml(report);
    modal.style.display = 'flex';

    const supplementInput = document.getElementById('supplementEvidence');
    if (supplementInput) {
        supplementInput.addEventListener('change', function() {
            validateEvidenceFiles(this, report.evidence ? report.evidence.length : 0);
        });
    }
}

/**
 * 关闭"我的举报"弹窗
 */
function closeMyReportModal() {
    const modal = document.getElementById('myReportModal');
    if (modal) modal.style.display = 'none';
    currentMyReport = null;
}

/**
 * 渲染"我的举报"内容
 */
function renderMyReportHtml(report) {
    const pending = report.status === 0;
    let html = '<div class="detail-view my-report-view">';
    html += '<p><strong>举报类型：</strong><span class="badge badge-' + report.report_type + '">' + report.report_type_label + '</span></p>';
    html += '<p><strong>举报时间：</strong>' + escapeHtml(report.created_at) + '</p>';
    html += '<p><strong>当前状态：</strong><span class="status-badge report-status-' + report.status_class + '">' + report.status_label + '</span></p>';

    if (report.description) {
        // 服务端入库时已做 HTML 转义，这里仅转换换行
        html += '<p><strong>补充说明：</strong></p><div class="detail-text">' + String(report.description).replace(/\n/g, '<br>') + '</div>';
    }

    const evidence = report.evidence || [];
    html += '<div class="evidence-section">';
    html += '<p><strong>证据图片（' + evidence.length + '/' + report.evidence_limit + '）</strong></p>';
    if (evidence.length) {
        html += '<div class="evidence-gallery">';
        evidence.forEach(function(ev) {
            html += '<div class="evidence-thumb"><img src="' + escapeHtml(ev.image) + '" alt="证据图片" onclick="window.open(this.src)"></div>';
        });
        html += '</div>';
    } else {
        html += '<p class="text-muted">暂未上传证据图片</p>';
    }
    html += '</div>';

    if (pending) {
        html += '<div class="supplement-form">';
        html += '<div class="form-group">';
        html += '<label for="supplementEvidence">补充证据图片 <span class="text-muted">(还可上传' + (report.evidence_limit - evidence.length) + '张，单张5MB以内)</span></label>';
        html += '<input type="file" id="supplementEvidence" name="evidence[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>';
        html += '<div class="evidence-preview" id="supplementEvidencePreview"></div>';
        html += '</div>';
        html += '<div class="form-tip"><p>⏳ 管理员处理前可以继续补充证据；举报一旦开始处理或处理完成，将无法撤回或补充。</p></div>';
        html += '<div class="form-actions">';
        html += '<button type="button" class="btn btn-secondary" onclick="closeMyReportModal()">关闭</button>';
        html += '<button type="button" class="btn btn-warning" id="supplementBtn" onclick="supplementEvidence(' + report.id + ', ' + report.message_id + ')">补充证据</button>';
        html += '<button type="button" class="btn btn-danger" id="withdrawBtn" onclick="withdrawReport(' + report.id + ', ' + report.message_id + ')">撤回举报</button>';
        html += '</div>';
        html += '</div>';
    } else {
        html += '<div class="form-tip"><p>ℹ️ 举报已处理，无法补充证据或撤回。</p></div>';
        if (report.process_note) {
            html += '<p><strong>处理备注：</strong></p><div class="detail-text">' + String(report.process_note).replace(/\n/g, '<br>') + '</div>';
        }
        html += '<div class="form-actions"><button type="button" class="btn btn-secondary" onclick="closeMyReportModal()">关闭</button></div>';
    }

    html += '</div>';
    return html;
}

/**
 * HTML 转义
 */
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * 补充举报证据（仅举报人、待处理状态可用）
 */
function supplementEvidence(reportId, messageId) {
    const input = document.getElementById('supplementEvidence');
    if (!input || !input.files || input.files.length === 0) {
        showToast('请先选择要补充的证据图片', 'warning');
        return;
    }
    const existingCount = currentMyReport && currentMyReport.evidence ? currentMyReport.evidence.length : 0;
    if (!validateEvidenceFiles(input, existingCount)) {
        return;
    }

    const btn = document.getElementById('supplementBtn');
    btn.disabled = true;
    btn.textContent = '提交中...';

    const formData = new FormData();
    formData.append('action', 'supplement');
    formData.append('report_id', reportId);
    formData.append('message_id', messageId);
    Array.from(input.files).forEach(function(file) {
        formData.append('evidence[]', file);
    });

    fetch('api/report.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.code === 0) {
            showToast(result.msg, 'success');
            if (result.data && result.data.report) {
                updateReportButton(messageId, result.data.report);
                openMyReportModal(messageId, result.data.report);
            }
        } else {
            showToast(result.msg || '补充失败', 'error');
            btn.disabled = false;
            btn.textContent = '补充证据';
        }
    })
    .catch(error => {
        console.error('补充证据失败:', error);
        showToast('网络错误，请稍后重试', 'error');
        btn.disabled = false;
        btn.textContent = '补充证据';
    });
}

/**
 * 撤回举报（仅举报人、待处理状态可用；撤回后可重新举报）
 */
function withdrawReport(reportId, messageId) {
    if (!confirm('确定撤回这条举报吗？撤回后可重新发起举报。')) return;

    const btn = document.getElementById('withdrawBtn');
    btn.disabled = true;
    btn.textContent = '撤回中...';

    const formData = new FormData();
    formData.append('action', 'withdraw');
    formData.append('report_id', reportId);
    formData.append('message_id', messageId);

    fetch('api/report.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.code === 0) {
            showToast(result.msg, 'success');
            closeMyReportModal();
            resetReportButton(messageId);
        } else {
            showToast(result.msg || '撤回失败', 'error');
            btn.disabled = false;
            btn.textContent = '撤回举报';
        }
    })
    .catch(error => {
        console.error('撤回举报失败:', error);
        showToast('网络错误，请稍后重试', 'error');
        btn.disabled = false;
        btn.textContent = '撤回举报';
    });
}

/**
 * 按举报状态同步举报按钮
 */
function updateReportButton(messageId, report) {
    const btn = document.querySelector('.report-btn[data-message-id="' + messageId + '"]');
    if (!btn || !report) return;

    const textMap = {0: '已举报', 1: '已处理', 2: '已处理', 3: '已驳回'};
    btn.classList.remove('btn-danger');
    btn.classList.add('btn-secondary');
    btn.disabled = false;
    const reportText = btn.querySelector('.report-text');
    if (reportText) {
        reportText.textContent = textMap[report.status] || '已举报';
    }
}

/**
 * 撤回后恢复举报按钮为可举报状态
 */
function resetReportButton(messageId) {
    const btn = document.querySelector('.report-btn[data-message-id="' + messageId + '"]');
    if (!btn) return;

    btn.classList.remove('btn-secondary');
    btn.classList.add('btn-danger');
    btn.disabled = false;
    const reportText = btn.querySelector('.report-text');
    if (reportText) {
        reportText.textContent = '举报';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initReportForm();
});

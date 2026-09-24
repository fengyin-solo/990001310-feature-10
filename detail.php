<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$db = getDB();

// 增加浏览量
$db->prepare("UPDATE messages SET views = views + 1 WHERE id = ?")->execute([$id]);

// 获取详情
$stmt = $db->prepare("SELECT * FROM messages WHERE id = ? AND status = 1");
$stmt->execute([$id]);
$msg = $stmt->fetch();

if (!$msg) {
    header('Location: index.php');
    exit;
}

// 当前访客对该留言的举报（撤回后记录被物理删除，此处为 null）
$myReport = getMyReport($msg['id']);
$myEvidences = $myReport ? getReportEvidences($myReport['id']) : [];

$pageTitle = cleanInput($msg['title']) . ' - 社区便民留言板';
$currentPage = '';
$cssPath = 'assets/css/style.css';
$jsPath = 'assets/js/main.js';

include __DIR__ . '/includes/header.php';
?>

<section class="detail-section">
    <div class="container">
        <div class="detail-card">
            <div class="detail-header">
                <span class="card-type type-<?= $msg['type'] ?>"><?= getTypeIcon($msg['type']) ?> <?= getTypeLabel($msg['type']) ?></span>
                <div class="detail-meta">
                    <span>👤 <?= cleanInput($msg['nickname']) ?></span>
                    <span>🕐 <?= $msg['created_at'] ?></span>
                    <span>👁 <?= $msg['views'] ?> 次浏览</span>
                </div>
            </div>

            <h1 class="detail-title"><?= cleanInput($msg['title']) ?></h1>

            <div class="detail-content">
                <?= nl2br(cleanInput($msg['content'])) ?>
            </div>

            <?php if ($msg['image']): ?>
            <div class="detail-image">
                <img src="<?= cleanInput($msg['image']) ?>" alt="留言图片" onclick="window.open(this.src)">
            </div>
            <?php endif; ?>

            <?php if ($msg['phone']): ?>
            <div class="detail-contact">
                <span>📞 联系方式：<?= cleanInput($msg['phone']) ?></span>
            </div>
            <?php endif; ?>

            <div class="detail-actions">
                <a href="index.php" class="btn btn-secondary">← 返回列表</a>
                <?php $isFav = isFavorited($msg['id']); ?>
                <button class="btn favorite-detail-btn <?= $isFav ? 'btn-warning' : 'btn-secondary' ?>" data-message-id="<?= $msg['id'] ?>" onclick="toggleFavorite(event, this)">
                    <span class="favorite-icon"><?= $isFav ? '⭐' : '☆' ?></span>
                    <span class="favorite-text"><?= $isFav ? '已收藏' : '收藏' ?></span>
                </button>
                <?php if (!$myReport): ?>
                <button class="btn btn-danger report-btn" data-message-id="<?= $msg['id'] ?>" onclick="openReportModal(<?= $msg['id'] ?>)">
                    <span>🚩</span>
                    <span class="report-text">举报</span>
                </button>
                <?php elseif ((int) $myReport['status'] === 0): ?>
                <button class="btn btn-secondary report-btn" data-message-id="<?= $msg['id'] ?>" disabled>
                    <span>🚩</span>
                    <span class="report-text">已举报·待处理</span>
                </button>
                <?php else: ?>
                <button class="btn btn-secondary report-btn" data-message-id="<?= $msg['id'] ?>" disabled>
                    <span>🚩</span>
                    <span class="report-text">已举报·<?= getReportStatusLabel($myReport['status']) ?></span>
                </button>
                <?php endif; ?>
                <a href="submit.php" class="btn btn-primary">发布留言</a>
            </div>

            <?php if ($myReport): ?>
            <!-- 我的举报状态：仅举报人本人可见 -->
            <div class="my-report-panel report-my-<?= getReportStatusClass($myReport['status']) ?>"
                 id="myReportPanel"
                 data-report-id="<?= (int) $myReport['id'] ?>"
                 data-status="<?= (int) $myReport['status'] ?>"
                 data-evidence-count="<?= count($myEvidences) ?>">
                <div class="my-report-head">
                    <h4>🚩 我的举报</h4>
                    <span class="status-badge report-status-<?= getReportStatusClass($myReport['status']) ?>">
                        <?= getReportStatusLabel($myReport['status']) ?>
                    </span>
                </div>
                <div class="my-report-meta">
                    <span>类型：<strong><?= getReportTypeLabel($myReport['report_type']) ?></strong></span>
                    <span>提交时间：<?= cleanInput($myReport['created_at']) ?></span>
                </div>
                <?php if ($myReport['description'] !== null && $myReport['description'] !== ''): ?>
                <div class="my-report-desc"><?= nl2br($myReport['description']) ?></div>
                <?php endif; ?>

                <div class="my-evidence-block">
                    <div class="my-evidence-title">
                        证据图片（<span class="evidence-count-num"><?= count($myEvidences) ?></span>/<?= REPORT_EVIDENCE_MAX ?>）
                    </div>
                    <div class="evidence-grid" id="myEvidenceGrid">
                        <?php foreach ($myEvidences as $ev): ?>
                        <a href="<?= cleanInput($ev['image']) ?>" target="_blank" class="evidence-item">
                            <img src="<?= cleanInput($ev['image']) ?>" alt="举报证据">
                        </a>
                        <?php endforeach; ?>
                        <?php if (empty($myEvidences)): ?>
                        <p class="evidence-empty text-muted">暂未补充证据图片</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ((int) $myReport['status'] === 0): ?>
                <div class="my-report-actions">
                    <button type="button" class="btn btn-info btn-sm" onclick="openEvidenceModal(<?= (int) $myReport['id'] ?>)">📷 补充证据</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="withdrawMyReport(<?= (int) $myReport['id'] ?>)">撤回举报</button>
                    <span class="text-muted">管理员处理前可补充证据或撤回，撤回后可重新举报</span>
                </div>
                <?php else: ?>
                <div class="my-report-actions">
                    <span class="text-muted">举报已处理，无法补充证据或撤回</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- 举报弹窗 -->
<div class="modal" id="reportModal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🚩 举报留言</h3>
            <button class="modal-close" onclick="closeReportModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="reportForm">
                <input type="hidden" id="reportMessageId" name="message_id">
                <div class="form-group">
                    <label>举报类型 <span class="required">*</span></label>
                    <div class="report-type-options">
                        <label class="report-type-option">
                            <input type="radio" name="report_type" value="spam" required>
                            <span>🗑️ 垃圾信息</span>
                        </label>
                        <label class="report-type-option">
                            <input type="radio" name="report_type" value="abuse">
                            <span>😡 辱骂攻击</span>
                        </label>
                        <label class="report-type-option">
                            <input type="radio" name="report_type" value="illegal">
                            <span>⚖️ 违法违规</span>
                        </label>
                        <label class="report-type-option">
                            <input type="radio" name="report_type" value="porn">
                            <span>🔞 色情低俗</span>
                        </label>
                        <label class="report-type-option">
                            <input type="radio" name="report_type" value="other">
                            <span>📝 其他</span>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="reportDescription">补充说明 <span class="text-muted">(可选，最多500字)</span></label>
                    <textarea id="reportDescription" name="description" rows="4" maxlength="500" placeholder="请描述具体的违规内容，帮助我们更好地处理..."></textarea>
                    <span class="char-count"><span id="reportDescCount">0</span>/500</span>
                </div>
                <div class="form-tip">
                    <p>⚠️ 恶意举报将被限制功能使用，请如实填写举报内容。提交后在管理员处理前还可补充证据图片或撤回。</p>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeReportModal()">取消</button>
                    <button type="submit" class="btn btn-danger" id="reportSubmitBtn">提交举报</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 补充证据弹窗 -->
<div class="modal" id="evidenceModal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📷 补充证据</h3>
            <button class="modal-close" onclick="closeEvidenceModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="evidenceForm">
                <input type="hidden" id="evidenceReportId" name="report_id">
                <div class="form-group">
                    <label for="evidenceFiles">选择证据图片 <span class="required">*</span></label>
                    <input type="file" id="evidenceFiles" name="evidence[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                    <p class="text-muted" style="margin-top:6px;">
                        支持 JPG / PNG / GIF / WebP，单张不超过 5MB，每条举报最多补充 <?= REPORT_EVIDENCE_MAX ?> 张。
                    </p>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEvidenceModal()">取消</button>
                    <button type="submit" class="btn btn-primary" id="evidenceSubmitBtn">上传证据</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

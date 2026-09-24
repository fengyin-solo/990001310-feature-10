<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, '不支持的请求方式');
}

$action = $_POST['action'] ?? 'submit';

// 证据补充与撤回以 report_id 定位举报，message_id 对其非必填
if ($action !== 'add_evidence' && $action !== 'withdraw') {
    $messageId = intval($_POST['message_id'] ?? 0);
    if ($messageId <= 0) {
        jsonResponse(1, '无效的留言ID');
    }
}

$db = getDB();

try {
    if ($action === 'check') {
        $reported = hasReported($messageId);
        jsonResponse(0, '查询成功', ['reported' => $reported]);
    } elseif ($action === 'submit') {
        $reportType = cleanInput($_POST['report_type'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');

        if (empty($reportType)) {
            jsonResponse(1, '请选择举报类型');
        }

        if (mb_strlen($description) > 500) {
            jsonResponse(1, '补充说明不能超过500字');
        }

        // 唯一键约束兜底：并发提交/失败重试不会重复生成记录
        $reportId = submitReport($messageId, $reportType, $description);
        jsonResponse(0, '举报提交成功，我们会尽快处理', ['report_id' => $reportId]);
    } elseif ($action === 'add_evidence') {
        $reportId = intval($_POST['report_id'] ?? 0);
        if ($reportId <= 0) {
            jsonResponse(1, '无效的举报ID');
        }
        if (empty($_FILES['evidence'])) {
            jsonResponse(1, '请选择要补充的证据图片');
        }

        // addReportEvidence 内部完成归属校验（仅举报人）、状态校验（仅待处理）、
        // 数量与图片校验；越权或状态不满足时不会改动任何记录。
        $evidences = addReportEvidence($reportId, $_FILES['evidence']);
        jsonResponse(0, '证据补充成功', ['evidences' => $evidences]);
    } elseif ($action === 'withdraw') {
        $reportId = intval($_POST['report_id'] ?? 0);
        if ($reportId <= 0) {
            jsonResponse(1, '无效的举报ID');
        }

        // withdrawReport 内部完成归属校验（仅举报人）与状态校验（仅待处理），
        // 与管理员处理操作通过行锁互斥，先落地者为准。
        withdrawReport($reportId);
        jsonResponse(0, '举报已撤回，您可以重新发起举报');
    } else {
        jsonResponse(1, '未知操作');
    }
} catch (Exception $e) {
    jsonResponse(1, $e->getMessage());
}

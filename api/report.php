<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, '不支持的请求方式');
}

$action = $_POST['action'] ?? 'submit';
$messageId = intval($_POST['message_id'] ?? 0);

if ($messageId <= 0 && !in_array($action, ['supplement', 'withdraw'], true)) {
    jsonResponse(1, '无效的留言ID');
}

$db = getDB();

try {
    if ($action === 'check' || $action === 'my_report') {
        // 查询当前访客对该留言的举报状态（本人仅可查看自己的举报）
        $report = getMyReport($messageId);
        $data = [
            'reported' => $report !== null,
            'report' => $report ? formatMyReport($report) : null,
        ];
        jsonResponse(0, '查询成功', $data);
    }

    if ($action === 'submit') {
        $reportType = cleanInput($_POST['report_type'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');

        if (empty($reportType)) {
            jsonResponse(1, '请选择举报类型');
        }

        if (mb_strlen($description) > 500) {
            jsonResponse(1, '补充说明不能超过500字');
        }

        // 唯一键 + 行锁保证并发提交以先落地的一次为准；
        // 失败重试幂等，不会重复生成记录
        $result = submitReport($messageId, $reportType, $description, $_FILES['evidence'] ?? []);
        $report = getMyReport($messageId);
        jsonResponse(
            0,
            $result['created'] ? '举报提交成功，我们会尽快处理' : '举报已提交，请勿重复操作',
            [
                'report_id' => $result['id'],
                'created' => $result['created'],
                'report' => $report ? formatMyReport($report) : null,
            ]
        );
    }

    if ($action === 'supplement') {
        $reportId = intval($_POST['report_id'] ?? 0);
        if ($reportId <= 0) {
            jsonResponse(1, '无效的举报ID');
        }
        addReportEvidence($reportId, $_FILES['evidence'] ?? []);
        $report = getReportByIdForOwner($reportId);
        jsonResponse(0, '证据补充成功', ['report' => $report ? formatMyReport($report) : null]);
    }

    if ($action === 'withdraw') {
        $reportId = intval($_POST['report_id'] ?? 0);
        if ($reportId <= 0) {
            jsonResponse(1, '无效的举报ID');
        }
        withdrawReport($reportId);
        jsonResponse(0, '举报已撤回', ['reported' => false, 'report' => null]);
    }

    jsonResponse(1, '未知操作');
} catch (Exception $e) {
    jsonResponse(1, $e->getMessage());
}

/**
 * 组装返回给举报人的举报数据（仅返回本人的记录）
 */
function formatMyReport($report) {
    return [
        'id' => intval($report['id']),
        'message_id' => intval($report['message_id']),
        'report_type' => $report['report_type'],
        'report_type_label' => getReportTypeLabel($report['report_type']),
        'description' => $report['description'] ?? '',
        'status' => intval($report['status']),
        'status_label' => getReportStatusLabel($report['status']),
        'status_class' => getReportStatusClass($report['status']),
        'created_at' => $report['created_at'],
        'processed_at' => $report['processed_at'],
        'process_note' => $report['process_note'] ?? '',
        'evidence' => array_map(function ($ev) {
            return [
                'id' => intval($ev['id']),
                'image' => $ev['image'],
                'created_at' => $ev['created_at'],
            ];
        }, $report['evidence'] ?? []),
        'evidence_limit' => REPORT_EVIDENCE_MAX_COUNT,
    ];
}

/**
 * 补充证据后重新读取本人举报（带归属校验，读不到返回 null）
 */
function getReportByIdForOwner($reportId) {
    $visitorId = getVisitorId();
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM reports WHERE id = ? AND visitor_id = ?");
    $stmt->execute([$reportId, $visitorId]);
    $report = $stmt->fetch();
    if (!$report) {
        return null;
    }
    $report['evidence'] = getReportEvidence($report['id']);
    return $report;
}

<?php
session_start();

/**
 * 返回JSON响应
 */
function jsonResponse($code, $msg, $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    $res = ['code' => $code, 'msg' => $msg];
    if ($data !== null) $res['data'] = $data;
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 获取留言类型文字
 */
function getTypeLabel($type) {
    $map = ['help' => '居民求助', 'suggest' => '意见建议', 'lost' => '失物招领'];
    return $map[$type] ?? '其他';
}

/**
 * 获取类型图标
 */
function getTypeIcon($type) {
    $map = ['help' => '🆘', 'suggest' => '💡', 'lost' => '🔍'];
    return $map[$type] ?? '📌';
}

/**
 * 获取状态文字
 */
function getStatusLabel($status) {
    $map = [0 => '待审核', 1 => '已通过', 2 => '已拒绝'];
    return $map[$status] ?? '未知';
}

/**
 * 获取状态样式类
 */
function getStatusClass($status) {
    $map = [0 => 'pending', 1 => 'approved', 2 => 'rejected'];
    return $map[$status] ?? '';
}

/**
 * 时间格式化
 */
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . '年前';
    if ($diff->m > 0) return $diff->m . '个月前';
    if ($diff->d > 0) return $diff->d . '天前';
    if ($diff->h > 0) return $diff->h . '小时前';
    if ($diff->i > 0) return $diff->i . '分钟前';
    return '刚刚';
}

/**
 * 检查管理员登录
 */
function requireAdmin() {
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * 过滤输入
 */
function cleanInput($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

/**
 * 获取访客唯一标识
 * 基于session和cookie实现匿名用户标识
 */
function getVisitorId() {
    if (empty($_SESSION['visitor_id'])) {
        if (!empty($_COOKIE['visitor_id'])) {
            $_SESSION['visitor_id'] = $_COOKIE['visitor_id'];
        } else {
            $visitorId = md5(uniqid('visitor_', true) . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
            $_SESSION['visitor_id'] = $visitorId;
            setcookie('visitor_id', $visitorId, time() + 86400 * 365, '/');
        }
    }
    return $_SESSION['visitor_id'];
}

/**
 * 检查留言是否已收藏
 */
function isFavorited($messageId) {
    $visitorId = getVisitorId();
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM favorites WHERE visitor_id = ? AND message_id = ?");
    $stmt->execute([$visitorId, $messageId]);
    return $stmt->fetch() !== false;
}

/**
 * 获取当前访客收藏的所有留言ID
 */
function getFavoritedMessageIds() {
    $visitorId = getVisitorId();
    $db = getDB();
    $stmt = $db->prepare("SELECT message_id FROM favorites WHERE visitor_id = ?");
    $stmt->execute([$visitorId]);
    return array_column($stmt->fetchAll(), 'message_id');
}

/**
 * 切换收藏状态
 * 返回: ['favorited' => bool, 'action' => 'add'|'remove']
 */
function toggleFavorite($messageId) {
    $visitorId = getVisitorId();
    $db = getDB();

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT id FROM favorites WHERE visitor_id = ? AND message_id = ? FOR UPDATE");
        $stmt->execute([$visitorId, $messageId]);
        $exists = $stmt->fetch();

        if ($exists) {
            $db->prepare("DELETE FROM favorites WHERE visitor_id = ? AND message_id = ?")
                ->execute([$visitorId, $messageId]);
            $result = ['favorited' => false, 'action' => 'remove'];
        } else {
            $db->prepare("INSERT INTO favorites (visitor_id, message_id) VALUES (?, ?)")
                ->execute([$visitorId, $messageId]);
            $result = ['favorited' => true, 'action' => 'add'];
        }

        $db->commit();
        return $result;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * 获取举报类型文字
 */
function getReportTypeLabel($type) {
    $map = [
        'spam' => '垃圾信息',
        'abuse' => '辱骂攻击',
        'illegal' => '违法违规',
        'porn' => '色情低俗',
        'other' => '其他'
    ];
    return $map[$type] ?? '未知';
}

/**
 * 获取举报状态文字
 */
function getReportStatusLabel($status) {
    $map = [
        0 => '待处理',
        1 => '已处理-已删除',
        2 => '已处理-已忽略',
        3 => '已驳回'
    ];
    return $map[$status] ?? '未知';
}

/**
 * 获取举报状态样式类
 */
function getReportStatusClass($status) {
    $map = [
        0 => 'pending',
        1 => 'resolved-deleted',
        2 => 'resolved-ignored',
        3 => 'rejected'
    ];
    return $map[$status] ?? '';
}

/**
 * 前台举报按钮在不同举报状态下的文字
 */
function reportButtonText($status) {
    $map = [
        0 => '已举报',
        1 => '已处理',
        2 => '已处理',
        3 => '已驳回'
    ];
    return $map[$status] ?? '已举报';
}

/**
 * 举报证据相关限制
 */
define('REPORT_EVIDENCE_MAX_COUNT', 6);
define('REPORT_EVIDENCE_MAX_SIZE', 5 * 1024 * 1024);
define('REPORT_EVIDENCE_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

/**
 * 检查当前访客是否已举报过某条留言
 */
function hasReported($messageId) {
    return getMyReport($messageId) !== null;
}

/**
 * 获取当前访客对某条留言的举报（含证据图片），未举报返回 null
 */
function getMyReport($messageId) {
    $visitorId = getVisitorId();
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM reports WHERE visitor_id = ? AND message_id = ?");
    $stmt->execute([$visitorId, $messageId]);
    $report = $stmt->fetch();
    if (!$report) {
        return null;
    }
    $report['evidence'] = getReportEvidence($report['id']);
    return $report;
}

/**
 * 获取举报的证据图片列表
 */
function getReportEvidence($reportId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, image, created_at FROM report_evidence WHERE report_id = ? ORDER BY id ASC");
    $stmt->execute([$reportId]);
    return $stmt->fetchAll();
}

/**
 * 校验并保存一张举报证据图片，返回相对路径
 */
function saveReportEvidenceFile($file) {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('证据图片上传失败，请重试');
    }
    if ($file['size'] > REPORT_EVIDENCE_MAX_SIZE) {
        throw new Exception('单张证据图片不能超过5MB');
    }

    // MIME 白名单 + finfo 实际类型双重校验
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMimes = REPORT_EVIDENCE_ALLOWED_TYPES;
    if (!in_array($file['type'], $allowedMimes, true) || !in_array($mime, $allowedMimes, true)) {
        throw new Exception('仅支持 JPG、PNG、GIF、WebP 格式的图片');
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $ext = $extMap[$mime];

    $uploadDir = __DIR__ . '/../uploads/reports/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('证据图片保存失败，请重试');
    }

    return 'uploads/reports/' . $filename;
}

/**
 * 将 $_FILES 中多文件字段规范化为数组（兼容空 input 与多文件结构）
 */
function normalizeEvidenceFiles($files) {
    if (!is_array($files) || !isset($files['error'])) {
        return [];
    }
    $list = [];
    if (is_array($files['error'])) {
        foreach ($files['error'] as $i => $error) {
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $list[] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $error,
                'size' => $files['size'][$i],
            ];
        }
    } elseif ($files['error'] !== UPLOAD_ERR_NO_FILE) {
        $list[] = $files;
    }
    return $list;
}

/**
 * 删除磁盘上的证据图片（忽略不存在的文件）
 */
function deleteEvidenceFiles(array $images) {
    foreach ($images as $image) {
        $path = __DIR__ . '/../' . $image;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * 提交举报（并发安全：唯一键保证同一访客对同一留言只有一条记录）
 *
 * 重试或并发提交时不会生成重复记录：
 * 已存在待处理举报则返回原记录；唯一键冲突时以先落地的一次为准。
 *
 * @return array ['id' => int, 'created' => bool]
 */
function submitReport($messageId, $reportType, $description = '', $evidenceFiles = []) {
    $visitorId = getVisitorId();
    $db = getDB();

    $validTypes = ['spam', 'abuse', 'illegal', 'porn', 'other'];
    if (!in_array($reportType, $validTypes)) {
        throw new Exception('无效的举报类型');
    }

    $stmt = $db->prepare("SELECT id FROM messages WHERE id = ? AND status = 1");
    $stmt->execute([$messageId]);
    if (!$stmt->fetch()) {
        throw new Exception('留言不存在或未通过审核');
    }

    $evidenceFiles = normalizeEvidenceFiles($evidenceFiles);
    if (count($evidenceFiles) > REPORT_EVIDENCE_MAX_COUNT) {
        throw new Exception('证据图片最多上传' . REPORT_EVIDENCE_MAX_COUNT . '张');
    }

    // 先落盘图片；若入库失败需回滚删除，避免孤儿文件
    $savedImages = [];
    foreach ($evidenceFiles as $file) {
        $savedImages[] = saveReportEvidenceFile($file);
    }

    $db->beginTransaction();
    try {
        // 锁定该访客在该留言上的举报记录，串行化并发提交
        $stmt = $db->prepare("SELECT id, status FROM reports WHERE visitor_id = ? AND message_id = ? FOR UPDATE");
        $stmt->execute([$visitorId, $messageId]);
        $existing = $stmt->fetch();

        if ($existing) {
            // 已有举报：不新增记录。待处理状态下本次携带的图片并入原举报
            $reportId = $existing['id'];
            if ($existing['status'] == 0 && $savedImages) {
                $countStmt = $db->prepare("SELECT COUNT(*) FROM report_evidence WHERE report_id = ?");
                $countStmt->execute([$reportId]);
                if (intval($countStmt->fetchColumn()) + count($savedImages) > REPORT_EVIDENCE_MAX_COUNT) {
                    throw new Exception('证据图片最多' . REPORT_EVIDENCE_MAX_COUNT . '张');
                }
                $evStmt = $db->prepare("INSERT INTO report_evidence (report_id, image) VALUES (?, ?)");
                foreach ($savedImages as $image) {
                    $evStmt->execute([$reportId, $image]);
                }
            }
            $db->commit();
            // 已有记录说明本次未创建（重试/并发），删除本次多传但无法归档的图片
            if ($existing['status'] != 0 && $savedImages) {
                deleteEvidenceFiles($savedImages);
            }
            return ['id' => $reportId, 'created' => false];
        }

        $stmt = $db->prepare("INSERT INTO reports (message_id, visitor_id, report_type, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$messageId, $visitorId, $reportType, $description]);
        $reportId = $db->lastInsertId();

        if ($savedImages) {
            $evStmt = $db->prepare("INSERT INTO report_evidence (report_id, image) VALUES (?, ?)");
            foreach ($savedImages as $image) {
                $evStmt->execute([$reportId, $image]);
            }
        }

        $db->commit();
        return ['id' => $reportId, 'created' => true];
    } catch (Exception $e) {
        $db->rollBack();
        deleteEvidenceFiles($savedImages);
        throw $e;
    }
}

/**
 * 补充举报证据
 *
 * 仅举报人本人、且举报处于待处理状态时允许补充；
 * 管理员、访客或举报已处理时均不允许改动原记录。
 */
function addReportEvidence($reportId, $evidenceFiles) {
    $visitorId = getVisitorId();
    $db = getDB();

    $evidenceFiles = normalizeEvidenceFiles($evidenceFiles);
    if (empty($evidenceFiles)) {
        throw new Exception('请选择要补充的证据图片');
    }

    $savedImages = [];
    foreach ($evidenceFiles as $file) {
        $savedImages[] = saveReportEvidenceFile($file);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT visitor_id, status FROM reports WHERE id = ? FOR UPDATE");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch();

        if (!$report) {
            throw new Exception('举报不存在');
        }
        if (!hash_equals($report['visitor_id'], $visitorId)) {
            throw new Exception('只有举报人本人可以补充证据');
        }
        if ($report['status'] != 0) {
            throw new Exception('举报已处理，无法补充证据');
        }

        $countStmt = $db->prepare("SELECT COUNT(*) FROM report_evidence WHERE report_id = ?");
        $countStmt->execute([$reportId]);
        if (intval($countStmt->fetchColumn()) + count($savedImages) > REPORT_EVIDENCE_MAX_COUNT) {
            throw new Exception('证据图片最多' . REPORT_EVIDENCE_MAX_COUNT . '张');
        }

        $evStmt = $db->prepare("INSERT INTO report_evidence (report_id, image) VALUES (?, ?)");
        foreach ($savedImages as $image) {
            $evStmt->execute([$reportId, $image]);
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        deleteEvidenceFiles($savedImages);
        throw $e;
    }
}

/**
 * 撤回举报
 *
 * 仅举报人本人、且举报处于待处理状态时允许撤回。
 * 撤回后物理删除举报及证据（不残留旧状态），同一留言可重新发起举报。
 * 处理中或已完成的举报不允许撤回。
 */
function withdrawReport($reportId) {
    $visitorId = getVisitorId();
    $db = getDB();

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT visitor_id, status FROM reports WHERE id = ? FOR UPDATE");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch();

        if (!$report) {
            throw new Exception('举报不存在');
        }
        if (!hash_equals($report['visitor_id'], $visitorId)) {
            throw new Exception('只有举报人本人可以撤回举报');
        }
        if ($report['status'] != 0) {
            throw new Exception('举报已处理，无法撤回');
        }

        // 收集证据路径，记录删除（外键级联）后清理磁盘文件
        $stmt = $db->prepare("SELECT image FROM report_evidence WHERE report_id = ?");
        $stmt->execute([$reportId]);
        $images = array_column($stmt->fetchAll(), 'image');

        $db->prepare("DELETE FROM reports WHERE id = ? AND status = 0")->execute([$reportId]);

        $db->commit();
        deleteEvidenceFiles($images);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * 获取待处理举报数量
 */
function getPendingReportCount() {
    $db = getDB();
    return $db->query("SELECT COUNT(*) FROM reports WHERE status = 0")->fetchColumn();
}

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
 * 每条举报最多可补充的证据图片数量
 */
define('REPORT_EVIDENCE_MAX', 6);

/**
 * 单张证据图片大小上限（5MB）
 */
define('REPORT_EVIDENCE_MAX_SIZE', 5 * 1024 * 1024);

/**
 * 允许的证据图片 MIME 类型 => 扩展名
 */
function reportEvidenceAllowedMimes() {
    return [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
}

/**
 * 检查当前访客是否已举报过某条留言
 * 撤回记录已被物理删除，因此撤回后本函数自然返回 false，可重新发起举报。
 */
function hasReported($messageId) {
    $visitorId = getVisitorId();
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM reports WHERE visitor_id = ? AND message_id = ?");
    $stmt->execute([$visitorId, $messageId]);
    return $stmt->fetch() !== false;
}

/**
 * 获取当前访客对某条留言的举报记录（不存在返回 null）
 */
function getMyReport($messageId) {
    $visitorId = getVisitorId();
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM reports WHERE visitor_id = ? AND message_id = ?");
    $stmt->execute([$visitorId, $messageId]);
    $report = $stmt->fetch();
    return $report ?: null;
}

/**
 * 获取某条举报的证据图片列表
 */
function getReportEvidences($reportId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, image, created_at FROM report_evidences WHERE report_id = ? ORDER BY id ASC");
    $stmt->execute([$reportId]);
    return $stmt->fetchAll();
}

/**
 * 规范化多文件上传字段（HTML multiple）
 * 输入形如 ['name'=>[...], 'tmp_name'=>[...], ...]，输出单文件数组列表
 */
function normalizeUploadedFiles(array $files) {
    $result = [];
    foreach ($files as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $i => $v) {
                $result[$i][$key] = $v;
            }
        }
    }
    return $result;
}

/**
 * 校验并保存一张证据图片，返回相对路径（uploads/evidence/xxx.jpg）
 * 校验不通过或保存失败时抛出 Exception
 */
function saveReportEvidenceFile(array $file) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('证据图片上传失败，请重试');
    }
    if ($file['size'] > REPORT_EVIDENCE_MAX_SIZE) {
        throw new Exception('单张证据图片不能超过5MB');
    }

    // 以文件实际内容识别 MIME，不信任客户端提交的 type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed = reportEvidenceAllowedMimes();
    if (!isset($allowed[$mime])) {
        throw new Exception('仅支持 JPG、PNG、GIF、WebP 格式的证据图片');
    }

    $uploadDir = __DIR__ . '/../uploads/evidence/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new Exception('证据存储目录不可写');
    }

    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        throw new Exception('证据图片保存失败，请重试');
    }

    return 'uploads/evidence/' . $filename;
}

/**
 * 删除一张证据图片文件（仅删除受管理目录内的文件）
 */
function deleteReportEvidenceFile($relativePath) {
    $relativePath = (string) $relativePath;
    if (strpos($relativePath, 'uploads/evidence/') !== 0) {
        return;
    }
    $fullPath = __DIR__ . '/../' . $relativePath;
    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

/**
 * 提交举报
 * 依赖 reports 表的 UNIQUE(visitor_id, message_id) 约束保证并发安全：
 * 并发提交或失败重试只有最先落地的一次会成功，不会重复生成记录。
 */
function submitReport($messageId, $reportType, $description = '') {
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

    if (hasReported($messageId)) {
        throw new Exception('您已经举报过这条留言了');
    }

    try {
        $stmt = $db->prepare("INSERT INTO reports (message_id, visitor_id, report_type, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$messageId, $visitorId, $reportType, $description]);
    } catch (PDOException $e) {
        // 唯一键冲突（并发提交/网络重试）：举报已存在，不重复生成记录
        $sqlStateCode = $e->errorInfo[1] ?? null;
        if ((int) $sqlStateCode === 1062) {
            throw new Exception('您已经举报过这条留言了，请勿重复提交');
        }
        throw $e;
    }

    return $db->lastInsertId();
}

/**
 * 补充举报证据（仅限举报人本人、且举报仍处于待处理状态）
 * 返回补充后该举报的全部证据列表
 */
function addReportEvidence($reportId, array $files) {
    $visitorId = getVisitorId();
    $db = getDB();

    // 展开多文件字段并过滤未选择的项
    $files = normalizeUploadedFiles($files);
    $files = array_values(array_filter($files, function ($f) {
        return isset($f['error']) && $f['error'] !== UPLOAD_ERR_NO_FILE;
    }));
    if (empty($files)) {
        throw new Exception('请先选择要补充的证据图片');
    }

    // 先落盘文件，再开事务写库
    $savedPaths = [];
    try {
        foreach ($files as $file) {
            $path = saveReportEvidenceFile($file);
            if ($path !== null) {
                $savedPaths[] = $path;
            }
        }
    } catch (Exception $e) {
        // 校验/保存失败：清理本次已保存的文件，不留孤儿文件
        foreach ($savedPaths as $path) {
            deleteReportEvidenceFile($path);
        }
        throw $e;
    }

    try {
        $db->beginTransaction();

        // 行锁：与管理员处理、撤回操作互斥，谁先落地谁生效
        $stmt = $db->prepare("SELECT * FROM reports WHERE id = ? FOR UPDATE");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch();

        if (!$report) {
            throw new Exception('举报不存在或已撤回');
        }
        if (!hash_equals($visitorId, (string) $report['visitor_id'])) {
            // 越权：不改动任何记录
            throw new Exception('无权补充此举报的证据');
        }
        if ((int) $report['status'] !== 0) {
            throw new Exception('举报已在处理中或已完成，无法补充证据');
        }

        $countStmt = $db->prepare("SELECT COUNT(*) FROM report_evidences WHERE report_id = ?");
        $countStmt->execute([$reportId]);
        $existCount = (int) $countStmt->fetchColumn();
        if ($existCount + count($savedPaths) > REPORT_EVIDENCE_MAX) {
            throw new Exception('每条举报最多补充' . REPORT_EVIDENCE_MAX . '张证据图片（已有' . $existCount . '张）');
        }

        $insert = $db->prepare("INSERT INTO report_evidences (report_id, image) VALUES (?, ?)");
        foreach ($savedPaths as $path) {
            $insert->execute([$reportId, $path]);
        }

        $evidences = getReportEvidences($reportId);
        $db->commit();
        return $evidences;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        // 入库失败（含越权/状态变更/数量超限）：回滚并清理刚保存的文件
        foreach ($savedPaths as $path) {
            deleteReportEvidenceFile($path);
        }
        throw $e;
    }
}

/**
 * 撤回举报（仅限举报人本人、且举报仍处于待处理状态）
 * 物理删除举报及其证据记录与文件：不残留任何旧状态，
 * 删除后 UNIQUE(visitor_id, message_id) 约束释放，可重新发起举报。
 */
function withdrawReport($reportId) {
    $visitorId = getVisitorId();
    $db = getDB();

    $db->beginTransaction();
    try {
        // 行锁：与管理员“处理”操作互斥，并发时先落地者为准
        $stmt = $db->prepare("SELECT * FROM reports WHERE id = ? FOR UPDATE");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch();

        if (!$report) {
            throw new Exception('举报不存在或已撤回');
        }
        if (!hash_equals($visitorId, (string) $report['visitor_id'])) {
            // 越权：不改动任何记录
            throw new Exception('无权撤回此举报');
        }
        if ((int) $report['status'] !== 0) {
            throw new Exception('举报已在处理中或已完成，无法撤回');
        }

        // ON DELETE CASCADE 会同步删除 report_evidences 记录
        $evidences = getReportEvidences($reportId);
        $db->prepare("DELETE FROM reports WHERE id = ? AND visitor_id = ? AND status = 0")
            ->execute([$reportId, $visitorId]);

        $db->commit();

        // 提交成功后再删除证据文件
        foreach ($evidences as $ev) {
            deleteReportEvidenceFile($ev['image']);
        }
        return true;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
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

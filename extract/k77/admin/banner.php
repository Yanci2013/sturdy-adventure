<?php
session_start();
// 登录验证已注释掉
// if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
//     header('Location: login.php');
//     exit;
// }
require '../config.php';

$msg = '';
$msgType = '';

if (!empty($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msgType = $_GET['type'] ?? 'info';
}

// 1、个人中心底部轮播上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['banner_img'])) {
    $uploadDir = '../uploads/user_banner/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $file = $_FILES['banner_img'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allow = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allow)) {
        $msg = '仅支持 jpg / png / gif / webp 图片格式';
        $msgType = 'danger';
    } elseif ($file['size'] > 3 * 1024 * 1024) {
        $msg = '图片不能超过 3M';
        $msgType = 'danger';
    } else {
        $fileName = date('YmdHis_') . mt_rand(100, 999) . '.' . $ext;
        $fullPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $fullPath)) {
            try {
                $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
                $sort = intval($_POST['sort'] ?? 0);
                $dbPath = 'uploads/user_banner/' . $fileName;
                $ins = $db->prepare("INSERT INTO user_center_banner (image_path, sort_order) VALUES (?, ?)");
                $ins->execute([$dbPath, $sort]);

                $msg = '底部轮播上传成功';
                $msgType = 'success';
            } catch (PDOException $e) {
                $msg = '数据库写入失败：' . $e->getMessage();
                $msgType = 'danger';
            }
        } else {
            $msg = '文件上传失败，请检查 ../uploads/user_banner/ 目录权限';
            $msgType = 'danger';
        }
    }
}

// 2、打手/个人头部模糊背景上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bg_img'])) {
    $uploadDir = '../uploads/user_header_bg/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $file = $_FILES['bg_img'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allow = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allow)) {
        $msg = '背景图仅支持 jpg / png / gif / webp';
        $msgType = 'danger';
    } elseif ($file['size'] > 3 * 1024 * 1024) {
        $msg = '图片不能超过 3M';
        $msgType = 'danger';
    } else {
        $fileName = date('YmdHis_') . mt_rand(100, 999) . '.' . $ext;
        $fullPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $fullPath)) {
            try {
                $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
                $sort = intval($_POST['bg_sort'] ?? 0);
                $status = intval($_POST['status'] ?? 1);
                $dbPath = 'uploads/user_header_bg/' . $fileName;
                $ins = $db->prepare("INSERT INTO user_center_header_bg (image_path, sort_order, status) VALUES (?, ?, ?)");
                $ins->execute([$dbPath, $sort, $status]);

                $msg = '头部模糊背景上传成功';
                $msgType = 'success';
            } catch (PDOException $e) {
                $msg = '数据库写入失败：' . $e->getMessage();
                $msgType = 'danger';
            }
        } else {
            $msg = '上传目录权限不足';
            $msgType = 'danger';
        }
    }
}

// 删除底部轮播
if (isset($_GET['del']) && is_numeric($_GET['del'])) {
    $delId = intval($_GET['del']);
    try {
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $row = $db->query("SELECT image_path FROM user_center_banner WHERE id = " . $delId)->fetch();
        if (!empty($row['image_path'])) {
            $filePath = '../' . $row['image_path'];
            if (file_exists($filePath)) unlink($filePath);
        }
        $db->exec("DELETE FROM user_center_banner WHERE id = " . $delId);
        header('Location: banner.php?msg=底部轮播删除成功&type=success');
        exit;
    } catch (PDOException $e) {
        header('Location: banner.php?msg=删除失败&type=danger');
        exit;
    }
}

// 删除头部背景
if (isset($_GET['delbg']) && is_numeric($_GET['delbg'])) {
    $delId = intval($_GET['delbg']);
    try {
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $row = $db->query("SELECT image_path FROM user_center_header_bg WHERE id = " . $delId)->fetch();
        if (!empty($row['image_path'])) {
            $filePath = '../' . $row['image_path'];
            if (file_exists($filePath)) unlink($filePath);
        }
        $db->exec("DELETE FROM user_center_header_bg WHERE id = " . $delId);
        header('Location: banner.php?msg=头部背景删除成功&type=success');
        exit;
    } catch (PDOException $e) {
        header('Location: banner.php?msg=删除失败&type=danger');
        exit;
    }
}

// 读取底部轮播列表
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $list = $db->query("SELECT * FROM user_center_banner ORDER BY sort_order DESC, id DESC")->fetchAll();
} catch (PDOException $e) {
    $list = [];
    $msg = '轮播读取失败';
    $msgType = 'danger';
}

// 读取头部模糊背景列表（打手/个人主页共用背景）
try {
    $bgList = $db->query("SELECT * FROM user_center_header_bg ORDER BY sort_order DESC, id DESC")->fetchAll();
} catch (PDOException $e) {
    $bgList = [];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>个人中心轮播+头部模糊背景管理</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft YaHei", "PingFang SC", sans-serif;
        }

        body {
            background: #f5f7fa;
            color: #333;
        }

        .admin-wrap {
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            width: 240px;
            background: #1a1a2e;
            color: #fff;
            flex-shrink: 0;
            padding-top: 20px;
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
            color: #ffd700;
        }

        .sidebar-menu {
            padding: 0 12px;
        }

        .menu-item {
            display: block;
            color: #b8c2cc;
            text-decoration: none;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 14px;
            transition: 0.2s;
        }

        .menu-item:hover,
        .menu-item.active {
            background: rgba(255, 215, 0, 0.15);
            color: #ffd700;
        }

        .menu-item i {
            width: 20px;
            margin-right: 8px;
        }

        .admin-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .admin-header {
            background: #fff;
            height: 60px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
        }

        .header-left {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .admin-user {
            font-size: 14px;
            color: #666;
        }

        .logout-btn {
            background: #ef4444;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }

        .admin-content {
            padding: 24px;
            flex: 1;
        }

        .page-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #1a1a2e;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert.success {
            background: #e6f9ed;
            color: #198754;
            border: 1px solid #b8e9cb;
        }

        .alert.danger {
            background: #fff0f0;
            color: #dc3545;
            border: 1px solid #ffcccc;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #1a1a2e;
        }

        form p {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #444;
            font-size: 14px;
        }

        input[type="file"],
        input[type="number"],
        select {
            width: 100%;
            max-width: 360px;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background: #fff;
        }

        button {
            background: #ffd700;
            color: #1a1a2e;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s;
        }

        button:hover {
            background: #ffb700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th,
        td {
            border: 1px solid #eee;
            padding: 14px;
            text-align: center;
            font-size: 14px;
        }

        th {
            background: #f7f8fa;
            color: #1a1a2e;
            font-weight: 700;
        }

        img.pre {
            max-width: 180px;
            max-height: 100px;
            border-radius: 8px;
            object-fit: cover;
        }

        .del {
            color: #ef4444;
            text-decoration: none;
            font-weight: 600;
        }

        .del:hover {
            text-decoration: underline;
        }

        .empty {
            padding: 30px;
            text-align: center;
            color: #888;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                display: none;
            }

            .admin-header {
                padding: 0 16px;
            }

            .admin-content {
                padding: 16px;
            }

            table,
            thead,
            tbody,
            th,
            td,
            tr {
                display: block;
            }

            thead {
                display: none;
            }

            tr {
                margin-bottom: 12px;
                border: 1px solid #eee;
                border-radius: 12px;
            }

            td {
                text-align: right;
                padding: 12px 16px;
                border: none;
                border-bottom: 1px solid #eee;
            }

            td:last-child {
                border-bottom: none;
            }

            td::before {
                content: attr(data-label);
                float: left;
                font-weight: 600;
                color: #1a1a2e;
            }

            img.pre {
                max-width: 100%;
                height: auto;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrap">
        <aside class="admin-sidebar">
            <div class="sidebar-title">小熠系统</div>
            <nav class="sidebar-menu">
                <a href="index.php" class="menu-item">
                    <i class="fas fa-home"></i> 后台首页
                </a>
                <a href="banner.php" class="menu-item active">
                    <i class="fas fa-images"></i> 轮播&背景管理
                </a>
            </nav>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">个人中心轮播 + 头部模糊背景（打手入驻共用）</div>
                <div class="header-right">
                    <span class="admin-user">管理员</span>
                </div>
            </header>

            <div class="admin-content">
                <h1 class="page-title">图片上传管理后台</h1>

                <?php if (!empty($msg)): ?>
                <div class="alert <?= $msgType ?>">
                    <?= htmlspecialchars($msg) ?>
                </div>
                <?php endif; ?>

                <!-- 模块1：底部轮播上传 -->
                <div class="card">
                    <h2 class="card-title">一、个人中心底部轮播图上传</h2>
                    <form method="post" enctype="multipart/form-data">
                        <p>
                            <label>选择轮播图片</label>
                            <input type="file" name="banner_img" accept="image/*" required>
                        </p>
                        <p>
                            <label>排序（数字越大越靠前）</label>
                            <input type="number" name="sort" value="0">
                        </p>
                        <p>
                            <button type="submit">提交上传轮播</button>
                        </p>
                    </form>
                </div>

                <!-- 模块2：头部模糊背景（打手/个人主页共用） -->
                <div class="card">
                    <h2 class="card-title">二、个人头部毛玻璃背景（打手入驻页面共用）</h2>
                    <form method="post" enctype="multipart/form-data">
                        <p>
                            <label>选择背景图</label>
                            <input type="file" name="bg_img" accept="image/*" required>
                        </p>
                        <p>
                            <label>排序</label>
                            <input type="number" name="bg_sort" value="0">
                        </p>
                        <p>
                            <label>是否启用（1启用 0关闭）</label>
                            <select name="status">
                                <option value="1">启用</option>
                                <option value="0">关闭</option>
                            </select>
                        </p>
                        <p>
                            <button type="submit">提交上传背景</button>
                        </p>
                    </form>
                </div>

                <!-- 底部轮播列表 -->
                <div class="card">
                    <h2 class="card-title">底部轮播列表</h2>
                    <?php if (empty($list)): ?>
                        <div class="empty">暂无上传轮播图</div>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>预览图</th>
                                <th>排序值</th>
                                <th>上传时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($list as $v): ?>
                            <tr>
                                <td data-label="ID"><?= $v['id'] ?></td>
                                <td data-label="预览图">
                                    <img class="pre" src="../<?= htmlspecialchars($v['image_path']) ?>" alt="">
                                </td>
                                <td data-label="排序值"><?= $v['sort_order'] ?></td>
                                <td data-label="上传时间"><?= $v['create_time'] ?></td>
                                <td data-label="操作">
                                    <a href="?del=<?= $v['id'] ?>" class="del" onclick="return confirm('确认删除该轮播图？')">删除</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <!-- 头部模糊背景列表 -->
                <div class="card">
                    <h2 class="card-title">头部毛玻璃背景列表（打手页面共用）</h2>
                    <?php if (empty($bgList)): ?>
                        <div class="empty">暂无背景图</div>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>预览背景</th>
                                <th>排序</th>
                                <th>状态</th>
                                <th>上传时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bgList as $v): ?>
                            <tr>
                                <td data-label="ID"><?= $v['id'] ?></td>
                                <td data-label="预览">
                                    <img class="pre" src="../<?= htmlspecialchars($v['image_path']) ?>" alt="">
                                </td>
                                <td data-label="排序"><?= $v['sort_order'] ?></td>
                                <td data-label="状态"><?= $v['status'] == 1 ? '✅启用' : '❌关闭' ?></td>
                                <td data-label="时间"><?= $v['create_time'] ?></td>
                                <td data-label="操作">
                                    <a href="?delbg=<?= $v['id'] ?>" class="del" onclick="return confirm('确认删除该背景？')">删除</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
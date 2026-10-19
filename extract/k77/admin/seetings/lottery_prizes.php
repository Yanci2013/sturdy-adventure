<?php
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';

if (!is_admin_logged_in()) {
    $_SESSION['error_msg'] = "请先登录管理员账号";
    header("Location: ../login.php");
    exit();
}

function checkAndCreateLotteryTables($pdo) {
    try {
        $tables = $pdo->query("SHOW TABLES LIKE 'lottery_prizes'")->rowCount();
        if ($tables == 0) {
            $sql = "CREATE TABLE lottery_prizes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                image VARCHAR(255) DEFAULT '',
                type VARCHAR(50) DEFAULT 'product',
                product_id INT DEFAULT NULL,
                probability DECIMAL(10,4) DEFAULT 0.0000,
                stock INT DEFAULT 0,
                sort_order INT DEFAULT 0,
                status TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($sql);
        }
    } catch (Exception $e) {
    }
}

checkAndCreateLotteryTables($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_prize'])) {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM lottery_prizes WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = '奖品已删除';
        header('Location: lottery_prizes.php');
        exit();
    }
    
    if (isset($_POST['save_prize'])) {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $type = $_POST['type'] ?? 'product';
        $product_id = !empty($_POST['product_id']) ? intval($_POST['product_id']) : null;
        $probability = floatval($_POST['probability'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        $existing_image = $_POST['existing_image'] ?? '';
        
        $image = $existing_image;
        if (!empty($_FILES['image']['name'])) {
            $uploadDir = '../../assets/images/lottery/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = basename($_FILES['image']['name']);
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($fileExt, $allowedExts)) {
                $newFileName = 'prize_' . time() . '.' . $fileExt;
                $uploadPath = $uploadDir . $newFileName;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                    if (!empty($existing_image)) {
                        @unlink('../../' . $existing_image);
                    }
                    $image = 'assets/images/lottery/' . $newFileName;
                }
            }
        }
        
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            $id = intval($_POST['id']);
            $sql = "UPDATE lottery_prizes SET name=?, description=?, image=?, type=?, product_id=?, probability=?, stock=?, sort_order=?, status=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $description, $image, $type, $product_id, $probability, $stock, $sort_order, $status, $id]);
        } else {
            $sql = "INSERT INTO lottery_prizes (name, description, image, type, product_id, probability, stock, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $description, $image, $type, $product_id, $probability, $stock, $sort_order, $status]);
        }
        
        $_SESSION['success'] = '奖品已保存';
        header('Location: lottery_prizes.php');
        exit();
    }
}

$prizes = $pdo->query("SELECT * FROM lottery_prizes ORDER BY sort_order DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("SELECT id, name, chinese_name FROM products WHERE status = 1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$editPrize = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM lottery_prizes WHERE id = ?");
    $stmt->execute([$id]);
    $editPrize = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>抽奖奖品管理</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f5;
            padding: 24px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;
        }
        .header h1 { font-size: 24px; font-weight: 600; color: #000; }
        .btn {
            padding: 10px 20px; background: #000; color: #fff; border: none; border-radius: 12px;
            cursor: pointer; font-size: 14px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn:hover { background: #333; }
        .btn-outline { background: #fff; color: #666; border: 1px solid #eaeaea; }
        .btn-outline:hover { background: #f5f5f5; color: #000; }
        .card {
            background: #fff; border-radius: 16px; padding: 24px; margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 500; color: #666; margin-bottom: 8px; }
        .form-control {
            width: 100%; padding: 12px 16px; border: 1px solid #eaeaea; border-radius: 12px;
            font-size: 14px; color: #1d1d1f; background: #fff;
        }
        .form-control:focus { outline: none; border-color: #000; box-shadow: 0 0 0 3px rgba(0,0,0,0.1); }
        .row { display: flex; gap: 20px; flex-wrap: wrap; }
        .col-md-6 { flex: 0 0 calc(50% - 10px); }
        .col-md-12 { flex: 0 0 100%; }
        @media (max-width: 768px) { .col-md-6 { flex: 0 0 100%; } }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f5f5f5; }
        th { background: #f8f8f8; color: #666; font-weight: 500; font-size: 13px; }
        td { color: #666; font-size: 13px; }
        .prize-image { width: 60px; height: 60px; border-radius: 8px; object-fit: cover; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .alert { padding: 16px; border-radius: 12px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .actions { display: flex; gap: 8px; }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }
        .image-preview { margin-top: 10px; max-width: 200px; border-radius: 12px; overflow: hidden; border: 1px solid #eaeaea; }
        .image-preview img { width: 100%; display: block; }
        .checkbox-wrapper { display: flex; align-items: center; gap: 10px; }
    </style>
  <link href="../res/admin-white.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-gift"></i> 抽奖奖品管理</h1>
            <a href="lottery_settings.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> 返回抽奖设置</a>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h3 style="margin-bottom: 20px; font-size: 18px; font-weight: 600;"><?php echo $editPrize ? '编辑奖品' : '添加奖品'; ?></h3>
            <form method="POST" enctype="multipart/form-data">
                <?php if ($editPrize): ?>
                    <input type="hidden" name="id" value="<?php echo $editPrize['id']; ?>">
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>奖品名称 *</label>
                            <input type="text" name="name" class="form-control" required value="<?php echo $editPrize ? htmlspecialchars($editPrize['name']) : ''; ?>" placeholder="请输入奖品名称">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>奖品类型</label>
                            <select name="type" class="form-control">
                                <option value="product" <?php echo ($editPrize && $editPrize['type'] == 'product') ? 'selected' : ''; ?>>商品奖品</option>
                                <option value="coupon" <?php echo ($editPrize && $editPrize['type'] == 'coupon') ? 'selected' : ''; ?>>优惠券</option>
                                <option value="points" <?php echo ($editPrize && $editPrize['type'] == 'points') ? 'selected' : ''; ?>>积分</option>
                                <option value="thank_you" <?php echo ($editPrize && $editPrize['type'] == 'thank_you') ? 'selected' : ''; ?>>谢谢参与</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>关联商品（商品奖品时选择）</label>
                            <select name="product_id" class="form-control">
                                <option value="">不关联商品</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" <?php echo ($editPrize && $editPrize['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['name'] . ' - ' . ($product['chinese_name'] ?? '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>中奖概率 (0-1，例如0.1表示10%)</label>
                            <input type="number" name="probability" class="form-control" step="0.0001" min="0" max="1" value="<?php echo $editPrize ? $editPrize['probability'] : '0.1'; ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>库存</label>
                            <input type="number" name="stock" class="form-control" value="<?php echo $editPrize ? $editPrize['stock'] : '0'; ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>排序（数字越大越靠前）</label>
                            <input type="number" name="sort_order" class="form-control" value="<?php echo $editPrize ? $editPrize['sort_order'] : '0'; ?>">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>奖品描述</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="请输入奖品描述"><?php echo $editPrize ? htmlspecialchars($editPrize['description']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label>奖品图片</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <input type="hidden" name="existing_image" value="<?php echo $editPrize ? htmlspecialchars($editPrize['image']) : ''; ?>">
                    <?php if ($editPrize && !empty($editPrize['image'])): ?>
                        <div class="image-preview">
                            <img src="../../<?php echo htmlspecialchars($editPrize['image']); ?>" alt="奖品图片">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="status" <?php echo (!$editPrize || $editPrize['status']) ? 'checked' : ''; ?>>
                        <span>启用此奖品</span>
                    </label>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="save_prize" class="btn"><i class="fas fa-save"></i> 保存</button>
                    <?php if ($editPrize): ?>
                        <a href="lottery_prizes.php" class="btn btn-outline">取消编辑</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h3 style="margin-bottom: 20px; font-size: 18px; font-weight: 600;">奖品列表</h3>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>图片</th>
                            <th>名称</th>
                            <th>类型</th>
                            <th>概率</th>
                            <th>库存</th>
                            <th>状态</th>
                            <th>排序</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($prizes)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px; color: #999;">
                                    暂无奖品，添加第一个奖品吧！
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($prizes as $prize): ?>
                                <tr>
                                    <td><?php echo $prize['id']; ?></td>
                                    <td>
                                        <?php if (!empty($prize['image'])): ?>
                                            <img src="../../<?php echo htmlspecialchars($prize['image']); ?>" class="prize-image">
                                        <?php else: ?>
                                            <span style="color: #ccc;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($prize['name']); ?></td>
                                    <td>
                                        <?php 
                                        $typeLabels = [
                                            'product' => '商品',
                                            'coupon' => '优惠券',
                                            'points' => '积分',
                                            'thank_you' => '谢谢参与'
                                        ];
                                        echo $typeLabels[$prize['type']] ?? $prize['type'];
                                        ?>
                                    </td>
                                    <td><?php echo ($prize['probability'] * 100); ?>%</td>
                                    <td><?php echo $prize['stock']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $prize['status'] ? 'badge-active' : 'badge-inactive'; ?>">
                                            <?php echo $prize['status'] ? '启用' : '禁用'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $prize['sort_order']; ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="?edit=<?php echo $prize['id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i> 编辑</a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="id" value="<?php echo $prize['id']; ?>">
                                                <button type="submit" name="delete_prize" class="btn btn-sm btn-outline" onclick="return confirm('确定要删除这个奖品吗？');" style="color: #ff4444;">
                                                    <i class="fas fa-trash"></i> 删除
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
<?php
/**
 * 后台 - 商品抽奖奖品管理
 * 每个商品可设置独立奖品
 */
require_once '../includes/auth_check.php';
require_once '../config.php';

$page_title = '商品抽奖奖品管理';
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

    // 处理添加/编辑奖品
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add' || $action === 'edit') {
            $name = trim($_POST['name'] ?? '');
            $image = trim($_POST['image'] ?? '');
            $probability = floatval($_POST['probability'] ?? 0);
            $stock = intval($_POST['stock'] ?? 0);
            $sort_order = intval($_POST['sort_order'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $type = trim($_POST['type'] ?? 'product');
            $status = isset($_POST['status']) ? 1 : 0;

            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO lottery_prizes (product_id, name, image, probability, stock, sort_order, description, type, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$product_id, $name, $image, $probability, $stock, $sort_order, $description, $type, $status]);
                $msg = '奖品添加成功';
            } else {
                $prize_id = intval($_POST['prize_id'] ?? 0);
                $stmt = $db->prepare("UPDATE lottery_prizes SET name=?, image=?, probability=?, stock=?, sort_order=?, description=?, type=?, status=? WHERE id=? AND product_id=?");
                $stmt->execute([$name, $image, $probability, $stock, $sort_order, $description, $type, $status, $prize_id, $product_id]);
                $msg = '奖品更新成功';
            }
        } elseif ($action === 'delete') {
            $prize_id = intval($_POST['prize_id'] ?? 0);
            $db->prepare("DELETE FROM lottery_prizes WHERE id=? AND product_id=?")->execute([$prize_id, $product_id]);
            $msg = '奖品已删除';
        } elseif ($action === 'toggle_lottery') {
            $is_lottery = isset($_POST['is_lottery']) ? 1 : 0;
            $db->prepare("UPDATE products SET is_lottery=? WHERE id=?")->execute([$is_lottery, $product_id]);
            $msg = $is_lottery ? '已开启抽奖' : '已关闭抽奖';
        }
    }

    // 获取商品信息
    $stmt = $db->prepare("SELECT id, name, is_lottery FROM products WHERE id=?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        die("商品不存在");
    }

    // 获取奖品列表
    $stmt = $db->prepare("SELECT * FROM lottery_prizes WHERE product_id=? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$product_id]);
    $prizes = $stmt->fetchAll();

    // 编辑时获取奖品信息
    $edit_prize = null;
    if (isset($_GET['edit'])) {
        $edit_id = intval($_GET['edit']);
        $stmt = $db->prepare("SELECT * FROM lottery_prizes WHERE id=? AND product_id=?");
        $stmt->execute([$edit_id, $product_id]);
        $edit_prize = $stmt->fetch();
    }

} catch (PDOException $e) {
    die("数据库错误: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $page_title; ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: -apple-system, sans-serif; background: #f0f5ff; color: #1e293b; padding: 20px; }
.container { max-width: 900px; margin: 0 auto; }
h1 { font-size: 22px; margin-bottom: 20px; color: #1e40af; }
.card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(59,130,246,0.06); }
.card h2 { font-size: 16px; margin-bottom: 15px; color: #1e293b; }
.form-row { display: flex; gap: 15px; margin-bottom: 12px; flex-wrap: wrap; }
.form-group { flex: 1; min-width: 150px; }
.form-group label { display: block; font-size: 13px; color: #475569; margin-bottom: 5px; font-weight: 500; }
.form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px;
    font-size: 14px; outline: none; transition: border-color 0.2s;
}
.form-group input:focus, .form-group select:focus { border-color: #3b82f6; }
.btn {
    padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer;
    font-size: 14px; font-weight: 500; transition: all 0.2s;
}
.btn-primary { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; }
.btn-primary:hover { opacity: 0.9; }
.btn-danger { background: #ef4444; color: #fff; }
.btn-secondary { background: #e2e8f0; color: #475569; }
.btn-sm { padding: 5px 12px; font-size: 12px; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
th { background: #f8fafc; color: #475569; font-weight: 600; }
tr:hover { background: #f8fafc; }
.badge { padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.badge-on { background: #dcfce7; color: #16a34a; }
.badge-off { background: #fee2e2; color: #dc2626; }
.msg { padding: 10px 15px; background: #dcfce7; color: #16a34a; border-radius: 8px; margin-bottom: 15px; font-size: 14px; }
.toggle-row { display: flex; align-items: center; gap: 10px; }
.toggle-row input[type="checkbox"] { width: 18px; height: 18px; }
.back-link { color: #3b82f6; text-decoration: none; font-size: 14px; margin-bottom: 15px; display: inline-block; }
.back-link:hover { text-decoration: underline; }
</style>
  <link href="./res/admin-white.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <a href="products.php" class="back-link">← 返回商品列表</a>
    <h1>抽奖奖品管理 - <?php echo htmlspecialchars($product['name']); ?></h1>

    <?php if (isset($msg)): ?>
    <div class="msg"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>抽奖开关</h2>
        <form method="post">
            <input type="hidden" name="action" value="toggle_lottery">
            <div class="toggle-row">
                <label style="font-size:14px;">
                    <input type="checkbox" name="is_lottery" <?php echo $product['is_lottery'] ? 'checked' : ''; ?>>
                    开启该商品的抽奖功能（下单后弹出大转盘抽奖）
                </label>
                <button type="submit" class="btn btn-primary btn-sm">保存</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2><?php echo $edit_prize ? '编辑奖品' : '添加奖品'; ?></h2>
        <form method="post">
            <input type="hidden" name="action" value="<?php echo $edit_prize ? 'edit' : 'add'; ?>">
            <?php if ($edit_prize): ?>
            <input type="hidden" name="prize_id" value="<?php echo $edit_prize['id']; ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>奖品名称 *</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($edit_prize['name'] ?? ''); ?>" placeholder="如：皮肤、点券、优惠券">
                </div>
                <div class="form-group">
                    <label>奖品类型</label>
                    <select name="type">
                        <option value="product" <?php echo ($edit_prize['type'] ?? '') === 'product' ? 'selected' : ''; ?>>实物/商品</option>
                        <option value="virtual" <?php echo ($edit_prize['type'] ?? '') === 'virtual' ? 'selected' : ''; ?>>虚拟物品</option>
                        <option value="coupon" <?php echo ($edit_prize['type'] ?? '') === 'coupon' ? 'selected' : ''; ?>>优惠券</option>
                        <option value="thank" <?php echo ($edit_prize['type'] ?? '') === 'thank' ? 'selected' : ''; ?>>谢谢参与</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>中奖概率（0-100，总和建议100）</label>
                    <input type="number" name="probability" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars($edit_prize['probability'] ?? '10'); ?>">
                </div>
                <div class="form-group">
                    <label>库存（0=不限）</label>
                    <input type="number" name="stock" min="0" value="<?php echo htmlspecialchars($edit_prize['stock'] ?? '0'); ?>">
                </div>
                <div class="form-group">
                    <label>排序（越小越靠前）</label>
                    <input type="number" name="sort_order" value="<?php echo htmlspecialchars($edit_prize['sort_order'] ?? '0'); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>奖品图片路径（上传后填路径，如 assets/uploads/prize.jpg）</label>
                    <input type="text" name="image" value="<?php echo htmlspecialchars($edit_prize['image'] ?? ''); ?>" placeholder="留空显示默认图标">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>奖品描述</label>
                    <textarea name="description" rows="2" placeholder="奖品说明"><?php echo htmlspecialchars($edit_prize['description'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="status" <?php echo !isset($edit_prize) || $edit_prize['status'] ? 'checked' : ''; ?>>
                        启用该奖品
                    </label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo $edit_prize ? '更新奖品' : '添加奖品'; ?></button>
            <?php if ($edit_prize): ?>
            <a href="?product_id=<?php echo $product_id; ?>" class="btn btn-secondary">取消编辑</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h2>奖品列表（共 <?php echo count($prizes); ?> 个）</h2>
        <?php if (empty($prizes)): ?>
        <p style="color:#94a3b8;font-size:14px;">暂无奖品，请添加</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>名称</th><th>类型</th><th>概率</th><th>库存</th><th>排序</th><th>状态</th><th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prizes as $p): ?>
                <tr>
                    <td><?php echo $p['id']; ?></td>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><?php echo $p['type']; ?></td>
                    <td><?php echo $p['probability']; ?>%</td>
                    <td><?php echo $p['stock']; ?></td>
                    <td><?php echo $p['sort_order']; ?></td>
                    <td><span class="badge <?php echo $p['status'] ? 'badge-on' : 'badge-off'; ?>"><?php echo $p['status'] ? '启用' : '禁用'; ?></span></td>
                    <td>
                        <a href="?product_id=<?php echo $product_id; ?>&edit=<?php echo $p['id']; ?>" class="btn btn-secondary btn-sm">编辑</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('确定删除？')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="prize_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

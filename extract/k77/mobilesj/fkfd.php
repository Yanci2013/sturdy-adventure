<?php
require_once 'header.php';

// 检查商家是否登录
if (empty($_SESSION['partner_id'])) {
    header("Location: partner_login.php");
    exit;
}

// 获取参数
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
$partner_id_param = $_GET['partner_id'] ?? '';
$hunter_id = filter_input(INPUT_GET, 'hunter_id', FILTER_VALIDATE_INT);

// 验证参数有效性
if (!$order_id || !$partner_id_param || !$hunter_id) {
    $_SESSION['error_msg'] = "参数不完整";
    header("Location: orders.php");
    exit;
}

// 验证当前用户是否为发单商家
if ($_SESSION['partner_id'] != $partner_id_param) {
    $_SESSION['error_msg'] = "无权操作此订单";
    header("Location: orders.php");
    exit;
}

// 获取当前商家ID
$partner_id = $_SESSION['partner_id'];

// 检查罚单状态，阻止非closed状态的重复罚单
try {
    $checkPenaltyStmt = $pdo->prepare("SELECT id, status FROM penalty_records WHERE order_no = ? ORDER BY id DESC LIMIT 1");
    $checkPenaltyStmt->execute([$order_id]);
    $existing_penalty = $checkPenaltyStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_penalty && $existing_penalty['status'] != 'closed') {
        $_SESSION['error_msg'] = "该订单已有罚单记录，不能重复开罚单";
        header("Location: order_view.php?id=$order_id");
        exit;
    }
} catch (PDOException $e) {
    error_log("检查罚单记录失败: " . $e->getMessage());
    $_SESSION['error_msg'] = "系统错误，请稍后再试";
    header("Location: order_view.php?id=$order_id");
    exit;
}

// 处理罚单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_penalty'])) {
    try {
        // 获取表单数据
        $reason_id = filter_input(INPUT_POST, 'penalty_reason', FILTER_VALIDATE_INT);
        $custom_reason = filter_input(INPUT_POST, 'custom_reason', FILTER_SANITIZE_STRING);
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        
        // 验证数据
        if (!$reason_id || $reason_id < 1) {
            throw new Exception("请选择有效的罚款原因");
        }
        
        if (!$amount || $amount <= 0) {
            throw new Exception("罚款金额必须大于0");
        }
        
        // 如果选择了自定义原因，先创建自定义原因记录
        $final_reason_id = $reason_id;
        if ($reason_id == 999 && !empty($custom_reason)) {
            // 插入自定义原因到penalty_reasons表
            $insert_reason_sql = "INSERT INTO penalty_reasons (reason_name, is_default, created_at, updated_at) 
                                  VALUES (?, 0, NOW(), NOW())";
            $insert_reason_stmt = $pdo->prepare($insert_reason_sql);
            $insert_reason_stmt->execute([$custom_reason]);
            $final_reason_id = $pdo->lastInsertId();
        } else {
            // 检查罚款原因是否存在
            $stmt = $pdo->prepare("SELECT id FROM penalty_reasons WHERE id = ?");
            $stmt->execute([$reason_id]);
            if (!$stmt->fetch()) {
                throw new Exception("选择的罚款原因不存在");
            }
            $final_reason_id = $reason_id;
        }
        
        // 处理多图上传
        $evidence_images = [];
        if (!empty($_FILES['evidence_images']['name'][0])) {
            $upload_dir = '../assets/images/penalty_evidence/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // 处理多文件上传
            $file_count = count($_FILES['evidence_images']['name']);
            if ($file_count > 10) {
                throw new Exception("最多只能上传10张图片");
            }
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['evidence_images']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                // 检查文件类型
                $file_name = time() . '_' . $i . '_' . basename($_FILES['evidence_images']['name'][$i]);
                $target_file = $upload_dir . $file_name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($imageFileType, $allowed_types)) {
                    throw new Exception("只允许上传JPG, JPEG, PNG和GIF格式的图片");
                }
                
                // 检查文件大小 (最大2MB)
                if ($_FILES['evidence_images']['size'][$i] > 2000000) {
                    throw new Exception("图片大小不能超过2MB");
                }
                
                // 移动上传的文件
                if (move_uploaded_file($_FILES['evidence_images']['tmp_name'][$i], $target_file)) {
                    $evidence_images[] = $file_name;
                }
            }
        }
        
        // 开始事务
        $pdo->beginTransaction();
        
        try {
            // 获取打手用户信息，检查余额
            $hunter_stmt = $pdo->prepare("SELECT id, balance, deposit FROM users WHERE id = ?");
            $hunter_stmt->execute([$hunter_id]);
            $hunter = $hunter_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$hunter) {
                throw new Exception("打手用户不存在");
            }
            
            // 验证罚款金额不能超过打手余额
            if ($amount > $hunter['balance']) {
                throw new Exception("罚款金额不能超过打手余额 (¥" . number_format($hunter['balance'], 2) . ")");
            }
            
            // 创建罚单记录 - 直接设置为completed状态
            $penalty_sql = "INSERT INTO penalty_records 
                            (initiator_id, handler_id, reason_id, penalized_id, amount, evidence_image, status, created_at, updated_at, order_no, deduction_balance, status_time) 
                            VALUES 
                            (:initiator, :handler, :reason, :penalized, :amount, :image, 'completed', NOW(), NOW(), :order_no, :deduction_balance, NOW())";
            
            $penalty_stmt = $pdo->prepare($penalty_sql);
            $result = $penalty_stmt->execute([
                ':initiator' => $partner_id,
                ':handler' => 1, // 默认处理人ID为1
                ':reason' => $final_reason_id,
                ':penalized' => $hunter_id,
                ':amount' => $amount,
                ':image' => !empty($evidence_images) ? implode(',', $evidence_images) : null,
                ':order_no' => $order_id,
                ':deduction_balance' => $amount
            ]);
            
            if (!$result) {
                $errorInfo = $penalty_stmt->errorInfo();
                throw new Exception("数据库插入失败: " . $errorInfo[2]);
            }
            
            $penalty_id = $pdo->lastInsertId();
            
            // 扣除打手余额
            $update_balance_sql = "UPDATE users SET balance = balance - :amount WHERE id = :hunter_id";
            $update_balance_stmt = $pdo->prepare($update_balance_sql);
            $update_balance_stmt->execute([
                ':amount' => $amount,
                ':hunter_id' => $hunter_id
            ]);
            
            // 记录资金明细到fund_details表
            $fund_sql = "INSERT INTO fund_details 
                        (user_id, source, destination, amount, related_id, note, create_time, order_no)
                        VALUES 
                        (:user_id, 'penalty', 'platform', :amount, :penalty_id, '罚款扣除', NOW(), :order_no)";
            $fund_stmt = $pdo->prepare($fund_sql);
            $fund_stmt->execute([
                ':user_id' => $hunter_id,
                ':amount' => -$amount,
                ':penalty_id' => $penalty_id,
                ':order_no' => $order_id
            ]);
            
            // 提交事务
            $pdo->commit();
            
            $_SESSION['success_msg'] = "罚单已成功开出，已扣除打手余额 ¥" . number_format($amount, 2);
            header("Location: order_view.php?id=$order_id");
            exit;
            
        } catch (Exception $e) {
            // 回滚事务
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $_SESSION['error_msg'] = "开罚单失败: " . $e->getMessage();
    }
}

// 获取罚款原因列表
try {
    $reasons_stmt = $pdo->query("SELECT id, reason_name FROM penalty_reasons ORDER BY id ASC");
    $penalty_reasons = $reasons_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("数据库错误: " . $e->getMessage());
    $_SESSION['error_msg'] = "获取罚款原因失败";
    header("Location: order_view.php?id=$order_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>开罚单 - 管理后台</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: "Microsoft YaHei", sans-serif;
        }
        
        .penalty-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .penalty-header {
            padding-bottom: 15px;
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .penalty-header h3 {
            font-size: 22px;
            color: #333;
            font-weight: 500;
        }
        
        .penalty-header p {
            color: #6c757d;
            margin-top: 5px;
        }
        
        /* 图片上传区域样式 */
        .upload-area {
            border: 2px dashed #dee2e6;
            padding: 20px;
            text-align: center;
            margin-bottom: 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-area:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        
        .upload-icon {
            font-size: 48px;
            color: #0d6efd;
            margin-bottom: 10px;
        }
        
        .upload-text {
            color: #495057;
        }
        
        .preview-container {
            margin-top: 15px;
        }
        
        .preview-item {
            position: relative;
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .preview-img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        .preview-remove {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #dc3545;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            text-align: center;
            line-height: 20px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .form-label {
            font-weight: 500;
        }
    </style>
    <link rel="stylesheet" href="../assets/css/theme-v2.css">
</head>
<body>
<!-- V2 加载动画 -->
<div class="v2-loader-overlay" id="v2Loader">
    <div class="v2-loader-logo-wrap">
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-logo">小</div>
    </div>
    <div class="v2-loader-text">
        <span>加</span><span>载</span><span>中</span><span>...</span>
    </div>
    <div class="v2-loader-progress">
        <div class="v2-loader-progress-bar"></div>
    </div>
</div>

    <div class="container">
        <div class="penalty-container">
            <div class="penalty-header">
                <h3><i class="bi bi-flag"></i> 开罚单</h3>
                <p>订单编号: <?php echo htmlspecialchars($order_id); ?></p>
            </div>
            
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error_msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_msg']); ?>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="penalty_reason" class="form-label">罚款原因</label>
                    <select class="form-select" id="penalty_reason" name="penalty_reason" required>
                        <option value="">-- 请选择罚款原因 --</option>
                        <?php foreach ($penalty_reasons as $reason): ?>
                            <option value="<?php echo $reason['id']; ?>"><?php echo htmlspecialchars($reason['reason_name']); ?></option>
                        <?php endforeach; ?>
                        <option value="999">-- 自定义原因 --</option>
                    </select>
                </div>
                
                <div class="mb-3" id="customReasonDiv" style="display: none;">
                    <label for="custom_reason" class="form-label">自定义原因</label>
                    <input type="text" class="form-control" id="custom_reason" name="custom_reason" 
                           placeholder="请输入自定义罚款原因">
                </div>
                
                <div class="mb-3">
                    <label for="amount" class="form-label">罚款金额</label>
                    <div class="input-group">
                        <span class="input-group-text">¥</span>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               min="0.01" step="0.01" placeholder="请输入罚款金额" required>
                    </div>
                    <div class="form-text">请输入大于0的金额，不能超过打手余额</div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">凭证图片</label>
                    <div class="upload-area" id="uploadArea">
                        <div class="upload-icon">
                            <i class="bi bi-cloud-arrow-up"></i>
                        </div>
                        <div class="upload-text">
                            点击或拖拽文件到此处上传<br>
                            <span class="text-muted">支持JPG, PNG格式，每张不超过2MB，最多10张</span>
                        </div>
                        <input type="file" name="evidence_images[]" id="fileInput" multiple accept="image/*" class="d-none">
                    </div>
                    
                    <div class="preview-container" id="previewContainer"></div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="order_view.php?id=<?php echo $order_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> 返回
                    </a>
                    <button type="submit" class="btn btn-primary" name="submit_penalty">
                        <i class="bi bi-check-circle"></i> 提交罚单
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script>
        // 图片上传处理
        document.getElementById('uploadArea').addEventListener('click', function() {
            document.getElementById('fileInput').click();
        });
        
        // 图片预览
        document.getElementById('fileInput').addEventListener('change', function(e) {
            var files = e.target.files;
            if(!files || files.length === 0) return;
            
            if(files.length > 10) {
                alert('最多只能上传10张图片');
                this.value = '';
                return;
            }
            
            document.getElementById('previewContainer').innerHTML = '';
            
            for(var i = 0; i < files.length; i++) {
                var file = files[i];
                
                if(!file.type.match('image.*')) {
                    alert('请选择图片文件: ' + file.name);
                    continue;
                }
                
                if(file.size > 2 * 1024 * 1024) {
                    alert('图片大小不能超过2MB');
                    continue;
                }
                
                var reader = new FileReader();
                reader.onload = (function(file) {
                    return function(e) {
                        var html = '<div class="preview-item">';
                        html += '<img src="'+ e.target.result +'" class="preview-img" title="'+file.name+'">';
                        html += '<span class="preview-remove" onclick="removeImage(this)">×</span>';
                        html += '</div>';
                        
                        document.getElementById('previewContainer').insertAdjacentHTML('beforeend', html);
                    };
                })(file);
                reader.readAsDataURL(file);
            }
        });
        
        // 移除图片
        function removeImage(element) {
            var previewItem = element.parentNode;
            previewItem.parentNode.removeChild(previewItem);
            
            // 更新文件输入框
            var fileInput = document.getElementById('fileInput');
            var files = Array.from(fileInput.files);
            var previewItems = document.querySelectorAll('.preview-item');
            var dataTransfer = new DataTransfer();
            
            // 重新构建文件列表
            previewItems.forEach(function(item, index) {
                var fileName = item.querySelector('img').title;
                var file = Array.from(fileInput.files).find(f => f.name === fileName);
                if(file) {
                    dataTransfer.items.add(file);
                }
            });
            
            fileInput.files = dataTransfer.files;
        }
        
        // 监听罚款原因选择变化
        document.getElementById('penalty_reason').addEventListener('change', function() {
            var customReasonDiv = document.getElementById('customReasonDiv');
            if (this.value == '999') {
                customReasonDiv.style.display = 'block';
                document.getElementById('custom_reason').required = true;
            } else {
                customReasonDiv.style.display = 'none';
                document.getElementById('custom_reason').required = false;
            }
        });
        
        // 表单验证
        document.querySelector('form').addEventListener('submit', function(e) {
            var reason = document.getElementById('penalty_reason').value;
            var amount = document.getElementById('amount').value;
            var customReason = document.getElementById('custom_reason').value;
            
            if(!reason) {
                alert('请选择罚款原因');
                e.preventDefault();
                return;
            }
            
            if(reason == '999' && !customReason.trim()) {
                alert('请输入自定义罚款原因');
                e.preventDefault();
                return;
            }
            
            if(!amount || isNaN(amount) || amount <= 0) {
                alert('请输入有效的金额');
                e.preventDefault();
                return;
            }
        });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>

<?php require_once 'footer.php'; ?>
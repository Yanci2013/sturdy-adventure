<?php
ob_start();
require_once __DIR__.'/../../includes/common.php';

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $config = $pdo->query("SELECT * FROM sys_notification_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'code' => 0,
                'data' => $config ?: null
            ]);
            exit;
            
        } catch (PDOException $e) {
            echo json_encode(['code' => 500, 'msg' => '获取配置失败: '.$e->getMessage()]);
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['code' => 405, 'msg' => '只支持POST请求']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        echo json_encode(['code' => 400, 'msg' => '无效的JSON数据']);
        exit;
    }

    try {
        $exists = $pdo->query("SELECT id FROM sys_notification_config LIMIT 1")->fetchColumn();
        
        $data = [
            ':sms_provider' => $input['sms_provider'] ?? null,
            ':sms_app_id' => $input['app_id'] ?? null,
            ':sms_app_key' => $input['app_key'] ?? null,
            ':sms_sign' => $input['sms_sign'] ?? null,
            ':sms_content' => $input['sms_content'] ?? null,
            ':order_accept_template' => $input['order_accept_template'] ?? '',
            ':order_complete_template' => $input['order_complete_template'] ?? '',
            ':smtp_host' => $input['smtp_host'] ?? null,
            ':smtp_port' => $input['smtp_port'] ?? null,
            ':from_email' => $input['from_email'] ?? null,
            ':email_password' => $input['email_password'] ?? null,
            ':from_name' => $input['from_name'] ?? '',
            ':smtp_secure' => $input['smtp_secure'] ?? 'ssl'
        ];
        
        if ($exists) {
            $stmt = $pdo->prepare("
                UPDATE sys_notification_config SET
                    sms_provider = :sms_provider,
                    sms_app_id = :sms_app_id,
                    sms_app_key = :sms_app_key,
                    sms_sign = :sms_sign,
                    sms_content = :sms_content,
                    order_accept_template = :order_accept_template,
                    order_complete_template = :order_complete_template,
                    smtp_host = :smtp_host,
                    smtp_port = :smtp_port,
                    from_email = :from_email,
                    email_password = :email_password,
                    from_name = :from_name,
                    smtp_secure = :smtp_secure,
                    update_time = NOW()
                WHERE id = :id
            ");
            
            $data[':id'] = $exists;
            $stmt->execute($data);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO sys_notification_config (
                    sms_provider, sms_app_id, sms_app_key, sms_sign, sms_content,
                    order_accept_template, order_complete_template,
                    smtp_host, smtp_port, from_email, email_password,
                    from_name, smtp_secure, create_time, update_time
                ) VALUES (
                    :sms_provider, :sms_app_id, :sms_app_key, :sms_sign, :sms_content,
                    :order_accept_template, :order_complete_template,
                    :smtp_host, :smtp_port, :from_email, :email_password,
                    :from_name, :smtp_secure, NOW(), NOW()
                )
            ");
            
            $stmt->execute($data);
        }
        
        echo json_encode(['code' => 0, 'msg' => '配置保存成功']);
        exit;
        
    } catch (PDOException $e) {
        error_log('数据库错误: ' . $e->getMessage());
        echo json_encode(['code' => 500, 'msg' => '服务器内部错误']);
        exit;
    } catch (Exception $e) {
        error_log('系统错误: ' . $e->getMessage());
        echo json_encode(['code' => 500, 'msg' => '服务器内部错误']);
        exit;
    }
}

$pageTitle = '短信与邮箱配置';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Ma+Shan+Zheng&family=Noto+Serif+SC:wght@400;500;600;700&family=ZCOOL+XiaoWei&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
      --primary-teal: #3baaa3;
      --primary-teal-dark: #2a8a84;
      --primary-teal-light: #5bc4bd;
      --scroll-paper: #fdf8e8;
      --scroll-paper-dark: #f5ecd8;
      --gold: #c9a45c;
      --gold-dark: #a8843d;
      --gold-light: #e0c078;
    }
    * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Noto Serif SC', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f5f5 0%, var(--scroll-paper) 100%);
            color: #333;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            padding: 12px;
            padding-bottom: 80px;
        }

        .app-container {
            max-width: 100%;
            margin: 0 auto;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            padding: 16px 12px;
            background: #ffffff;
            margin: -12px -12px 12px -12px;
            border-radius: 0 0 16px 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .header-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--gold-dark);
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .card-header {
            font-size: 16px;
            font-weight: 600;
            color: var(--gold-dark);
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--scroll-paper-dark);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--scroll-paper-dark);
            border-radius: 12px;
            font-size: 14px;
            color: #333;
            background: #ffffff;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(0,0,0,0.1);
        }

        .form-input::placeholder {
            color: #cccccc;
        }

        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--scroll-paper-dark);
            border-radius: 12px;
            font-size: 14px;
            color: #333;
            background: #ffffff;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(0,0,0,0.1);
        }

        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--scroll-paper-dark);
            border-radius: 12px;
            font-size: 14px;
            color: #333;
            background: #ffffff;
            min-height: 100px;
            resize: vertical;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(0,0,0,0.1);
        }

        .form-textarea::placeholder {
            color: #cccccc;
        }

        .form-hint {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-teal) 0%, var(--primary-teal-dark) 100%);
            color: #ffffff;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-teal-light) 0%, var(--primary-teal) 100%);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #f5f5f5 0%, var(--scroll-paper) 100%);
            color: #666;
        }

        .btn-secondary:hover {
            background: #e8e8e8;
            transform: translateY(-2px);
        }

        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert-info {
            background: #f2f2f2;
            color: #666;
            border: 1px solid var(--scroll-paper-dark);
        }

        .alert a {
            color: #dc2626;
            text-decoration: underline;
            font-weight: 600;
        }

        .alert a:hover {
            color: #b91c1c;
        }

        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #dddddd;
            border-radius: 2px;
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .card {
                padding: 14px;
            }

            .header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .form-input,
            .form-select,
            .form-textarea {
                padding: 10px 14px;
                font-size: 13px;
            }

            .btn {
                padding: 10px 20px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1 class="header-title">📱 短信与邮箱配置</h1>
    </header>

    <main class="app-container">
        <div class="card">
            <div class="card-header">短信接口设置</div>
            
            <div class="alert alert-info">
                还没有短信宝账号？<a href="http://www.smsbao.com/reg?r=HKGR" target="_blank">立即注册</a>，获取短信接口服务
            </div>
            
            <form id="sms-form">
                <div class="form-group">
                    <label class="form-label">接口选择</label>
                    <select name="sms_provider" class="form-select">
                        <option value="smsbao">短信宝短信接口</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">AppId</label>
                    <input type="text" name="app_id" placeholder="请输入AppId" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">AppKey</label>
                    <input type="password" name="app_key" placeholder="请输入AppKey" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">短信签名内容</label>
                    <input type="text" name="sms_sign" placeholder="请输入短信签名" class="form-input" required>
                    <div class="form-hint">必须是已添加、并通过审核的短信签名。</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">用户绑定手机号模版</label>
                    <textarea name="sms_content" placeholder="请输入短信内容（不含签名，验证码用{code}代替）" class="form-textarea"></textarea>
                    <div class="form-hint">实际发送内容示例：验证码{code}</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">打手接单通知模版ID</label>
                    <input type="text" name="order_accept_template" placeholder="请输入模板ID" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">订单完成通知ID</label>
                    <input type="text" name="order_complete_template" placeholder="请输入模板ID" class="form-input">
                </div>
                
                <div class="form-group">
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">保存短信配置</button>
                    </div>
                </div>
            </form>
            
            <div class="alert alert-info" style="margin-top: 16px;">
                短信宝提供稳定可靠的短信服务，<a href="http://www.smsbao.com/reg?r=HKGR" target="_blank">立即注册</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">邮箱发件配置</div>
            
            <form id="email-form">
                <div class="form-group">
                    <label class="form-label">SMTP服务器</label>
                    <input type="text" name="smtp_host" placeholder="例如：smtp.163.com" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">SMTP端口</label>
                    <input type="number" name="smtp_port" placeholder="例如：465" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">发件邮箱</label>
                    <input type="email" name="from_email" placeholder="请输入发件邮箱地址" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">邮箱密码</label>
                    <input type="password" name="email_password" placeholder="请输入邮箱密码或授权码" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">发件人名称</label>
                    <input type="text" name="from_name" placeholder="请输入发件人名称" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">安全协议</label>
                    <select name="smtp_secure" class="form-select">
                        <option value="ssl">SSL</option>
                        <option value="tls">TLS</option>
                        <option value="">无</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">保存邮箱配置</button>
                        <button type="button" id="test-email" class="btn btn-secondary">测试邮箱配置</button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(10px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 50 + index * 30);
        });

        function loadConfig() {
            fetch(window.location.href, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(res => {
                if(res.code === 0 && res.data) {
                    const smsData = {
                        sms_provider: res.data.sms_provider,
                        app_id: res.data.sms_app_id,
                        app_key: res.data.sms_app_key,
                        sms_sign: res.data.sms_sign,
                        sms_content: res.data.sms_content,
                        order_accept_template: res.data.order_accept_template,
                        order_complete_template: res.data.order_complete_template
                    };
                    
                    Object.keys(smsData).forEach(key => {
                        const input = document.querySelector(`#sms-form [name="${key}"]`);
                        if (input && smsData[key] !== null) {
                            input.value = smsData[key];
                        }
                    });
                    
                    const emailData = {
                        smtp_host: res.data.smtp_host,
                        smtp_port: res.data.smtp_port,
                        from_email: res.data.from_email,
                        email_password: res.data.email_password,
                        from_name: res.data.from_name,
                        smtp_secure: res.data.smtp_secure
                    };
                    
                    Object.keys(emailData).forEach(key => {
                        const input = document.querySelector(`#email-form [name="${key}"]`);
                        if (input && emailData[key] !== null) {
                            input.value = emailData[key];
                        }
                    });
                }
            })
            .catch(() => {
                console.log('加载配置失败');
            });
        }

        loadConfig();

        document.getElementById('sms-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(res => {
                if(res.code === 0) {
                    alert('短信配置保存成功');
                } else {
                    alert(res.msg || '保存失败');
                }
            })
            .catch(() => {
                alert('请求失败，请检查网络');
            });
        });

        document.getElementById('email-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(res => {
                if(res.code === 0) {
                    alert('邮箱配置保存成功');
                } else {
                    alert(res.msg || '保存失败');
                }
            })
            .catch(() => {
                alert('请求失败，请检查网络');
            });
        });

        document.getElementById('test-email').addEventListener('click', function() {
            const testEmail = prompt('请输入测试邮箱地址');
            if (!testEmail) {
                return;
            }
            
            alert('测试邮箱功能需要后端支持');
        });
    });
    </script>
</body>
</html>

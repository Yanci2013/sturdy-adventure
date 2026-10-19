<?php
// 默认标题和Logo
$default_logo = '../assets/images/小熠电竞.png';
$default_site_title = "广州小熠科技网络";
$site_logo = $default_logo;
$site_title = $default_site_title;

// 加载配置数据库
$config_path = __DIR__ . '/config.php';
if (file_exists($config_path)) {
    require_once $config_path;
} elseif (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    die('缺少config配置文件');
}

$set = [];

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $stmt = $db->query("SELECT site_logo, site_title FROM settings WHERE id=1");
    $result = $stmt->fetch();
    if ($result) {
        $set = $result;
    }
} catch (Exception $e) {
    error_log("读取站点配置失败：" . $e->getMessage());
}

if (!empty($set['site_title'])) {
    $site_title = $set['site_title'];
}
if (!empty($set['site_logo'])) {
    $logo_path = $set['site_logo'];
    if (!preg_match('/^https?:\/\//i', $logo_path) && strpos($logo_path, '/') !== 0) {
        $site_logo = '../' . ltrim($logo_path, './');
    } else {
        $site_logo = $logo_path;
    }
}

function simpleMarkdownToHtml($text) {
    $lines = explode("\n", $text);
    $html = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $html .= "<br>";
        } elseif (preg_match('/^### (.+)/', $trimmed, $m)) {
            $html .= "<h4>" . htmlspecialchars($m[1]) . "</h4>";
        } elseif (preg_match('/^## (.+)/', $trimmed, $m)) {
            $html .= "<h3>" . htmlspecialchars($m[1]) . "</h3>";
        } elseif (preg_match('/^# (.+)/', $trimmed, $m)) {
            $html .= "<h2>" . htmlspecialchars($m[1]) . "</h2>";
        } else {
            $html .= "<p>" . htmlspecialchars($trimmed) . "</p>";
        }
    }
    return $html;
}

$user_agreement_html = simpleMarkdownToHtml(<<<AGREE
# 用户服务协议
## 一、平台说明
广州小熠科技网络系统为电竞兼职服务平台，所有注册、使用平台服务的用户均需完全认可本协议全部条款，注册登录即代表自愿接受约束。

## 二、用户资格
1. 用户必须年满18周岁；未满18周岁需监护人书面授权后方可使用。
2. 禁止使用他人身份、手机号、社交账号冒用注册。
3. 账号仅限本人使用，禁止租借、售卖、共享账号。

## 三、账号规范
1. 用户需妥善保管账号密码，账号产生一切行为由账号持有人承担全部责任。
2. 禁止利用账号发布违法、色情、暴力、引流类信息。
3. 违规账号平台有权限制功能、永久封禁并清空收益。

## 四、平台服务规则
1. 平台提供游戏陪玩、打手接单等中介服务，所有交易仅允许平台内完成。
2. 禁止私下添加客户微信/QQ线下交易，一经查实永久封号。
3. 订单纠纷由平台客服依据订单记录判定责任。

## 五、收益与结算
1. 平台按照公示比例抽取服务费，每日统一结算订单收益。
2. 出现客诉、跑单、作弊行为，平台可扣除对应赔付金额。
3. 提现仅支持平台正规渠道，不支持第三方代提。

## 六、免责声明
1. 用户自身操作泄露账号导致损失，平台不承担赔偿。
2. 因网络、服务器临时维护造成短暂无法访问，不做赔付。
3. 用户违反法律法规产生责任，全部由用户自行承担。
AGREE);

$privacy_html = simpleMarkdownToHtml(<<<PRIVACY
# 隐私政策
## 一、信息收集说明
平台仅收集账号注册、接单、提现必需信息，不会额外收集无关个人隐私。
收集内容包含昵称、微信号、城市、年龄、游戏擅长类目，仅用于身份核验与订单匹配。

## 二、信息使用范围
1. 仅用于平台账号审核、订单对接、收益发放。
2. 不会向第三方出售、出租、共享你的个人信息。
3. 客服处理纠纷时可调取必要聊天、订单记录作为凭证。

## 三、信息存储安全
平台采用加密存储用户数据，后台管理员分级权限查看，防止信息泄露。

## 四、权限授权说明
使用平台需允许基础设备信息、网络权限，仅用于正常页面加载、消息推送，无恶意采集行为。

## 五、信息删除与账号注销
用户可联系管理员申请注销账号，注销后个人信息将定时清理，无法恢复。
PRIVACY);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($site_title); ?></title>
<meta name="renderer" content="webkit">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta http-equiv="Cache-Control" content="max-age=86400">
<meta name="theme-color" content="#1a1a2e">
<link rel="stylesheet" href="assets/css/theme-v2.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
html,body{height:100%;}
body{
    font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Helvetica Neue",sans-serif;
    background: linear-gradient(180deg, #eaf2ff 0%, #f5f9ff 30%, #f8fbff 100%);
    display:flex;
    flex-direction:column;
    align-items:center;
    padding:0 24px;
    padding-top:10vh;
    min-height:100vh;
    position:relative;
    overflow-x:hidden;
}
/* 背景装饰光晕 */
body::before{
    content:'';
    position:fixed;
    top:-20%;
    right:-20%;
    width:300px;
    height:300px;
    background: radial-gradient(circle, rgba(59,130,246,0.1) 0%, transparent 70%);
    border-radius:50%;
    pointer-events:none;
}
body::after{
    content:'';
    position:fixed;
    bottom:-10%;
    left:-15%;
    width:250px;
    height:250px;
    background: radial-gradient(circle, rgba(6,182,212,0.08) 0%, transparent 70%);
    border-radius:50%;
    pointer-events:none;
}
/* Logo区域 */
.logo-wrap{
    width:130px;height:130px;
    background: linear-gradient(135deg, rgba(59,130,246,0.1), rgba(6,182,212,0.05));
    border-radius:32px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-bottom:20px;
    border: 1.5px solid rgba(59,130,246,0.2);
    box-shadow: 0 8px 40px rgba(59,130,246,0.1), inset 0 1px 0 rgba(255,255,255,0.8);
    position:relative;
    animation: logoFloat 3s ease-in-out infinite;
}
@keyframes logoFloat{
    0%,100%{transform:translateY(0);}
    50%{transform:translateY(-6px);}
}
.logo-wrap::before{
    content:'';
    position:absolute;
    inset:-2px;
    border-radius:34px;
    background: linear-gradient(135deg, rgba(59,130,246,0.3), transparent 50%);
    z-index:-1;
    filter:blur(8px);
}
.logo-img{
    width:84px;height:84px;
    border-radius:20px;
    object-fit:cover;
    box-shadow: 0 4px 16px rgba(59,130,246,0.15);
}
/* 标题 */
.main-title{
    font-size:30px;
    font-weight:800;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #06b6d4 100%);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
    margin-bottom:28px;
    text-align:center;
    letter-spacing:2px;
}
/* 副标题 */
.sub-title{
    font-size:13px;
    color:#64748b;
    margin-bottom:28px;
    letter-spacing:4px;
    text-transform:uppercase;
}
/* 提示卡片 */
.tip-card{
    width:100%;
    background: #ffffff;
    border-radius:20px;
    padding:20px;
    border:1px solid rgba(59,130,246,0.1);
    box-shadow: 0 4px 20px rgba(59,130,246,0.08);
    margin-bottom:24px;
}
.tip-card p{
    font-size:14px;
    color:#334155;
    line-height:1.8;
    margin-bottom:8px;
    display:flex;
    align-items:flex-start;
    gap:6px;
}
.tip-card p:last-child{margin-bottom:0;}
.tip-card .star{
    color:#ef4444;
    flex-shrink:0;
    font-weight:bold;
}
.tip-card a{
    color:#2563eb;
    text-decoration:none;
    font-weight:600;
    border-bottom:1px dashed rgba(59,130,246,0.3);
}
/* 勾选行 */
.check-line{
    width:100%;
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:28px;
}
.check-line input[type="checkbox"]{
    display:none;
}
.check-line .checkbox-custom{
    width:24px;height:24px;
    border:2px solid #cbd5e1;
    border-radius:7px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
    transition:all 0.25s ease;
    cursor:pointer;
    background:#fff;
}
.check-line input:checked + .checkbox-custom{
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border-color:#3b82f6;
    box-shadow: 0 0 16px rgba(59,130,246,0.3);
}
.check-line input:checked + .checkbox-custom::after{
    content:'✓';
    color:#fff;
    font-size:14px;
    font-weight:bold;
}
.check-line label{
    font-size:14px;
    color:#475569;
    cursor:pointer;
    line-height:1.5;
}
/* 开始按钮 */
.start-btn{
    width:100%;
    height:56px;
    border-radius:16px;
    border:none;
    font-size:17px;
    font-weight:700;
    background: #e2e8f0;
    color:#94a3b8;
    cursor:not-allowed;
    transition:all 0.3s ease;
    letter-spacing:2px;
    border:1px solid #cbd5e1;
    position:relative;
    overflow:hidden;
}
.start-btn.active{
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color:#fff;
    cursor:pointer;
    box-shadow: 0 6px 24px rgba(59,130,246,0.35), 0 0 0 1px rgba(59,130,246,0.3);
    transform:translateY(0);
}
.start-btn.active:active{
    transform:translateY(1px) scale(0.98);
    box-shadow: 0 3px 12px rgba(59,130,246,0.3);
}
.start-btn.active::before{
    content:'';
    position:absolute;
    top:0;left:-100%;
    width:100%;height:100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: btnShine 2s infinite;
}
@keyframes btnShine{
    0%{left:-100%;}
    50%,100%{left:100%;}
}
/* 底部版权 */
.footer-text{
    margin-top:auto;
    padding:20px 0;
    font-size:11px;
    color:#94a3b8;
    text-align:center;
}
/* 弹窗样式 */
.modal-mask{
    position:fixed;top:0;left:0;right:0;bottom:0;
    background:rgba(15,23,42,0.5);
    z-index:9999;
    display:none;
    align-items:center;justify-content:center;
    padding:20px;
    animation: fadeIn 0.25s ease;
}
@keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
.modal-mask.show{display:flex;}
.modal-box{
    width:100%;max-width:500px;
    background: #ffffff;
    border-radius:24px;
    overflow:hidden;
    border:1px solid rgba(59,130,246,0.1);
    box-shadow: 0 20px 60px rgba(59,130,246,0.2);
    animation: modalPop 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes modalPop{
    from{transform:scale(0.9) translateY(20px);opacity:0;}
    to{transform:scale(1) translateY(0);opacity:1;}
}
.modal-head{
    padding:18px 22px;
    display:flex;justify-content:space-between;
    align-items:center;
    border-bottom:1px solid #e2e8f0;
    background: linear-gradient(135deg, #eff6ff, #f0f9ff);
}
.modal-head h3{
    font-size:18px;
    font-weight:700;
    color:#2563eb;
    letter-spacing:1px;
}
.modal-close{
    border:none;background:none;
    font-size:24px;color:#94a3b8;
    cursor:pointer;
    width:32px;height:32px;
    display:flex;align-items:center;justify-content:center;
    border-radius:50%;
    transition:all 0.2s;
}
.modal-close:active{
    background:#e2e8f0;
    color:#475569;
}
.modal-body{
    padding:22px;
    max-height:55vh;
    overflow-y:auto;
    font-size:14px;
    line-height:1.8;
    color:#334155;
}
.modal-body h2{
    color:#2563eb;
    font-size:18px;
    margin:16px 0 10px;
    font-weight:700;
}
.modal-body h2:first-child{margin-top:0;}
.modal-body h3{
    color:#1e40af;
    font-size:15px;
    margin:14px 0 8px;
    font-weight:600;
}
.modal-body p{margin-bottom:8px;}
.modal-foot{
    padding:16px;
    text-align:center;
    border-top:1px solid #e2e8f0;
}
.modal-foot button{
    padding:12px 48px;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border:none;
    border-radius:12px;
    font-size:15px;
    font-weight:700;
    color:#fff;
    cursor:pointer;
    box-shadow: 0 4px 16px rgba(59,130,246,0.3);
    transition:all 0.2s;
}
.modal-foot button:active{
    transform:scale(0.96);
}
</style>
</head>
<body>
<!-- 全新加载动画 -->
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
<!-- 弹窗 -->
<div class="modal-mask" id="userAgreeMask">
    <div class="modal-box">
        <div class="modal-head">
            <h3>用户服务协议</h3>
            <button class="modal-close" onclick="closeMask('userAgreeMask')">×</button>
        </div>
        <div class="modal-body"><?php echo $user_agreement_html; ?></div>
        <div class="modal-foot">
            <button onclick="closeMask('userAgreeMask')">我已阅读</button>
        </div>
    </div>
</div>
<div class="modal-mask" id="privacyMask">
    <div class="modal-box">
        <div class="modal-head">
            <h3>隐私政策</h3>
            <button class="modal-close" onclick="closeMask('privacyMask')">×</button>
        </div>
        <div class="modal-body"><?php echo $privacy_html; ?></div>
        <div class="modal-foot">
            <button onclick="closeMask('privacyMask')">我已阅读</button>
        </div>
    </div>
</div>

<div class="logo-wrap">
    <img class="logo-img" src="<?php echo htmlspecialchars($site_logo); ?>" 
         alt="<?php echo htmlspecialchars($site_title); ?>" 
         onerror="this.src='<?php echo htmlspecialchars($default_logo); ?>'">
</div>

<h1 class="main-title"><?php echo htmlspecialchars($site_title); ?></h1>
<div class="sub-title">E-SPORTS PLATFORM</div>

<div class="tip-card">
    <p><span class="star">*</span> 请阅读并同意<a onclick="openMask('userAgreeMask')">《用户服务协议》</a>与<a onclick="openMask('privacyMask')">《隐私政策》</a></p>
    <p><span class="star">*</span> 本人承诺已成年，或经监护人授权使用本平台</p>
    <p><span class="star">*</span> 本平台仅限指定人群使用，同意授权信息后方可登录使用</p>
</div>

<div class="check-line">
    <input type="checkbox" id="agreeCheck">
    <span class="checkbox-custom" onclick="document.getElementById('agreeCheck').click()"></span>
    <label for="agreeCheck">已阅读并同意协议政策，允许获取相关信息</label>
</div>

<button class="start-btn" id="enterBtn" disabled>开 始 使 用</button>
<div class="footer-text">© 2026 小熠系统 · 专业电竞服务平台</div>

<script>
// 页面加载动画
window.addEventListener('load', function(){
    setTimeout(function(){
        var loader = document.getElementById('v2Loader');
        if(loader){
            loader.classList.add('hidden');
            setTimeout(function(){ if(loader.parentNode) loader.parentNode.removeChild(loader); }, 500);
        }
    }, 800);
});
function openMask(id){
    document.getElementById(id).classList.add('show');
}
function closeMask(id){
    document.getElementById(id).classList.remove('show');
}
// 点击遮罩关闭弹窗
document.querySelectorAll(".modal-mask").forEach(mask=>{
    mask.onclick = function(e){
        if(e.target === mask) mask.classList.remove("show");
    }
})

const checkbox = document.getElementById("agreeCheck");
const btn = document.getElementById("enterBtn");
checkbox.onchange = function(){
    if(checkbox.checked){
        btn.classList.add("active");
        btn.disabled = false;
    }else{
        btn.classList.remove("active");
        btn.disabled = true;
    }
}
btn.onclick = function(){
    if(!btn.disabled){
        var loader = document.getElementById('v2Loader');
        if(loader){ loader.classList.remove('hidden'); loader.style.display = 'flex'; }
        setTimeout(function(){ location.href = "kehu"; }, 400);
    }
}
</script>
</body>
</html>
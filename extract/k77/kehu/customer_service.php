<?php
/**
 * 内置客服 - 聊天页面
 */
require_once '../includes/session_manager.php';
require_once '../config.php';

if (!is_h5_logged_in()) {
    header("Location: login.php");
    exit;
}

$user_id = get_h5_user_id();
$session_id = 'user_' . $user_id;

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

    $stmt = $db->prepare("SELECT * FROM chat_messages WHERE session_id = ? ORDER BY created_at ASC LIMIT 100");
    $stmt->execute([$session_id]);
    $messages = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT nickname, username, avatar FROM h5user WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

} catch (PDOException $e) {
    $messages = [];
    $user = ['nickname' => '用户', 'username' => '', 'avatar' => ''];
}

$user_name = $user['nickname'] ?: $user['username'] ?: '用户';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>在线客服</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/theme-v2.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', sans-serif;
    background: #f0f5ff;
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.chat-header {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #fff;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 2px 12px rgba(59,130,246,0.2);
    position: relative;
    z-index: 10;
}
.chat-header .back-btn {
    color: #fff; font-size: 20px; cursor: pointer; text-decoration: none;
    width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
    border-radius: 50%; transition: background 0.2s;
}
.chat-header .back-btn:active { background: rgba(255,255,255,0.2); }
.chat-header .avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; border: 2px solid rgba(255,255,255,0.3);
}
.chat-header .info { flex: 1; }
.chat-header .name { font-size: 16px; font-weight: 700; }
.chat-header .status { font-size: 11px; opacity: 0.85; display: flex; align-items: center; gap: 4px; }
.chat-header .status::before {
    content: ''; width: 6px; height: 6px; background: #4ade80; border-radius: 50%;
}
.chat-body { flex: 1; overflow-y: auto; padding: 16px 12px; background: #f0f5ff; }
.msg-row { display: flex; margin-bottom: 16px; animation: msgIn 0.3s ease; }
@keyframes msgIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
.msg-row.admin { justify-content: flex-start; }
.msg-row.user { justify-content: flex-end; }
.msg-avatar {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 16px;
}
.msg-row.admin .msg-avatar {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; margin-right: 8px;
}
.msg-row.user .msg-avatar {
    background: #e2e8f0; color: #475569; margin-left: 8px; order: 2;
}
.msg-bubble {
    max-width: 70%; padding: 10px 14px; border-radius: 16px;
    font-size: 14px; line-height: 1.5; word-break: break-word;
}
.msg-row.admin .msg-bubble {
    background: #fff; color: #1e293b; border-radius: 4px 16px 16px 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.msg-row.user .msg-bubble {
    background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff;
    border-radius: 16px 4px 16px 16px; box-shadow: 0 2px 8px rgba(59,130,246,0.25);
}
.msg-time { text-align: center; font-size: 11px; color: #94a3b8; margin: 8px 0; }
.quick-questions {
    padding: 8px 12px; background: #fff; border-top: 1px solid #e2e8f0;
    display: flex; gap: 8px; overflow-x: auto; flex-shrink: 0;
}
.quick-questions::-webkit-scrollbar { display: none; }
.quick-q {
    flex-shrink: 0; padding: 6px 14px; background: #eff6ff; color: #2563eb;
    border-radius: 16px; font-size: 12px; cursor: pointer; border: 1px solid #bfdbfe;
    transition: all 0.2s; white-space: nowrap;
}
.quick-q:active { transform: scale(0.95); background: #dbeafe; }
.chat-input-bar {
    background: #fff; padding: 10px 12px;
    padding-bottom: calc(10px + env(safe-area-inset-bottom));
    display: flex; align-items: center; gap: 10px;
    border-top: 1px solid #e2e8f0; flex-shrink: 0;
}
.chat-input {
    flex: 1; height: 40px; border: 1.5px solid #e2e8f0; border-radius: 20px;
    padding: 0 16px; font-size: 14px; background: #f8fafc; outline: none;
    transition: all 0.2s; color: #1e293b;
}
.chat-input:focus {
    border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}
.send-btn {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; border: none;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    transition: all 0.2s; flex-shrink: 0; box-shadow: 0 2px 8px rgba(59,130,246,0.3);
}
.send-btn:active { transform: scale(0.9); }
.send-btn:disabled { opacity: 0.5; }
.typing-indicator {
    display: flex; gap: 4px; padding: 10px 14px; background: #fff;
    border-radius: 4px 16px 16px 16px; width: fit-content;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.typing-indicator span {
    width: 6px; height: 6px; background: #94a3b8; border-radius: 50%;
    animation: typing 1.4s infinite;
}
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
    30% { transform: translateY(-4px); opacity: 1; }
}
</style>
</head>
<body>
<div class="chat-header">
    <a href="javascript:history.back()" class="back-btn"><i class="fas fa-chevron-left"></i></a>
    <div class="avatar"><i class="fas fa-headset"></i></div>
    <div class="info">
        <div class="name">在线客服</div>
        <div class="status">在线</div>
    </div>
</div>
<div class="chat-body" id="chatBody">
    <div class="msg-time">今天</div>
    <?php if (empty($messages)): ?>
    <div class="msg-row admin">
        <div class="msg-avatar"><i class="fas fa-headset"></i></div>
        <div class="msg-bubble">您好！欢迎咨询，请问有什么可以帮您？</div>
    </div>
    <?php else: ?>
    <?php foreach ($messages as $msg): ?>
    <div class="msg-row <?php echo $msg['sender_type'] === 'h5user' ? 'user' : 'admin'; ?>">
        <div class="msg-avatar"><i class="fas <?php echo $msg['sender_type'] === 'h5user' ? 'fa-user' : 'fa-headset'; ?>"></i></div>
        <div class="msg-bubble"><?php echo htmlspecialchars($msg['content']); ?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<div class="quick-questions">
    <div class="quick-q" onclick="sendQuick('如何下单？')">如何下单</div>
    <div class="quick-q" onclick="sendQuick('订单问题')">订单问题</div>
    <div class="quick-q" onclick="sendQuick('退款申请')">退款申请</div>
    <div class="quick-q" onclick="sendQuick('打手咨询')">打手咨询</div>
    <div class="quick-q" onclick="sendQuick('充值问题')">充值问题</div>
</div>
<div class="chat-input-bar">
    <input type="text" class="chat-input" id="chatInput" placeholder="输入消息..." maxlength="500">
    <button class="send-btn" id="sendBtn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
</div>
<script>
const chatBody = document.getElementById('chatBody');
const chatInput = document.getElementById('chatInput');
const sendBtn = document.getElementById('sendBtn');
const sessionId = '<?php echo $session_id; ?>';
const userId = <?php echo $user_id; ?>;

function scrollToBottom() { chatBody.scrollTop = chatBody.scrollHeight; }
scrollToBottom();

const autoReplies = {
    '如何下单？': '您可以在首页浏览商品，点击商品进入详情页，点击"立即下单"按钮，填写信息后提交订单即可。',
    '订单问题': '请提供您的订单号，我来帮您查询订单状态。您可以在"我的-订单列表"中查看订单号。',
    '退款申请': '如需退款，请在订单详情页点击"申请退款"，填写退款原因后提交，我们会尽快处理。',
    '打手咨询': '我们平台有大量优质打手，您可以在分类页面查看打手信息，下单时可指定打手。',
    '充值问题': '您可以在"我的-充值"页面进行充值，支持微信支付，充值后余额可用于下单。'
};

function addMessage(content, isUser) {
    const row = document.createElement('div');
    row.className = 'msg-row ' + (isUser ? 'user' : 'admin');
    row.innerHTML = '<div class="msg-avatar"><i class="fas ' + (isUser ? 'fa-user' : 'fa-headset') + '"></i></div>' +
                    '<div class="msg-bubble">' + escapeHtml(content) + '</div>';
    chatBody.appendChild(row);
    scrollToBottom();
}
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
function showTyping() {
    const row = document.createElement('div');
    row.className = 'msg-row admin';
    row.id = 'typingRow';
    row.innerHTML = '<div class="msg-avatar"><i class="fas fa-headset"></i></div>' +
                    '<div class="typing-indicator"><span></span><span></span><span></span></div>';
    chatBody.appendChild(row);
    scrollToBottom();
}
function hideTyping() {
    const t = document.getElementById('typingRow');
    if (t) t.remove();
}
async function sendMessage() {
    const text = chatInput.value.trim();
    if (!text) return;
    sendBtn.disabled = true;
    addMessage(text, true);
    chatInput.value = '';
    try {
        const formData = new FormData();
        formData.append('session_id', sessionId);
        formData.append('user_id', userId);
        formData.append('content', text);
        await fetch('api/chat_send.php', { method: 'POST', body: formData });
    } catch(e) {}
    showTyping();
    setTimeout(() => {
        hideTyping();
        let reply = autoReplies[text] || '感谢您的咨询，客服人员会尽快回复您。';
        addMessage(reply, false);
        try {
            const formData = new FormData();
            formData.append('session_id', sessionId);
            formData.append('user_id', userId);
            formData.append('content', reply);
            formData.append('is_admin', '1');
            fetch('api/chat_send.php', { method: 'POST', body: formData });
        } catch(e) {}
    }, 1000 + Math.random() * 1000);
    sendBtn.disabled = false;
}
function sendQuick(text) { chatInput.value = text; sendMessage(); }
chatInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });
</script>
</body>
</html>

<?php
// 开启输出缓冲
ob_start();

require_once("../../includes/session_manager.php");
include("../../includes/common.php");

// 检查管理员登录
if (!is_admin_logged_in()) {
    ob_end_clean();
    header("Location: ../login.php");
    exit();
}

// 页面标题
$pageTitle = "关于系统";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title><?php echo $pageTitle; ?></title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="../res/layui/css/layui.css?v=2.9.0" rel="stylesheet">
  <link href="../res/adminui/dist/css/admin.css?v=1.0.0" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .layui-card-body {
        padding: 40px;
    }
    .about-section {
        margin-bottom: 30px;
    }
    .about-section h3 {
        font-size: 18px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e5e7eb;
    }
    .about-section p {
        font-size: 14px;
        color: #374151;
        line-height: 1.8;
        margin-bottom: 10px;
    }
    .info-item {
        display: flex;
        margin-bottom: 12px;
    }
    .info-label {
        font-weight: 600;
        color: #111827;
        min-width: 100px;
    }
    .info-value {
        color: #374151;
    }
    .version-badge {
        display: inline-block;
        padding: 4px 12px;
        background: #dbeafe;
        color: #1d4ed8;
        border-radius: 20px;
        font-weight: 600;
        font-size: 14px;
    }
    .log-item {
        margin-bottom: 20px;
    }
    .log-date {
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
        font-size: 15px;
    }
    .log-content {
        font-size: 14px;
        color: #374151;
        line-height: 1.6;
    }
    .log-content li {
        margin-bottom: 5px;
        margin-left: 15px;
    }
  </style>
</head>
<body>
  <div class="layui-fluid">
    <div class="layui-row layui-col-space15">
      <div class="layui-col-md12">
        <div class="layui-card">
          <div class="layui-card-header">
            <h2 style="font-size: 20px; font-weight: 600;">
              <i class="fas fa-info-circle"></i> 关于系统
            </h2>
          </div>
          <div class="layui-card-body">

            <div class="about-section">
              <h3><i class="fas fa-desktop"></i> 系统信息</h3>
              <div class="info-item">
                <span class="info-label">系统版本</span>
                <span class="info-value"><span class="version-badge">V6</span></span>
              </div>
              <div class="info-item">
                <span class="info-label">开发团队</span>
                <span class="info-value">广州小熠科技网络</span>
              </div>
              <div class="info-item">
                <span class="info-label">最后更新</span>
                <span class="info-value">2026-05-06</span>
              </div>
            </div>

            <div class="about-section">
              <h3><i class="fas fa-file-alt"></i> 系统描述</h3>
              <p>本系统是一个专业的游戏陪玩服务管理平台，致力于为游戏陪玩行业提供一站式数字化解决方案。平台集成了商品管理、订单处理、用户管理、财务管理、数据统计等核心功能模块，通过智能化的派单系统和灵活的权限管理机制，帮助商家实现高效运营。</p>
              <p>系统采用前后端分离架构设计，后端基于PHP开发，前端采用LayUI框架，提供流畅的用户体验和直观的数据展示。支持多种订单类型管理，包括普通陪玩订单、预约订单、包团订单等，可满足不同业务场景需求。</p>
              <p>平台具备完善的财务结算体系，支持保证金缴纳、余额充值、提现申请等多种资金管理模式。同时提供丰富的营销工具，如邀请码系统、抽奖活动等，帮助商家获取更多客户。系统还支持多管理员协作，可根据员工职责分配不同的操作权限，实现精细化的团队管理。</p>
            </div>

            <div class="about-section">
              <h3><i class="fas fa-cogs"></i> 技术栈</h3>
              <p><strong>后端技术</strong>：PHP 7.4 作为服务器端脚本语言，提供强大的Web应用支持；MySQL 8.0 作为关系型数据库，存储用户数据、订单信息、商品数据等核心业务数据。</p>
              <p><strong>前端框架</strong>：LayUI 作为主要UI框架，提供优雅的模块化组件和响应式布局支持；Chart.js 用于数据可视化，展示订单统计、用户增长等关键业务指标。</p>
              <p><strong>基础技术</strong>：HTML5 提供语义化的页面结构；CSS3 实现丰富的视觉效果和动画交互；JavaScript 实现客户端逻辑和异步通信。</p>
              <p><strong>扩展支持</strong>：GD 库用于图片处理和验证码生成；CURL 扩展用于第三方API调用和微信授权登录；JSON 扩展用于数据格式转换。</p>
              <p><strong>服务器环境</strong>：Nginx 作为Web服务器，提供高性能的HTTP服务和反向代理功能。</p>
            </div>

            <div class="about-section">
              <h3><i class="fas fa-server"></i> 搭建需求</h3>
              <p>• PHP 7.4 </p>
              <p>• MySQL 8.0 或更高版本（支持 JSON 数据类型和窗口函数）</p>
              <p>• Nginx 服务器（配置 URL 重写和 HTTPS 支持）</p>
              <p>• 关闭防跨站攻击（open_basedir 正确配置）</p>
              <p>• 开启强制 SSL 访问（HTTPS 全站加密）</p>
              <p>• 服务器支持 GD 库（图片处理和验证码）</p>
              <p>• 服务器支持 CURL 扩展（微信登录和第三方接口）</p>
              <p>• 服务器支持 JSON 扩展（数据交互）</p>
            </div>

            <div class="about-section">
              <h3><i class="fas fa-history"></i> 更新日志</h3>
              
              <div class="log-item">
                <div class="log-date"><i class="fas fa-calendar-alt"></i> 2026-05-06</div>
                <div class="log-content">
                  <ul>
                    <li>修复商家端支付后不能正常回调的问题</li>
                    <li>修复抽奖看不见抽到的是什么这个问题</li>
                    <li>修复打手端没办法正常提交订单的问题</li>
                    <li>修复一些已知的问题</li>
                  </ul>
                </div>
              </div>
              
            <div class="log-item">
                <div class="log-date"><i class="fas fa-calendar-alt"></i> 2026-05-05</div>
                <div class="log-content">
                  <ul>
                    <li>新增陪护功能，支持一单共享给多人</li>
                    <li>修复一些已知的问题</li>
                  </ul>
                </div>
              </div>
              
            <div class="log-item">
                <div class="log-date"><i class="fas fa-calendar-alt"></i> 2026-05-04</div>
                <div class="log-content">
                  <ul>
                    <li>优化了系统体验，修复若干已知问题</li>
                    <li>新增APP接单实时通知功能</li>
                    <li>新增新入驻打手资质审核功能</li>
                    <li>新增打手管理后台违规封禁功能</li>
                  </ul>
                </div>
              </div>

              <div class="log-item">
                <div class="log-date"><i class="fas fa-calendar-alt"></i> 2026-05-04</div>
                <div class="log-content">
                  <ul>
                    <li>修复了一些已知的问题</li>
                    <li>新增微信官方支付渠道，可直接在H5端拉起支付</li>
                    <li>新增手动转单功能（支持A打手将订单转给B打手）</li>
                    <li>新增打回大厅功能（打手无法完成订单时可发回大厅重新分配）</li>
                    <li>修复后台陪玩列表只显示保证金不显示余额的问题</li>
                    <li>新增首页快捷入口板块（后台支持自定义配置）</li>
                    <li>新增System登录日志记录功能</li>
                    <li>修复打手注册后不同步昵称与头像的问题</li>
                    <li>新增打手管事端账号密码登录模态框，支持多状态登录</li>
                  </ul>
                </div>
              </div>

              <div class="log-item">
                <div class="log-date"><i class="fas fa-calendar-alt"></i> 2026-04-25</div>
                <div class="log-content">
                  <ul>
                    <li>修复首页无法修改下单须知部分内容的问题</li>
                    <li>修复一些已知的问题</li>
                  </ul>
                </div>
              </div>

              <div class="log-item">
                <div class="log-date"><i class="fas fa-calendar-alt"></i> 2026-04-22</div>
                <div class="log-content">
                  <ul>
                    <li>系统正式发布 V1 版本</li>
                  </ul>
                </div>
              </div>

            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="../res/layui/layui.js"></script>
</body>
</html>
<?php
// 关闭输出缓冲
ob_end_flush();
?>

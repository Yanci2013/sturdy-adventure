<!-- 底部导航栏 - 纯白色简洁版 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  /* 全局样式 */
  body {
    font-family: 'Inter', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
    margin: 0;
    padding: 0;
    background-color: #ffffff;
    min-height: 100vh;
    padding-bottom: 90px;
    padding-top: 10px;
    color: #222222;
  }
  
  /* 底部导航栏 - 浮动透明样式 */
  .qcloud-nav {
    position: fixed;
    bottom: 20px;
    left: 20px;
    right: 20px;
    height: 70px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
    display: flex;
    justify-content: space-around;
    align-items: center;
    z-index: 1000;
    border-radius: 100px;
    border: 1px solid rgba(0, 0, 0, 0.05);
    margin-bottom: 0;
    transition: all 0.3s ease;
  }
  
  .qcloud-nav:hover {
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12);
  }
  
  /* 导航项 */
  .qcloud-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    flex: 1;
    text-decoration: none;
    color: #999999;
    transition: all 0.2s ease;
    position: relative;
  }
  
  .qcloud-nav-item:hover {
    transform: translateY(-2px);
  }
  
  /* 图标容器 */
  .qcloud-icon {
    width: 32px;
    height: 32px;
    margin-bottom: 2px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
  }
  
  /* 图标样式 - 灰色系 */
  .qcloud-icon i {
    font-size: 24px;
    color: #999999;
    transition: all 0.2s ease;
  }
  
  /* 文本样式 */
  .qcloud-text {
    font-size: 12px;
    font-weight: 400;
    letter-spacing: 0.3px;
    color: #999999;
    transition: all 0.2s ease;
  }
  
  /* 活动状态 - 加深灰色 */
  .qcloud-active .qcloud-icon i {
    color: #222222;
  }
  
  .qcloud-active .qcloud-text {
    color: #222222;
    font-weight: 500;
  }
  
  /* 悬停效果 - 加深灰色 */
  .qcloud-nav-item:hover .qcloud-icon i {
    color: #666666;
  }
  
  .qcloud-nav-item:hover .qcloud-text {
    color: #666666;
  }
  
  /* 响应式调整 */
  @media (max-width: 480px) {
    .qcloud-nav {
      height: 64px;
      bottom: 16px;
      left: 16px;
      right: 16px;
      border-radius: 32px;
    }
    
    .qcloud-icon i {
      font-size: 22px;
    }
    
    .qcloud-text {
      font-size: 11px;
    }
  }
  
  @media (max-width: 360px) {
    .qcloud-nav {
      height: 60px;
      bottom: 14px;
      left: 12px;
      right: 12px;
      border-radius: 30px;
    }
    
    .qcloud-icon i {
      font-size: 20px;
    }
    
    .qcloud-text {
      font-size: 10px;
    }
  }
  
  /* 深色模式适配（可选） */
  @media (prefers-color-scheme: dark) {
    .qcloud-nav {
      background: rgba(40, 40, 40, 0.7);
      border-color: rgba(255, 255, 255, 0.05);
    }
    
    .qcloud-nav:hover {
      background: rgba(50, 50, 50, 0.85);
    }
    
    .qcloud-icon i,
    .qcloud-text {
      color: #b0b0b0;
    }
    
    .qcloud-active .qcloud-icon i,
    .qcloud-active .qcloud-text {
      color: #e0e0e0;
    }
    
    .qcloud-nav-item:hover .qcloud-icon i,
    .qcloud-nav-item:hover .qcloud-text {
      color: #d0d0d0;
    }
  }
</style>

<!-- 底部导航菜单 -->
<div class="qcloud-nav">
  <a href="index.php" class="qcloud-nav-item">
    <div class="qcloud-icon">
      <i class="fas fa-home"></i>
    </div>
    <span class="qcloud-text">首页</span>
  </a>
  
  <a href="orders.php" class="qcloud-nav-item">
    <div class="qcloud-icon">
      <i class="fas fa-list"></i>
    </div>
    <span class="qcloud-text">订单</span>
  </a>
  
  <a href="profile.php" class="qcloud-nav-item">
    <div class="qcloud-icon">
      <i class="fas fa-user"></i>
    </div>
    <span class="qcloud-text">我的</span>
  </a>
</div>

<script>
// 合并DOMContentLoaded事件处理
document.addEventListener('DOMContentLoaded', function() {
  // 获取当前页面路径
  const currentPage = window.location.pathname.split('/').pop();
  const navItems = document.querySelectorAll('.qcloud-nav-item');
  
  // 移除所有活动状态并设置当前活动项
  navItems.forEach(item => {
    item.classList.remove('qcloud-active');
    if (item.getAttribute('href') === currentPage) {
      item.classList.add('qcloud-active');
    }
  });

  // 容器入场动画
  const nav = document.querySelector('.qcloud-nav');
  
  if (nav) {
    nav.style.opacity = '0';
    nav.style.transform = 'translateY(10px)';
    
    setTimeout(() => {
      nav.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
      nav.style.opacity = '1';
      nav.style.transform = 'translateY(0)';
    }, 100);
  }
});

// 点击导航项时更新状态
document.querySelectorAll('.qcloud-nav-item').forEach(item => {
  item.addEventListener('click', function(e) {
    // 移除所有活动状态
    document.querySelectorAll('.qcloud-nav-item').forEach(navItem => {
      navItem.classList.remove('qcloud-active');
    });
    
    // 设置当前项为活动状态
    this.classList.add('qcloud-active');
  });
});
</script>
</body>
</html>
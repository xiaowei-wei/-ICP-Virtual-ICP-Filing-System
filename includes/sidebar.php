<!-- 公共侧边栏导航 -->
<nav id="sidebarMenu" class="sidebar-glass col-md-3 col-lg-2 d-md-block sidebar collapse">
  <div class="sidebar-sticky pt-3">
    <div class="sidebar-crystal-logo" title="SEO水晶棱镜标识">
      <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <radialGradient id="crystalGradient" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#fffbe6"/>
            <stop offset="100%" stop-color="#b3e5fc"/>
          </radialGradient>
        </defs>
        <polygon points="24,4 44,24 24,44 4,24" fill="url(#crystalGradient)" stroke="#b3e5fc" stroke-width="2"/>
        <circle cx="24" cy="24" r="8" fill="#fffde7" stroke="#ffd700" stroke-width="1.5"/>
      </svg>
      <span class="sidebar-brand-text" style="margin-left:12px;font-size:1.18rem;font-weight:700;color:#2196f3;letter-spacing:1px;">虚拟ICP备案系统</span>
    </div>
    <ul class="nav flex-column sidebar-menu">
      <li class="nav-item">
        <a class="nav-link" href="dashboard.php">
          <span class="sidebar-icon dashboard-icon"></span>
          <span class="sidebar-text">仪表盘</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="applications.php">
          <span class="sidebar-icon docflow-icon"></span>
          <span class="sidebar-text">申请管理</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="announcements.php">
          <span class="sidebar-icon bell-icon"></span>
          <span class="sidebar-text">公告管理</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="about_manage.php">
          <span class="sidebar-icon info-icon"></span>
          <span class="sidebar-text">内容管理</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="statistics.php">
          <span class="sidebar-icon gear-icon"></span>
          <span class="sidebar-text">统计分析</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="settings.php">
          <span class="sidebar-icon setting-icon"></span>
          <span class="sidebar-text">系统设置</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'seo_settings.php') ? 'active' : ''; ?>" href="seo_settings.php">
          <span class="sidebar-icon seo-icon"></span>
          <span class="sidebar-text">SEO设置</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_selectable_numbers.php') ? 'active' : ''; ?>" href="manage_selectable_numbers.php">
          <span class="sidebar-icon list-icon"></span> <!-- 您可能需要为这个图标选择一个合适的类名或SVG -->
          <span class="sidebar-text">号码池管理</span>
        </a>
      </li>
      <li class="nav-item urgent">
        <a class="nav-link" href="logout.php">
          <span class="sidebar-icon logout-icon"></span>
          <span class="sidebar-text">退出登录</span>
        </a>
      </li>
    </ul>
    <div class="sidebar-split-line">
      <canvas id="sidebarParticles" width="180" height="4"></canvas>
    </div>
    <div class="sidebar-workbench-shadow"></div>
  </div>
</nav>
<?php
// 管理员登录页面
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// 初始化变量
$error = '';
$username = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        try {
            $db = db_connect();
            $stmt = $db->prepare("SELECT id, username, password, status FROM admin_users WHERE username = :username LIMIT 1");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();
            
            if ($user && $user['status'] == 1) {
                // 验证密码 (使用默认密码123456进行简化演示)
                if ($password === '123456' || password_verify($password, $user['password'])) {
                    // 登录成功
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    
                    // 更新最后登录时间
                    $update = $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = :id");
                    $update->execute(['id' => $user['id']]);
                    
                    // 记录登录日志
                    log_operation($user['id'], '管理员登录');
                    
                    // 重定向到管理后台
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = '密码错误';
                }
            } else {
                $error = '用户不存在或已被禁用';
            }
        } catch (PDOException $e) {
            error_log('登录失败: ' . $e->getMessage());
            $error = '系统错误，请稍后再试';
        }
    }
}

// 页面标题
$page_title = '管理员登录';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #232a34 0%, #181d23 100%);
            min-height: 100vh;
            font-family: 'Orbitron', 'Microsoft YaHei', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            letter-spacing: 0.5px;
        }
        .glass-bg {
            background: rgba(30,34,40,0.55);
            box-shadow: 0 8px 40px 0 rgba(0,0,0,0.25);
            backdrop-filter: blur(16px);
            border-radius: 18px;
            border: 1.5px solid rgba(255,255,255,0.08);
        }
        .login-card {
            margin-top: 8vh;
            padding: 38px 36px 32px 36px;
            box-shadow: 0 8px 40px 0 rgba(0,0,0,0.22);
            border-radius: 18px;
            background: rgba(255,255,255,0.08);
            position: relative;
            z-index: 2;
        }
        .login-card .admin-badge {
            position: absolute;
            top: -32px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(90deg,#1976d2 60%,#5eaefd 100%);
            color: #fff;
            font-size: 1.1em;
            font-weight: bold;
            padding: 8px 28px 8px 18px;
            border-radius: 18px 18px 18px 0;
            box-shadow: 0 2px 12px 0 rgba(52,120,246,0.18);
            letter-spacing: 2px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .login-card .admin-badge i {
            font-size: 1.2em;
            margin-right: 4px;
        }
        .login-card .divider {
            height: 1px;
            background: linear-gradient(90deg,rgba(255,255,255,0.08) 0%,rgba(52,120,246,0.18) 100%);
            margin: 18px 0 24px 0;
            border-radius: 8px;
        }
        .login-card .form-group label {
            color: #bfc9d1;
            font-size: 1em;
            font-weight: 500;
            letter-spacing: 1px;
        }
        .login-card .form-control {
            background: rgba(255,255,255,0.22);
            border: none;
            border-radius: 12px;
            color: #232a34;
            font-size: 1.08em;
            box-shadow: 0 0 0 0 rgba(52,120,246,0.18);
            transition: box-shadow 0.4s, background 0.4s;
            outline: none;
            height: 48px;
        }
        .login-card .form-control:focus {
            background: rgba(255,255,255,0.38);
            box-shadow: 0 0 16px 2px #1976d2, 0 0 0 0.2rem rgba(52,120,246,0.18);
            border: none;
        }
        .breath-glow {
            animation: breath-glow 2.2s infinite alternate;
        }
        @keyframes breath-glow {
            0% { box-shadow: 0 0 0 0 #1976d2; }
            100% { box-shadow: 0 0 16px 2px #1976d2; }
        }
        .login-card .input-group-text {
            background: transparent;
            border: none;
            color: #1976d2;
            font-size: 1.2em;
        }
        .login-card .login-btn {
            background: linear-gradient(90deg,#1976d2 60%,#5eaefd 100%);
            border: none;
            color: #fff;
            font-weight: bold;
            font-size: 1.15em;
            border-radius: 12px;
            box-shadow: 0 4px 16px 0 rgba(52,120,246,0.13);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .login-card .login-btn:hover {
            background: linear-gradient(90deg,#2868d6 60%,#4e9be0 100%);
            box-shadow: 0 8px 32px 0 rgba(52,120,246,0.18);
        }
        .login-card .status-tip {
            color: #5eaefd;
            font-size: 1em;
            letter-spacing: 1.5px;
            margin-bottom: 12px;
            text-align: center;
            font-family: 'Orbitron', monospace;
        }
        .login-card .fingerprint-switch {
            cursor: pointer;
            color: #1976d2;
            font-size: 1.5em;
            margin-right: 10px;
            transition: color 0.2s;
        }
        .login-card .fingerprint-switch.active {
            color: #5eaefd;
        }
        .login-card .dynamic-keypad {
            display: none;
            margin-top: 10px;
        }
        .login-card .dynamic-keypad.active {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }
        .login-card .dynamic-keypad button {
            width: 48px;
            height: 48px;
            font-size: 1.2em;
            border-radius: 10px;
            border: none;
            background: rgba(255,255,255,0.18);
            color: #1976d2;
            margin: 2px;
            transition: background 0.2s;
        }
        .login-card .dynamic-keypad button:active {
            background: #1976d2;
            color: #fff;
        }
        .login-card .error-feather {
            position: absolute;
            left: 50%;
            top: -38px;
            transform: translateX(-50%);
            width: 38px;
            height: 38px;
            pointer-events: none;
            z-index: 10;
            animation: feather-fall 1.2s linear forwards;
        }
        @keyframes feather-fall {
            0% { opacity: 0; transform: translateX(-50%) translateY(-30px) rotate(-10deg); }
            60% { opacity: 1; }
            100% { opacity: 0.7; transform: translateX(-50%) translateY(38px) rotate(18deg); }
        }
        .login-card .particle-bg {
            position: absolute;
            left: 0; top: 0; width: 100%; height: 100%;
            z-index: 0;
            pointer-events: none;
        }
        .login-card .divider-8px {
            height: 8px;
            background: linear-gradient(90deg,rgba(255,255,255,0.08) 0%,rgba(52,120,246,0.18) 100%);
            margin: 18px 0 24px 0;
            border-radius: 8px;
        }
        .modern-font {
            font-family: 'Orbitron', 'Segoe UI', 'Microsoft YaHei', monospace;
            font-weight: 700;
            letter-spacing: 2px;
        }
    </style>
</head>
<body>
    <div class="login-page" style="min-height:100vh;display:flex;align-items:center;justify-content:center;position:relative;">
        <div class="glass-bg login-card position-relative" style="width:400px;">
            <div class="admin-badge"><i class="fas fa-user-shield"></i> 管理员专属</div>
            <div class="divider-8px"></div>
            <div class="status-tip modern-font" id="status-tip">管理员身份核验中...</div>
            <?php if ($error): ?>
                <div class="alert alert-danger position-relative" style="z-index:2;">
                    <?php echo $error; ?>
                    <svg class="error-feather" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19 2 Q25 10 36 12 Q28 18 32 36 Q20 32 10 36 Q12 28 2 19 Q12 18 19 2 Z" fill="#e53935" fill-opacity="0.7"/>
                    </svg>
                </div>
            <?php endif; ?>
            <form method="post" action="" autocomplete="off">
                <div class="form-group">
                    <label for="username">用户名</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control breath-glow" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required autofocus autocomplete="off">
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">密码</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text fingerprint-switch" id="fingerprint-switch" title="切换指纹/密码"><i class="fas fa-fingerprint"></i></span>
                        </div>
                        <input type="password" class="form-control breath-glow" id="password" name="password" required autocomplete="off">
                    </div>
                    <div class="dynamic-keypad mt-2" id="dynamic-keypad"></div>
                </div>
                <div class="divider"></div>
                <button type="submit" class="btn login-btn btn-block">登 录</button>
            </form>
            <div class="divider-8px"></div>
            <div class="text-center text-muted" style="font-size:0.98em;">
            </div>
            <canvas class="particle-bg" id="particle-bg"></canvas>
        </div>
    </div>
    <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // 指纹/密码切换
        let isFingerprint = false;
        const switchBtn = document.getElementById('fingerprint-switch');
        const pwdInput = document.getElementById('password');
        const keypad = document.getElementById('dynamic-keypad');
        switchBtn.addEventListener('click', function() {
            isFingerprint = !isFingerprint;
            if(isFingerprint) {
                switchBtn.classList.add('active');
                switchBtn.innerHTML = '<i class="fas fa-keyboard"></i>';
                keypad.classList.add('active');
                pwdInput.type = 'text';
                pwdInput.readOnly = true;
                pwdInput.value = '';
                renderKeypad();
            } else {
                switchBtn.classList.remove('active');
                switchBtn.innerHTML = '<i class="fas fa-fingerprint"></i>';
                keypad.classList.remove('active');
                pwdInput.type = 'password';
                pwdInput.readOnly = false;
                pwdInput.value = '';
            }
        });
        function renderKeypad() {
            keypad.innerHTML = '';
            let nums = Array.from({length:10},(_,i)=>i);
            nums = nums.sort(()=>Math.random()-0.5);
            nums.forEach(n=>{
                let btn = document.createElement('button');
                btn.type = 'button';
                btn.innerText = n;
                btn.onclick = ()=>{ pwdInput.value += n; };
                keypad.appendChild(btn);
            });
            let delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.innerHTML = '<i class="fas fa-backspace"></i>';
            delBtn.onclick = ()=>{ pwdInput.value = pwdInput.value.slice(0,-1); };
            keypad.appendChild(delBtn);
        }
        // 粒子安全验证动效
        const canvas = document.getElementById('particle-bg');
        if(canvas) {
            const ctx = canvas.getContext('2d');
            let w = canvas.width = canvas.offsetWidth = 400;
            let h = canvas.height = canvas.offsetHeight = 420;
            let particles = Array.from({length:48},()=>({
                x:Math.random()*w,
                y:Math.random()*h,
                r:Math.random()*2.2+1.2,
                dx:(Math.random()-0.5)*0.7,
                dy:(Math.random()-0.5)*0.7,
                c:`rgba(${52+Math.floor(Math.random()*40)},${120+Math.floor(Math.random()*60)},246,0.18)`
            }));
            function draw() {
                ctx.clearRect(0,0,w,h);
                for(let p of particles) {
                    ctx.beginPath();
                    ctx.arc(p.x,p.y,p.r,0,2*Math.PI);
                    ctx.fillStyle=p.c;
                    ctx.fill();
                    p.x+=p.dx; p.y+=p.dy;
                    if(p.x<0||p.x>w) p.dx*=-1;
                    if(p.y<0||p.y>h) p.dy*=-1;
                }
                requestAnimationFrame(draw);
            }
            draw();
        }
        // 登录错误红羽飘落动效（已集成SVG）
        // 身份核验中状态提示动画
        let tip = document.getElementById('status-tip');
        let dots = 0;
        setInterval(()=>{
            dots = (dots+1)%4;
            tip.innerText = '管理员身份核验中' + '.'.repeat(dots);
        }, 700);
    </script>
</body>
</html>
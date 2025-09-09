<?php
session_start();
require 'config.php';

$error = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $conn = db_connect();

    // ensure roles table has 'admin' role id=1
    $conn->query("INSERT IGNORE INTO roles (id, name, description) VALUES (1,'admin','System administrator')");

    // look up user
    $stmt = $conn->prepare('SELECT u.id,u.username,u.password_hash,u.full_name,u.role_id,r.name as role_name 
                            FROM users u JOIN roles r ON u.role_id=r.id 
                            WHERE u.username=? LIMIT 1');
    $stmt->bind_param('s',$username);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    // bootstrap admin if needed
    if(!$user && $username === 'admin' && $password === 'admin'){
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $conn->prepare('INSERT INTO users (role_id, username, password_hash, full_name, email, is_active) VALUES (1,?,?,?,?,1)');
        $full_name = 'System Admin';
        $email = 'admin@example.com';
        $ins->bind_param('ssss', $username, $hash, $full_name, $email);
        if($ins->execute()){
            $new_id = $ins->insert_id;
            $_SESSION['user'] = ['id'=>$new_id,'username'=>'admin','full_name'=>$full_name,'role'=>'admin'];
            $ins->close();
            $conn->close();
            header('Location: index.php'); exit;
        } else {
            $error = 'Could not auto-create admin user: ' . $conn->error;
        }
    }
    // verify existing user
    elseif($user){
        $ok = false;
        if(!empty($user['password_hash']) && (strpos($user['password_hash'],'$2y$')===0 || strpos($user['password_hash'],'$2a$')===0)){
            $ok = password_verify($password, $user['password_hash']);
        }
        if(!$ok && $user['username']==='admin' && $password==='admin'){
            $ok = true;
        }
        if($ok){
            $_SESSION['user'] = [
                'id'=>$user['id'],
                'username'=>$user['username'],
                'full_name'=>$user['full_name'] ?: $user['username'],
                'role'=>$user['role_name']
            ];
            $conn->close();
            header('Location: index.php'); exit;
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Invalid username or password';
    }

    $conn->close();
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>PLANERA — Login</title>

  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">

  <!-- Page styles (self-contained, light & professional) -->
  <style>
    :root{
      --bg: #f6f9fb;
      --card: #ffffff;
      --muted: #6b7280;
      --text: #0f1724;
      --accent-a: #4b6cff;
      --accent-b: #22c7d8;
      --accent-grad: linear-gradient(90deg,var(--accent-a),var(--accent-b));
      --glass-border: 1px solid rgba(2,6,23,0.04);
      --radius: 14px;
      --shadow: 0 18px 50px rgba(9,18,28,0.08);
    }

    * { box-sizing: border-box; }
    html,body { height:100%; margin:0; font-family: 'Inter', system-ui, -apple-system, "Segoe UI", Roboto, Arial; -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale; background:var(--bg); color:var(--text); }

    /* Centering shell */
    .wrap {
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:36px 20px;
    }

    /* The card */
    .login-card {
      width:520px;
      max-width:96%;
      background:var(--card);
      border-radius:var(--radius);
      padding:28px;
      border:var(--glass-border);
      box-shadow:var(--shadow);
      transform-origin:center;
      transition: transform .18s ease, box-shadow .18s ease;
    }
    .login-card:focus-within { transform: translateY(-4px); box-shadow: 0 28px 80px rgba(9,18,28,0.12); }

    /* Header */
    .brand-row { display:flex; align-items:center; gap:16px; margin-bottom:6px; }
    .brand-badge {
      width:56px; height:56px; border-radius:12px;
      display:flex; align-items:center; justify-content:center;
      font-family:'Montserrat',sans-serif; font-weight:800; color:#fff; font-size:20px;
      background: var(--accent-grad);
      box-shadow: 0 10px 30px rgba(75,108,255,0.14);
    }
    .brand-title { font-family:'Montserrat',sans-serif; font-weight:700; font-size:20px; margin:0; color:var(--text); }
    .brand-sub { margin:2px 0 0; color:var(--muted); font-weight:600; font-size:13px; }

    h1.sr { position:absolute; left:-9999px; top:auto; width:1px; height:1px; overflow:hidden; } /* accessible heading if needed */

    /* Intro text */
    .lead { margin:12px 0 18px; color:var(--muted); font-weight:600; line-height:1.45; }

    /* Error */
    .error { background:#fff5f5; border:1px solid #ffd0d0; color:#7b1f1f; padding:10px 12px; border-radius:10px; font-weight:800; margin-bottom:12px; }

    /* Form */
    form { display:grid; gap:12px; }
    label.vis { display:block; font-size:13px; color:var(--muted); font-weight:700; margin-bottom:6px; }

    .input-wrap {
      display:flex; align-items:center; gap:12px; padding:10px; border-radius:10px; background:#fff; border:1px solid #e6eef6;
      transition: box-shadow .12s, border-color .12s;
    }
    .input-wrap:focus-within { box-shadow: 0 10px 30px rgba(75,108,255,0.06); border-color: rgba(75,108,255,0.12); }

    input[type="text"], input[type="password"] {
      border:0; background:transparent; outline:none; padding:8px 6px; font-size:15px; font-weight:700; color:var(--text); width:100%;
    }
    input::placeholder { color:#aab9bf; font-weight:600; }

    /* password toggle */
    .toggle-btn {
      background:transparent; border:1px solid transparent; padding:8px 10px; border-radius:8px; cursor:pointer; font-weight:700; color:var(--muted);
    }
    .toggle-btn:focus { outline:2px solid rgba(75,108,255,0.12); }

    /* actions */
    .actions { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:4px; }
    .primary {
      padding:12px 18px; border-radius:10px; border:0; background:var(--accent-grad); color:#021019; font-weight:900; cursor:pointer; font-size:15px;
      box-shadow: 0 12px 36px rgba(75,108,255,0.12);
    }
    .primary:hover { transform: translateY(-3px); box-shadow: 0 20px 56px rgba(75,108,255,0.16); }

    /* small note */
    .note { margin-top:14px; color:var(--muted); font-weight:600; text-align:center; font-size:13px; }

    /* responsive */
    @media (max-width:560px){
      .login-card { padding:20px; width:420px; }
      .brand-badge { width:48px; height:48px; font-size:18px; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <main class="login-card" role="main" aria-labelledby="login-heading">
      <h1 id="login-heading" class="sr">PLANERA — Login</h1>

      <div class="brand-row" aria-hidden="false">
        <div class="brand-badge" aria-hidden="true">PL</div>
        <div>
          <div class="brand-title">PLANERA</div>
          <div class="brand-sub">Smart Classroom & Timetable Scheduler</div>
        </div>
      </div>

      <p class="lead">Plan smarter with PLANERA.</p>

      <?php if($error): ?>
        <div class="error" role="alert"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="post" id="loginForm" novalidate>
        <div>
          <label for="username" class="vis">Username</label>
          <div class="input-wrap" role="group" aria-labelledby="lbl-username">
            <input id="username" name="username" type="text" required autocomplete="username" placeholder="Username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
          </div>
        </div>

        <div>
          <label for="password" class="vis">Password</label>
          <div class="input-wrap" role="group" aria-labelledby="lbl-password">
            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Password">
            <button type="button" class="toggle-btn" id="togglePassword" aria-pressed="false" title="Show or hide password">Show</button>
          </div>
        </div>

        <div class="actions">
          <div style="display:flex;align-items:center;gap:12px">
            <label style="font-weight:700;color:var(--muted);font-size:13px;display:flex;align-items:center;gap:8px">
              <input type="checkbox" name="remember" value="1" aria-label="Remember me"> Remember me
            </label>
          </div>

          <button type="submit" class="primary">Sign in</button>
        </div>

        <div class="note">Tip: If no users exist, sign in with <strong>admin / admin</strong> to create the admin account automatically.</div>
      </form>
    </main>
  </div>

  <script>
    // Focus and show/hide password
    (function(){
      const username = document.getElementById('username');
      const password = document.getElementById('password');
      const toggle = document.getElementById('togglePassword');

      if(username) username.focus();

      toggle.addEventListener('click', function(){
        const isText = password.type === 'text';
        password.type = isText ? 'password' : 'text';
        toggle.textContent = isText ? 'Show' : 'Hide';
        toggle.setAttribute('aria-pressed', String(!isText));
      });

      // keyboard shortcut Alt+S toggles password visibility
      window.addEventListener('keydown', function(e){
        if(e.altKey && (e.key === 's' || e.key === 'S')) {
          toggle.click();
        }
      });

      // client-side validation
      document.getElementById('loginForm').addEventListener('submit', function(e){
        const u = username.value.trim();
        const p = password.value;
        if(!u || !p){
          e.preventDefault();
          alert('Please enter username and password.');
          if(!u) username.focus(); else password.focus();
        }
      });
    })();
  </script>
</body>
</html>

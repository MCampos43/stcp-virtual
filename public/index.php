<?php require_once __DIR__.'/../config.php'; db();
if (user()) redirect('dashboard.php');
$error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $stmt=db()->prepare('SELECT * FROM users WHERE email=? AND active=1 LIMIT 1'); $stmt->execute([trim($_POST['email']??'')]); $u=$stmt->fetch();
    if ($u && password_verify($_POST['password']??'', $u['password'])) { $_SESSION['user_id']=$u['id']; redirect('dashboard.php'); }
    $error='Email ou palavra-passe incorretos.';
}
layout_start('Entrar'); ?>
<section class="login"><div class="login-card"><div class="hero-logo">STCP</div><h1>Portal STCP Virtual</h1><p class="muted">Gestão de motoristas, linhas e escalas.</p><?php if($error): ?><div class="error"><?=e($error)?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>Email<input type="email" name="email" required autocomplete="username"></label><label>Palavra-passe<input type="password" name="password" required autocomplete="current-password"></label><button class="btn primary">Entrar</button></form><div class="demo"><strong>Demo:</strong><br>Admin: admin@stcp.local / Admin123!<br>Motorista: motorista@stcp.local / Motorista123!</div></div></section>
<?php layout_end(); ?>

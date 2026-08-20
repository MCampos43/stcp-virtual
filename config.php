<?php
session_start();

const DB_FILE = __DIR__ . '/data/stcp.sqlite';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (!is_dir(dirname(DB_FILE))) mkdir(dirname(DB_FILE), 0777, true);
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        init_db($pdo);
    }
    return $pdo;
}

function init_db(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('admin','driver')),
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS lines (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        origin TEXT,
        destination TEXT,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        driver_id INTEGER NOT NULL,
        line_id INTEGER NOT NULL,
        service_date TEXT NOT NULL,
        start_time TEXT NOT NULL,
        end_time TEXT NOT NULL,
        notes TEXT,
        confirmed INTEGER NOT NULL DEFAULT 0,
        confirmed_at TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(driver_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(line_id) REFERENCES lines(id) ON DELETE CASCADE
    )");

    $count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)');
        $stmt->execute(['Administrador','admin@stcp.local',password_hash('Admin123!', PASSWORD_DEFAULT),'admin']);
    }
    $count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='driver'")->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)');
        $stmt->execute(['Motorista Demo','motorista@stcp.local',password_hash('Motorista123!', PASSWORD_DEFAULT),'driver']);
    }
    $count = (int)$pdo->query('SELECT COUNT(*) FROM lines')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO lines(code,name,origin,destination) VALUES(?,?,?,?)');
        $stmt->execute(['500','Porto – Matosinhos','Porto','Matosinhos']);
        $stmt->execute(['502','Bolhão – Aeroporto','Bolhão','Aeroporto']);
    }
    $count = (int)$pdo->query('SELECT COUNT(*) FROM schedules')->fetchColumn();
    if ($count === 0) {
        $driverId = (int)$pdo->query("SELECT id FROM users WHERE role='driver' ORDER BY id LIMIT 1")->fetchColumn();
        $lineId = (int)$pdo->query("SELECT id FROM lines ORDER BY id LIMIT 1")->fetchColumn();
        $date = date('Y-m-d');
        $stmt = $pdo->prepare('INSERT INTO schedules(driver_id,line_id,service_date,start_time,end_time,notes) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$driverId,$lineId,$date,'06:00','14:00','Turno de demonstração']);
    }
}

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function flash(?string $message = null): ?string {
    if ($message !== null) { $_SESSION['flash'] = $message; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}
function user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT * FROM users WHERE id=? AND active=1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}
function require_login(): array { $u=user(); if (!$u) redirect('index.php'); return $u; }
function require_admin(): array { $u=require_login(); if ($u['role'] !== 'admin') redirect('dashboard.php'); return $u; }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_check(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(403); exit('Pedido inválido.'); } }
function layout_start(string $title, ?array $u=null): void {
    $flash=flash();
?>
<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · STCP Virtual</title><link rel="stylesheet" href="style.css"></head><body>
<header class="top"><a href="dashboard.php" class="brand"><span class="brandmark">STCP</span><span>STCP Virtual</span></a><?php if($u): ?><nav><?php if($u['role']==='admin'): ?><a href="dashboard.php">Painel</a><a href="drivers.php">Motoristas</a><a href="lines.php">Linhas</a><a href="schedules.php">Escalas</a><?php else: ?><a href="dashboard.php">A minha chapa</a><?php endif; ?><span class="user">Olá, <?=e($u['name'])?></span><a class="logout" href="logout.php">Sair</a></nav><?php endif; ?></header>
<main class="container"><?php if($flash): ?><div class="flash"><?=e($flash)?></div><?php endif; ?>
<?php }
function layout_end(): void { echo '</main><footer>STCP Virtual · sistema demonstrativo</footer></body></html>'; }
?>

<?php
session_start();
// Canonical URL: /login/ → /login (301) supaya path relatif (asset, redirect) selalu resolve dari root
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if ($reqPath !== '/' && substr($reqPath, -1) === '/') {
    header('Location: ' . rtrim($reqPath, '/'), true, 301);
    exit;
}
require_once __DIR__ . '/config/database.php';
try {
    $cfgRows   = db()->query("SELECT key_name, key_value FROM config")->fetchAll(PDO::FETCH_KEY_PAIR);
    $namaKelas = htmlspecialchars($cfgRows['nama_kelas'] ?? 'RPL 1', ENT_QUOTES);
} catch (Throwable $e) {
    $namaKelas = 'RPL 1';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = db()->prepare('SELECT * FROM pengurus WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_nama'] = $user['nama'];
        header('Location: dashboard');
        exit;
    }
    $error = 'Username atau password salah';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Bendahara - Cashflow <?= $namaKelas ?></title>
    <script>
        (function() {
            const saved = localStorage.getItem('theme');
            const theme = saved ? saved : 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4 relative">
    <!-- Top Bar Theme Switcher -->
    <div class="absolute top-4 right-4">
        <button id="theme-toggle-btn" class="btn-secondary p-2 w-9 h-9 flex items-center justify-center rounded-lg cursor-pointer" title="Switch Theme">
            <i id="theme-toggle-icon" class="fa-solid fa-sun text-amber-500 text-sm"></i>
        </button>
    </div>

    <div class="w-full max-w-sm">
        <div class="brand-mark justify-center mb-8 gap-3">
            <div class="brand-icon w-8 h-8 text-sm">
                <i class="fa-solid fa-money-bill-wave"></i>
            </div>
            <span class="text-xl font-bold tracking-tight">Cashflow <?= $namaKelas ?></span>
        </div>

        <form method="POST" class="card-linear p-8">
            <div class="mb-6 text-center">
                <h1 class="display-md text-xl font-bold mb-1">Login Bendahara</h1>
                <p class="text-xs text-[var(--ink-muted)]">Masuk untuk mengelola keuangan kelas</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="badge-danger w-full justify-center px-3 py-2 rounded-md mb-4 text-xs font-medium flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation text-xs"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="space-y-4 mb-6">
                <div>
                    <label class="eyebrow block mb-1">Username</label>
                    <div class="relative">
                        <input name="username" placeholder="Masukkan username" required class="input-linear pl-9">
                        <i class="fa-solid fa-user absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[var(--ink-tertiary)]"></i>
                    </div>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Password</label>
                    <div class="relative">
                        <input name="password" type="password" placeholder="••••••••" required class="input-linear pl-9">
                        <i class="fa-solid fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[var(--ink-tertiary)]"></i>
                    </div>
                </div>
            </div>

            <button class="btn-primary w-full py-2.5 gap-2">
                <i class="fa-solid fa-right-to-bracket text-xs"></i>
                <span>Masuk ke Dashboard</span>
            </button>

            <div class="mt-6 text-center border-t border-[var(--hairline)] pt-4">
                <a href="index" class="text-xs text-[var(--ink-muted)] hover:text-[var(--ink)] transition-colors inline-flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span>Kembali ke Web Publik</span>
                </a>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $(function() {
            function updateThemeUI(theme) {
                if (theme === 'dark') {
                    $('#theme-toggle-icon').attr('class', 'fa-solid fa-moon text-indigo-400 text-sm');
                    $('#theme-toggle-btn').attr('title', 'Switch to Light Theme');
                } else {
                    $('#theme-toggle-icon').attr('class', 'fa-solid fa-sun text-amber-500 text-sm');
                    $('#theme-toggle-btn').attr('title', 'Switch to Dark Theme');
                }
            }
            const currentTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', currentTheme);
            updateThemeUI(currentTheme);

            $('#theme-toggle-btn').on('click', function() {
                const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                updateThemeUI(newTheme);
            });
        });
    </script>
</body>
</html>

<?php
session_start();
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = db()->prepare('SELECT * FROM pengurus WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_nama'] = $user['nama'];
        header('Location: admin_dashboard.php');
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
    <title>Login Bendahara - Cashflow Kelas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-[#010102] text-[#f7f8f8] min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="brand-mark justify-center mb-8">
            <div class="brand-icon w-8 h-8 text-sm">⚡</div>
            <span class="text-xl">Cashflow RPL 1</span>
        </div>

        <form method="POST" class="card-linear p-8 shadow-2xl">
            <div class="mb-6 text-center">
                <h1 class="display-md text-xl font-semibold mb-1">Login Bendahara</h1>
                <p class="text-xs text-[#8a8f98]">Masuk untuk mengelola keuangan kelas</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-3 py-2 rounded-md mb-4 text-xs font-medium flex items-center gap-2">
                    <span>⚠️</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="space-y-4 mb-6">
                <div>
                    <label class="eyebrow block mb-1">Username</label>
                    <input name="username" placeholder="Masukkan username" required class="input-linear">
                </div>
                <div>
                    <label class="eyebrow block mb-1">Password</label>
                    <input name="password" type="password" placeholder="••••••••" required class="input-linear">
                </div>
            </div>

            <button class="btn-primary w-full py-2.5">Masuk ke Dashboard</button>

            <div class="mt-6 text-center border-t border-[#23252a] pt-4">
                <a href="index.php" class="text-xs text-[#8a8f98] hover:text-[#f7f8f8] transition-colors">
                    ← Kembali ke Web Publik
                </a>
            </div>
        </form>
    </div>
</body>
</html>

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
    <title>Login Bendahara</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">
    <form method="POST" class="bg-white p-8 rounded-lg shadow w-80">
        <h1 class="text-xl font-bold mb-4 text-slate-800">Login Bendahara</h1>
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 text-red-700 p-2 rounded mb-3 text-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <input name="username" placeholder="Username" required class="w-full border p-2 mb-3 rounded">
        <input name="password" type="password" placeholder="Password" required class="w-full border p-2 mb-3 rounded">
        <button class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">Masuk</button>
    </form>
</body>
</html>

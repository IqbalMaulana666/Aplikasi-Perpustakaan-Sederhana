<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();

// Menentukan base path proyek secara dinamis
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if (preg_match('/[\\/](pages|auth|api|includes|config)$/i', $base_path)) {
    $base_path = dirname($base_path);
}
$base_path = rtrim(str_replace('\\', '/', $base_path), '/');

if (isset($_SESSION['user_id'])) {
    header("Location: " . $base_path . "/index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        $query = "SELECT * FROM users WHERE username = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                // Log activity
                $action = 'login';
                $details = 'Admin login: ' . $username;
                $log_query = "INSERT INTO activity_logs (admin_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param("iss", $user['id'], $action, $details);
                $log_stmt->execute();

                header("Location: " . $base_path . "/pages/dashboard.php");
                exit;
            } else {
                $error = 'Username atau password salah';
            }
        } else {
            $error = 'Username atau password salah';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <script>
        // Don't apply dark mode on login page
        localStorage.removeItem('theme');
        document.documentElement.classList.remove('dark-mode');
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BukuKita - SMP TAQ SADAMIYYAH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="app-icon">
                    <i class="fas fa-book"></i>
                </div>
                <h1>Selamat Datang di BukuKita</h1>
                <p>Kelola Perpustakaan Sekolah Jadi Lebih Mudah</p>
                <div class="school-name">SMP TAQ SADAMIYYAH</div>
            </div>

            <?php if ($error): ?>
                <div class="login-alert" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <div class="login-form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Masukkan username" required autofocus>
                </div>

                <div class="login-form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Masukkan password" required>
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Masuk</span>
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

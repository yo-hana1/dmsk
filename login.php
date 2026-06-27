<?php
// e:\dmsk 29-4-26\login.php
session_start();

// Jika sudah login, langsung arahkan ke dashboard
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validasi sederhana: mengizinkan masuk untuk keperluan prototipe/demo
    // Default akun: admin@wkp.co.id / admin
    if (!empty($email) && !empty($password)) {
        $_SESSION['user'] = [
            'email' => $email,
            'name' => ($email === 'admin@wkp.co.id') ? 'Administrator' : explode('@', $email)[0],
            'role' => ($email === 'admin@wkp.co.id') ? 'Administrator' : 'User'
        ];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = 'Email dan password tidak boleh kosong!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WKP DMS - Sign In</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .login-page {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #8B0000 0%, #343A40 100%);
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: var(--primary);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .login-header p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .input-group-custom {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-group-custom i {
            position: absolute;
            left: 12px;
            color: #adb5bd;
            font-size: 1.1rem;
        }
        .input-group-custom input {
            padding-left: 40px;
        }
        .error-message {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            text-align: center;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-header">
                <h1>WKP DMS</h1>
                <p>PT. Wijaya Kusuma Perdana</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-group-custom">
                        <i class="bi bi-envelope"></i>
                        <input type="email" name="email" id="email" class="form-control" placeholder="email@wkp.co.id" required value="admin@wkp.co.id">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group-custom">
                        <i class="bi bi-lock"></i>
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required value="admin">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px; justify-content: center;">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                </button>
            </form>

            <div style="margin-top: 25px; text-align: center; font-size: 0.85rem;">
                <a href="#" style="color: var(--primary); font-weight: 500;">Lupa password?</a>
            </div>
        </div>
    </div>
</body>
</html>

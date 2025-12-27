<?php
/**
 * Admin Login
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Already logged in?
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (login($username, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LiveDJ Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0d0d0d;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 400px;
            width: 100%;
        }
        h1 {
            font-size: 28px;
            margin-bottom: 8px;
            text-align: center;
        }
        .subtitle {
            color: #888;
            margin-bottom: 40px;
            text-align: center;
        }
        .card {
            background: #1a1a1a;
            border-radius: 12px;
            padding: 32px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        input {
            width: 100%;
            padding: 12px 16px;
            font-size: 16px;
            background: #0d0d0d;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-family: inherit;
        }
        input:focus {
            outline: none;
            border-color: #666;
        }
        button {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            background: #fff;
            color: #0d0d0d;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
        }
        button:hover {
            background: #eee;
        }
        .error {
            background: #7f1d1d;
            border: 1px solid #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>LiveDJ</h1>
        <p class="subtitle">Admin Panel</p>

        <div class="card">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>

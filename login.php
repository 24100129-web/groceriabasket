<?php
require __DIR__ . '/app_state.php';
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $normalizedEmail = strtolower($email);
    $demoLoginsDisabled = getenv('GROCERIA_DISABLE_DEMO_LOGINS') === '1';
    $isDemoCredential = ($normalizedEmail === 'admin@groceriabasket.com' && $password === 'admin123')
        || ($normalizedEmail === 'buyer@groceriabasket.com' && $password === 'buyer123');
    $adminLogin = !$demoLoginsDisabled && $normalizedEmail === 'admin@groceriabasket.com' && $password === 'admin123';
    $buyerLogin = !$demoLoginsDisabled && $normalizedEmail === 'buyer@groceriabasket.com' && $password === 'buyer123';
    $account = null;
    if (!$isDemoCredential && !$adminLogin && !$buyerLogin && ($db = groceria_db())) {
        $statement = $db->prepare('SELECT id, full_name, email, role, password_hash FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => strtolower($email)]);
        $storedAccount = $statement->fetch();
        if ($storedAccount && password_verify($password, $storedAccount['password_hash']) && in_array($storedAccount['role'], ['admin', 'buyer'], true)) {
            $account = $storedAccount;
            $adminLogin = $storedAccount['role'] === 'admin';
            $buyerLogin = $storedAccount['role'] === 'buyer';
        }
    }
    if (!$isDemoCredential && !$adminLogin && !$buyerLogin && !$db) {
        $storedAccount = groceria_find_customer_by_email($email);
        if ($storedAccount && password_verify($password, $storedAccount['password_hash'])) {
            $account = $storedAccount;
            $buyerLogin = true;
        }
    }
    if ($adminLogin || $buyerLogin) {
        session_regenerate_id(true);
        $_SESSION['groceria_user'] = [
            'id' => isset($account['id']) ? (string) $account['id'] : null,
            'email' => strtolower($email),
            'role' => $adminLogin ? 'admin' : 'buyer',
            'name' => $account['full_name'] ?? ($adminLogin ? 'Angela Mercado' : 'Groceria Buyer'),
        ];
        $isDemoAdmin = $adminLogin && strtolower($email) === 'admin@groceriabasket.com';
        if ($isDemoAdmin || empty($_SESSION['groceria_user']['id'])) {
            $_SESSION['groceria_user']['id'] = groceria_resolve_user_id(strtolower($email), $_SESSION['groceria_user']['name'], $_SESSION['groceria_user']['role'], $password);
        }
        header('Location: ' . ($adminLogin ? 'dashboard.php' : 'bundles.php'));
        exit;
    }
    if ($email === '' || $password === '') {
        $loginError = 'Enter your email and password to continue.';
    } else {
        $loginError = 'Incorrect email or password.';
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | Groceria Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>
<body class="login-page">
    <main class="login-scene">
        <section class="login-card" aria-labelledby="login-title">
            <div class="login-brand"><img class="custom-logo" src="images/logo.png" alt="Groceria Basket logo"></div>
            <p class="login-kicker">Smart store management for growing groceries</p>
            <h1 id="login-title">Welcome back</h1>
            <p class="login-copy">Sign in to manage your store, inventory, and daily sales.</p>
            <?php if ($loginError !== ''): ?><div class="login-error"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
            <form method="post" action="login.php" class="login-form">
                <label for="email">Email or username</label>
                <div class="input-wrap"><span>✉</span><input id="email" name="email" type="email" placeholder="Enter your email" autocomplete="email" required></div>
                <label for="password">Password</label>
                <div class="input-wrap"><span>♙</span><input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required><button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password">◉</button></div>
                <div class="form-options"><a href="#" data-forgot-password>Forgot password?</a></div>
                <button class="login-button" type="submit">Sign in <span>→</span></button>
            </form>
            <p class="login-register-link">New customer? <a href="register.php">Create an account</a></p>
            <footer><b>GROCERIA BASKET</b><span>Prototype workspace · 2026</span></footer>
        </section>
    </main>
    <script>
        const password = document.getElementById('password');
        const toggle = document.getElementById('passwordToggle');
        toggle.addEventListener('click', () => {
            const visible = password.type === 'text';
            password.type = visible ? 'password' : 'text';
            toggle.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
        });
        document.querySelector('[data-forgot-password]').addEventListener('click', (event) => {
            event.preventDefault();
        });
    </script>
</body>
</html>

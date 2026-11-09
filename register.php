<?php
require __DIR__ . '/app_state.php';
if (($_SESSION['groceria_user']['role'] ?? '') === 'buyer') {
    header('Location: profile.php');
    exit;
}
$error = '';
$form = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(groceria_csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your form expired. Refresh the page and try again.';
    } else {
        $profile = [
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'contact_number' => trim((string) ($_POST['contact_number'] ?? '')),
            'house_street' => trim((string) ($_POST['house_street'] ?? '')),
            'barangay' => trim((string) ($_POST['barangay'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'province' => trim((string) ($_POST['province'] ?? '')),
            'zip_code' => trim((string) ($_POST['zip_code'] ?? '')),
            'address_details' => trim((string) ($_POST['address_details'] ?? '')),
        ];
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        if (!preg_match('/^[0-9+()\-\s]{7,20}$/', $profile['contact_number'])) {
            $error = 'Enter a valid contact number.';
        } elseif (!preg_match('/^[A-Za-z0-9 -]{3,12}$/', $profile['zip_code'])) {
            $error = 'Enter a valid ZIP or postal code.';
        } elseif ($password !== $confirmPassword) {
            $error = 'The passwords do not match.';
        } else {
            try {
                $userId = groceria_register_customer($profile, $password);
                session_regenerate_id(true);
                $_SESSION['groceria_user'] = ['id' => $userId, 'email' => strtolower($profile['email']), 'role' => 'buyer', 'name' => $profile['full_name']];
                header('Location: bundles.php');
                exit;
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            } catch (PDOException $exception) {
                $error = 'Your account could not be created. Check the information and try again.';
            }
        }
    }
}
$csrfToken = groceria_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Buyer Account | Groceria Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="css/login.css"><link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page registration-page">
<main class="registration-scene"><section class="registration-card"><a class="registration-brand" href="login.php">Groceria<span>Basket</span></a><span class="eyebrow">Buyer account</span><h1>Create your account</h1><p>Save your contact and delivery address for checkout.</p>
<?php if ($error !== ''): ?><div class="login-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="registration-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
<label>Full name<input name="full_name" autocomplete="name" required value="<?= htmlspecialchars((string) ($form['full_name'] ?? '')) ?>"></label>
<label>Email address<input type="email" name="email" autocomplete="email" required value="<?= htmlspecialchars((string) ($form['email'] ?? '')) ?>"></label>
<label>Contact number<input type="tel" name="contact_number" autocomplete="tel" required value="<?= htmlspecialchars((string) ($form['contact_number'] ?? '')) ?>"></label>
<label>Password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
<label>Confirm password<input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required></label>
<fieldset><legend>Saved delivery address</legend>
<label>House / unit number and street<input name="house_street" autocomplete="address-line1" required value="<?= htmlspecialchars((string) ($form['house_street'] ?? '')) ?>"></label>
<label>Barangay<input name="barangay" required value="<?= htmlspecialchars((string) ($form['barangay'] ?? '')) ?>"></label>
<div class="registration-address-row"><label>City / Municipality<input name="city" autocomplete="address-level2" required value="<?= htmlspecialchars((string) ($form['city'] ?? '')) ?>"></label><label>Province<input name="province" autocomplete="address-level1" required value="<?= htmlspecialchars((string) ($form['province'] ?? '')) ?>"></label></div>
<div class="registration-address-row"><label>ZIP / Postal code<input name="zip_code" autocomplete="postal-code" required value="<?= htmlspecialchars((string) ($form['zip_code'] ?? '')) ?>"></label><label>Landmark / additional details <small>(optional)</small><input name="address_details" value="<?= htmlspecialchars((string) ($form['address_details'] ?? '')) ?>"></label></div>
</fieldset><button class="login-button" type="submit">Create account <span>→</span></button></form>
<p class="login-register-link">Already registered? <a href="login.php">Sign in</a></p></section></main>
</body></html>

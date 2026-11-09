<?php
require __DIR__ . '/app_state.php';
if (($_SESSION['groceria_user']['role'] ?? '') !== 'buyer') {
    header('Location: login.php');
    exit;
}
$profile = groceria_get_customer_profile();
$error = '';
$saved = isset($_GET['saved']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(groceria_csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your form expired. Refresh the page and try again.';
    } else {
        $profile = [
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'contact_number' => trim((string) ($_POST['contact_number'] ?? '')),
            'house_street' => trim((string) ($_POST['house_street'] ?? '')),
            'barangay' => trim((string) ($_POST['barangay'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'province' => trim((string) ($_POST['province'] ?? '')),
            'zip_code' => trim((string) ($_POST['zip_code'] ?? '')),
            'address_details' => trim((string) ($_POST['address_details'] ?? '')),
        ];
        if (!preg_match('/^[0-9+()\-\s]{7,20}$/', $profile['contact_number'])) {
            $error = 'Enter a valid contact number.';
        } elseif (!preg_match('/^[A-Za-z0-9 -]{3,12}$/', $profile['zip_code'])) {
            $error = 'Enter a valid ZIP or postal code.';
        } else {
            try {
                groceria_save_customer_profile($profile);
                header('Location: profile.php?saved=1');
                exit;
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }
    }
}
$csrfToken = groceria_csrf_token();
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>My Account | Groceria Basket</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="css/style.css"></head>
<body class="bundle-storefront"><header class="shop-header"><a class="shop-brand" href="bundles.php">Groceria<span>Basket</span></a><nav><a href="bundles.php">Shop Bundles</a><a href="cart.php">Cart</a><a href="buyer-orders.php">My Orders</a><a class="active" href="profile.php">My Account</a><a href="logout.php">Log out</a></nav></header>
<main class="shop-main profile-page"><section class="shop-hero"><div><span class="eyebrow">Buyer account</span><h1>Personal information</h1><p>Update your contact details and saved delivery address.</p></div></section>
<?php if ($saved): ?><p class="profile-success">Your personal information and saved address have been updated.</p><?php endif; ?><?php if ($error !== ''): ?><p class="checkout-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" class="profile-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><label>Full name<input name="full_name" required value="<?= htmlspecialchars((string) ($profile['full_name'] ?? '')) ?>"></label><label>Email address<input type="email" readonly value="<?= htmlspecialchars((string) ($profile['email'] ?? ($_SESSION['groceria_user']['email'] ?? ''))) ?>"></label><label>Contact number<input name="contact_number" type="tel" required value="<?= htmlspecialchars((string) ($profile['contact_number'] ?? '')) ?>"></label><fieldset><legend>Default delivery address</legend><label>House / unit number and street<input name="house_street" required value="<?= htmlspecialchars((string) ($profile['house_street'] ?? '')) ?>"></label><label>Barangay<input name="barangay" required value="<?= htmlspecialchars((string) ($profile['barangay'] ?? '')) ?>"></label><div class="registration-address-row"><label>City / Municipality<input name="city" required value="<?= htmlspecialchars((string) ($profile['city'] ?? '')) ?>"></label><label>Province<input name="province" required value="<?= htmlspecialchars((string) ($profile['province'] ?? '')) ?>"></label></div><div class="registration-address-row"><label>ZIP / Postal code<input name="zip_code" required value="<?= htmlspecialchars((string) ($profile['zip_code'] ?? '')) ?>"></label><label>Landmark / additional details <small>(optional)</small><input name="address_details" value="<?= htmlspecialchars((string) ($profile['address_details'] ?? '')) ?>"></label></div></fieldset><button class="shop-checkout" type="submit">Save profile</button></form></main></body></html>

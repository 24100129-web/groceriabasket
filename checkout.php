<?php
require __DIR__ . '/app_state.php';
if (($_SESSION['groceria_user']['role'] ?? '') !== 'buyer') {
    header('Location: login.php');
    exit;
}

$mode = (string) ($_POST['mode'] ?? $_GET['mode'] ?? 'cart');
$isBuyNow = $mode === 'buy-now';
$profile = groceria_get_customer_profile();
$profileFields = ['full_name', 'contact_number', 'house_street', 'barangay', 'city', 'province', 'zip_code'];
$hasSavedAddress = true;
foreach ($profileFields as $profileField) {
    if (trim((string) ($profile[$profileField] ?? '')) === '') {
        $hasSavedAddress = false;
        break;
    }
}
$deliverDifferent = ($_POST['deliver_different'] ?? '') === '1';
$cart = groceria_cart();
$cartById = [];
foreach ($cart as $entry) {
    $cartById[(int) $entry['bundle_id']] = $entry;
}
$error = '';
$placedOrder = (string) ($_GET['placed'] ?? '');
$checkoutItems = [];
$submittedItems = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['groceria_csrf'] ?? ''), $submittedToken)) {
        $error = 'Your checkout expired. Please reload and try again.';
    } else {
        $submittedItems = json_decode((string) ($_POST['checkout_items'] ?? '[]'), true) ?: [];
        if ($isBuyNow) {
            $buyBundleId = (int) ($_POST['buy_bundle_id'] ?? 0);
            $submittedItems = array_values(array_filter($submittedItems, static fn ($item) => (int) ($item['bundle_id'] ?? 0) === $buyBundleId));
        } else {
            $selectedIds = array_unique(array_map(static fn ($item) => (int) ($item['bundle_id'] ?? 0), $submittedItems));
            $submittedItems = [];
            foreach ($selectedIds as $selectedId) {
                if (isset($cartById[$selectedId])) {
                    $submittedItems[] = ['bundle_id' => $selectedId, 'quantity' => (int) $cartById[$selectedId]['quantity']];
                }
            }
        }
        $accountName = trim((string) ($profile['full_name'] ?? $_SESSION['groceria_user']['name'] ?? ''));
        $accountContact = trim((string) ($profile['contact_number'] ?? ''));
        if ($deliverDifferent || !$hasSavedAddress) {
            $prefix = $deliverDifferent ? 'alt_' : 'delivery_';
            $deliveryProfile = [
                'full_name' => trim((string) ($_POST[$prefix . 'name'] ?? '')),
                'contact_number' => trim((string) ($_POST[$prefix . 'contact'] ?? '')),
                'house_street' => trim((string) ($_POST[$prefix . 'house_street'] ?? '')),
                'barangay' => trim((string) ($_POST[$prefix . 'barangay'] ?? '')),
                'city' => trim((string) ($_POST[$prefix . 'city'] ?? '')),
                'province' => trim((string) ($_POST[$prefix . 'province'] ?? '')),
                'zip_code' => trim((string) ($_POST[$prefix . 'zip_code'] ?? '')),
                'address_details' => trim((string) ($_POST[$prefix . 'details'] ?? '')),
            ];
        } else {
            $deliveryProfile = $profile;
        }
        $deliveryAddress = implode(', ', array_filter([
            $deliveryProfile['house_street'] ?? '',
            !empty($deliveryProfile['barangay']) ? 'Barangay ' . $deliveryProfile['barangay'] : '',
            $deliveryProfile['city'] ?? '',
            $deliveryProfile['province'] ?? '',
            $deliveryProfile['zip_code'] ?? '',
        ]));
        $recipient = trim((string) ($deliveryProfile['full_name'] ?? ''));
        $deliveryContact = trim((string) ($deliveryProfile['contact_number'] ?? ''));
        $deliveryInstructions = trim((string) ($deliveryProfile['address_details'] ?? ''));
        $requiredDeliveryFields = ['full_name', 'contact_number', 'house_street', 'barangay', 'city', 'province', 'zip_code'];
        $deliveryValid = true;
        foreach ($requiredDeliveryFields as $field) {
            if (trim((string) ($deliveryProfile[$field] ?? '')) === '') {
                $deliveryValid = false;
                break;
            }
        }
        if (!$deliveryValid || !preg_match('/^[0-9+()\-\s]{7,20}$/', $deliveryContact) || !preg_match('/^[A-Za-z0-9 -]{3,12}$/', (string) ($deliveryProfile['zip_code'] ?? '')) || !$submittedItems) {
            $error = 'Complete the required delivery details with a valid contact number and postal code.';
        } else {
            try {
                $purchasedIds = array_map(static fn ($item) => (int) $item['bundle_id'], $submittedItems);
                $orderNumber = groceria_record_order([
                    'buyer_name' => $accountName !== '' ? $accountName : $recipient,
                    'buyer_contact' => $accountContact !== '' ? $accountContact : $deliveryContact,
                    'delivery_recipient' => $recipient,
                    'delivery_contact' => $deliveryContact,
                    'delivery_address' => $deliveryAddress,
                    'delivery_instructions' => $deliveryInstructions,
                    'items' => $submittedItems,
                    'checkout_token' => (string) ($_POST['checkout_token'] ?? ''),
                    'remove_cart_ids' => $isBuyNow ? [] : $purchasedIds,
                ]);
                if (!$hasSavedAddress && !$deliverDifferent && isset($_POST['save_default_address'])) {
                    groceria_save_customer_profile([
                        'full_name' => $deliveryProfile['full_name'], 'contact_number' => $deliveryProfile['contact_number'],
                        'house_street' => $deliveryProfile['house_street'], 'barangay' => $deliveryProfile['barangay'],
                        'city' => $deliveryProfile['city'], 'province' => $deliveryProfile['province'],
                        'zip_code' => $deliveryProfile['zip_code'], 'address_details' => $deliveryProfile['address_details'],
                    ]);
                } elseif ($deliverDifferent && isset($_POST['save_alternative_address'])) {
                    groceria_save_customer_profile([
                        'full_name' => $deliveryProfile['full_name'], 'contact_number' => $deliveryProfile['contact_number'],
                        'house_street' => $deliveryProfile['house_street'], 'barangay' => $deliveryProfile['barangay'],
                        'city' => $deliveryProfile['city'], 'province' => $deliveryProfile['province'],
                        'zip_code' => $deliveryProfile['zip_code'], 'address_details' => $deliveryProfile['address_details'],
                    ]);
                }
                if (!$isBuyNow) {
                    $remainingCart = array_values(array_filter($cart, static fn ($item) => !in_array((int) $item['bundle_id'], $purchasedIds, true)));
                    groceria_save_cart(array_map(static fn ($item) => ['bundle_id' => $item['bundle_id'], 'quantity' => $item['quantity']], $remainingCart));
                }
                header('Location: checkout.php?placed=' . rawurlencode($orderNumber));
                exit;
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }
    }
}

if ($placedOrder === '') {
    if ($isBuyNow) {
        $bundleId = (int) ($_POST['buy_bundle_id'] ?? $_GET['bundle_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['buy_quantity'] ?? $_GET['quantity'] ?? 1));
        foreach (groceria_bundles() as $bundle) {
            if ((int) $bundle['id'] === $bundleId) {
                $checkoutItems[] = ['bundle_id' => $bundleId, 'name' => $bundle['name'], 'image' => $bundle['image'] ?? '', 'price' => (float) $bundle['price'], 'quantity' => $quantity];
                break;
            }
        }
    } else {
        $selectedIds = $_SERVER['REQUEST_METHOD'] === 'POST'
            ? array_map(static fn ($item) => (int) ($item['bundle_id'] ?? 0), $submittedItems)
            : array_map('intval', $_GET['bundle_ids'] ?? array_keys($cartById));
        foreach ($selectedIds as $id) {
            if (isset($cartById[$id])) {
                $checkoutItems[] = $cartById[$id];
            }
        }
    }
    foreach ($checkoutItems as $item) {
        $bundle = null;
        foreach (groceria_bundles() as $candidate) {
            if ((int) $candidate['id'] === (int) $item['bundle_id']) { $bundle = $candidate; break; }
        }
        if (!$bundle || (int) $item['quantity'] > groceria_bundle_available_count($bundle)) {
            $error = 'A selected bundle is no longer available in the requested quantity.';
            break;
        }
    }
}

$csrfToken = groceria_csrf_token();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['groceria_checkout_token']) || $placedOrder !== '') {
    $_SESSION['groceria_checkout_token'] = bin2hex(random_bytes(32));
}
$total = array_sum(array_map(static fn ($item) => (float) $item['price'] * (int) $item['quantity'], $checkoutItems));
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Checkout | Groceria Basket</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="css/style.css"></head>
<body class="bundle-storefront"><header class="shop-header"><a class="shop-brand" href="bundles.php">Groceria<span>Basket</span></a><nav><a href="bundles.php">Shop Bundles</a><a href="cart.php">Cart</a><a href="buyer-orders.php">My Orders</a><a href="profile.php">My Account</a><a href="logout.php">Log out</a></nav></header>
<main class="shop-main checkout-page"><section class="shop-hero"><div><span class="eyebrow">Secure checkout</span><h1><?= $placedOrder !== '' ? 'Order received' : 'Checkout' ?></h1><p><?= $placedOrder !== '' ? 'Your order is saved and ready to track.' : ($isBuyNow ? 'Review your selected bundle before placing the order.' : 'Confirm the selected bundles and delivery details.') ?></p></div></section>
<?php if ($placedOrder !== ''): ?><section class="buyer-empty-state"><h2>Order <?= htmlspecialchars($placedOrder) ?> placed</h2><p>Track the order status and delivery details from My Orders.</p><a class="shop-checkout buyer-link-button" href="buyer-orders.php">Track order</a></section>
<?php elseif (!$checkoutItems): ?><section class="buyer-empty-state"><h2>No bundles selected</h2><p>Add bundles to your cart or return to the shop.</p><a class="shop-checkout buyer-link-button" href="bundles.php">Shop bundles</a></section>
<?php else: ?>
<?php if ($error !== ''): ?><p class="checkout-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" class="checkout-layout"><input type="hidden" name="place_order" value="1"><input type="hidden" name="mode" value="<?= $isBuyNow ? 'buy-now' : 'cart' ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="checkout_token" value="<?= htmlspecialchars($_SESSION['groceria_checkout_token']) ?>"><input type="hidden" name="checkout_items" value="<?= htmlspecialchars(json_encode(array_map(static fn ($item) => ['bundle_id' => $item['bundle_id'], 'quantity' => $item['quantity']], $checkoutItems)), ENT_QUOTES) ?>"><input type="hidden" name="buy_bundle_id" value="<?= $isBuyNow ? (int) $checkoutItems[0]['bundle_id'] : 0 ?>">
<section class="checkout-items"><h2>Order summary</h2><?php foreach ($checkoutItems as $item): ?><article class="checkout-item"><?php if ($item['image'] && str_contains((string) $item['image'], '/')): ?><img src="<?= htmlspecialchars((string) $item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>"><?php else: ?><div class="checkout-item-placeholder">GB</div><?php endif; ?><div class="checkout-item-info"><h3><?= htmlspecialchars($item['name']) ?></h3><span><?= groceria_format_money((float) $item['price']) ?> each · Qty <?= (int) $item['quantity'] ?></span></div><strong><?= groceria_format_money((float) $item['price'] * (int) $item['quantity']) ?></strong></article><?php endforeach; ?><div class="checkout-grand-total"><span>Total</span><strong><?= groceria_format_money($total) ?></strong></div></section>
<section class="checkout-buyer"><h2>Delivery Address</h2>
<?php if ($hasSavedAddress): ?>
<div class="saved-delivery-address"><strong><?= htmlspecialchars((string) $profile['full_name']) ?></strong><span><?= htmlspecialchars((string) $profile['contact_number']) ?></span><address><?= htmlspecialchars(implode("\n", array_filter([$profile['house_street'], 'Barangay ' . $profile['barangay'], implode(', ', array_filter([$profile['city'], $profile['province'], $profile['zip_code']]))]))) ?></address><?php if (!empty($profile['address_details'])): ?><small>Landmark: <?= htmlspecialchars((string) $profile['address_details']) ?></small><?php endif; ?><a href="profile.php">Edit saved address</a></div>
<?php else: ?>
<p class="address-needed-note">Add your delivery address to place this order.</p>
<div class="default-address-entry" id="defaultAddressEntry"><h3>Save as my default address</h3>
<label>Recipient full name<input name="delivery_name" required value="<?= htmlspecialchars((string) ($_POST['delivery_name'] ?? ($_SESSION['groceria_user']['name'] ?? ''))) ?>"></label><label>Contact number<input type="tel" name="delivery_contact" required value="<?= htmlspecialchars((string) ($_POST['delivery_contact'] ?? '')) ?>"></label><label>House / unit number and street<input name="delivery_house_street" required value="<?= htmlspecialchars((string) ($_POST['delivery_house_street'] ?? '')) ?>"></label><label>Barangay<input name="delivery_barangay" required value="<?= htmlspecialchars((string) ($_POST['delivery_barangay'] ?? '')) ?>"></label><div class="registration-address-row"><label>City / Municipality<input name="delivery_city" required value="<?= htmlspecialchars((string) ($_POST['delivery_city'] ?? '')) ?>"></label><label>Province<input name="delivery_province" required value="<?= htmlspecialchars((string) ($_POST['delivery_province'] ?? '')) ?>"></label></div><div class="registration-address-row"><label>ZIP / Postal code<input name="delivery_zip_code" required value="<?= htmlspecialchars((string) ($_POST['delivery_zip_code'] ?? '')) ?>"></label><label>Landmark / details<input name="delivery_details" value="<?= htmlspecialchars((string) ($_POST['delivery_details'] ?? '')) ?>"></label></div>
<label class="address-save-choice"><input type="checkbox" name="save_default_address" value="1" checked> Save this address to my account</label></div>
<?php endif; ?>
<label class="different-address-choice"><input type="checkbox" id="deliverDifferentAddress" name="deliver_different" value="1" <?= $deliverDifferent ? 'checked' : '' ?>> Deliver to a different address</label>
<fieldset class="alternative-address-form" id="alternativeAddressForm" <?= $deliverDifferent ? '' : 'hidden' ?>><legend>Alternative delivery address</legend>
<label>Recipient's full name<input name="alt_name" <?= $deliverDifferent ? 'required' : 'disabled' ?> value="<?= htmlspecialchars((string) ($_POST['alt_name'] ?? '')) ?>"></label><label>Recipient's contact number<input type="tel" name="alt_contact" <?= $deliverDifferent ? 'required' : 'disabled' ?> value="<?= htmlspecialchars((string) ($_POST['alt_contact'] ?? '')) ?>"></label><label>House / unit number and street<input name="alt_house_street" <?= $deliverDifferent ? 'required' : 'disabled' ?> value="<?= htmlspecialchars((string) ($_POST['alt_house_street'] ?? '')) ?>"></label><label>Barangay<input name="alt_barangay" <?= $deliverDifferent ? 'required' : 'disabled' ?> value="<?= htmlspecialchars((string) ($_POST['alt_barangay'] ?? '')) ?>"></label><div class="registration-address-row"><label>City / Municipality<input name="alt_city" <?= $deliverDifferent ? 'required' : 'disabled' ?> value="<?= htmlspecialchars((string) ($_POST['alt_city'] ?? '')) ?>"></label><label>Province<input name="alt_province" <?= $deliverDifferent ? 'required' : 'disabled' ?> value="<?= htmlspecialchars((string) ($_POST['alt_province'] ?? '')) ?>"></label></div><div class="registration-address-row"><label>ZIP / Postal code<input name="alt_zip_code" <?= $deliverDifferent ? 'required' : 'disabled' ?> value="<?= htmlspecialchars((string) ($_POST['alt_zip_code'] ?? '')) ?>"></label><label>Additional instructions / landmark<input name="alt_details" value="<?= htmlspecialchars((string) ($_POST['alt_details'] ?? '')) ?>"></label></div><label class="address-save-choice"><input type="checkbox" name="save_alternative_address" value="1" <?= isset($_POST['save_alternative_address']) ? 'checked' : '' ?>> Also save this as my default address</label>
</fieldset>
<button class="shop-checkout" type="submit">Place order</button><a class="checkout-back-link" href="<?= $isBuyNow ? 'bundles.php' : 'cart.php' ?>">Return</a></section></form>
<?php endif; ?></main><script src="js/script.js"></script></body></html>

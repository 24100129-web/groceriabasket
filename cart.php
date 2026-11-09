<?php
require __DIR__ . '/app_state.php';
if (($_SESSION['groceria_user']['role'] ?? '') !== 'buyer') {
    header('Location: login.php');
    exit;
}
$cart = groceria_cart();
$total = array_sum(array_map(static fn ($item) => $item['price'] * $item['quantity'], $cart));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Cart | Groceria Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="css/style.css"><meta name="csrf-token" content="<?= htmlspecialchars(groceria_csrf_token()) ?>">
</head>
<body class="bundle-storefront">
<header class="shop-header"><a class="shop-brand" href="bundles.php">Groceria<span>Basket</span></a><nav><a href="bundles.php">Shop Bundles</a><a class="active" href="cart.php">Cart</a><a href="buyer-orders.php">My Orders</a><a href="profile.php">My Account</a><a href="logout.php">Log out</a></nav></header>
<main class="shop-main buyer-cart-page"><section class="shop-hero"><div><span class="eyebrow">Your basket</span><h1>Shopping Cart</h1><p>Review your bundles and choose which items to check out.</p></div></section>
<?php if (!$cart): ?><section class="buyer-empty-state"><h2>Your cart is empty</h2><p>Browse the available bundles and add your favorites.</p><a class="shop-checkout buyer-link-button" href="bundles.php">Shop bundles</a></section>
<?php else: ?><form method="get" action="checkout.php" id="cartCheckoutForm"><section class="buyer-cart-list">
<?php foreach ($cart as $item): ?>
<article class="buyer-cart-item" data-cart-item data-bundle-id="<?= (int) $item['bundle_id'] ?>" data-unit-price="<?= (float) $item['price'] ?>"><label class="cart-select"><input type="checkbox" name="bundle_ids[]" value="<?= (int) $item['bundle_id'] ?>" checked><span class="sr-only">Select <?= htmlspecialchars($item['name']) ?></span></label>
<?php if ($item['image'] && str_contains($item['image'], '/')): ?><img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="buyer-item-image"><?php else: ?><div class="buyer-item-image buyer-image-placeholder" aria-hidden="true">GB</div><?php endif; ?>
<div class="buyer-cart-item-info"><h2><?= htmlspecialchars($item['name']) ?></h2><span><?= groceria_format_money((float) $item['price']) ?> each</span></div><div class="cart-quantity-control"><button type="button" data-cart-action="decrease" aria-label="Decrease quantity">−</button><span data-cart-quantity><?= (int) $item['quantity'] ?></span><button type="button" data-cart-action="increase" aria-label="Increase quantity">+</button></div><strong class="cart-item-subtotal"><?= groceria_format_money((float) $item['price'] * $item['quantity']) ?></strong><button type="button" class="shop-remove" data-cart-action="remove" aria-label="Remove <?= htmlspecialchars($item['name']) ?>">×</button></article>
<?php endforeach; ?></section><div class="buyer-cart-footer"><a href="bundles.php">Continue shopping</a><div><span>Selected cart total</span><strong id="buyerCartTotal"><?= groceria_format_money($total) ?></strong><button type="submit" class="shop-checkout">Proceed to checkout</button></div></div></form><?php endif; ?></main>
<script src="js/script.js"></script></body></html>

<?php
require __DIR__ . '/app_state.php';

if (($_SESSION['groceria_user']['role'] ?? '') !== 'buyer') {
    header('Location: login.php');
    exit;
}

$pageTitle = 'My Orders';
$orders = array_reverse(groceria_orders());
$statuses = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Completed', 'Cancelled'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> | Groceria Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bundle-storefront">
    <header class="shop-header"><a class="shop-brand" href="bundles.php">Groceria<span>Basket</span></a><nav><a href="bundles.php">Shop Bundles</a><a href="cart.php">Cart</a><a class="active" href="buyer-orders.php">My Orders</a><a href="profile.php">My Account</a><a href="logout.php">Log out</a></nav></header>
    <main class="shop-main buyer-orders-page">
        <section class="shop-hero"><div><span class="eyebrow">Order tracking</span><h1>My Orders</h1><p>Track your bundle request from confirmation to delivery.</p></div></section>
        <?php if (!$orders): ?>
            <section class="buyer-empty-state"><h2>No orders yet</h2><p>Your purchased bundles will appear here.</p><a class="shop-checkout buyer-link-button" href="bundles.php">Shop bundles</a></section>
        <?php else: ?>
            <section class="buyer-order-list">
                <?php foreach ($orders as $order): ?>
                    <article class="buyer-order-card">
                        <div class="buyer-order-heading"><div><span class="eyebrow">Order <?= htmlspecialchars((string) $order['order_number']) ?></span><h2><?= htmlspecialchars((string) $order['created_at']) ?></h2></div><span class="order-status status-<?= strtolower(str_replace(' ', '-', (string) $order['status'])) ?>"><?= htmlspecialchars((string) $order['status']) ?></span></div>
                        <div class="buyer-order-items">
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="buyer-order-item">
                                    <?php if (!empty($item['image']) && str_contains((string) $item['image'], '/')): ?><img src="<?= htmlspecialchars((string) $item['image']) ?>" alt="<?= htmlspecialchars((string) $item['name']) ?>"><?php else: ?><div class="buyer-order-item-placeholder">GB</div><?php endif; ?>
                                    <div><strong><?= htmlspecialchars((string) $item['name']) ?></strong><span><?= (int) $item['quantity'] ?> × <?= groceria_format_money((float) $item['price']) ?></span></div>
                                    <b><?= groceria_format_money((float) ($item['subtotal'] ?? ((float) $item['price'] * (int) $item['quantity']))) ?></b>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="buyer-order-details"><div><span>Deliver to</span><strong><?= htmlspecialchars((string) ($order['delivery_recipient'] ?? $order['buyer_name'])) ?> · <?= htmlspecialchars((string) ($order['delivery_contact'] ?? $order['buyer_contact'])) ?></strong></div><div><span>Delivery address</span><strong><?= nl2br(htmlspecialchars((string) $order['delivery_address'])) ?><?php if (!empty($order['delivery_instructions'])): ?><br>Instructions: <?= htmlspecialchars((string) $order['delivery_instructions']) ?><?php endif; ?></strong></div><div><span>Total</span><strong><?= groceria_format_money((float) $order['total']) ?></strong></div></div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>

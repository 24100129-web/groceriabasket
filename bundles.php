<?php
require __DIR__ . '/app_state.php';

if (($_SESSION['groceria_user']['role'] ?? '') !== 'buyer') {
	header('Location: login.php');
	exit;
}

$bundles = array_values(array_filter(groceria_bundles(), static fn ($bundle) => groceria_bundle_status($bundle) !== 'Out of Stock'));
$storeSettings = groceria_store_settings();
$tones = ['dot-yellow', 'dot-red', 'dot-blue'];
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Shop Bundles | Groceria Basket</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="css/style.css">
	<meta name="csrf-token" content="<?= htmlspecialchars(groceria_csrf_token()) ?>">
</head>
<body class="bundle-storefront">
	<header class="shop-header">
		<a class="shop-brand" href="bundles.php">Groceria<span>Basket</span></a>
		<nav><a class="active" href="bundles.php">Shop Bundles</a><a href="cart.php">Cart</a><a href="buyer-orders.php">My Orders</a><a href="profile.php">My Account</a><a href="#about">About our store</a><a href="logout.php">Log out</a></nav>
		<a class="cart-button" href="cart.php">Cart <b id="shopCartCount"><?= array_sum(array_column(groceria_cart(), 'quantity')) ?></b></a>
	</header>
	<main class="shop-main">
		<section class="shop-hero"><div><span class="eyebrow">Customer shopping</span><h1>Good groceries, bundled for you.</h1><p>Choose a ready-made package, review your order, and send your request to Groceria Basket.</p></div><div class="shop-hero-stamp">BUNDLES<br><strong>ONLY</strong></div></section>
		<div class="shop-toolbar"><div><h2>Available bundles</h2><span>Every package is prepared from our store inventory.</span></div><input id="bundleSearch" type="search" placeholder="Search bundles..." aria-label="Search bundles"></div>
		<section class="shop-grid">
			<?php foreach ($bundles as $index => $bundle): ?>
				<?php $hasBundleImage = str_contains((string) ($bundle['image'] ?? ''), '/'); ?>
				<article class="shop-card" data-bundle-card data-bundle-search="<?= htmlspecialchars(strtolower($bundle['name'] . ' ' . $bundle['description'])) ?>">
					<div class="shop-card-art <?= $hasBundleImage ? 'has-image' : 'tone-' . htmlspecialchars($tones[$index % count($tones)]) ?>"<?= $hasBundleImage ? ' style="background-image:url(\'' . htmlspecialchars((string) $bundle['image'], ENT_QUOTES) . '\')"' : '' ?>><span>GB</span><small>GROCERY PACKAGE</small></div>
					<div class="shop-card-body">
						<h3><?= htmlspecialchars($bundle['name']) ?></h3>
						<p><?= htmlspecialchars($bundle['description']) ?></p>
						<div class="shop-includes"><b>Includes</b><span><?= htmlspecialchars(implode(' · ', array_map(static fn ($item) => $item['product_name'] . ' × ' . $item['quantity'], $bundle['items']))) ?></span></div>
						<div class="shop-card-bottom"><strong><?= groceria_format_money((float) $bundle['price']) ?></strong><small><?= groceria_bundle_available_count($bundle) ?> available</small><div class="shop-purchase-actions"><button class="shop-add" type="button" data-bundle-id="<?= (int) $bundle['id'] ?>">Add to Cart</button><a class="shop-buy-now" href="checkout.php?mode=buy-now&amp;bundle_id=<?= (int) $bundle['id'] ?>&amp;quantity=1">Buy Now</a></div></div>
					</div>
				</article>
			<?php endforeach; ?>
		</section>
		<section class="shop-about" id="about">
			<div class="shop-about-heading"><span class="eyebrow">About our store</span><h2><?= htmlspecialchars((string) $storeSettings['store_name']) ?></h2></div>
			<p class="shop-about-copy">Welcome to <?= htmlspecialchars((string) $storeSettings['store_name']) ?>. We bring together everyday grocery essentials in convenient bundles for your home.</p>
			<div class="shop-about-details">
				<?php if (trim((string) $storeSettings['address']) !== ''): ?><div><strong>Address</strong><span><?= nl2br(htmlspecialchars((string) $storeSettings['address'])) ?></span></div><?php endif; ?>
				<?php if (trim((string) $storeSettings['contact_number']) !== ''): ?><div><strong>Contact number</strong><a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', (string) $storeSettings['contact_number'])) ?>"><?= htmlspecialchars((string) $storeSettings['contact_number']) ?></a></div><?php endif; ?>
				<?php if (trim((string) $storeSettings['email']) !== ''): ?><div><strong>Email</strong><a href="mailto:<?= htmlspecialchars((string) $storeSettings['email']) ?>"><?= htmlspecialchars((string) $storeSettings['email']) ?></a></div><?php endif; ?>
				<?php if (trim((string) $storeSettings['opening_hours']) !== ''): ?><div><strong>Opening hours</strong><span><?= htmlspecialchars((string) $storeSettings['opening_hours']) ?></span></div><?php endif; ?>
			</div>
		</section>
	</main>
	<div id="buyerCartNotice" class="buyer-cart-alert" role="status" aria-live="polite" hidden>
		<span class="buyer-cart-alert-icon" aria-hidden="true">✓</span>
		<div class="buyer-cart-alert-copy"><strong>Added to cart</strong><span class="buyer-cart-alert-message">Your bundle is saved in your cart.</span></div>
		<a href="cart.php">View cart</a>
		<button type="button" class="buyer-cart-alert-close" aria-label="Dismiss alert">×</button>
	</div>
	<footer class="shop-footer"><?= htmlspecialchars((string) $storeSettings['store_name']) ?> · Curated grocery bundles for your home</footer>
	<script src="js/script.js"></script>
</body>
</html>
<?php exit; ?>
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Shop Bundles | Groceria Basket</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="css/style.css"></head><body class="bundle-storefront"><header class="shop-header"><a class="shop-brand" href="bundles.php">Groceria<span>Basket</span></a><nav><a class="active" href="bundles.php">Shop Bundles</a><a href="#about">About our store</a></nav><button class="cart-button" id="shopCartButton" type="button">Cart <b id="shopCartCount">0</b></button></header><main class="shop-main"><section class="shop-hero"><div><span class="eyebrow">Customer shopping</span><h1>Good groceries, bundled for you.</h1><p>Choose a ready-made package, review your order, and send your request to Groceria Basket.</p></div><div class="shop-hero-stamp">BUNDLES<br><strong>ONLY</strong></div></section><div class="shop-toolbar"><div><h2>Available bundles</h2><span>Every package is prepared from our store inventory.</span></div><input id="bundleSearch" type="search" placeholder="Search bundles..." aria-label="Search bundles"></div><section class="shop-grid"><?php foreach ($bundles as $index => $bundle): ?><article class="shop-card" data-bundle-card data-bundle-search="<?= htmlspecialchars(strtolower($bundle['name'] . ' ' . $bundle['description'])) ?>"><div class="shop-card-art tone-<?= htmlspecialchars($tones[$index % count($tones)]) ?>"><span>GB</span><small>GROCERY PACKAGE</small></div><div class="shop-card-body"><h3><?= htmlspecialchars($bundle['name']) ?></h3><p><?= htmlspecialchars($bundle['description']) ?></p><div class="shop-includes"><b>Includes</b><span><?= htmlspecialchars(implode(' · ', array_map(static fn ($item) => $item['product_name'] . ' × ' . $item['quantity'], $bundle['items']))) ?></span></div><div class="shop-card-bottom"><strong><?= groceria_format_money((float) $bundle['price']) ?></strong><small><?= groceria_bundle_available_count($bundle) ?> available</small><button class="shop-add" type="button" data-bundle-id="<?= (int) $bundle['id'] ?>" data-bundle-name="<?= htmlspecialchars($bundle['name'], ENT_QUOTES) ?>" data-bundle-price="<?= (float) $bundle['price'] ?>">Add to cart</button></div></div></article><?php endforeach; ?></section></main><aside class="shop-cart" id="shopCart" aria-label="Shopping cart" hidden><div class="shop-cart-heading"><div><span class="eyebrow">Your order</span><h2>Cart</h2></div><button type="button" id="closeShopCart" aria-label="Close cart">×</button></div><div id="shopCartItems" class="shop-cart-items"><p>Your cart is empty.</p></div><div class="shop-cart-total"><span>Total</span><strong id="shopCartTotal">₱0.00</strong></div><form id="shopOrderForm" class="shop-order-form"><label>Your name<input name="buyer_name" required></label><label>Contact number<input name="buyer_contact" type="tel" required></label><label>Delivery or pickup details<textarea name="buyer_details" rows="2" required></textarea></label><button class="shop-checkout" type="submit">Send order request</button></form><p id="shopOrderMessage" class="shop-order-message" hidden></p></aside><div class="shop-cart-backdrop" id="shopCartBackdrop" hidden></div><footer class="shop-footer">Groceria Basket · Curated grocery bundles for your home</footer><script src="js/script.js"></script></body></html>

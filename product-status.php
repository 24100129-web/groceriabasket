<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'Product Status';
$activePage = 'status';
$products = groceria_products();
$groups = [
    'Low Stock' => array_values(array_filter($products, static fn ($product) => groceria_product_status($product) === 'Low Stock')),
    'Out of Stock' => array_values(array_filter($products, static fn ($product) => groceria_product_status($product) === 'Out of Stock')),
    'Expired' => array_values(array_filter($products, static fn ($product) => groceria_product_status($product) === 'Expired')),
    'Damaged' => array_values(array_filter($products, static fn ($product) => groceria_product_status($product) === 'Damaged')),
];

include __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div>
        <span class="eyebrow">Inventory watch</span>
        <h1>Product Status</h1>
        <p>Review essential physical-store product conditions at a glance.</p>
    </div>
</section>
<section class="status-grid">
    <?php foreach ($groups as $label => $items): ?>
        <article class="panel status-panel">
            <div class="panel-heading">
                <div><span class="eyebrow"><?= htmlspecialchars($label) ?></span><h2><?= count($items) ?> items</h2></div>
            </div>
            <?php if (empty($items)): ?>
                <div class="status-empty">No products in this status.</div>
            <?php else: ?>
                <div class="status-list">
                    <?php foreach ($items as $product): ?>
                        <div class="status-row">
                            <div class="status-dot dot-<?= strtolower(str_replace(' ', '-', $label)) ?>"><?= strtoupper(substr($product['name'], 0, 1)) ?></div>
                            <div>
                                <b><?= htmlspecialchars($product['name']) ?></b>
                                <small><?= htmlspecialchars($product['category']) ?> · <?= (int) $product['stock_quantity'] ?> units</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php include __DIR__ . '/partials/footer.php'; ?>

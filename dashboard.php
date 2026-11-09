<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$products = groceria_products();
$bundles = groceria_bundles();
$sales = groceria_sales();
$remainingCapital = groceria_remaining_capital();
$spentCapital = groceria_capital_spent();
$today = date('Y-m-d');
$todaySales = 0.0;
$walkInSales = 0.0;
$onlineSales = 0.0;
foreach ($sales as $sale) {
    $total = (float) $sale['total'];
    if (($sale['date'] ?? '') === $today) {
        $todaySales += $total;
    }
    if (strtoupper((string) $sale['sale_type']) === 'WALK-IN') {
        $walkInSales += $total;
    }
    if (strtoupper((string) $sale['sale_type']) === 'ONLINE') {
        $onlineSales += $total;
    }
}

$recentTransactions = array_slice(array_reverse($sales), 0, 4);
$lowStockProducts = array_values(array_filter($products, static fn ($product) => groceria_product_status($product) === 'Low Stock'));
$bundleCounts = ['Available' => 0, 'Low Stock' => 0, 'Out of Stock' => 0];
foreach ($bundles as $bundle) {
    $status = groceria_bundle_status($bundle);
    if (isset($bundleCounts[$status])) {
        $bundleCounts[$status]++;
    }
}
include __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div>
        <span class="eyebrow"><?= date('l, F d, Y') ?></span>
        <h1>Good morning, Ana.</h1>
        <p>Here is what is happening in your store today.</p>
    </div>
    <a class="button button-primary" href="inventory.php">+ Add Product</a>
</section>
<section class="stat-grid" aria-label="Store summary">
    <article class="stat-card accent-green"><div class="stat-icon">₱</div><div><span>Today's Sales</span><strong><?= groceria_format_money($todaySales) ?></strong><small class="trend positive">↗ <?= count($sales) ?> transactions</small></div></article>
    <article class="stat-card accent-blue"><div class="stat-icon">₱</div><div><span>Walk-In Sales</span><strong><?= groceria_format_money($walkInSales) ?></strong><small class="trend positive">Walk-in store</small></div></article>
    <article class="stat-card accent-amber"><div class="stat-icon">₱</div><div><span>Online Sales</span><strong><?= groceria_format_money($onlineSales) ?></strong><small class="trend positive">Bundle orders</small></div></article>
    <article class="stat-card accent-red"><div class="stat-icon">!</div><div><span>Low Stock</span><strong><?= count($lowStockProducts) ?> <small>Products</small></strong><small class="trend negative">Needs attention</small></div></article>
</section>
<section class="panel capital-dashboard-card">
    <div class="panel-heading">
        <div><span class="eyebrow">Capital / Puhunan</span><h2>Where the capital went</h2></div>
        <a class="text-link" href="capital-history.php">View history →</a>
    </div>
    <div class="capital-dashboard-content">
        <div><span>Initial capital</span><strong><?= groceria_format_money(groceria_initial_capital()) ?></strong></div>
        <span class="capital-arrow">−</span>
        <div><span>Total spent</span><strong class="capital-spent"><?= groceria_format_money($spentCapital) ?></strong></div>
        <span class="capital-arrow">=</span>
        <div class="capital-dashboard-remaining"><span>Remaining capital</span><strong><?= groceria_format_money($remainingCapital) ?></strong></div>
    </div>
</section>
<section class="content-grid dashboard-grid">
    <article class="panel sales-panel">
        <div class="panel-heading">
            <div><span class="eyebrow">Sales overview</span><h2>Bundle and walk-in summary</h2></div>
            <select class="select-control" aria-label="Sales period"><option>This week</option><option>Last week</option></select>
        </div>
        <div class="chart-wrap">
            <div class="chart-y-axis"><span>₱2k</span><span>₱1.5k</span><span>₱1k</span><span>₱500</span><span>₱0</span></div>
            <div class="chart">
                <div class="chart-grid-lines"></div>
                <div class="bars">
                    <div class="bar-item"><div class="bar" style="height:25%"></div><span>Mon</span></div>
                    <div class="bar-item"><div class="bar" style="height:46%"></div><span>Tue</span></div>
                    <div class="bar-item"><div class="bar" style="height:39%"></div><span>Wed</span></div>
                    <div class="bar-item active"><div class="bar" style="height:82%"><b><?= groceria_format_money($todaySales) ?></b></div><span>Thu</span></div>
                    <div class="bar-item"><div class="bar" style="height:58%"></div><span>Fri</span></div>
                    <div class="bar-item"><div class="bar" style="height:65%"></div><span>Sat</span></div>
                    <div class="bar-item"><div class="bar" style="height:22%"></div><span>Sun</span></div>
                </div>
            </div>
        </div>
    </article>
    <article class="panel quick-panel">
        <div class="panel-heading">
            <div><span class="eyebrow">Shortcuts</span><h2>Quick actions</h2></div>
        </div>
        <div class="quick-actions">
            <a class="quick-action" href="pos.php"><span class="action-icon green">↗</span><span><b>New Sale</b><small>Open cashier</small></span><i>›</i></a>
            <a class="quick-action" href="bulk-restock.php"><span class="action-icon orange">+</span><span><b>Restock</b><small>Update inventory</small></span><i>›</i></a>
            <a class="quick-action" href="sales-history.php"><span class="action-icon blue">▤</span><span><b>View Reports</b><small>See sales history</small></span><i>›</i></a>
        </div>
    </article>
</section>
<section class="content-grid lower-grid">
    <article class="panel table-panel">
        <div class="panel-heading">
            <div><span class="eyebrow">Latest activity</span><h2>Recent transactions</h2></div>
            <a class="text-link" href="pos.php">Open cashier →</a>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Invoice</th><th>Items</th><th>Date</th><th>Amount</th><th>Type</th></tr></thead>
                <tbody>
                    <?php foreach ($recentTransactions as $transaction): ?>
                        <tr>
                            <td class="strong">#<?= htmlspecialchars((string) $transaction['id']) ?></td>
                            <td><?= htmlspecialchars((string) $transaction['items']) ?></td>
                            <td class="muted"><?= htmlspecialchars((string) $transaction['date']) ?></td>
                            <td class="strong"><?= groceria_format_money((float) $transaction['total']) ?></td>
                            <td><span class="status <?= strtolower(str_replace(' ', '-', (string) $transaction['sale_type'])) ?>"><?= htmlspecialchars((string) $transaction['sale_type']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
    <article class="panel best-panel">
        <div class="panel-heading">
            <div><span class="eyebrow">Bundle status</span><h2>Online bundle status</h2></div>
        </div>
        <div class="product-rank"><span class="rank">A</span><span class="product-dot dot-yellow">✓</span><span class="product-info"><b>Available bundles</b><small><?= $bundleCounts['Available'] ?> active bundles</small></span><strong><?= $bundleCounts['Available'] ?></strong></div>
        <div class="product-rank"><span class="rank">L</span><span class="product-dot dot-blue">!</span><span class="product-info"><b>Low stock bundles</b><small>Near restock threshold</small></span><strong><?= $bundleCounts['Low Stock'] ?></strong></div>
        <div class="product-rank"><span class="rank">O</span><span class="product-dot dot-red">×</span><span class="product-info"><b>Out-of-stock bundles</b><small>Unavailable for online</small></span><strong><?= $bundleCounts['Out of Stock'] ?></strong></div>
    </article>
</section>
<section class="panel table-panel inventory-preview">
    <div class="panel-heading">
        <div><span class="eyebrow">Stock watch</span><h2>Low stock products</h2></div>
        <a class="text-link" href="inventory.php">View inventory →</a>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Min. stock</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($lowStockProducts as $product): ?>
                    <tr>
                        <td class="strong"><?= htmlspecialchars($product['name']) ?></td>
                        <td class="muted"><?= htmlspecialchars($product['category']) ?></td>
                        <td><?= (int) $product['stock_quantity'] ?> units</td>
                        <td><?= (int) $product['minimum_stock'] ?> units</td>
                        <td><span class="status low-stock"><?= groceria_product_status($product) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/partials/footer.php'; ?>

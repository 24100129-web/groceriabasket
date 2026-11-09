<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'Inventory Reports';
$activePage = 'inventory-reports';
$products = array_values(array_filter(groceria_products(), static fn ($product) => empty($product['is_deleted'])));
$categoryReport = [];
$stockUnits = 0;
$purchaseValue = 0.0;
$retailValue = 0.0;
foreach ($products as $product) {
    $category = (string) ($product['category'] ?? 'General');
    $quantity = (int) $product['stock_quantity'];
    if (!isset($categoryReport[$category])) {
        $categoryReport[$category] = ['products' => 0, 'units' => 0, 'purchase_value' => 0.0, 'retail_value' => 0.0];
    }
    $categoryReport[$category]['products']++;
    $categoryReport[$category]['units'] += $quantity;
    $categoryReport[$category]['purchase_value'] += (float) $product['purchase_price'] * $quantity;
    $categoryReport[$category]['retail_value'] += (float) $product['selling_price'] * $quantity;
    $stockUnits += $quantity;
    $purchaseValue += (float) $product['purchase_price'] * $quantity;
    $retailValue += (float) $product['selling_price'] * $quantity;
}
ksort($categoryReport);
include __DIR__ . '/partials/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Stock valuation</span><h1>Inventory Reports</h1><p>Current active inventory quantities and value based on catalog costs and prices.</p></div><a class="text-link" href="inventory.php">Manage inventory →</a></section>
<section class="stat-grid report-summary"><article class="stat-card accent-green"><div class="stat-icon">▤</div><div><span>Active products</span><strong><?= count($products) ?></strong><small>Deleted items excluded</small></div></article><article class="stat-card accent-blue"><div class="stat-icon">#</div><div><span>Units in stock</span><strong><?= number_format($stockUnits) ?></strong><small>Across active products</small></div></article><article class="stat-card accent-amber"><div class="stat-icon">₱</div><div><span>Purchase value</span><strong><?= groceria_format_money($purchaseValue) ?></strong><small>Current stock at cost</small></div></article><article class="stat-card accent-red"><div class="stat-icon">₱</div><div><span>Potential retail value</span><strong><?= groceria_format_money($retailValue) ?></strong><small>Current stock at selling price</small></div></article></section>
<section class="panel table-panel"><div class="panel-heading"><div><span class="eyebrow">Category summary</span><h2>Stock value by category</h2></div></div><div class="table-scroll"><table><thead><tr><th>Category</th><th>Products</th><th>Units</th><th>Purchase value</th><th>Potential retail value</th></tr></thead><tbody>
<?php foreach ($categoryReport as $category => $row): ?><tr><td class="strong"><?= htmlspecialchars($category) ?></td><td><?= (int) $row['products'] ?></td><td><?= number_format((int) $row['units']) ?></td><td><?= groceria_format_money((float) $row['purchase_value']) ?></td><td class="strong"><?= groceria_format_money((float) $row['retail_value']) ?></td></tr><?php endforeach; ?>
<?php if (!$categoryReport): ?><tr><td colspan="5" class="muted">No active inventory products.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php include __DIR__ . '/partials/footer.php'; ?>

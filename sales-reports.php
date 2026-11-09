<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'Sales Reports';
$activePage = 'sales-reports';
$transactions = [];
foreach (groceria_sales() as $sale) {
    $transactions[] = [
        'number' => '#' . (string) $sale['id'], 'date' => (string) ($sale['date'] ?? ''),
        'time' => (string) ($sale['time'] ?? ''), 'items' => (string) ($sale['items'] ?? ''),
        'type' => (string) ($sale['sale_type'] ?? 'WALK-IN'), 'payment' => (string) ($sale['payment_method'] ?? 'CASH'),
        'total' => (float) ($sale['total'] ?? 0),
    ];
}
foreach (groceria_orders(null) as $order) {
    $transactions[] = [
        'number' => (string) ($order['order_number'] ?? 'Online order'), 'date' => (string) ($order['created_at'] ?? ''),
        'time' => '', 'items' => implode(', ', array_map(static fn ($item) => $item['name'] . ' × ' . $item['quantity'], $order['items'] ?? [])),
        'type' => 'ONLINE', 'payment' => 'Online order', 'total' => (float) ($order['total'] ?? 0),
    ];
}
usort($transactions, static fn ($a, $b) => strcmp($b['date'] . ' ' . $b['time'], $a['date'] . ' ' . $a['time']));
$totalSales = array_sum(array_column($transactions, 'total'));
$walkInSales = array_sum(array_map(static fn ($row) => $row['type'] === 'WALK-IN' ? $row['total'] : 0, $transactions));
$onlineSales = array_sum(array_map(static fn ($row) => $row['type'] === 'ONLINE' ? $row['total'] : 0, $transactions));
include __DIR__ . '/partials/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Business performance</span><h1>Sales Reports</h1><p>Compare walk-in POS and online bundle sales from saved transactions.</p></div><a class="text-link" href="sales-history.php">Open sales history →</a></section>
<section class="stat-grid report-summary"><article class="stat-card accent-green"><div class="stat-icon">₱</div><div><span>Total recorded sales</span><strong><?= groceria_format_money($totalSales) ?></strong><small><?= count($transactions) ?> transactions</small></div></article><article class="stat-card accent-blue"><div class="stat-icon">▣</div><div><span>Walk-in POS</span><strong><?= groceria_format_money($walkInSales) ?></strong><small>In-store sales</small></div></article><article class="stat-card accent-amber"><div class="stat-icon">▰</div><div><span>Online bundles</span><strong><?= groceria_format_money($onlineSales) ?></strong><small>Buyer orders</small></div></article><article class="stat-card accent-red"><div class="stat-icon">#</div><div><span>Average transaction</span><strong><?= groceria_format_money($transactions ? $totalSales / count($transactions) : 0) ?></strong><small>Across all recorded sales</small></div></article></section>
<section class="panel table-panel"><div class="panel-heading"><div><span class="eyebrow">Sales data</span><h2>Transaction breakdown</h2></div></div><div class="table-scroll"><table><thead><tr><th>Transaction</th><th>Date / time</th><th>Items</th><th>Type</th><th>Payment</th><th>Total</th></tr></thead><tbody>
<?php foreach ($transactions as $sale): ?><tr><td class="strong"><?= htmlspecialchars($sale['number']) ?></td><td><?= htmlspecialchars(trim($sale['date'] . ' ' . $sale['time'])) ?></td><td><?= htmlspecialchars($sale['items']) ?></td><td><?= htmlspecialchars($sale['type']) ?></td><td><?= htmlspecialchars($sale['payment']) ?></td><td class="strong"><?= groceria_format_money($sale['total']) ?></td></tr><?php endforeach; ?>
<?php if (!$transactions): ?><tr><td colspan="6" class="muted">No sales transactions have been recorded yet.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php include __DIR__ . '/partials/footer.php'; ?>

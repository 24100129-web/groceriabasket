<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'Sales History';
$activePage = 'sales-history';
$sales = groceria_sales();
foreach (groceria_orders(null) as $order) {
    $createdAt = (string) ($order['created_at'] ?? '');
    $timestamp = strtotime($createdAt);
    $sales[] = [
        'id' => (string) ($order['order_number'] ?? 'ONLINE'),
        'date' => $timestamp === false ? $createdAt : date('Y-m-d', $timestamp),
        'time' => $timestamp === false ? '' : date('g:i A', $timestamp),
        'items' => implode(', ', array_map(
            static fn ($item) => (string) ($item['name'] ?? 'Bundle') . ' × ' . (int) ($item['quantity'] ?? 0),
            $order['items'] ?? []
        )),
        'total' => (float) ($order['total'] ?? 0),
        'sale_type' => 'ONLINE',
        'cashier' => (string) ($order['buyer_name'] ?? $order['delivery_recipient'] ?? 'Online order'),
        'sort_timestamp' => $timestamp === false ? 0 : $timestamp,
    ];
}
foreach ($sales as &$sale) {
    if (!isset($sale['sort_timestamp'])) {
        $timestamp = strtotime((string) ($sale['date'] ?? '') . ' ' . (string) ($sale['time'] ?? ''));
        $sale['sort_timestamp'] = $timestamp === false ? 0 : $timestamp;
    }
}
unset($sale);
usort($sales, static fn ($first, $second) => $second['sort_timestamp'] <=> $first['sort_timestamp']);
$selectedType = $_GET['type'] ?? 'ALL';
$selectedType = strtoupper($selectedType);

if ($selectedType !== 'ALL' && $selectedType !== 'WALK-IN' && $selectedType !== 'ONLINE') {
    $selectedType = 'ALL';
}

if ($selectedType !== 'ALL') {
    $sales = array_values(array_filter($sales, static fn ($sale) => strtoupper((string) $sale['sale_type']) === $selectedType));
}

include __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div>
        <span class="eyebrow">Records</span>
        <h1>Sales History</h1>
        <p>Track walk-in and online bundle transactions from your store.</p>
    </div>
</section>
<section class="panel table-panel">
    <div class="panel-heading">
        <div><span class="eyebrow">Sales records</span><h2>Transaction log</h2></div>
        <div class="table-tools">
            <a class="filter-button <?= $selectedType === 'ALL' ? 'active' : '' ?>" href="sales-history.php?type=ALL">All</a>
            <a class="filter-button <?= $selectedType === 'WALK-IN' ? 'active' : '' ?>" href="sales-history.php?type=WALK-IN">Walk-In</a>
            <a class="filter-button <?= $selectedType === 'ONLINE' ? 'active' : '' ?>" href="sales-history.php?type=ONLINE">Online</a>
        </div>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Sale Type</th>
                    <th>Cashier</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td class="strong">#<?= htmlspecialchars((string) $sale['id']) ?></td>
                        <td><?= htmlspecialchars((string) $sale['date']) ?></td>
                        <td><?= htmlspecialchars((string) $sale['time']) ?></td>
                        <td>
                            <ul class="sales-items">
                                <?php foreach (explode(',', (string) $sale['items']) as $item): ?>
                                    <li><?= htmlspecialchars(trim($item)) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                        <td class="strong"><?= groceria_format_money((float) $sale['total']) ?></td>
                        <td><span class="status <?= strtolower(str_replace(' ', '-', (string) $sale['sale_type'])) ?>"><?= htmlspecialchars((string) $sale['sale_type']) ?></span></td>
                        <td><?= htmlspecialchars((string) $sale['cashier']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$sales): ?><tr><td colspan="7" class="muted">No sales transactions have been recorded yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/partials/footer.php'; ?>

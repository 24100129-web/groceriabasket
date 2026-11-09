<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'History Log';
$activePage = 'history';
$events = [];
foreach (groceria_sales() as $sale) {
    $events[] = [
        'date' => trim((string) ($sale['date'] ?? '') . ' ' . (string) ($sale['time'] ?? '')),
        'type' => 'Sale', 'title' => 'Transaction #' . (string) $sale['id'],
        'detail' => (string) ($sale['items'] ?? ''), 'actor' => (string) ($sale['cashier'] ?? 'Staff'),
        'amount' => (float) ($sale['total'] ?? 0),
    ];
}
foreach (groceria_orders(null) as $order) {
    $events[] = [
        'date' => (string) ($order['created_at'] ?? ''), 'type' => 'Online order',
        'title' => 'Order ' . (string) ($order['order_number'] ?? ''),
        'detail' => (string) ($order['buyer_name'] ?? 'Buyer') . ' · ' . implode(', ', array_map(static fn ($item) => $item['name'] . ' × ' . $item['quantity'], $order['items'] ?? [])),
        'actor' => (string) ($order['delivery_recipient'] ?? $order['buyer_name'] ?? 'Buyer'), 'amount' => (float) ($order['total'] ?? 0),
    ];
}
foreach (groceria_capital_transactions() as $transaction) {
    $events[] = [
        'date' => (string) ($transaction['date'] ?? ''), 'type' => 'Capital expense',
        'title' => (string) ($transaction['description'] ?? 'Capital expense'), 'detail' => 'Puhunan deduction',
        'actor' => (string) ($transaction['recorded_by'] ?? 'Admin'), 'amount' => -(float) ($transaction['amount'] ?? 0),
    ];
}
foreach (groceria_deleted_products_history() as $entry) {
    $events[] = [
        'date' => (string) ($entry['deleted_at'] ?? ''), 'type' => 'Product deleted',
        'title' => (string) ($entry['product_name'] ?? 'Product'), 'detail' => (string) ($entry['reason'] ?? ''),
        'actor' => (string) ($entry['deleted_by'] ?? 'Admin'), 'amount' => null,
    ];
}
usort($events, static fn ($left, $right) => (strtotime($right['date']) ?: 0) <=> (strtotime($left['date']) ?: 0));
include __DIR__ . '/partials/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Admin audit</span><h1>History Log</h1><p>Recent sales, buyer orders, capital expenses, and inventory deletion events.</p></div></section>
<section class="panel table-panel"><div class="panel-heading"><div><span class="eyebrow">Activity timeline</span><h2><?= count($events) ?> recorded events</h2></div></div><div class="table-scroll"><table><thead><tr><th>Date and time</th><th>Activity</th><th>Details</th><th>Recorded by</th><th>Amount</th></tr></thead><tbody>
<?php foreach ($events as $event): ?><tr><td><?= htmlspecialchars($event['date']) ?></td><td><span class="history-event-type"><?= htmlspecialchars($event['type']) ?></span><strong class="history-event-title"><?= htmlspecialchars($event['title']) ?></strong></td><td><?= htmlspecialchars($event['detail']) ?></td><td><?= htmlspecialchars($event['actor']) ?></td><td class="strong"><?php if ($event['amount'] !== null): ?><?= $event['amount'] < 0 ? '-' : '' ?><?= groceria_format_money(abs((float) $event['amount'])) ?><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$events): ?><tr><td colspan="5" class="muted">No activity has been recorded yet.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php include __DIR__ . '/partials/footer.php'; ?>

<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'Online Orders';
$activePage = 'online-orders';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_status'], $_POST['order_number'])) {
    groceria_update_order_status((string) $_POST['order_number'], (string) $_POST['order_status']);
}
$orders = array_reverse(groceria_orders());
$statuses = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Completed', 'Cancelled'];
include __DIR__ . '/partials/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Customer purchases</span><h1>Online Orders</h1><p>Review buyer orders, delivery addresses, totals, and current fulfillment status.</p></div></section>
<section class="panel table-panel online-orders-panel">
    <div class="panel-heading"><div><span class="eyebrow">Buyer order log</span><h2>All online bundle orders</h2></div></div>
    <div class="table-scroll"><table><thead><tr><th>Order</th><th>Buyer</th><th>Items</th><th>Delivery address</th><th>Total</th><th>Date</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($orders as $order): ?>
        <tr><td class="strong"><?= htmlspecialchars((string) $order['order_number']) ?></td><td><strong><?= htmlspecialchars((string) $order['buyer_name']) ?></strong><br><small><?= htmlspecialchars((string) $order['buyer_contact']) ?></small></td><td><?php foreach ($order['items'] as $item): ?><div class="admin-order-item"><?php if (!empty($item['image']) && str_contains((string) $item['image'], '/')): ?><img src="<?= htmlspecialchars((string) $item['image']) ?>" alt=""><?php endif; ?><span><?= htmlspecialchars((string) $item['name']) ?> × <?= (int) $item['quantity'] ?></span></div><?php endforeach; ?></td><td><strong><?= htmlspecialchars((string) ($order['delivery_recipient'] ?? $order['buyer_name'])) ?></strong><br><small><?= htmlspecialchars((string) ($order['delivery_contact'] ?? $order['buyer_contact'])) ?></small><br><?= nl2br(htmlspecialchars((string) $order['delivery_address'])) ?><?php if (!empty($order['delivery_instructions'])): ?><br><small>Instructions: <?= htmlspecialchars((string) $order['delivery_instructions']) ?></small><?php endif; ?></td><td class="strong"><?= groceria_format_money((float) $order['total']) ?></td><td><?= htmlspecialchars((string) $order['created_at']) ?></td><td><form method="post" class="order-status-form"><input type="hidden" name="order_number" value="<?= htmlspecialchars((string) $order['order_number']) ?>"><select name="order_status" onchange="this.form.submit()" aria-label="Update order status"><?php foreach ($statuses as $status): ?><option value="<?= htmlspecialchars($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="7" class="muted">No buyer orders have been placed yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php include __DIR__ . '/partials/footer.php'; ?>

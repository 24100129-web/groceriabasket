<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'Customer Records';
$activePage = 'customers';
$customers = groceria_customer_records();
$orders = groceria_orders(null);
$ordersByCustomer = [];
foreach ($orders as $order) {
    $userId = (string) ($order['user_id'] ?? '');
    if (!isset($ordersByCustomer[$userId])) {
        $ordersByCustomer[$userId] = ['count' => 0, 'total' => 0.0, 'latest' => ''];
    }
    $ordersByCustomer[$userId]['count']++;
    $ordersByCustomer[$userId]['total'] += (float) ($order['total'] ?? $order['total_amount'] ?? 0);
    $ordersByCustomer[$userId]['latest'] = max($ordersByCustomer[$userId]['latest'], (string) ($order['created_at'] ?? ''));
}
include __DIR__ . '/partials/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Buyer accounts</span><h1>Customer Records</h1><p>Registered customers, contact information, saved delivery addresses, and purchase activity.</p></div></section>
<section class="inventory-summary"><div><span>Registered buyers</span><strong><?= count($customers) ?></strong></div><div><span>Customers with orders</span><strong><?= count($ordersByCustomer) ?></strong></div><div><span>Online order total</span><strong><?= groceria_format_money(array_sum(array_map(static fn ($order) => (float) ($order['total'] ?? $order['total_amount'] ?? 0), $orders))) ?></strong></div><div><span>Orders recorded</span><strong><?= count($orders) ?></strong></div></section>
<section class="panel table-panel"><div class="panel-heading"><div><span class="eyebrow">Customer directory</span><h2>Buyer contact and delivery details</h2></div></div><div class="table-scroll"><table><thead><tr><th>Customer</th><th>Email</th><th>Contact</th><th>Saved address</th><th>Orders</th><th>Order total</th><th>Last order</th></tr></thead><tbody>
<?php foreach ($customers as $customer): $id = (string) $customer['id']; $activity = $ordersByCustomer[$id] ?? ['count' => 0, 'total' => 0.0, 'latest' => '']; ?>
<tr><td class="strong"><?= htmlspecialchars((string) ($customer['full_name'] ?? '')) ?></td><td><?= htmlspecialchars((string) ($customer['email'] ?? '')) ?></td><td><?= htmlspecialchars((string) ($customer['contact_number'] ?? 'Not provided')) ?></td><td class="customer-address"><?= htmlspecialchars(groceria_format_customer_address($customer) ?: 'Address not provided') ?></td><td><?= (int) $activity['count'] ?></td><td class="strong"><?= groceria_format_money((float) $activity['total']) ?></td><td><?= htmlspecialchars((string) $activity['latest']) ?></td></tr>
<?php endforeach; ?>
<?php if (!$customers): ?><tr><td colspan="7" class="muted">No registered buyer accounts yet.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php include __DIR__ . '/partials/footer.php'; ?>

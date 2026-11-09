<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'Deleted Products History';
$activePage = 'deleted-products';
$history = groceria_deleted_products_history();
include __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div>
        <span class="eyebrow">Inventory audit</span>
        <h1>Deleted Products History</h1>
        <p>Review products marked as deleted, including the reason and administrator responsible.</p>
    </div>
    <a class="text-link" href="inventory.php">Back to inventory</a>
</section>
<section class="panel table-panel deleted-history-panel">
    <div class="panel-heading"><div><span class="eyebrow">Audit trail</span><h2><?= count($history) ?> recorded deletions</h2></div></div>
    <div class="table-scroll"><table>
        <thead><tr><th>Product</th><th>Product details at deletion</th><th>Reason</th><th>Deleted by</th><th>Date and time</th></tr></thead>
        <tbody>
        <?php foreach ($history as $entry): $snapshot = $entry['product_snapshot'] ?? []; ?>
            <tr>
                <td class="strong"><?= htmlspecialchars((string) ($entry['product_name'] ?? $snapshot['name'] ?? 'Unknown product')) ?><br><small>ID <?= (int) ($entry['product_id'] ?? $snapshot['id'] ?? 0) ?></small></td>
                <td class="deleted-product-snapshot">
                    <span>Category: <?= htmlspecialchars((string) ($snapshot['category'] ?? '—')) ?></span>
                    <span>Supplier: <?= htmlspecialchars((string) ($snapshot['supplier'] ?? '—')) ?></span>
                    <span>Stock: <?= (int) ($snapshot['stock_quantity'] ?? 0) ?></span>
                    <span>Purchase: <?= groceria_format_money((float) ($snapshot['purchase_price'] ?? 0)) ?></span>
                    <span>Selling: <?= groceria_format_money((float) ($snapshot['selling_price'] ?? 0)) ?></span>
                </td>
                <td><?= nl2br(htmlspecialchars((string) ($entry['reason'] ?? ''))) ?></td>
                <td><?= htmlspecialchars((string) ($entry['deleted_by'] ?? 'Unknown')) ?></td>
                <td><?= htmlspecialchars((string) ($entry['deleted_at'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$history): ?><tr><td colspan="5" class="muted">No products have been deleted.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<?php include __DIR__ . '/partials/footer.php'; ?>

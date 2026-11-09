<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'Bulk Restock';
$activePage = 'restock';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock'])) {
    $products = groceria_products();
    $restockCost = 0.0;
    foreach ($products as $index => $product) {
        if (!empty($product['is_deleted'])) {
            continue;
        }
        $restockQty = isset($_POST['restock_qty_' . $product['id']]) ? (int) $_POST['restock_qty_' . $product['id']] : 0;
        if ($restockQty > 0) {
            $products[$index]['stock_quantity'] = (int) $product['stock_quantity'] + $restockQty;
            $products[$index]['status'] = groceria_product_status($products[$index]);
            $restockCost += (float) $product['purchase_price'] * $restockQty;
        }
    }
    groceria_save_products($products);
    if ($restockCost > 0) {
        groceria_record_capital_transaction('Bought inventory through bulk restock', $restockCost);
    }
}

$products = array_values(array_filter(groceria_products(), static fn ($product) => empty($product['is_deleted'])));
include __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div>
        <span class="eyebrow">Inventory</span>
        <h1>Bulk Restock</h1>
        <p>Restock physical store inventory across multiple products in one action.</p>
    </div>
</section>
<section class="panel table-panel">
    <div class="panel-heading"><div><span class="eyebrow">Restock queue</span><h2>Update stock quantities</h2></div></div>
    <form method="post">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Current Stock</th>
                        <th>Minimum Stock</th>
                        <th>Restock Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td class="strong"><?= htmlspecialchars($product['name']) ?></td>
                            <td><?= (int) $product['stock_quantity'] ?> units</td>
                            <td><?= (int) $product['minimum_stock'] ?> units</td>
                            <td><input type="number" name="restock_qty_<?= (int) $product['id'] ?>" value="0" min="0" class="restock-input"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="panel-heading restock-submit-wrap">
            <button type="submit" name="restock" class="button button-primary">Confirm Restock</button>
        </div>
    </form>
</section>
<?php include __DIR__ . '/partials/footer.php'; ?>

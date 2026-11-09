<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'Suppliers';
$activePage = 'suppliers';
$products = groceria_products();
$suppliers = [];
foreach ($products as $product) {
    $supplier = $product['supplier'];
    if (!isset($suppliers[$supplier])) {
        $suppliers[$supplier] = ['name' => $supplier, 'products' => 0, 'total_value' => 0];
    }
    $suppliers[$supplier]['products']++;
    $suppliers[$supplier]['total_value'] += (float) $product['purchase_price'] * (int) $product['stock_quantity'];
}

include __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div>
        <span class="eyebrow">Inventory</span>
        <h1>Suppliers</h1>
        <p>Keep track of your main grocery supply partners.</p>
    </div>
</section>
<section class="panel table-panel">
    <div class="panel-heading"><div><span class="eyebrow">Supplier list</span><h2>Active partners</h2></div></div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Supplier</th>
                    <th>Products</th>
                    <th>Stock Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $supplier): ?>
                    <tr>
                        <td class="strong"><?= htmlspecialchars($supplier['name']) ?></td>
                        <td><?= (int) $supplier['products'] ?></td>
                        <td><?= groceria_format_money((float) $supplier['total_value']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/partials/footer.php'; ?>

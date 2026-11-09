<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'Inventory';
$activePage = 'inventory';
if (($_SESSION['groceria_user']['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}
$products = groceria_products();
$deleteError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_action'])) {
    $action = $_POST['product_action'];
    if ($action === 'add_product') {
        $name = trim((string) ($_POST['product_name'] ?? ''));
        if ($name !== '') {
            $products[] = [
                'id' => count($products) + 1,
                'name' => $name,
                'category' => trim((string) ($_POST['category'] ?? 'General')),
                'supplier' => trim((string) ($_POST['supplier'] ?? 'General Supplier')),
                'purchase_price' => (float) ($_POST['purchase_price'] ?? 0),
                'selling_price' => (float) ($_POST['selling_price'] ?? 0),
                'stock_quantity' => (int) ($_POST['stock_quantity'] ?? 0),
                'minimum_stock' => (int) ($_POST['minimum_stock'] ?? 0),
                'expiry_date' => (string) ($_POST['expiry_date'] ?? date('Y-m-d')),
                'status' => 'Available',
                'is_damaged' => false,
                'is_deleted' => false,
                'image' => strtoupper(substr($name, 0, 1)),
            ];
            groceria_save_products($products);
        }
    }

    if ($action === 'soft_delete_product') {
        if (!hash_equals(groceria_csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
            $deleteError = 'Your request expired. Refresh the page and try again.';
        } elseif (($_POST['confirm_delete'] ?? '') !== '1') {
            $deleteError = 'Confirm the product deletion before continuing.';
        } else {
            try {
                groceria_soft_delete_product((int) ($_POST['product_id'] ?? 0), (string) ($_POST['deletion_reason'] ?? ''), (string) ($_POST['admin_password'] ?? ''));
                header('Location: inventory.php?deleted=1');
                exit;
            } catch (InvalidArgumentException|RuntimeException $exception) {
                $deleteError = $exception->getMessage();
            } catch (Throwable $exception) {
                error_log('Product soft deletion failed: ' . $exception->getMessage());
                $deleteError = 'The product could not be deleted. No changes were confirmed; please try again.';
            }
        }
    }

    if ($action === 'update_product') {
        $updateId = (int) ($_POST['product_id'] ?? 0);
        foreach ($products as $index => $product) {
            if ((int) $product['id'] === $updateId) {
                $products[$index]['name'] = trim((string) ($_POST['product_name'] ?? $product['name']));
                $products[$index]['category'] = trim((string) ($_POST['category'] ?? $product['category']));
                $products[$index]['supplier'] = trim((string) ($_POST['supplier'] ?? $product['supplier']));
                $products[$index]['purchase_price'] = (float) ($_POST['purchase_price'] ?? $product['purchase_price']);
                $products[$index]['selling_price'] = (float) ($_POST['selling_price'] ?? $product['selling_price']);
                $products[$index]['stock_quantity'] = (int) ($_POST['stock_quantity'] ?? $product['stock_quantity']);
                $products[$index]['minimum_stock'] = (int) ($_POST['minimum_stock'] ?? $product['minimum_stock']);
                $products[$index]['expiry_date'] = (string) ($_POST['expiry_date'] ?? $product['expiry_date']);
                $products[$index]['status'] = groceria_product_status($products[$index]);
                break;
            }
        }
        groceria_save_products($products);
    }
}

$search = trim((string) ($_GET['search'] ?? ''));
$category = (string) ($_GET['category'] ?? 'All');
$products = groceria_products();
$activeProducts = array_values(array_filter($products, static fn ($product) => empty($product['is_deleted'])));
$filteredProducts = array_values(array_filter($activeProducts, static function ($product) use ($search, $category) {
    $matchesSearch = $search === '' || stripos($product['name'], $search) !== false || stripos($product['category'], $search) !== false;
    $matchesCategory = $category === 'All' || $product['category'] === $category;
    return $matchesSearch && $matchesCategory;
}));
$categoryOptions = array_values(array_unique(array_map(static fn ($product) => $product['category'], $activeProducts)));
include __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div>
        <span class="eyebrow">Stock control</span>
        <h1>Inventory</h1>
        <p>Keep an eye on products, pricing, and stock levels.</p>
    </div>
    <button class="button button-primary" id="addProductButton">+ Add Product</button>
</section>
<?php if ($deleteError !== ''): ?><p class="delete-feedback delete-feedback-error" role="alert"><?= htmlspecialchars($deleteError) ?></p><?php elseif (isset($_GET['deleted'])): ?><p class="delete-feedback" role="status">Product marked as Deleted. The audit record is available in Deleted Products History.</p><?php endif; ?>
<section class="inventory-summary">
    <div><span>Active products</span><strong><?= count($activeProducts) ?></strong></div>
    <div><span>Low stock</span><strong class="red-text"><?= groceria_low_stock_count() ?></strong></div>
    <div><span>Out of stock</span><strong class="amber-text"><?= groceria_out_of_stock_count() ?></strong></div>
    <div><span>Categories</span><strong><?= count($categoryOptions) ?></strong></div>
</section>
<section class="panel inventory-form-panel" id="productForm" hidden>
    <div class="panel-heading">
        <div><span class="eyebrow">Inventory form</span><h2>Add Product</h2></div>
        <button class="text-button" id="closeProductForm">Close</button>
    </div>
    <form method="post" class="inventory-form">
        <input type="hidden" name="product_action" value="add_product">
        <div class="inventory-form-grid">
            <label>Product Name<input type="text" name="product_name" required></label>
            <label>Category<select name="category"><?php foreach ($categoryOptions as $option): ?><option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option><?php endforeach; ?></select></label>
            <label>Supplier<input type="text" name="supplier" value="General Supplier"></label>
            <label>Purchase Price<input type="number" step="0.01" name="purchase_price" value="0"></label>
            <label>Selling Price<input type="number" step="0.01" name="selling_price" value="0"></label>
            <label>Stock Quantity<input type="number" name="stock_quantity" value="0" min="0"></label>
            <label>Minimum Stock<input type="number" name="minimum_stock" value="0" min="0"></label>
            <label>Expiry Date<input type="date" name="expiry_date" value="<?= date('Y-m-d') ?>"></label>
        </div>
        <button type="submit" class="button button-primary">Save Product</button>
    </form>
</section>
<section class="panel table-panel">
    <div class="panel-heading">
        <div><span class="eyebrow">Product catalog</span><h2>All products</h2></div>
        <div class="table-tools">
            <form method="get" class="inventory-filters">
                <div class="mini-search"><span>⌕</span><input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search product"></div>
                <select class="select-control" name="category">
                    <option value="All" <?= $category === 'All' ? 'selected' : '' ?>>All</option>
                    <?php foreach ($categoryOptions as $option): ?><option value="<?= htmlspecialchars($option) ?>" <?= $category === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option><?php endforeach; ?>
                </select>
                <button class="filter-button" type="submit">Filter</button>
            </form>
        </div>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Product name</th>
                    <th>Category</th>
                    <th>Stock quantity</th>
                    <th>Purchase price</th>
                    <th>Selling price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filteredProducts as $item): ?>
                    <tr>
                        <td class="strong"><?= htmlspecialchars($item['name']) ?></td>
                        <td class="muted"><?= htmlspecialchars($item['category']) ?></td>
                        <td><?= (int) $item['stock_quantity'] ?> units</td>
                        <td class="muted"><?= groceria_format_money((float) $item['purchase_price']) ?></td>
                        <td class="strong"><?= groceria_format_money((float) $item['selling_price']) ?></td>
                        <td><span class="status <?= strtolower(str_replace(' ', '-', groceria_product_status($item))) ?>"><?= groceria_product_status($item) ?></span></td>
                        <td>
                            <div class="table-actions">
                                <button type="button" class="mini-action danger" data-delete-product="<?= (int) $item['id'] ?>" data-product-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>">Delete</button>
                                <button type="button" class="mini-action" data-edit-product="<?= (int) $item['id'] ?>">Edit</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<div class="delete-modal-backdrop" id="deleteProductModal" hidden>
    <section class="delete-modal" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
        <button type="button" class="delete-modal-close" data-cancel-delete aria-label="Cancel deletion">×</button>
        <span class="eyebrow">Inventory control</span>
        <h2 id="deleteModalTitle">Delete product</h2>
        <form method="post" id="deleteProductForm">
            <input type="hidden" name="product_action" value="soft_delete_product">
            <input type="hidden" name="product_id" id="deleteProductId" value="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(groceria_csrf_token()) ?>">
            <input type="hidden" name="confirm_delete" id="confirmDeleteValue" value="0">
            <div id="deleteDetailsStep">
                <p class="delete-product-name" id="deleteProductName"></p>
                <label class="delete-field">Reason for deletion<textarea name="deletion_reason" id="deletionReason" rows="3" maxlength="1000" required></textarea></label>
                <label class="delete-field">Your administrator password<input type="password" name="admin_password" id="deleteAdminPassword" autocomplete="current-password" required></label>
                <div class="delete-modal-actions"><button type="button" class="button button-primary" id="reviewDeleteButton">Review deletion</button><button type="button" class="mini-action" data-cancel-delete>Cancel</button></div>
            </div>
            <div id="deleteConfirmStep" hidden>
                <p>This will mark the following product as <strong>Deleted</strong>. Existing sales and order records will remain unchanged.</p>
                <dl class="delete-confirm-details"><dt>Product</dt><dd id="confirmProductName"></dd><dt>Reason</dt><dd id="confirmDeleteReason"></dd></dl>
                <div class="delete-modal-actions"><button type="submit" class="button danger-button" id="confirmDeleteButton">Confirm Delete</button><button type="button" class="mini-action" data-cancel-delete>Cancel</button></div>
            </div>
        </form>
    </section>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>

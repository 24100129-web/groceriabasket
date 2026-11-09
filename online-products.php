<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'Online Products';
$activePage = 'online-products';
$products = array_values(array_filter(groceria_products(), static fn ($product) => empty($product['is_deleted'])));
$bundles = groceria_bundles();
$bundleError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['bundle_action'] ?? '') === 'delete_bundle') {
    if (!hash_equals(groceria_csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
        $bundleError = 'Your request expired. Refresh the page and try again.';
    } else {
        $bundleId = (int) ($_POST['bundle_id'] ?? 0);
        $remainingBundles = array_values(array_filter($bundles, static fn ($bundle) => (int) $bundle['id'] !== $bundleId));
        if (count($remainingBundles) === count($bundles)) {
            $bundleError = 'That bundle could not be found.';
        } else {
            groceria_save_bundles($remainingBundles);
            header('Location: online-products.php?bundle_deleted=1');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['bundle_action'] ?? '') === 'save_bundle') {
    $bundleId = (int) ($_POST['bundle_id'] ?? 0);
    $name = trim((string) ($_POST['bundle_name'] ?? ''));
    $status = in_array($_POST['bundle_status'] ?? 'Available', ['Available', 'Hidden'], true) ? $_POST['bundle_status'] : 'Available';
    if ($name !== '') {
        $items = [];
        foreach ($_POST['bundle_item_name'] ?? [] as $index => $productName) {
            $productName = trim((string) $productName);
            $qty = (int) ($_POST['bundle_item_qty'][$index] ?? 0);
            if ($productName !== '' && $qty > 0) {
                $items[] = ['product_name' => $productName, 'quantity' => $qty];
            }
        }

        if (!empty($items)) {
            $image = 'dot-yellow';
            foreach ($bundles as $bundle) {
                if ((int) $bundle['id'] === $bundleId) {
                    $image = $bundle['image'];
                    break;
                }
            }
            $uploadedImage = $_FILES['bundle_image'] ?? null;
            if ($uploadedImage && $uploadedImage['error'] === UPLOAD_ERR_OK && is_uploaded_file($uploadedImage['tmp_name'])) {
                $imageInfo = @getimagesize($uploadedImage['tmp_name']);
                $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if ($imageInfo && isset($allowedTypes[$imageInfo['mime']])) {
                    $uploadDirectory = __DIR__ . '/images/bundles';
                    if (!is_dir($uploadDirectory)) {
                        mkdir($uploadDirectory, 0755, true);
                    }
                    $fileName = 'bundle-' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$imageInfo['mime']];
                    if (move_uploaded_file($uploadedImage['tmp_name'], $uploadDirectory . '/' . $fileName)) {
                        $image = 'images/bundles/' . $fileName;
                    }
                }
            }
            $bundleData = [
                'name' => $name,
                'description' => trim((string) ($_POST['bundle_description'] ?? '')),
                'price' => (float) ($_POST['bundle_price'] ?? 0),
                'status' => $status,
                'image' => $image,
                'items' => $items,
            ];
            $updated = false;
            foreach ($bundles as $index => $bundle) {
                if ((int) $bundle['id'] === $bundleId) {
                    $bundles[$index] = array_merge($bundle, $bundleData);
                    $updated = true;
                    break;
                }
            }
            if (!$updated) {
                $bundleData['id'] = empty($bundles) ? 1 : max(array_column($bundles, 'id')) + 1;
                $bundles[] = $bundleData;
            }
            groceria_save_bundles($bundles);
        }
    }
}

$availableBundles = array_values(array_filter($bundles, static fn ($bundle) => groceria_bundle_status($bundle) === 'Available'));
include __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div>
        <span class="eyebrow">Online store management</span>
        <h1>Online Products</h1>
        <p>Manage bundles customers can buy online using your existing physical inventory.</p>
    </div>
    <button class="button button-primary" id="addBundleButton">+ Add Bundle</button>
</section>
<section class="bundle-summary">
    <div><span>Active bundles</span><strong><?= count($bundles) ?></strong></div>
    <div><span>Available bundles</span><strong><?= count($availableBundles) ?></strong></div>
    <div><span>Physical products used</span><strong><?= count($products) ?></strong></div>
    <div><span>Store channel</span><strong class="channel-label">BUNDLES ONLY</strong></div>
</section>
<?php if ($bundleError !== ''): ?><div class="delete-feedback delete-feedback-error" role="alert"><?= htmlspecialchars($bundleError) ?></div><?php elseif (isset($_GET['bundle_deleted'])): ?><div class="delete-feedback" role="status">Bundle deleted. Inventory products were not changed.</div><?php endif; ?>
<section class="bundle-grid">
    <?php foreach ($bundles as $bundle): $status = groceria_bundle_status($bundle); $available = groceria_bundle_available_count($bundle); ?>
        <article class="bundle-card">
            <?php $hasBundleImage = str_contains((string) $bundle['image'], '/'); ?>
            <div class="bundle-art <?= $hasBundleImage ? 'has-image' : htmlspecialchars($bundle['image']) ?>"<?= $hasBundleImage ? ' style="background-image: url(\'' . htmlspecialchars($bundle['image'], ENT_QUOTES) . '\')"' : '' ?>><span>GB</span><small>BUNDLE</small></div>
            <div class="bundle-card-body">
                <div class="bundle-card-heading">
                    <div><span class="eyebrow">Online package</span><h2><?= htmlspecialchars($bundle['name']) ?></h2></div>
                    <span class="status <?= strtolower(str_replace(' ', '-', $status)) ?>"><?= htmlspecialchars($status) ?></span>
                </div>
                <p><?= htmlspecialchars($bundle['description']) ?></p>
                <div class="bundle-includes">
                    <b>Includes</b>
                    <?php foreach ($bundle['items'] as $item): ?>
                        <span><?= htmlspecialchars($item['product_name']) ?> × <?= (int) $item['quantity'] ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="bundle-card-footer">
                    <div>
                        <strong><?= groceria_format_money((float) $bundle['price']) ?></strong>
                        <small><?= $available ?> packages available</small>
                    </div>
                    <div>
                        <button class="outline-button" type="button">View</button>
                        <button class="button button-primary small-button edit-bundle-button" type="button" data-bundle-id="<?= (int) $bundle['id'] ?>" data-bundle-name="<?= htmlspecialchars($bundle['name'], ENT_QUOTES) ?>" data-bundle-description="<?= htmlspecialchars($bundle['description'], ENT_QUOTES) ?>" data-bundle-price="<?= htmlspecialchars((string) $bundle['price'], ENT_QUOTES) ?>" data-bundle-status="<?= htmlspecialchars($bundle['status'], ENT_QUOTES) ?>" data-bundle-items="<?= htmlspecialchars(json_encode($bundle['items']), ENT_QUOTES, 'UTF-8') ?>">Edit</button>
                        <form method="post" onsubmit="return confirm('Delete this bundle only? The inventory products included in it will not be deleted.');">
                            <input type="hidden" name="bundle_action" value="delete_bundle">
                            <input type="hidden" name="bundle_id" value="<?= (int) $bundle['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(groceria_csrf_token()) ?>">
                            <button class="button danger-button small-button" type="submit">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</section>
<section class="panel bundle-form-panel" id="bundleForm" hidden>
    <div class="panel-heading">
        <div><span class="eyebrow">Bundle builder</span><h2 id="bundleFormTitle">Add Online Bundle</h2></div>
        <button class="text-button" id="closeBundleForm">Close</button>
    </div>
    <form method="post" class="bundle-form" enctype="multipart/form-data">
        <input type="hidden" name="bundle_action" value="save_bundle">
        <input type="hidden" name="bundle_id" id="bundleId" value="0">
        <label>Bundle Name<input type="text" name="bundle_name" placeholder="e.g. Christmas Family Package" required></label>
        <label>Bundle Description<textarea name="bundle_description" rows="2" placeholder="Describe what this package is for"></textarea></label>
        <label>Bundle Picture<input type="file" name="bundle_image" accept="image/jpeg,image/png,image/webp"><small class="form-hint">JPG, PNG, or WebP. Leave empty to keep the current picture.</small></label>
        <div class="bundle-builder-grid">
            <div>
                <b>Products from Inventory</b>
                <?php foreach ($products as $product): ?>
                    <label class="check-row">
                        <input class="bundle-item-checkbox" type="checkbox" name="bundle_item_name[]" value="<?= htmlspecialchars($product['name']) ?>">
                        <?= htmlspecialchars($product['name']) ?>
                        <input class="quantity-input" type="number" name="bundle_item_qty[]" min="1" value="1">
                    </label>
                <?php endforeach; ?>
            </div>
            <div>
                <label>Bundle Price<input type="number" step="0.01" name="bundle_price" value="0"></label>
                <label>Online Status<select name="bundle_status"><option>Available</option><option>Hidden</option></select></label>
            </div>
        </div>
        <button type="submit" class="button button-primary" id="bundleSubmitButton">Save Bundle</button>
    </form>
</section>
<?php include __DIR__ . '/partials/footer.php'; ?>

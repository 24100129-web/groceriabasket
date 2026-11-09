<?php
require __DIR__ . '/app_state.php';
$pageTitle = 'Store Management';
$activePage = 'store';
$settings = groceria_store_settings();
$error = '';
$saved = isset($_GET['saved']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(groceria_csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your form expired. Refresh and try again.';
    } else {
        try {
            groceria_save_store_settings(array_merge($settings, $_POST));
            header('Location: store-management.php?saved=1');
            exit;
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            $settings = array_merge($settings, $_POST);
        }
    }
}
include __DIR__ . '/partials/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Business profile</span><h1>Store Management</h1><p>Maintain the store information used by staff and customer order records.</p></div></section>
<?php if ($saved): ?><p class="settings-message">Store information saved.</p><?php endif; ?><?php if ($error !== ''): ?><p class="delete-feedback-error settings-message" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<section class="panel store-settings-panel"><div class="panel-heading"><div><span class="eyebrow">Store profile</span><h2>Contact and location</h2></div></div><form method="post" class="store-settings-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(groceria_csrf_token()) ?>"><label>Store name<input name="store_name" required maxlength="120" value="<?= htmlspecialchars((string) $settings['store_name']) ?>"></label><label>Store address<textarea name="address" rows="3" maxlength="255"><?= htmlspecialchars((string) $settings['address']) ?></textarea></label><div class="settings-form-row"><label>Contact number<input name="contact_number" maxlength="60" value="<?= htmlspecialchars((string) $settings['contact_number']) ?>"></label><label>Store email<input type="email" name="email" maxlength="160" value="<?= htmlspecialchars((string) $settings['email']) ?>"></label></div><label>Opening hours<input name="opening_hours" maxlength="120" placeholder="e.g. Monday–Saturday, 7:00 AM–8:00 PM" value="<?= htmlspecialchars((string) $settings['opening_hours']) ?>"></label><button class="button button-primary" type="submit">Save store information</button></form></section>
<?php include __DIR__ . '/partials/footer.php'; ?>

<?php
require __DIR__ . '/app_state.php';
$pageTitle = 'Settings';
$activePage = 'settings';
$settings = groceria_store_settings();
$error = '';
$saved = isset($_GET['saved']);
$timezones = ['Asia/Manila', 'UTC', 'Asia/Singapore', 'Asia/Tokyo', 'America/Los_Angeles', 'America/New_York', 'Europe/London'];
$transactionsReset = isset($_GET['transactions_reset']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(groceria_csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your form expired. Refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'reset_sales') {
        if (!groceria_verify_current_admin_password((string) ($_POST['admin_password'] ?? ''))) {
            $error = 'The admin password is incorrect. No transactions were deleted.';
        } else {
            try {
                groceria_reset_sales();
                header('Location: settings.php?transactions_reset=1');
                exit;
            } catch (RuntimeException $exception) {
                error_log('Groceria transaction reset failed: ' . $exception->getMessage());
                $error = 'Transactions could not be fully reset. Please try again or contact support.';
            }
        }
    } else {
        $submitted = array_merge($settings, $_POST);
        $submitted['low_stock_alerts'] = isset($_POST['low_stock_alerts']) ? 1 : 0;
        try {
            groceria_save_store_settings($submitted);
            header('Location: settings.php?saved=1');
            exit;
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            $settings = $submitted;
        }
    }
}
include __DIR__ . '/partials/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">System preferences</span><h1>Settings</h1><p>Configure store alerts and regional date/time behavior.</p></div></section>
<?php if ($saved): ?><p class="settings-message">Settings saved.</p><?php endif; ?><?php if ($transactionsReset): ?><p class="settings-message">All sales transactions and online orders were deleted. New sales will start again from the beginning. Inventory and capital records were not changed.</p><?php endif; ?><?php if ($error !== ''): ?><p class="delete-feedback-error settings-message" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<section class="panel store-settings-panel"><div class="panel-heading"><div><span class="eyebrow">Inventory alerts</span><h2>Low stock notifications</h2></div></div><form method="post" class="store-settings-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(groceria_csrf_token()) ?>"><input type="hidden" name="store_name" value="<?= htmlspecialchars((string) $settings['store_name']) ?>"><input type="hidden" name="address" value="<?= htmlspecialchars((string) $settings['address']) ?>"><input type="hidden" name="contact_number" value="<?= htmlspecialchars((string) $settings['contact_number']) ?>"><input type="hidden" name="email" value="<?= htmlspecialchars((string) $settings['email']) ?>"><input type="hidden" name="opening_hours" value="<?= htmlspecialchars((string) $settings['opening_hours']) ?>"><input type="hidden" name="currency_code" value="<?= htmlspecialchars((string) $settings['currency_code']) ?>"><label>Store timezone<select name="timezone"><?php foreach ($timezones as $timezone): ?><option value="<?= htmlspecialchars($timezone) ?>" <?= $settings['timezone'] === $timezone ? 'selected' : '' ?>><?= htmlspecialchars($timezone) ?></option><?php endforeach; ?></select></label><label class="settings-toggle"><input type="checkbox" name="low_stock_alerts" value="1" <?= !empty($settings['low_stock_alerts']) ? 'checked' : '' ?>><span><strong>Show low-stock count in admin navigation</strong><small>When disabled, inventory status remains available on the Product Status page.</small></span></label><button class="button button-primary" type="submit">Save settings</button></form></section>
<section class="panel store-settings-panel settings-reset-panel"><div class="panel-heading"><div><span class="eyebrow">Danger zone</span><h2>Reset sales transactions</h2></div></div><div class="settings-reset-content"><p>Permanently delete all walk-in sales and online order records. Transaction numbering will start over. Inventory quantities and capital records will remain unchanged.</p><form method="post" onsubmit="return confirm('Permanently delete all sales transactions and online orders? This cannot be undone. Inventory and capital records will not be changed.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(groceria_csrf_token()) ?>"><input type="hidden" name="action" value="reset_sales"><label class="settings-reset-password">Admin password<input type="password" name="admin_password" autocomplete="current-password" required></label><button class="button danger-button" type="submit">Reset all transactions</button></form></div></section>
<?php include __DIR__ . '/partials/footer.php'; ?>

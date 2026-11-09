<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'Cashier / POS';
$activePage = 'pos';
$products = array_values(array_filter(groceria_products(), static fn ($product) => empty($product['is_deleted'])));
$receipt = groceria_get_last_receipt();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_sale'])) {
    $payload = json_decode((string) ($_POST['cart_json'] ?? '[]'), true);
    $cashReceived = (float) ($_POST['cash_received'] ?? 0);
    $paymentMethod = in_array(strtolower((string) ($_POST['payment_method'] ?? 'cash')), ['cash', 'gcash'], true)
        ? strtolower((string) ($_POST['payment_method'] ?? 'cash'))
        : 'cash';
    $productsSession = groceria_products();
    $errors = [];
    $lineItems = [];
    $total = 0.0;

    foreach ($payload as $entry) {
        $product = groceria_product_lookup($productsSession, (string) ($entry['name'] ?? ''));
        if (!$product) {
            $errors[] = 'Product not found.';
            continue;
        }

        $qty = max(0, (int) ($entry['qty'] ?? 0));
        if ($qty <= 0) {
            continue;
        }

        if ($qty > (int) $product['stock_quantity']) {
            $errors[] = $product['name'] . ' only has ' . $product['stock_quantity'] . ' left in stock.';
            continue;
        }

        $amount = (float) $product['selling_price'] * $qty;
        $total += $amount;
        $lineItems[] = [
            'name' => $product['name'],
            'qty' => $qty,
            'price' => (float) $product['selling_price'],
            'amount' => $amount,
            'label' => $product['name'] . ' × ' . $qty,
        ];
    }

    if (empty($lineItems) || !empty($errors)) {
        $receipt = ['error' => implode(' ', $errors)];
    } else {
        foreach ($lineItems as $lineItem) {
            foreach ($productsSession as $index => $product) {
                if (strtolower($product['name']) === strtolower($lineItem['name'])) {
                    $productsSession[$index]['stock_quantity'] = max(0, (int) $product['stock_quantity'] - $lineItem['qty']);
                    $productsSession[$index]['status'] = groceria_product_status($productsSession[$index]);
                    break;
                }
            }
        }

        if ($paymentMethod === 'gcash') {
            $cashReceived = 0.0;
        }
        groceria_save_products($productsSession);
        $change = $paymentMethod === 'cash' ? max(0, $cashReceived - $total) : 0.0;
        $saleType = 'WALK-IN';
        groceria_insert_sale($saleType, $lineItems, $cashReceived, $total, 'Angela Mercado', strtoupper($paymentMethod));
        $receipt = [
            'transaction_id' => '000' . (count(groceria_sales())),
            'items' => $lineItems,
            'total' => $total,
            'cash' => $cashReceived,
            'change' => $change,
            'payment_method' => strtoupper($paymentMethod),
            'sale_type' => $saleType,
        ];
        groceria_set_last_receipt($receipt);
    }
}

include __DIR__ . '/partials/header.php';
?>
<section class="pos-screen">
    <div class="pos-workspace">
        <div class="pos-title-row"><h1>POS</h1><span class="pos-live"><i></i> Walk-in cashier</span></div>
        <div class="pos-search-large"><span>▦</span><input id="posSearch" type="search" placeholder="Search product by name" aria-label="Search products"><b>⌕</b></div>
        <p class="pos-tip">💡 Search for individual products, add to cart, adjust quantity, and complete the sale.</p>
        <div class="pos-product-grid" id="posProductGrid">
            <?php foreach ($products as $product): ?>
                <?php $status = groceria_product_status($product); ?>
                <button type="button" class="pos-product" data-product-name="<?= htmlspecialchars($product['name']) ?>" data-product-price="<?= (float) $product['selling_price'] ?>" data-product-stock="<?= (int) $product['stock_quantity'] ?>" data-product-status="<?= htmlspecialchars($status) ?>">
                    <span class="product-dot dot-<?= strtolower(str_replace(' ', '-', $product['name'])) ?>"><?= htmlspecialchars($product['image']) ?></span>
                    <span><b><?= htmlspecialchars($product['name']) ?></b><small><?= groceria_format_money((float) $product['selling_price']) ?></small></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <aside class="pos-order-panel">
        <form id="saleForm" method="post">
            <input type="hidden" name="complete_sale" value="1">
            <input type="hidden" id="cartJson" name="cart_json" value="[]">
            <div class="pos-total-display"><span>TOTAL</span><strong id="totalAmount">₱0.00</strong><small>PHP (Philippines)</small></div>
            <div class="customer-type"><b>Customer Type</b><div><button class="selected" type="button">Regular</button><button type="button">PWD</button><button type="button">Senior</button></div></div>
            <div id="cartList" class="cart-list">
                <div class="pos-cart-empty">No items selected</div>
            </div>
            <div class="pos-subtotal"><span id="subtotalLabel">Subtotal (0 items)</span><b id="subtotalAmount">₱0.00</b></div>
            <div class="payment-box">
                <b>Payment Method</b>
                <div class="payment-tabs">
                    <button type="button" data-payment-method="gcash">GCash</button>
                    <button type="button" class="selected" data-payment-method="cash">Cash</button>
                </div>
                <input type="hidden" id="paymentMethod" name="payment_method" value="cash">
                <div class="pos-payment-qr" id="gcashPaymentPanel" hidden>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&amp;margin=4&amp;data=GROCERIA%20BASKET%20DEMO%20ONLY%20-%20NOT%20A%20PAYMENT%20CODE" alt="Demo QR code. This code does not accept payments." width="144" height="144">
                    
                </div>
                <div class="cash-payment-details" id="cashPaymentDetails">
                    <label>Amount Tendered<input id="cashReceived" type="number" step="0.01" min="0" value="0" name="cash_received" aria-label="Amount tendered"></label>
                    <div class="cash-shortcuts">
                        <button type="button" class="cash-button" data-value="100">₱100</button>
                        <button type="button" class="cash-button" data-value="500">₱500</button>
                        <button type="button" class="cash-button" data-value="1000">₱1,000</button>
                        <button type="button" class="cash-button selected" data-value="exact">Exact</button>
                    </div>
                    <div class="change-due"><span>Change Due</span><strong id="changeDue">₱0.00</strong></div>
                </div>
            </div>
            <div class="pos-actions"><button type="submit" class="complete-sale">Complete Sale</button><button type="button" class="cancel-sale" id="clearCart">Cancel</button></div>
        </form>
    </aside>
</section>
<?php if ($receipt && !empty($receipt['items'])): ?>
    <div class="receipt-modal" id="receiptModal">
        <div class="receipt-box">
            <h3>GROCERIA BASKET</h3>
            <p>Walk-In Sale</p>
            <small>Transaction #: <?= htmlspecialchars((string) $receipt['transaction_id']) ?></small>
            <?php foreach ($receipt['items'] as $item): ?>
                <div class="receipt-row"><span><?= htmlspecialchars($item['name']) ?> × <?= (int) $item['qty'] ?></span><strong><?= groceria_format_money((float) $item['amount']) ?></strong></div>
            <?php endforeach; ?>
            <div class="receipt-row total-row"><span>TOTAL</span><strong><?= groceria_format_money((float) $receipt['total']) ?></strong></div>
            <div class="receipt-row"><span>Payment Method</span><strong><?= htmlspecialchars((string) ($receipt['payment_method'] ?? 'CASH')) ?></strong></div>
            <?php if (($receipt['payment_method'] ?? 'CASH') === 'CASH'): ?>
                <div class="receipt-row"><span>Cash</span><strong><?= groceria_format_money((float) $receipt['cash']) ?></strong></div>
                <div class="receipt-row"><span>Change</span><strong><?= groceria_format_money((float) $receipt['change']) ?></strong></div>
            <?php endif; ?>
            <div class="receipt-tag">Sale Type: WALK-IN</div>
            <button type="button" class="button button-primary" id="newSale">New Sale</button>
        </div>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>

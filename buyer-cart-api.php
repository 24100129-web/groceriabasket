<?php
require __DIR__ . '/app_state.php';
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['groceria_user']['role'] ?? '') !== 'buyer') {
    http_response_code(401);
    echo json_encode(['error' => 'Please sign in as a buyer.']);
    exit;
}
function groceria_cart_response(): array
{
    $cart = groceria_cart();
    return ['cart' => $cart, 'count' => array_sum(array_column($cart, 'quantity'))];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(groceria_cart_response(), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!hash_equals(groceria_csrf_token(), (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    http_response_code(419);
    echo json_encode(['error' => 'Your session expired. Refresh and try again.']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true) ?: [];
$action = (string) ($payload['action'] ?? '');
$bundleId = (int) ($payload['bundle_id'] ?? 0);
$cart = groceria_cart();
$items = [];
foreach ($cart as $item) {
    $items[(int) $item['bundle_id']] = (int) $item['quantity'];
}
$bundle = null;
foreach (groceria_bundles() as $candidate) {
    if ((int) $candidate['id'] === $bundleId) {
        $bundle = $candidate;
        break;
    }
}

if ($action === 'add' || $action === 'set') {
    if (!$bundle) {
        http_response_code(404);
        echo json_encode(['error' => 'Bundle not found.']);
        exit;
    }
    $requested = $action === 'add' ? ($items[$bundleId] ?? 0) + 1 : max(0, (int) ($payload['quantity'] ?? 0));
    if ($requested > groceria_bundle_available_count($bundle)) {
        http_response_code(422);
        echo json_encode(['error' => 'There is not enough stock for that quantity.']);
        exit;
    }
    if ($requested > 0) {
        $items[$bundleId] = $requested;
    } else {
        unset($items[$bundleId]);
    }
} elseif ($action === 'remove') {
    unset($items[$bundleId]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown cart action.']);
    exit;
}

$updatedCart = [];
foreach ($items as $id => $quantity) {
    $updatedCart[] = ['bundle_id' => $id, 'quantity' => $quantity];
}
groceria_save_cart($updatedCart);
echo json_encode(groceria_cart_response(), JSON_UNESCAPED_UNICODE);

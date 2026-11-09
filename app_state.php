<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



function groceria_db(): ?PDO
{
    static $connection = false;

    if ($connection !== false) {
        return $connection;
    }

    $host = getenv('GROCERIA_DB_HOST') ?: '127.0.0.1';
    $database = getenv('GROCERIA_DB_NAME') ?: 'groceria_basket';
    $port = getenv('GROCERIA_DB_PORT') ?: '3306';
    $username = getenv('GROCERIA_DB_USER') ?: 'root';
    $password = getenv('GROCERIA_DB_PASSWORD');
    if ($password === false) {
        $password = '';
    }

    try {
        $connection = new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

    } catch (PDOException $exception) {
        error_log('Groceria database connection failed: ' . $exception->getMessage());
        $connection = null;
    }

    return $connection;
}

function groceria_csrf_token(): string
{
    if (empty($_SESSION['groceria_csrf'])) {
        $_SESSION['groceria_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['groceria_csrf'];
}

function groceria_private_data_path(string $fileName, ?string $legacyPath = null): string
{
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.groceria-private';
    if (!is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
    $path = $directory . DIRECTORY_SEPARATOR . $fileName;
    if (!is_file($path) && $legacyPath !== null && is_file($legacyPath)) {
        $contents = file_get_contents($legacyPath);
        if ($contents !== false && file_put_contents($path, $contents, LOCK_EX) !== false) {
            unlink($legacyPath);
        }
    }
    return $path;
}

function groceria_resolve_user_id(string $email, string $name, string $role, string $password): string
{
    $db = groceria_db();
    if (!$db) {
        return strtolower($email);
    }
    $query = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $query->execute(['email' => strtolower($email)]);
    $userId = $query->fetchColumn();
    if ($userId === false) {
        $insert = $db->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (:name, :email, :hash, :role)');
        $insert->execute(['name' => $name, 'email' => strtolower($email), 'hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role]);
        $userId = (string) $db->lastInsertId();
    }
    return (string) $userId;
}

function groceria_customer_accounts(): array
{
    $path = groceria_private_data_path('customer_accounts.json', __DIR__ . '/database/customer_accounts.json');
    return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
}

function groceria_save_customer_accounts(array $accounts): void
{
    file_put_contents(groceria_private_data_path('customer_accounts.json', __DIR__ . '/database/customer_accounts.json'), json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function groceria_customer_profiles(): array
{
    $path = groceria_private_data_path('customer_profiles.json', __DIR__ . '/database/customer_profiles.json');
    return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
}

function groceria_customer_records(): array
{
    $db = groceria_db();
    if ($db) {
        $query = $db->prepare('SELECT id, full_name, email, contact_number, house_street, barangay, city, province, zip_code, address_details, created_at FROM users WHERE role = :role ORDER BY created_at DESC, id DESC');
        $query->execute(['role' => 'buyer']);
        return array_map(static fn ($row) => array_merge($row, ['id' => (string) $row['id']]), $query->fetchAll());
    }
    $profiles = groceria_customer_profiles();
    $records = [];
    foreach (groceria_customer_accounts() as $account) {
        $profile = $profiles[(string) $account['id']] ?? [];
        $records[] = array_merge($profile, [
            'id' => (string) $account['id'],
            'full_name' => $profile['full_name'] ?? $account['full_name'] ?? '',
            'email' => $account['email'],
            'created_at' => $account['created_at'] ?? '',
        ]);
    }
    return $records;
}

function groceria_store_settings(): array
{
    $defaults = [
        'store_name' => 'Groceria Basket', 'address' => '', 'contact_number' => '',
        'email' => '', 'opening_hours' => '', 'currency_code' => 'PHP',
        'timezone' => 'Asia/Manila', 'low_stock_alerts' => 1,
    ];
    $db = groceria_db();
    if ($db) {
        $row = $db->query('SELECT id, store_name, address, contact_number, email, opening_hours, currency_code, timezone, low_stock_alerts FROM stores ORDER BY id LIMIT 1')->fetch();
        if (!$row) {
            $insert = $db->prepare('INSERT INTO stores (store_name) VALUES (:store_name)');
            $insert->execute(['store_name' => $defaults['store_name']]);
            $row = ['id' => (int) $db->lastInsertId()];
        }
        $settings = array_merge($defaults, array_intersect_key($row, $defaults), ['id' => (int) $row['id']]);
    } else {
        $path = groceria_private_data_path('store_settings.json');
        $saved = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        $settings = array_merge($defaults, $saved);
    }
    if (in_array($settings['timezone'], timezone_identifiers_list(), true)) {
        date_default_timezone_set($settings['timezone']);
    }
    return $settings;
}

function groceria_save_store_settings(array $settings): void
{
    $allowed = ['store_name', 'address', 'contact_number', 'email', 'opening_hours', 'currency_code', 'timezone'];
    $clean = [];
    foreach ($allowed as $field) {
        $clean[$field] = trim((string) ($settings[$field] ?? ''));
    }
    $clean['low_stock_alerts'] = isset($settings['low_stock_alerts']) ? 1 : 0;
    if ($clean['store_name'] === '' || mb_strlen($clean['store_name'], 'UTF-8') > 120) {
        throw new InvalidArgumentException('Enter a store name up to 120 characters.');
    }
    if ($clean['email'] !== '' && !filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid store email address.');
    }
    foreach (['address' => 255, 'contact_number' => 60, 'email' => 160, 'opening_hours' => 120, 'currency_code' => 3, 'timezone' => 64] as $field => $maximum) {
        if (mb_strlen($clean[$field], 'UTF-8') > $maximum) {
            throw new InvalidArgumentException('The ' . str_replace('_', ' ', $field) . ' field is too long.');
        }
    }
    if (!in_array($clean['timezone'], timezone_identifiers_list(), true)) {
        throw new InvalidArgumentException('Choose a valid store timezone.');
    }
    $db = groceria_db();
    if ($db) {
        $current = groceria_store_settings();
        $update = $db->prepare('UPDATE stores SET store_name = :store_name, address = :address, contact_number = :contact_number, email = :email, opening_hours = :opening_hours, currency_code = :currency_code, timezone = :timezone, low_stock_alerts = :low_stock_alerts WHERE id = :id');
        $update->execute($clean + ['id' => (int) $current['id']]);
        return;
    }
    file_put_contents(groceria_private_data_path('store_settings.json'), json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function groceria_find_customer_by_email(string $email): ?array
{
    foreach (groceria_customer_accounts() as $account) {
        if (strtolower((string) $account['email']) === strtolower($email)) {
            return $account;
        }
    }
    return null;
}

function groceria_register_customer(array $profile, string $password): string
{
    $email = strtolower(trim((string) ($profile['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        throw new InvalidArgumentException('Enter a valid email and a password with at least 8 characters.');
    }
    if (in_array($email, ['admin@groceriabasket.com', 'buyer@groceriabasket.com'], true)) {
        throw new InvalidArgumentException('That email is reserved for a demo account. Use another email address.');
    }
    $required = ['full_name', 'contact_number', 'house_street', 'barangay', 'city', 'province', 'zip_code'];
    foreach ($required as $field) {
        if (trim((string) ($profile[$field] ?? '')) === '') {
            throw new InvalidArgumentException('Complete all required personal and address fields.');
        }
    }
    $db = groceria_db();
    $cleanFields = array_merge($required, ['address_details']);
    $clean = array_combine($cleanFields, array_map(static fn ($field) => trim((string) ($profile[$field] ?? '')), $cleanFields));
    $maximumLengths = ['full_name' => 120, 'contact_number' => 30, 'house_street' => 180, 'barangay' => 100, 'city' => 100, 'province' => 100, 'zip_code' => 16, 'address_details' => 255];
    if (strlen($email) > 160) {
        throw new InvalidArgumentException('Email address is too long.');
    }
    foreach ($maximumLengths as $field => $maximum) {
        if (mb_strlen($clean[$field], 'UTF-8') > $maximum) {
            throw new InvalidArgumentException('The ' . str_replace('_', ' ', $field) . ' field is too long.');
        }
    }
    if ($db) {
        $exists = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $exists->execute(['email' => $email]);
        if ($exists->fetchColumn() !== false) {
            throw new InvalidArgumentException('An account with this email already exists.');
        }
        $insert = $db->prepare('INSERT INTO users (full_name, email, password_hash, role, contact_number, house_street, barangay, city, province, zip_code, address_details) VALUES (:full_name, :email, :password_hash, \'buyer\', :contact_number, :house_street, :barangay, :city, :province, :zip_code, :address_details)');
        $insert->execute(array_merge($clean, ['email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]));
        return (string) $db->lastInsertId();
    }
    $accounts = groceria_customer_accounts();
    foreach ($accounts as $account) {
        if (strtolower((string) $account['email']) === $email) {
            throw new InvalidArgumentException('An account with this email already exists.');
        }
    }
    $userId = 'buyer-' . substr(hash('sha256', $email), 0, 24);
    $accounts[] = ['id' => $userId, 'email' => $email, 'full_name' => $clean['full_name'], 'password_hash' => password_hash($password, PASSWORD_DEFAULT)];
    groceria_save_customer_accounts($accounts);
    $profiles = groceria_customer_profiles();
    $profiles[$userId] = array_merge($clean, ['email' => $email]);
    file_put_contents(groceria_private_data_path('customer_profiles.json', __DIR__ . '/database/customer_profiles.json'), json_encode($profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $userId;
}

function groceria_get_customer_profile(): array
{
    $userId = groceria_current_user_id();
    $db = groceria_db();
    if ($db && ctype_digit($userId)) {
        $query = $db->prepare('SELECT full_name, email, contact_number, house_street, barangay, city, province, zip_code, address_details FROM users WHERE id = :id AND role = \'buyer\' LIMIT 1');
        $query->execute(['id' => (int) $userId]);
        $profile = $query->fetch();
        if ($profile) {
            return $profile;
        }
    }
    $profiles = groceria_customer_profiles();
    $profile = $profiles[$userId] ?? [];
    $profile['full_name'] = $profile['full_name'] ?? ($_SESSION['groceria_user']['name'] ?? '');
    $profile['email'] = $profile['email'] ?? ($_SESSION['groceria_user']['email'] ?? '');
    return $profile;
}

function groceria_save_customer_profile(array $profile): void
{
    $userId = groceria_current_user_id();
    $fields = ['full_name', 'contact_number', 'house_street', 'barangay', 'city', 'province', 'zip_code', 'address_details'];
    $values = [];
    foreach ($fields as $field) {
        $values[$field] = trim((string) ($profile[$field] ?? ''));
    }
    $maximumLengths = ['full_name' => 120, 'contact_number' => 30, 'house_street' => 180, 'barangay' => 100, 'city' => 100, 'province' => 100, 'zip_code' => 16, 'address_details' => 255];
    foreach ($maximumLengths as $field => $maximum) {
        if (mb_strlen($values[$field], 'UTF-8') > $maximum) {
            throw new InvalidArgumentException('The ' . str_replace('_', ' ', $field) . ' field is too long.');
        }
    }
    foreach (array_slice($fields, 0, 7) as $field) {
        if ($values[$field] === '') {
            throw new InvalidArgumentException('Complete all required personal and address fields.');
        }
    }
    $db = groceria_db();
    if ($db && ctype_digit($userId)) {
        $update = $db->prepare('UPDATE users SET full_name = :full_name, contact_number = :contact_number, house_street = :house_street, barangay = :barangay, city = :city, province = :province, zip_code = :zip_code, address_details = :address_details WHERE id = :id AND role = \'buyer\'');
        $update->execute($values + ['id' => (int) $userId]);
    } else {
        $profiles = groceria_customer_profiles();
        $profiles[$userId] = array_merge($profiles[$userId] ?? [], $values, ['email' => $_SESSION['groceria_user']['email'] ?? '']);
        file_put_contents(groceria_private_data_path('customer_profiles.json', __DIR__ . '/database/customer_profiles.json'), json_encode($profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        $accounts = groceria_customer_accounts();
        foreach ($accounts as &$account) {
            if ((string) $account['id'] === $userId) {
                $account['full_name'] = $values['full_name'];
            }
        }
        unset($account);
        groceria_save_customer_accounts($accounts);
    }
    $_SESSION['groceria_user']['name'] = $values['full_name'];
}

function groceria_format_customer_address(array $profile): string
{
    $street = trim((string) ($profile['house_street'] ?? ''));
    $barangay = trim((string) ($profile['barangay'] ?? ''));
    $cityProvince = trim(implode(', ', array_filter([
        trim((string) ($profile['city'] ?? '')),
        trim((string) ($profile['province'] ?? '')),
    ])));
    $zip = trim((string) ($profile['zip_code'] ?? ''));
    if ($zip !== '') {
        $cityProvince .= ($cityProvince !== '' ? ', ' : '') . $zip;
    }
    return implode("\n", array_filter([$street, $barangay !== '' ? 'Barangay ' . $barangay : '', $cityProvince, trim((string) ($profile['address_details'] ?? ''))]));
}

function groceria_store_state(): void
{
    if (!isset($_SESSION['groceria_products'])) {
        $_SESSION['groceria_products'] = [
            [
                'id' => 1,
                'name' => 'Sinandomeng Rice 5kg',
                'category' => 'Staples',
                'supplier' => 'Prime Foods',
                'purchase_price' => 240.00,
                'selling_price' => 285.00,
                'stock_quantity' => 24,
                'minimum_stock' => 10,
                'expiry_date' => '2026-12-15',
                'status' => 'Available',
                'is_damaged' => false,
                'image' => 'R',
            ],
            [
                'id' => 2,
                'name' => 'Nescafe Classic Coffee 25g',
                'category' => 'Beverages',
                'supplier' => 'Metro Supplies',
                'purchase_price' => 90.00,
                'selling_price' => 120.00,
                'stock_quantity' => 15,
                'minimum_stock' => 8,
                'expiry_date' => '2027-02-20',
                'status' => 'Available',
                'is_damaged' => false,
                'image' => 'C',
            ],
            [
                'id' => 3,
                'name' => 'Bear Brand Milk 33g',
                'category' => 'Dairy',
                'supplier' => 'Prime Foods',
                'purchase_price' => 72.00,
                'selling_price' => 85.00,
                'stock_quantity' => 8,
                'minimum_stock' => 10,
                'expiry_date' => '2026-11-02',
                'status' => 'Low Stock',
                'is_damaged' => false,
                'image' => 'M',
            ],
            [
                'id' => 4,
                'name' => 'Lucky Me! Pancit Canton Original 60g',
                'category' => 'Pantry',
                'supplier' => 'Metro Supplies',
                'purchase_price' => 11.00,
                'selling_price' => 15.00,
                'stock_quantity' => 45,
                'minimum_stock' => 12,
                'expiry_date' => '2027-06-18',
                'status' => 'Available',
                'is_damaged' => false,
                'image' => 'N',
            ],
            [
                'id' => 5,
                'name' => 'Gardenia Classic White Bread 400g',
                'category' => 'Bakery',
                'supplier' => 'Metro Supplies',
                'purchase_price' => 42.00,
                'selling_price' => 55.00,
                'stock_quantity' => 5,
                'minimum_stock' => 8,
                'expiry_date' => '2026-09-14',
                'status' => 'Low Stock',
                'is_damaged' => false,
                'image' => 'B',
            ],
            [
                'id' => 6,
                'name' => 'Argentina Corned Beef 175g',
                'category' => 'Pantry',
                'supplier' => 'Prime Foods',
                'purchase_price' => 25.00,
                'selling_price' => 32.00,
                'stock_quantity' => 0,
                'minimum_stock' => 10,
                'expiry_date' => '2027-04-11',
                'status' => 'Out of Stock',
                'is_damaged' => false,
                'image' => 'S',
            ],
            [
                'id' => 7,
                'name' => 'Natures Best Fresh Eggs 12pcs',
                'category' => 'Dairy',
                'supplier' => 'Metro Supplies',
                'purchase_price' => 82.00,
                'selling_price' => 96.00,
                'stock_quantity' => 32,
                'minimum_stock' => 10,
                'expiry_date' => '2026-10-25',
                'status' => 'Available',
                'is_damaged' => false,
                'image' => 'E',
            ],
            [
                'id' => 8,
                'name' => 'Coca-Cola 1.5L',
                'category' => 'Beverages',
                'supplier' => 'Prime Foods',
                'purchase_price' => 65.00,
                'selling_price' => 75.00,
                'stock_quantity' => 12,
                'minimum_stock' => 8,
                'expiry_date' => '2027-08-01',
                'status' => 'Available',
                'is_damaged' => false,
                'image' => 'D',
            ],
            [
                'id' => 9,
                'name' => 'Surf Powder Detergent 55g',
                'category' => 'Household',
                'supplier' => 'Metro Supplies',
                'purchase_price' => 9.00,
                'selling_price' => 12.00,
                'stock_quantity' => 28,
                'minimum_stock' => 10,
                'expiry_date' => '2028-01-10',
                'status' => 'Available',
                'is_damaged' => false,
                'image' => 'S',
            ],
        ];
    }

    if (!isset($_SESSION['groceria_bundles'])) {
        $_SESSION['groceria_bundles'] = [
            [
                'id' => 1,
                'name' => 'Christmas Family Package',
                'description' => 'A complete grocery package for the whole family.',
                'price' => 999.00,
                'status' => 'Available',
                'image' => 'dot-yellow',
                'items' => [
                    ['product_name' => 'Sinandomeng Rice 5kg', 'quantity' => 2],
                    ['product_name' => 'Nescafe Classic Coffee 25g', 'quantity' => 1],
                    ['product_name' => 'Bear Brand Milk 33g', 'quantity' => 1],
                    ['product_name' => 'Lucky Me! Pancit Canton Original 60g', 'quantity' => 2],
                ],
            ],
            [
                'id' => 2,
                'name' => 'Snack Bundle',
                'description' => 'A cheerful mix of snacks for sharing at home.',
                'price' => 450.00,
                'status' => 'Available',
                'image' => 'dot-red',
                'items' => [
                    ['product_name' => 'Gardenia Classic White Bread 400g', 'quantity' => 3],
                    ['product_name' => 'Natures Best Fresh Eggs 12pcs', 'quantity' => 2],
                    ['product_name' => 'Coca-Cola 1.5L', 'quantity' => 2],
                ],
            ],
            [
                'id' => 3,
                'name' => 'Family Essentials Bundle',
                'description' => 'Everyday pantry staples in one convenient package.',
                'price' => 620.00,
                'status' => 'Available',
                'image' => 'dot-blue',
                'items' => [
                    ['product_name' => 'Sinandomeng Rice 5kg', 'quantity' => 1],
                    ['product_name' => 'Bear Brand Milk 33g', 'quantity' => 1],
                    ['product_name' => 'Argentina Corned Beef 175g', 'quantity' => 2],
                ],
            ],
        ];
    }

    $bundleStoragePath = __DIR__ . '/database/bundles.json';
    if (is_file($bundleStoragePath)) {
        $storedBundles = json_decode((string) file_get_contents($bundleStoragePath), true);
        if (is_array($storedBundles) && !empty($storedBundles)) {
            $_SESSION['groceria_bundles'] = $storedBundles;
        }
    } else {
        file_put_contents(
            $bundleStoragePath,
            json_encode($_SESSION['groceria_bundles'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    if (!isset($_SESSION['groceria_sales'])) {
        $salesPath = groceria_private_data_path('pos_sales.json');
        if (is_file($salesPath)) {
            $_SESSION['groceria_sales'] = json_decode((string) file_get_contents($salesPath), true) ?: [];
        } else {
            $_SESSION['groceria_sales'] = [
                [
                    'id' => '1001',
                    'date' => '2026-09-14',
                    'time' => '10:42 AM',
                    'items' => 'Sinandomeng Rice 5kg × 2, Nescafe Classic Coffee 25g × 1',
                    'total' => 570.00,
                    'sale_type' => 'WALK-IN',
                    'cashier' => 'Angela Mercado',
                ],
                [
                    'id' => '1002',
                    'date' => '2026-09-14',
                    'time' => '11:20 AM',
                    'items' => 'Christmas Family Package × 1',
                    'total' => 999.00,
                    'sale_type' => 'ONLINE',
                    'cashier' => 'Angela Mercado',
                ],
            ];
        }
    }

    if (!isset($_SESSION['groceria_capital_transactions'])) {
        $_SESSION['groceria_capital_transactions'] = [
            [
                'id' => 1,
                'date' => '2026-09-01',
                'description' => 'Bought initial inventory',
                'amount' => 3000.00,
                'recorded_by' => 'Angela Mercado',
            ],
            [
                'id' => 2,
                'date' => '2026-09-05',
                'description' => 'Other store expenses',
                'amount' => 2000.00,
                'recorded_by' => 'Angela Mercado',
            ],
        ];
    }

    $legacyNameMap = [
        'Rice 5kg' => 'Sinandomeng Rice 5kg',
        'COFFEE' => 'Nescafe Classic Coffee 25g',
        'Coffee' => 'Nescafe Classic Coffee 25g',
        'Bear Brand Milk' => 'Bear Brand Milk 33g',
        'Lucky Me Noodles' => 'Lucky Me! Pancit Canton Original 60g',
        'Bread' => 'Gardenia Classic White Bread 400g',
        'Canned Sardines' => 'Argentina Corned Beef 175g',
        'Fresh Eggs' => 'Natures Best Fresh Eggs 12pcs',
        'Soft Drinks' => 'Coca-Cola 1.5L',
    ];
    foreach ($_SESSION['groceria_products'] as $index => $product) {
        if (isset($legacyNameMap[$product['name']])) {
            $_SESSION['groceria_products'][$index]['name'] = $legacyNameMap[$product['name']];
        }
    }
    foreach ($_SESSION['groceria_bundles'] as $bundleIndex => $bundle) {
        foreach ($bundle['items'] as $itemIndex => $item) {
            if (isset($legacyNameMap[$item['product_name']])) {
                $_SESSION['groceria_bundles'][$bundleIndex]['items'][$itemIndex]['product_name'] = $legacyNameMap[$item['product_name']];
            }
        }
    }
    foreach ($_SESSION['groceria_sales'] as $saleIndex => $sale) {
        $saleItems = [];
        foreach (explode(',', (string) $sale['items']) as $saleItem) {
            $itemParts = explode(' × ', trim($saleItem), 2);
            $itemName = trim($itemParts[0]);
            $quantity = isset($itemParts[1]) ? ' × ' . trim($itemParts[1]) : '';
            if (isset($legacyNameMap[$itemName])) {
                $itemName = $legacyNameMap[$itemName];
            } elseif (str_contains($itemName, 'Nescafe Classic Coffee 25g')) {
                $itemName = 'Nescafe Classic Coffee 25g';
            }
            $saleItems[] = $itemName . $quantity;
        }
        $_SESSION['groceria_sales'][$saleIndex]['items'] = implode(', ', $saleItems);
    }
}

function groceria_products(): array
{
    groceria_store_state();
    $db = groceria_db();
    if ($db) {
        $count = (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn();
        if ($count === 0) {
            $insert = $db->prepare('INSERT INTO products (id, product_name, category, supplier, stock_quantity, minimum_stock, purchase_price, selling_price, status, expiry_date, is_damaged, image_path, is_deleted) VALUES (:id, :name, :category, :supplier, :stock, :minimum_stock, :purchase_price, :selling_price, :status, :expiry_date, :damaged, :image_path, 0)');
            foreach ($_SESSION['groceria_products'] as $product) {
                $insert->execute([
                    'id' => $product['id'], 'name' => $product['name'], 'category' => $product['category'],
                    'supplier' => $product['supplier'], 'stock' => $product['stock_quantity'],
                    'minimum_stock' => $product['minimum_stock'], 'purchase_price' => $product['purchase_price'],
                    'selling_price' => $product['selling_price'], 'status' => $product['status'],
                    'expiry_date' => $product['expiry_date'], 'damaged' => !empty($product['is_damaged']) ? 1 : 0,
                    'image_path' => $product['image_path'] ?? null,
                ]);
            }
        }
        $rows = $db->query('SELECT id, product_name, category, supplier, stock_quantity, minimum_stock, purchase_price, selling_price, status, expiry_date, is_damaged, image_path, is_deleted FROM products ORDER BY id')->fetchAll();
        $_SESSION['groceria_products'] = array_map(static fn ($row) => [
            'id' => (int) $row['id'], 'name' => $row['product_name'], 'category' => $row['category'] ?? 'General',
            'supplier' => $row['supplier'] ?? 'General Supplier', 'stock_quantity' => (int) $row['stock_quantity'],
            'minimum_stock' => (int) $row['minimum_stock'], 'purchase_price' => (float) $row['purchase_price'],
            'selling_price' => (float) $row['selling_price'], 'status' => $row['status'],
            'expiry_date' => $row['expiry_date'] ?? '', 'is_damaged' => (bool) $row['is_damaged'],
            'image' => $row['image_path'] ?: strtoupper(substr($row['product_name'], 0, 1)),
            'image_path' => $row['image_path'],
            'is_deleted' => (bool) $row['is_deleted'],
        ], $rows);
    } else {
        $path = __DIR__ . '/database/products.json';
        if (is_file($path)) {
            $_SESSION['groceria_products'] = json_decode((string) file_get_contents($path), true) ?: $_SESSION['groceria_products'];
        } else {
            file_put_contents($path, json_encode($_SESSION['groceria_products'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    return $_SESSION['groceria_products'];
}

function groceria_save_products(array $products): void
{
    $_SESSION['groceria_products'] = array_values($products);
    $db = groceria_db();
    if ($db) {
        $statement = $db->prepare('INSERT INTO products (id, product_name, category, supplier, stock_quantity, minimum_stock, purchase_price, selling_price, status, expiry_date, is_damaged, image_path, is_deleted) VALUES (:id, :name, :category, :supplier, :stock, :minimum_stock, :purchase_price, :selling_price, :status, :expiry_date, :damaged, :image_path, :is_deleted) ON DUPLICATE KEY UPDATE product_name = VALUES(product_name), category = VALUES(category), supplier = VALUES(supplier), stock_quantity = VALUES(stock_quantity), minimum_stock = VALUES(minimum_stock), purchase_price = VALUES(purchase_price), selling_price = VALUES(selling_price), status = VALUES(status), expiry_date = VALUES(expiry_date), is_damaged = VALUES(is_damaged), image_path = VALUES(image_path), is_deleted = VALUES(is_deleted)');
        foreach ($products as $product) {
            $statement->execute([
                'id' => $product['id'], 'name' => $product['name'], 'category' => $product['category'],
                'supplier' => $product['supplier'], 'stock' => $product['stock_quantity'],
                'minimum_stock' => $product['minimum_stock'], 'purchase_price' => $product['purchase_price'],
                'selling_price' => $product['selling_price'], 'status' => $product['status'],
                'expiry_date' => $product['expiry_date'] ?: null, 'damaged' => !empty($product['is_damaged']) ? 1 : 0,
                'image_path' => $product['image_path'] ?? null,
                'is_deleted' => !empty($product['is_deleted']) ? 1 : 0,
            ]);
        }
        $productIds = array_values(array_map(static fn ($product) => (int) $product['id'], $products));
        if ($productIds) {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $deleteMissing = $db->prepare('DELETE FROM products WHERE id NOT IN (' . $placeholders . ')');
            $deleteMissing->execute($productIds);
        } else {
            $db->exec('DELETE FROM products');
        }
    } else {
        file_put_contents(__DIR__ . '/database/products.json', json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}

function groceria_current_user_id(): string
{
    if (empty($_SESSION['groceria_user']['id'])) {
        $user = $_SESSION['groceria_user'] ?? [];
        $_SESSION['groceria_user']['id'] = groceria_resolve_user_id(
            (string) ($user['email'] ?? 'buyer@groceriabasket.com'),
            (string) ($user['name'] ?? 'Groceria Buyer'),
            (string) ($user['role'] ?? 'buyer'),
            ''
        );
    }
    return (string) $_SESSION['groceria_user']['id'];
}

function groceria_verify_current_admin_password(string $password): bool
{
    if (($_SESSION['groceria_user']['role'] ?? '') !== 'admin' || trim($password) === '') {
        return false;
    }
    $userId = groceria_current_user_id();
    $db = groceria_db();
    if ($db && ctype_digit($userId)) {
        $query = $db->prepare('SELECT password_hash FROM users WHERE id = :id AND role = \'admin\' LIMIT 1');
        $query->execute(['id' => (int) $userId]);
        $hash = $query->fetchColumn();
        return is_string($hash) && password_verify($password, $hash);
    }
    return getenv('GROCERIA_DISABLE_DEMO_LOGINS') !== '1'
        && strtolower((string) ($_SESSION['groceria_user']['email'] ?? '')) === 'admin@groceriabasket.com'
        && hash_equals('admin123', $password);
}

function groceria_delete_product_is_blocked(array $product): bool
{
    foreach (groceria_bundles() as $bundle) {
        foreach ($bundle['items'] as $item) {
            if (strcasecmp((string) $item['product_name'], (string) $product['name']) === 0) {
                return true;
            }
        }
    }
    return false;
}

function groceria_soft_delete_product(int $productId, string $reason, string $password): void
{
    if (($_SESSION['groceria_user']['role'] ?? '') !== 'admin') {
        throw new RuntimeException('Only an authorized administrator can delete products.');
    }
    $reason = trim($reason);
    if ($reason === '' || mb_strlen($reason, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Enter a reason for deletion (up to 1,000 characters).');
    }
    if (!groceria_verify_current_admin_password($password)) {
        throw new InvalidArgumentException('The administrator password is incorrect.');
    }
    $products = groceria_products();
    $productIndex = null;
    foreach ($products as $index => $product) {
        if ((int) $product['id'] === $productId) {
            $productIndex = $index;
            break;
        }
    }
    if ($productIndex === null || !empty($products[$productIndex]['is_deleted'])) {
        throw new InvalidArgumentException('This product is not available for deletion.');
    }
    $product = $products[$productIndex];
    if (groceria_delete_product_is_blocked($product)) {
        throw new InvalidArgumentException('This product is used by an existing bundle. Update or remove that bundle before deleting the product.');
    }
    $entry = [
        'product_id' => $productId,
        'product_name' => (string) $product['name'],
        'reason' => $reason,
        'deleted_by_user_id' => groceria_current_user_id(),
        'deleted_by' => (string) ($_SESSION['groceria_user']['name'] ?? $_SESSION['groceria_user']['email']),
        'deleted_at' => date('Y-m-d H:i:s'),
        'product_snapshot' => $product,
    ];
    $db = groceria_db();
    if ($db) {
        $db->beginTransaction();
        try {
            $lock = $db->prepare('SELECT is_deleted FROM products WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $productId]);
            $isDeleted = $lock->fetchColumn();
            if ($isDeleted === false || (bool) $isDeleted) {
                throw new InvalidArgumentException('This product is not available for deletion.');
            }
            $update = $db->prepare('UPDATE products SET is_deleted = 1, status = \'Deleted\' WHERE id = :id');
            $update->execute(['id' => $productId]);
            $audit = $db->prepare('INSERT INTO deleted_products_history (product_id, product_name, product_snapshot, deletion_reason, deleted_by_user_id, deleted_by_name, deleted_at) VALUES (:product_id, :product_name, :snapshot, :reason, :user_id, :user_name, :deleted_at)');
            $audit->execute([
                'product_id' => $productId, 'product_name' => $entry['product_name'],
                'snapshot' => json_encode($product, JSON_UNESCAPED_UNICODE), 'reason' => $reason,
                'user_id' => ctype_digit($entry['deleted_by_user_id']) ? (int) $entry['deleted_by_user_id'] : null,
                'user_name' => $entry['deleted_by'], 'deleted_at' => $entry['deleted_at'],
            ]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    } else {
        $products[$productIndex]['is_deleted'] = true;
        $products[$productIndex]['status'] = 'Deleted';
        groceria_save_products($products);
        $path = groceria_private_data_path('deleted_products_history.json');
        $history = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        $history[] = $entry;
        file_put_contents($path, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}

function groceria_deleted_products_history(): array
{
    if (($_SESSION['groceria_user']['role'] ?? '') !== 'admin') {
        return [];
    }
    $db = groceria_db();
    if ($db) {
        $rows = $db->query('SELECT product_id, product_name, product_snapshot, deletion_reason AS reason, deleted_by_user_id, deleted_by_name AS deleted_by, deleted_at FROM deleted_products_history ORDER BY deleted_at DESC, id DESC')->fetchAll();
        return array_map(static function ($row) {
            $row['product_snapshot'] = json_decode((string) $row['product_snapshot'], true) ?: [];
            return $row;
        }, $rows);
    }
    $path = groceria_private_data_path('deleted_products_history.json');
    return is_file($path) ? array_reverse(json_decode((string) file_get_contents($path), true) ?: []) : [];
}

function groceria_cart(): array
{
    $userId = groceria_current_user_id();
    $db = groceria_db();
    if ($db) {
        $query = $db->prepare('SELECT bundle_id, quantity FROM cart_items WHERE user_id = :user_id ORDER BY bundle_id');
        $query->execute(['user_id' => (int) $userId]);
        $cart = $query->fetchAll();
    } else {
        $path = groceria_private_data_path('carts.json', __DIR__ . '/database/carts.json');
        $carts = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        $cart = $carts[$userId] ?? [];
    }
    $bundles = [];
    foreach (groceria_bundles() as $bundle) {
        $bundles[(string) $bundle['id']] = $bundle;
    }
    return array_values(array_filter(array_map(static function ($entry) use ($bundles) {
        $bundle = $bundles[(string) $entry['bundle_id']] ?? null;
        if (!$bundle) {
            return null;
        }
        return ['bundle_id' => (int) $bundle['id'], 'name' => $bundle['name'], 'image' => $bundle['image'] ?? '', 'price' => (float) $bundle['price'], 'quantity' => (int) $entry['quantity']];
    }, $cart)));
}

function groceria_save_cart(array $items): void
{
    $userId = groceria_current_user_id();
    $cart = [];
    foreach ($items as $item) {
        $bundleId = (int) ($item['bundle_id'] ?? 0);
        $quantity = max(0, (int) ($item['quantity'] ?? 0));
        if ($bundleId > 0 && $quantity > 0) {
            $cart[$bundleId] = ($cart[$bundleId] ?? 0) + $quantity;
        }
    }
    $db = groceria_db();
    if ($db) {
        $db->beginTransaction();
        try {
            $delete = $db->prepare('DELETE FROM cart_items WHERE user_id = :user_id');
            $delete->execute(['user_id' => (int) $userId]);
            $insert = $db->prepare('INSERT INTO cart_items (user_id, bundle_id, quantity) VALUES (:user_id, :bundle_id, :quantity)');
            foreach ($cart as $bundleId => $quantity) {
                $insert->execute(['user_id' => (int) $userId, 'bundle_id' => $bundleId, 'quantity' => $quantity]);
            }
            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
        return;
    }
    $path = groceria_private_data_path('carts.json', __DIR__ . '/database/carts.json');
    $carts = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    $carts[$userId] = array_map(static fn ($bundleId, $quantity) => ['bundle_id' => $bundleId, 'quantity' => $quantity], array_keys($cart), array_values($cart));
    file_put_contents($path, json_encode($carts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function groceria_bundles(): array
{
    groceria_store_state();
    $db = groceria_db();
    if ($db) {
        $db->exec('CREATE TABLE IF NOT EXISTS bundles (
            id INT UNSIGNED NOT NULL PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            description TEXT NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT \'Available\',
            image VARCHAR(255) NOT NULL DEFAULT \'\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $db->exec('CREATE TABLE IF NOT EXISTS bundle_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            bundle_id INT UNSIGNED NOT NULL,
            product_name VARCHAR(180) NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            item_order INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_bundle_items_bundle_order (bundle_id, item_order),
            CONSTRAINT fk_bundle_items_bundle FOREIGN KEY (bundle_id) REFERENCES bundles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $bundleCount = (int) $db->query('SELECT COUNT(*) FROM bundles')->fetchColumn();
        if ($bundleCount === 0 && !empty($_SESSION['groceria_bundles'])) {
            groceria_save_bundles($_SESSION['groceria_bundles']);
        }

        $rows = $db->query('SELECT id, name, description, price, status, image FROM bundles ORDER BY id')->fetchAll();
        $bundles = [];
        $bundleIndexes = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $bundleIndexes[$id] = count($bundles);
            $bundles[] = [
                'id' => $id,
                'name' => $row['name'],
                'description' => $row['description'],
                'price' => (float) $row['price'],
                'status' => $row['status'],
                'image' => $row['image'],
                'items' => [],
            ];
        }
        if ($bundles) {
            $items = $db->query('SELECT bundle_id, product_name, quantity FROM bundle_items ORDER BY bundle_id, item_order, id')->fetchAll();
            foreach ($items as $item) {
                $bundleId = (int) $item['bundle_id'];
                if (isset($bundleIndexes[$bundleId])) {
                    $bundles[$bundleIndexes[$bundleId]]['items'][] = [
                        'product_name' => $item['product_name'],
                        'quantity' => (int) $item['quantity'],
                    ];
                }
            }
        }
        $_SESSION['groceria_bundles'] = $bundles;
        return $bundles;
    }
    return $_SESSION['groceria_bundles'];
}

function groceria_save_bundles(array $bundles): void
{
    $db = groceria_db();
    if ($db) {
        $db->exec('CREATE TABLE IF NOT EXISTS bundles (
            id INT UNSIGNED NOT NULL PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            description TEXT NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT \'Available\',
            image VARCHAR(255) NOT NULL DEFAULT \'\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $db->exec('CREATE TABLE IF NOT EXISTS bundle_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            bundle_id INT UNSIGNED NOT NULL,
            product_name VARCHAR(180) NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            item_order INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_bundle_items_bundle_order (bundle_id, item_order),
            CONSTRAINT fk_bundle_items_bundle FOREIGN KEY (bundle_id) REFERENCES bundles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $db->beginTransaction();
        try {
            $bundleIds = [];
            $upsert = $db->prepare('INSERT INTO bundles (id, name, description, price, status, image) VALUES (:id, :name, :description, :price, :status, :image) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), price = VALUES(price), status = VALUES(status), image = VALUES(image)');
            $deleteItems = $db->prepare('DELETE FROM bundle_items WHERE bundle_id = :bundle_id');
            $insertItem = $db->prepare('INSERT INTO bundle_items (bundle_id, product_name, quantity, item_order) VALUES (:bundle_id, :product_name, :quantity, :item_order)');
            foreach ($bundles as $bundle) {
                $bundleId = (int) $bundle['id'];
                $bundleIds[] = $bundleId;
                $upsert->execute([
                    'id' => $bundleId,
                    'name' => (string) $bundle['name'],
                    'description' => (string) ($bundle['description'] ?? ''),
                    'price' => (float) $bundle['price'],
                    'status' => (string) ($bundle['status'] ?? 'Available'),
                    'image' => (string) ($bundle['image'] ?? ''),
                ]);
                $deleteItems->execute(['bundle_id' => $bundleId]);
                foreach (array_values($bundle['items'] ?? []) as $order => $item) {
                    $insertItem->execute([
                        'bundle_id' => $bundleId,
                        'product_name' => (string) $item['product_name'],
                        'quantity' => (int) $item['quantity'],
                        'item_order' => $order,
                    ]);
                }
            }
            if ($bundleIds) {
                $placeholders = implode(',', array_fill(0, count($bundleIds), '?'));
                $deleteMissing = $db->prepare('DELETE FROM bundles WHERE id NOT IN (' . $placeholders . ')');
                $deleteMissing->execute($bundleIds);
            } else {
                $db->exec('DELETE FROM bundles');
            }
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }
    $_SESSION['groceria_bundles'] = array_values($bundles);
    file_put_contents(
        __DIR__ . '/database/bundles.json',
        json_encode(array_values($bundles), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function groceria_orders(?string $userId = null): array
{
    $db = groceria_db();
    if ($db) {
        $buyer = $userId ?? (($_SESSION['groceria_user']['role'] ?? '') === 'buyer' ? groceria_current_user_id() : null);
        $sql = 'SELECT id, order_number, user_id, buyer_name, buyer_contact, delivery_recipient, delivery_contact, delivery_address, delivery_instructions, total_amount, sold_at, status FROM sales WHERE order_type = \'online\'';
        if ($buyer !== null) {
            $sql .= ' AND user_id = :user_id';
        }
        $sql .= ' ORDER BY sold_at DESC, id DESC';
        $query = $db->prepare($sql);
        $query->execute($buyer !== null ? ['user_id' => (int) $buyer] : []);
        $orders = [];
        $itemsQuery = $db->prepare('SELECT bundle_id, item_name, image_path, quantity, unit_price, subtotal FROM sale_items WHERE sale_id = :sale_id ORDER BY id');
        foreach ($query->fetchAll() as $row) {
            $itemsQuery->execute(['sale_id' => $row['id']]);
            $orders[] = [
                'order_number' => $row['order_number'], 'user_id' => (string) $row['user_id'],
                'buyer_name' => $row['buyer_name'] ?? '', 'buyer_contact' => $row['buyer_contact'],
                'delivery_recipient' => $row['delivery_recipient'] ?? ($row['buyer_name'] ?? ''),
                'delivery_contact' => $row['delivery_contact'] ?? $row['buyer_contact'],
                'delivery_address' => $row['delivery_address'], 'delivery_instructions' => $row['delivery_instructions'] ?? '',
                'total' => (float) $row['total_amount'], 'created_at' => $row['sold_at'], 'status' => $row['status'],
                'items' => array_map(static fn ($item) => ['bundle_id' => (int) $item['bundle_id'], 'name' => $item['item_name'], 'image' => $item['image_path'] ?? '', 'quantity' => (int) $item['quantity'], 'price' => (float) $item['unit_price'], 'subtotal' => (float) $item['subtotal']], $itemsQuery->fetchAll()),
            ];
        }
        return $orders;
    }
    $path = groceria_private_data_path('orders.json', __DIR__ . '/database/orders.json');
    $orders = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    $buyer = $userId ?? (($_SESSION['groceria_user']['role'] ?? '') === 'buyer' ? groceria_current_user_id() : null);
    return $buyer === null ? $orders : array_values(array_filter($orders, static fn ($order) => (string) ($order['user_id'] ?? '') === $buyer));
}

function groceria_save_orders(array $orders): void
{
    $_SESSION['groceria_orders'] = $orders;
    file_put_contents(groceria_private_data_path('orders.json', __DIR__ . '/database/orders.json'), json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function groceria_record_order(array $order): string
{
    $userId = groceria_current_user_id();
    $token = (string) ($order['checkout_token'] ?? '');
    $db = groceria_db();
    if ($db && $token !== '') {
        $existing = $db->prepare('SELECT order_number FROM sales WHERE checkout_token = :token AND user_id = :user_id LIMIT 1');
        $existing->execute(['token' => $token, 'user_id' => (int) $userId]);
        $existingNumber = $existing->fetchColumn();
        if ($existingNumber !== false) {
            return (string) $existingNumber;
        }
    }
    $ordersPath = groceria_private_data_path('orders.json', __DIR__ . '/database/orders.json');
    $orders = !$db && is_file($ordersPath) ? (json_decode((string) file_get_contents($ordersPath), true) ?: []) : [];
    if (!$db && $token !== '') {
        foreach ($orders as $existing) {
            if ((string) ($existing['user_id'] ?? '') === $userId && ($existing['checkout_token'] ?? '') === $token) {
                return (string) $existing['order_number'];
            }
        }
    }
    $items = $order['items'] ?? [];
    $bundleCatalog = [];
    foreach (groceria_bundles() as $bundle) {
        $bundleCatalog[(int) $bundle['id']] = $bundle;
    }
    $quantities = [];
    foreach ($items as $item) {
        $id = (int) ($item['bundle_id'] ?? 0);
        $quantity = max(0, (int) ($item['quantity'] ?? 0));
        if (!isset($bundleCatalog[$id]) || $quantity <= 0) {
            throw new RuntimeException('The selected bundle is no longer available.');
        }
        $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
    }
    if (!$quantities) {
        throw new RuntimeException('Your order has no items.');
    }
    $orderItems = [];
    $total = 0.0;
    $requirements = [];
    foreach ($quantities as $id => $quantity) {
        $bundle = $bundleCatalog[$id];
        $available = groceria_bundle_available_count($bundle);
        if ($quantity > $available) {
            throw new RuntimeException($bundle['name'] . ' has only ' . $available . ' available.');
        }
        foreach ($bundle['items'] as $component) {
            $name = (string) $component['product_name'];
            $requirements[$name] = ($requirements[$name] ?? 0) + (int) $component['quantity'] * $quantity;
        }
        $price = (float) $bundle['price'];
        $subtotal = $price * $quantity;
        $total += $subtotal;
        $orderItems[] = ['bundle_id' => $id, 'name' => $bundle['name'], 'image' => $bundle['image'] ?? '', 'quantity' => $quantity, 'price' => $price, 'subtotal' => $subtotal];
    }
    if ($db) {
        $db->beginTransaction();
        try {
            if ($token !== '') {
                $existing = $db->prepare('SELECT order_number FROM sales WHERE checkout_token = :token AND user_id = :user_id LIMIT 1');
                $existing->execute(['token' => $token, 'user_id' => (int) $userId]);
                $existingNumber = $existing->fetchColumn();
                if ($existingNumber !== false) {
                    $db->rollBack();
                    return (string) $existingNumber;
                }
            }
            $stockRows = [];
            $stockQuery = $db->prepare('SELECT id, stock_quantity FROM products WHERE product_name = :name FOR UPDATE');
            foreach ($requirements as $name => $needed) {
                $stockQuery->execute(['name' => $name]);
                $row = $stockQuery->fetch();
                if (!$row || (int) $row['stock_quantity'] < $needed) {
                    throw new RuntimeException('Insufficient stock for ' . $name . '.');
                }
                $stockRows[] = ['id' => (int) $row['id'], 'remaining' => (int) $row['stock_quantity'] - $needed];
            }
            $insert = $db->prepare('INSERT INTO sales (user_id, buyer_name, buyer_contact, delivery_recipient, delivery_contact, delivery_address, delivery_instructions, total_amount, status, order_type, checkout_token) VALUES (:user_id, :buyer_name, :buyer_contact, :recipient, :delivery_contact, :address, :instructions, :total, \'Pending\', \'online\', :token)');
            $insert->execute([
                'user_id' => (int) $userId, 'buyer_name' => $order['buyer_name'], 'buyer_contact' => $order['buyer_contact'],
                'recipient' => $order['delivery_recipient'] ?? $order['buyer_name'], 'delivery_contact' => $order['delivery_contact'] ?? $order['buyer_contact'],
                'address' => $order['delivery_address'], 'instructions' => $order['delivery_instructions'] ?? '',
                'total' => $total, 'token' => $token !== '' ? $token : null,
            ]);
            $saleId = (int) $db->lastInsertId();
            $orderNumber = 'GB-' . str_pad((string) $saleId, 6, '0', STR_PAD_LEFT);
            $updateNumber = $db->prepare('UPDATE sales SET order_number = :number WHERE id = :id');
            $updateNumber->execute(['number' => $orderNumber, 'id' => $saleId]);
            $itemInsert = $db->prepare('INSERT INTO sale_items (sale_id, bundle_id, item_name, image_path, quantity, unit_price, subtotal) VALUES (:sale_id, :bundle_id, :name, :image, :quantity, :price, :subtotal)');
            foreach ($orderItems as $item) {
                $itemInsert->execute(['sale_id' => $saleId, 'bundle_id' => $item['bundle_id'], 'name' => $item['name'], 'image' => $item['image'], 'quantity' => $item['quantity'], 'price' => $item['price'], 'subtotal' => $item['subtotal']]);
            }
            $stockUpdate = $db->prepare('UPDATE products SET stock_quantity = :stock WHERE id = :id');
            foreach ($stockRows as $stock) {
                $stockUpdate->execute(['stock' => $stock['remaining'], 'id' => $stock['id']]);
            }
            foreach ($order['remove_cart_ids'] ?? [] as $bundleId) {
                $cartDelete = $db->prepare('DELETE FROM cart_items WHERE user_id = :user_id AND bundle_id = :bundle_id');
                $cartDelete->execute(['user_id' => (int) $userId, 'bundle_id' => (int) $bundleId]);
            }
            $db->commit();
            return $orderNumber;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($token !== '') {
                $existing = $db->prepare('SELECT order_number FROM sales WHERE checkout_token = :token AND user_id = :user_id LIMIT 1');
                $existing->execute(['token' => $token, 'user_id' => (int) $userId]);
                $existingNumber = $existing->fetchColumn();
                if ($existingNumber !== false) {
                    return (string) $existingNumber;
                }
            }
            throw $exception;
        }
    }
    $products = groceria_products();
    foreach ($requirements as $name => $needed) {
        $found = false;
        foreach ($products as $index => $product) {
            if (strcasecmp($product['name'], $name) === 0) {
                if ((int) $product['stock_quantity'] < $needed) {
                    throw new RuntimeException('Insufficient stock for ' . $name . '.');
                }
                $products[$index]['stock_quantity'] -= $needed;
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new RuntimeException('Product not found: ' . $name . '.');
        }
    }
    groceria_save_products($products);
    $orderNumber = 'GB-' . str_pad((string) (1001 + count($orders)), 6, '0', STR_PAD_LEFT);
    $orders[] = array_merge($order, ['user_id' => $userId, 'items' => $orderItems, 'total' => $total, 'order_number' => $orderNumber, 'created_at' => date('Y-m-d g:i A'), 'status' => 'Pending']);
    groceria_save_orders($orders);
    return $orderNumber;
}

function groceria_update_order_status(string $orderNumber, string $status): void
{
    $allowedStatuses = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Completed', 'Cancelled'];
    if (!in_array($status, $allowedStatuses, true)) {
        return;
    }
    $db = groceria_db();
    if ($db) {
        $update = $db->prepare('UPDATE sales SET status = :status WHERE order_number = :number AND order_type = \'online\'');
        $update->execute(['status' => $status, 'number' => $orderNumber]);
        return;
    }
    $orders = groceria_orders(null);
    foreach ($orders as &$order) {
        if (($order['order_number'] ?? '') === $orderNumber) {
            $order['status'] = $status;
            break;
        }
    }
    unset($order);
    groceria_save_orders($orders);
}

function groceria_sales(): array
{
    groceria_store_state();
    $path = groceria_private_data_path('pos_sales.json');
    if (is_file($path)) {
        $_SESSION['groceria_sales'] = json_decode((string) file_get_contents($path), true) ?: [];
    }
    return $_SESSION['groceria_sales'];
}

function groceria_reset_sales(): void
{
    $db = groceria_db();
    if ($db) {
        $db->exec('DELETE FROM sales');
        $db->exec('ALTER TABLE sales AUTO_INCREMENT = 1');
    }

    $salesPath = groceria_private_data_path('pos_sales.json');
    if (file_put_contents($salesPath, '[]', LOCK_EX) === false) {
        throw new RuntimeException('Unable to clear saved POS transactions.');
    }

    $ordersPath = groceria_private_data_path('orders.json', __DIR__ . '/database/orders.json');
    if (file_put_contents($ordersPath, '[]', LOCK_EX) === false) {
        throw new RuntimeException('Unable to clear saved online orders.');
    }

    $_SESSION['groceria_sales'] = [];
    $_SESSION['groceria_orders'] = [];
    unset($_SESSION['groceria_last_receipt']);
}

function groceria_initial_capital(): float
{
    return 10000.00;
}

function groceria_capital_transactions(): array
{
    groceria_store_state();
    return $_SESSION['groceria_capital_transactions'];
}

function groceria_capital_spent(): float
{
    return array_sum(array_map(static fn ($transaction) => (float) $transaction['amount'], groceria_capital_transactions()));
}

function groceria_remaining_capital(): float
{
    return max(0, groceria_initial_capital() - groceria_capital_spent());
}

function groceria_record_capital_transaction(string $description, float $amount, string $recordedBy = 'Angela Mercado'): void
{
    if ($description === '' || $amount <= 0) {
        return;
    }

    $transactions = groceria_capital_transactions();
    $transactions[] = [
        'id' => count($transactions) + 1,
        'date' => date('Y-m-d'),
        'description' => $description,
        'amount' => $amount,
        'recorded_by' => $recordedBy,
    ];
    $_SESSION['groceria_capital_transactions'] = $transactions;
}

function groceria_format_money(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function groceria_product_lookup(array $products, string $name): ?array
{
    foreach ($products as $product) {
        if (empty($product['is_deleted']) && strtolower($product['name']) === strtolower($name)) {
            return $product;
        }
    }

    return null;
}

function groceria_product_status(array $product): string
{
    if (!empty($product['is_deleted'])) {
        return 'Deleted';
    }
    $expiryTimestamp = strtotime($product['expiry_date']);
    if (!empty($product['is_damaged'])) {
        return 'Damaged';
    }
    if ($expiryTimestamp !== false && $expiryTimestamp < time()) {
        return 'Expired';
    }
    if ((int) $product['stock_quantity'] <= 0) {
        return 'Out of Stock';
    }
    if ((int) $product['stock_quantity'] <= (int) $product['minimum_stock']) {
        return 'Low Stock';
    }
    return 'Available';
}

function groceria_bundle_available_count(array $bundle): int
{
    $products = groceria_products();
    $availableCount = PHP_INT_MAX;

    foreach ($bundle['items'] as $item) {
        $product = groceria_product_lookup($products, $item['product_name']);
        $stock = $product ? (int) $product['stock_quantity'] : 0;
        $needed = (int) $item['quantity'];
        $remaining = $needed > 0 ? intdiv($stock, $needed) : 0;
        $availableCount = min($availableCount, $remaining);
    }

    return $availableCount < 0 ? 0 : $availableCount;
}

function groceria_bundle_status(array $bundle): string
{
    $available = groceria_bundle_available_count($bundle);
    if ($available <= 0) {
        return 'Out of Stock';
    }
    if ($available <= 3) {
        return 'Low Stock';
    }
    return 'Available';
}

function groceria_total_sales_for(string $type): float
{
    $total = 0.0;
    foreach (groceria_sales() as $sale) {
        if ($type === 'ALL' || strtoupper($sale['sale_type']) === strtoupper($type)) {
            $total += (float) $sale['total'];
        }
    }

    return $total;
}

function groceria_low_stock_count(): int
{
    $count = 0;
    foreach (groceria_products() as $product) {
        if (groceria_product_status($product) === 'Low Stock') {
            $count++;
        }
    }
    return $count;
}

function groceria_out_of_stock_count(): int
{
    $count = 0;
    foreach (groceria_products() as $product) {
        if (groceria_product_status($product) === 'Out of Stock') {
            $count++;
        }
    }
    return $count;
}

function groceria_expired_count(): int
{
    $count = 0;
    foreach (groceria_products() as $product) {
        if (groceria_product_status($product) === 'Expired') {
            $count++;
        }
    }
    return $count;
}

function groceria_damaged_count(): int
{
    $count = 0;
    foreach (groceria_products() as $product) {
        if (groceria_product_status($product) === 'Damaged') {
            $count++;
        }
    }
    return $count;
}

function groceria_insert_sale(string $saleType, array $items, float $cash, float $total, string $cashier = 'Angela Mercado', string $paymentMethod = 'CASH'): void
{
    $sales = groceria_sales();
    $nextId = 1000 + count($sales) + 1;
    $sales[] = [
        'id' => (string) $nextId,
        'date' => date('Y-m-d'),
        'time' => date('g:i A'),
        'items' => implode(', ', array_map(static fn ($row) => $row['label'], $items)),
        'total' => $total,
        'sale_type' => strtoupper($saleType),
        'cashier' => $cashier,
        'cash_received' => $cash,
        'payment_method' => strtoupper($paymentMethod),
    ];

    $_SESSION['groceria_sales'] = $sales;
    $path = groceria_private_data_path('pos_sales.json');
    if (file_put_contents($path, json_encode($sales, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        throw new RuntimeException('Unable to save the POS transaction.');
    }
}

function groceria_get_last_receipt(): ?array
{
    return $_SESSION['groceria_last_receipt'] ?? null;
}

function groceria_set_last_receipt(array $receipt): void
{
    $_SESSION['groceria_last_receipt'] = $receipt;
}

groceria_store_settings();

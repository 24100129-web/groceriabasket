
<?php
require_once __DIR__ . '/app_state.php';

echo "<h2>Groceria App Database Check</h2>";

try {
    $db = groceria_db();

    if (!$db) {
        echo "<h3 style='color:red'>NOT CONNECTED</h3>";
        echo "Your app_state.php is using its fallback storage.";
        exit;
    }

    echo "<h3 style='color:green'>CONNECTED!</h3>";

    echo "<h3>Users Table Columns:</h3>";

    $columns = $db->query("SHOW COLUMNS FROM users");

    foreach ($columns as $column) {
        echo htmlspecialchars($column['Field']) . "<br>";
    }

} catch (Throwable $e) {
    echo "<h3 style='color:red'>ERROR</h3>";
    echo htmlspecialchars($e->getMessage());
}

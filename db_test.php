
<?php
$host = '127.0.0.1';
$db = 'groceria_basket';
$user = 'root';
$pass = '';

echo "<h2>Groceria Basket Database Test</h2>";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=3306;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<h3 style='color:green'>SUCCESS!</h3>";
    echo "PHP is connected to your MySQL database.";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>CONNECTION FAILED</h3>";
    echo "Error: " . htmlspecialchars($e->getMessage());
}
?>

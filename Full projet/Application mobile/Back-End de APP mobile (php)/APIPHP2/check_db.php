<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the qr_logins table exists
$check_table = $conn->query("SHOW TABLES LIKE 'qr_logins'");
if ($check_table->num_rows == 0) {
    echo "Table 'qr_logins' does not exist.<br>";
} else {
    echo "Table 'qr_logins' exists.<br>";
    
    // Check structure of qr_logins table
    $columns = $conn->query("SHOW COLUMNS FROM qr_logins");
    echo "<h3>qr_logins table structure:</h3>";
    echo "<ul>";
    while ($col = $columns->fetch_assoc()) {
        echo "<li><strong>" . $col['Field'] . "</strong> - " . $col['Type'] . " - Key: " . $col['Key'] . "</li>";
    }
    echo "</ul>";
}

// Check structure of client table
$client_columns = $conn->query("SHOW COLUMNS FROM client");
echo "<h3>client table structure:</h3>";
echo "<ul>";
while ($col = $client_columns->fetch_assoc()) {
    echo "<li><strong>" . $col['Field'] . "</strong> - " . $col['Type'] . " - Key: " . $col['Key'] . "</li>";
}
echo "</ul>";

$conn->close();
?>
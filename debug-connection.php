cat > /var/www/html/library/debug-connection.php << 'EOF'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Detailed Database Debug</h2>";

// 1. Check if we can include the file
if (file_exists('config/database.php')) {
    echo "✅ config/database.php exists<br>";
    require_once 'config/database.php';
    echo "✅ config/database.php loaded<br>";
} else {
    die("❌ config/database.php not found!");
}

// 2. Try direct PDO connection without the class
echo "<h3>Testing Direct PDO Connection:</h3>";
try {
    $host = "localhost";
    $dbname = "library-system";
    $username = "root";
    $password = "";
    
    echo "Connecting to: host=$host, db=$dbname, user=$username<br>";
    echo "Password: " . (empty($password) ? "(empty)" : "(set)") . "<br>";
    
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    echo "✅ Direct PDO connection successful!<br>";
    
    // Test query
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Query works!<br>";
    
    // Check books table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM books");
    $result = $stmt->fetch();
    echo "📚 Books in database: " . $result['count'] . "<br>";
    
} catch(PDOException $e) {
    echo "❌ Direct PDO connection failed: " . $e->getMessage() . "<br>";
    echo "Error Code: " . $e->getCode() . "<br>";
}

// 3. Try using the class method
echo "<h3>Testing Class Method:</h3>";
try {
    $db = getDB();
    echo "✅ getDB() worked!<br>";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM books");
    $result = $stmt->fetch();
    echo "📚 Books in database: " . $result['count'] . "<br>";
    
} catch(PDOException $e) {
    echo "❌ getDB() failed: " . $e->getMessage() . "<br>";
}

// 4. Check MySQL socket
echo "<h3>MySQL Socket Info:</h3>";
$socket = shell_exec("mysql -u root -e \"SHOW VARIABLES LIKE 'socket';\" 2>&1");
echo "<pre>$socket</pre>";

// 5. Check if MySQL is running
echo "<h3>MySQL Service:</h3>";
$status = shell_exec("sudo systemctl is-active mysql 2>&1");
echo "Status: " . trim($status) . "<br>";
?>
EOF
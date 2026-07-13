cat > /var/www/html/library/test-direct.php << 'EOF'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Direct MySQL Connection Test</h2>";

// 1. Check if config file exists
if (file_exists('config/database.php')) {
    echo "✅ config/database.php exists<br>";
    require_once 'config/database.php';
    
    // 2. Try to get connection
    try {
        $db = getDB();
        echo "✅ Database connection successful!<br>";
        
        // 3. Try to query
        $stmt = $db->query("SELECT COUNT(*) as count FROM books");
        $result = $stmt->fetch();
        echo "📚 Books in database: " . $result['count'] . "<br>";
        
        // 4. Show sample books
        $stmt = $db->query("SELECT title, author FROM books LIMIT 3");
        while($row = $stmt->fetch()) {
            echo "📖 " . $row['title'] . " by " . $row['author'] . "<br>";
        }
        
    } catch(PDOException $e) {
        echo "❌ Database error: " . $e->getMessage() . "<br>";
        echo "Error code: " . $e->getCode() . "<br>";
    }
    
} else {
    echo "❌ config/database.php NOT FOUND!<br>";
    echo "Current directory: " . __DIR__ . "<br>";
    echo "Files in config/:<br>";
    system("ls -la config/");
}
?>
EOF
cat > /var/www/html/library/test-db-connection.php << 'EOF'
<?php
require_once 'config/database.php';

try {
    $db = getDB();
    echo "✅ Database connected successfully!<br>";
    
    // Test query
    $stmt = $db->query("SELECT COUNT(*) as count FROM contact_messages");
    $result = $stmt->fetch();
    echo "📝 Contact messages in database: " . $result['count'];
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
EOF
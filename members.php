<?php
/**
* members.php file
*/

//---session start and authentication---
session_start();

// check if user is logged in 
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'librarian') {
    header('Location: login.php');
    exit();
}

//---DB Connection---
require_once 'config/database.php';

//---handle actions---
$successMessage = '';
$errorMessage = '';
$action = $_GET['action'] ?? '';
$memberId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// handle member status toggle ...active/suspended
if ($action === 'toggle' && $memberId > 0) {
    try {
        $db = getDB();
        
        // get current status
        $stmt = $db->prepare("SELECT status FROM members WHERE member_id = ?");
        $stmt->execute([$memberId]);
        $currentStatus = $stmt->fetch()['status'];
        
        // Toggle status
        $newStatus = $currentStatus === 'active' ? 'suspended' : 'active';
        
        $stmt = $db->prepare("UPDATE members SET status = ? WHERE member_id = ?");
        $stmt->execute([$newStatus, $memberId]);
        
        $successMessage = "✅ Member status updated to: " . ucfirst($newStatus);
        
    } catch(PDOException $e) {
        $errorMessage = "❌ Error: " . $e->getMessage();
    }
}

// handle member deletion
if ($action === 'delete' && $memberId > 0) {
    try {
        $db = getDB();
        
        // check if member has active borrowings
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM borrowings 
            WHERE member_id = ? AND status IN ('borrowed', 'overdue')
        ");
        $stmt->execute([$memberId]);
        $activeBorrowings = $stmt->fetchColumn();
        
        if ($activeBorrowings > 0) {
            $errorMessage = "❌ Cannot delete member with active borrowings. They have $activeBorrowings book(s) currently checked out.";
        } else {
            // Delete member
            $stmt = $db->prepare("DELETE FROM members WHERE member_id = ?");
            $stmt->execute([$memberId]);
            $successMessage = "✅ Member deleted successfully.";
        }
        
    } catch(PDOException $e) {
        $errorMessage = "❌ Error: " . $e->getMessage();
    }
}

// ---Get all members----
try {
    $db = getDB();
    
    // get all members with borrowing statistics
    $stmt = $db->query("
        SELECT 
            m.*,
            COUNT(DISTINCT b.borrow_id) as total_borrowings,
            SUM(CASE WHEN b.status IN ('borrowed', 'overdue') THEN 1 ELSE 0 END) as active_borrowings,
            COALESCE(SUM(CASE WHEN b.status = 'overdue' THEN 1 ELSE 0 END), 0) as overdue_count
        FROM members m
        LEFT JOIN borrowings b ON m.member_id = b.member_id
        GROUP BY m.member_id
        ORDER BY m.created_at DESC
    ");
    $members = $stmt->fetchAll();
    
    $totalMembers = count($members);
    $activeMembers = array_reduce($members, function($carry, $member) {
        return $carry + ($member['status'] === 'active' ? 1 : 0);
    }, 0);
    
} catch(PDOException $e) {
    $errorMessage = "❌ Unable to load members: " . $e->getMessage();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Management - Library System</title>
    
    <!-- Include CSS files -->
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/members.css">
</head>
<body>
    <div class="admin-container">
        
        <!--Sidebar--->
        <nav class="sidebar">
            <h2>📚 Library Admin</h2>
            <ul>
                <li><a href="admin/dashboard.php">📊 Dashboard</a></li>
                <li><a href="members.php" class="active">👥 Members</a></li>
                <li><a href="admin/messages.php">✉️ Messages</a></li>
                <li><a href="admin/settings.php">⚙️ Settings</a></li>
                <li><a href="logout.php" class="logout-link">🚪 Logout</a></li>
            </ul>
        </nav>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="members-container">
                
                <div class="members-header">
                    <h1>👥 Member Management</h1>
                    <div class="stats">
                        <span>👤 Total: <?php echo $totalMembers ?? 0; ?></span>
                        <span>🟢 Active: <?php echo $activeMembers ?? 0; ?></span>
                        <span>🔴 Suspended: <?php echo ($totalMembers ?? 0) - ($activeMembers ?? 0); ?></span>
                    </div>
                </div>
                
                <!--- messages--->
                <?php if ($successMessage): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>
                <?php if ($errorMessage): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>
                
                <!---search & add -->
                <div class="search-box">
                    <input type="text" 
                           id="searchInput" 
                           placeholder="🔍 Search by name, email, or card number..."
                           onkeyup="filterTable()">
                    <a href="register.php" class="btn btn-primary">➕ Add New Member</a>
                </div>
                
                <!---members table--->
                <div class="table-container">
                    <table id="membersTable">
                        <thead>
                            <tr>
                                <th>Card No</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Borrowings</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($members)): ?>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($member['library_card_no']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                        <td><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php 
                                                $typeClass = $member['membership_type'] ?? 'adult';
                                            ?>
                                            <span class="membership-type <?php echo $typeClass; ?>">
                                                <?php echo ucfirst($typeClass); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $member['status']; ?>">
                                                <?php echo ucfirst($member['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($member['active_borrowings'] > 0): ?>
                                                <span class="active-borrowings">
                                                    <?php echo $member['active_borrowings']; ?>
                                                </span>
                                            <?php else: ?>
                                                <?php echo $member['total_borrowings']; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="?action=toggle&id=<?php echo $member['member_id']; ?>" 
                                                   class="btn <?php echo $member['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                                    <?php echo $member['status'] === 'active' ? '⏸️ Suspend' : '▶️ Activate'; ?>
                                                </a>
                                                <a href="?action=delete&id=<?php echo $member['member_id']; ?>" 
                                                   class="btn btn-danger btn-sm"
                                                   onclick="return confirm('⚠️ Are you sure you want to delete this member?\n\nThis action cannot be undone!')">
                                                    🗑️ Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state">
                                        <p>📭 No members found.</p>
                                        <a href="register.php" class="btn btn-primary" style="margin-top: 10px;">Add Your First Member</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
            </div>
        </main>
    </div>
    
    <!---JavaScript for search filter--->
    <script>
        function filterTable() {
            var input = document.getElementById('searchInput');
            var filter = input.value.toLowerCase();
            var table = document.getElementById('membersTable');
            var rows = table.getElementsByTagName('tr');
            
            for (var i = 1; i < rows.length; i++) {
                var cells = rows[i].getElementsByTagName('td');
                var found = false;
                
                // Check columns 0 (card), 1 (name), 2 (email)
                for (var j = 0; j < 3 && j < cells.length; j++) {
                    var text = cells[j].textContent.toLowerCase();
                    if (text.indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        }
    </script>
    
</body>
</html>

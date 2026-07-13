<?php
/**
 * admin/dashboard.php
 * Admin dashboard ...showinfg library statistics
 */

// --- Session & Authentication---
session_start();

// user and admin login check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header('Location: ../login.php');
    exit();
}


//--- DB Connection---
require_once '../config/database.php';

//---Get Stats---
try {
    $db = getDB();

    // get total number of books
    $stmt = $db->query("SELECT COUNT(*) as count FROM books");
    $totalBooks = $stmt->fetch()['count'];

    // get total number of members
    $stmt = $db->query("SELECT COUNT(*) as count FROM members");
    $totalMembers = $stmt->fetch()['count'];

    // get borrowing statistics by status
    //borrowed - active borrowings
    $stmt = $db->query("SELECT COUNT(*) as count FROM borrowings WHERE status = 'borrowed'");
    $activeBorrowings = $stmt->fetch()['count'];

    // overdue- overdue boods(not returned)
    $stmt = $db->query("SELECT COUNT(*) as count FROM borrowings WHERE status = 'overdue'");
    $overdueBooks = $stmt->fetch()['count'];

    // returned - Returned books
    $stmt = $db->query("SELECT COUNT(*) as count FROM borrowings WHERE status = 'returned'");
    $returnedBooks = $stmt->fetch()['count'];

    //lost - Lost books
    $stmt = $db->query("SELECT COUNT(*) as count FROM borrowings WHERE status = 'lost'");
    $lostBooks = $stmt->fetch()['count'];


    // Total borrowings ... all statuses added together
    $stmt = $db->query("SELECT COUNT(*) as count FROM borrowings");
    $totalBorrowings = $stmt->fetch()['count'];

    
    // get total fines collected
    $stmt = $db->query("SELECT SUM(fine_amount) as total FROM fines WHERE status = 'paid'");
    $totalFines = $stmt->fetch()['total'] ?? 0;
    
    // get pending fines
    $stmt = $db->query("SELECT SUM(amount) as total FROM fines WHERE status = 'pending'");
    $pendingFines = $stmt->fetch()['total'] ?? 0;

    
    // get recent borrowings ...last 5 - with all statuses
    $stmt = $db->query("
        SELECT 
            b.borrow_id,
            m.full_name AS member_name,
            bk.title AS book_title,
            b.borrow_date,
            b.due_date,
            b.return_date,
            b.status,
            b.fine_amount,
            CASE
                WHEN b.status = 'returned' THEN '✅ Returned'
                WHEN b.status = 'borrowed' THEN '📖 Borrowed'
                WHEN b.status = 'overdue' THEN '⚠️ Overdue'
                WHEN b.status = 'lost' THEN '❌ Lost'
                ELSE b.status
            END AS status_label
        FROM borrowings b
        JOIN members m ON b.member_id = m.member_id
        JOIM books bk ON b.book_id = bk.book_id
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    $recentBorrowings = $stmt->fetchAll();


    // get recent messages ...last 5
    $stmt = $db->query("
        SELECT
            message_id,
            fullname,
            email,
            subject,
            create_at,
            replied
        FROM contact_messages
        ORDER BY create_at DESC
        LIMIT 5
    ");
    $recentMessages = $stmt->fetchAll();

    // get lost books with member details
    $stmt = $db->query("
        SELECT
            m.full_name AS member_name,
            bk.title AS book_title,
            b.borrow_date,
            b.due_date,
            b.fine_amount
        FROM borrowings b
        JOIN members m ON b.member_id = m.member_id
        JOIN books bk ON b.book_id = bk.book_id
        WHERE b.status = 'lost'
        ORDER BY b.created_at DESC   
    ");
    $lostBooksList = $stmt->fetchAll();

} catch(PDOException $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    $error = "Unable to load dashboard. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Library System</title>

    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
    <div class="admin-container">

        <!-- Sidebar Navigation--->
        <nav class="sidebar">
            <h2>📚 Library Admin</h2>
            <ul>
                <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
                <li><a href="user.php">👥Users</li>
                <li><a href="messages.php">✉️ Messages</a></li>
                <li><a href="settings.php">⚙️ Settings</a></li>
                <li><a href="../logout.php" class="logout-link">🚪 Logout</a></li>
            </ul>
        </nav>

        
        <!--Main Content --->
        <main class="main-content">
            <h1>📊 Dashboard</h1>
            <p class="welcome">Welcome back, <?php echo htmlspecialchars($_SESSION['full name'] ?? 'Admin');?>!</p>
        
            <!---Stats Cards--->
            <div class="stats-grid">

                <!-- total books -->
                <div class="stat-card blue">
                    <h3>📚 Total Books</h3>
                    <div class="stat-number"><?php echo $totalBooks ?? 0; ?></div>
                    <div class="stat-label">In Library</div>
                </div>

                <!-- total members--->
                <div class="stat-card green">
                    <h3>👥 Total Members</h3>
                    <div class="stat-number"><?php echo $totalMembers ?? 0; ?></div>
                    <div class="stat-label">Registered Users</div>
                </div>

                 <!-- active borrowings -->
                <div class="stat-card orange">
                    <h3>📖 Active Borrowings</h3>
                    <div class="stat-number"><?php echo $activeBorrowings ?? 0; ?></div>
                    <div class="stat-label">Currently Borrowed</div>
                </div>

                <!-- overdue books--->
                <div class="stat-card red">
                    <h3>⚠️ Overdue Books</h3>
                    <div class="stat-number"><?php echo $overdueBooks ?? 0; ?></div>
                    <div class="stat-label">Need Attention</div>
                </div>

                <!---total borrowings -->
                <div class="stat-card blue">
                    <h3>📊 Total Borrowings</h3>
                    <div class="stat-number"><?php echo $totalBorrowings ?? 0; ?></div>
                    <div class="stat-label">All Transactions</div>
                </div>

                <!--- total fines--->
                <div class="stat-card green">
                    <h3>💰 Total Fines</h3>
                    <div class="stat-number">GHS <?php echo number_format($totalFines ?? 0, 2); ?></div>
                    <div class="stat-label">Collected</div>
                </div>

                <!---pending fines--->
                <div class="stat-card orange">
                    <h3>⏳ Pending Fines</h3>
                    <div class="stat-number">GHS <?php echo number_format($pendingFines ?? 0, 2); ?></div>
                    <div class="stat-label">Awaiting Payment</div>
                </div>

                <!--lost books--->
                <div class="stat-card lost">
                    <h3>❌ Lost Books</h3>
                    <div class="stat-number"><?php echo $lostBooks ?? 0; ?></div>
                    <div class="stat-label">Need Replacement</div>
                </div>
            
            </div>

            
            <!---Recent Activity Tables--->
            <div class="recent-grid">

                
                <!---recent borrowings -->
                <div class="table-container">
                    <h3>📖 Recent Borrowings</h3>
                    <?php if(!empty($recentBorrowings)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Book</th>
                                    <th>Status</th>
                                    <th>Fine</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBorrowings as $borrowing): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($borrowing['member_name']); ?></td>
                                        <td><?php echo htmlspecialchars($borrowing['book_title']); ?></td>
                                        <td>
                                            <span class="status <?php echo $borrowing['status']; ?>">
                                                <?php echo $borrowing['status_label']; ?>
                                            </span>
                                        </td>
                                        <td class="fine-amount <?php echo $borrowing['fine_amount'] <= 0 ? 'zero' : ''; ?>">
                                            <?php echo $borrowing['fine_amount'] > 0 ? 'GHS ' . number_format($borrowing['fine_amount'], 2) : '-'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                            </tbody>
                        </table>
                        <?php else: ?>
                            <p>No recent borrowings.</p>
                        <?php endif; ?>

                </div>


                <!-- recent messages--->
                 <div class="table-container">
                    <h3>✉️ Recent Messages</h3>
                    <?php if(!empty($recentMessages)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>From</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recentMessages as $message):?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($message['fullname']); ?></td>
                                        <td><?php echo htmlspecialchars($message['subject']); ?></td>
                                        <td>
                                            <span class="status <?php echo $message['replied'] ? 'active' : 'pending'; ?>">
                                                <?php echo $message['replied'] ? 'Replied' : 'Pending'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    <?php else: ?>
                        <p>No messages.</p>
                    <?php endif; ?>
                 </div>
            </div>


            <!---lost books section-->
            <?php if (!empty($lostBooksList)): ?>
                <div class="lost-section">
                    <h4>❌ Lost Books</h4>ee
                    <p>The following books have been marked as lost/mising:</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Book</th>
                                <th>Borrowed On</th>
                                <th>Due Date</th>
                                <th>Fine</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lostBooksList as $lost): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($lost['member_name']); ?></td>
                                    <td><?php echo htmlspecialchars($lost['book_title']); ?></td>
                                    <td><?php echo $lost['borrow_date']; ?></td>
                                    <td><?php echo $lost['due_date']; ?></td>
                                    <td>GHS <?php echo number_format($lost['fine_amount'] ?? 0, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        
        </main>
    </div>
    
</body>
</html>

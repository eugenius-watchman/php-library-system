<?php
/**
 * admin/messages.php
 */


// ----Session and Authentication----
session_start();

// check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// ---DB Connection----
require_once '../config/database.php';

// ---handle actions----
$successMessage = '';
$errorMessage = '';
$filter = $_GET['filter'] ?? 'all';

// mark message as replied
if (isset($_GET['reply_id'])) {
    $messageId = (int)$_GET['reply_id'];
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE contact_messages 
            SET replied = TRUE, replied_by = :librarian_id 
            WHERE message_id = :message_id
        ");
        $stmt->execute([
            ':message_id' => $messageId,
            ':librarian_id' => $_SESSION['user_id']
        ]);
        $successMessage = "✅ Message marked as replied.";
    } catch(PDOException $e) {
        $errorMessage = "❌ Error: " . $e->getMessage();
    }
}

// mark message as unreplied...
if (isset($_GET['unreply_id'])) {
    $messageId = (int)$_GET['unreply_id'];
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE contact_messages 
            SET replied = FALSE, replied_by = NULL 
            WHERE message_id = :message_id
        ");
        $stmt->execute([':message_id' => $messageId]);
        $successMessage = "✅ Message marked as unreplied.";
    } catch(PDOException $e) {
        $errorMessage = "❌ Error: " . $e->getMessage();
    }
}

// delete message
if (isset($_GET['delete_id'])) {
    $messageId = (int)$_GET['delete_id'];
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM contact_messages WHERE message_id = :message_id");
        $stmt->execute([':message_id' => $messageId]);
        $successMessage = "✅ Message deleted.";
    } catch(PDOException $e) {
        $errorMessage = "❌ Error: " . $e->getMessage();
    }
}

//handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $messageId = (int)$_POST['message_id'];
    $replyContent = trim($_POST['reply_content']);
    
    if (empty($replyContent)) {
        $errorMessage = "❌ Please enter a reply.";
    } else {
        try {
            $db = getDB();
            
            // get original message
            $stmt = $db->prepare("
                SELECT fullname, email, subject, message 
                FROM contact_messages 
                WHERE message_id = :message_id
            ");
            $stmt->execute([':message_id' => $messageId]);
            $message = $stmt->fetch();
            
            if ($message) {
                // save reply notes in database
                $stmt = $db->prepare("
                    UPDATE contact_messages 
                    SET replied = TRUE, 
                        reply_notes = :reply_notes,
                        replied_by = :librarian_id
                    WHERE message_id = :message_id
                ");
                $stmt->execute([
                    ':reply_notes' => $replyContent,
                    ':librarian_id' => $_SESSION['user_id'],
                    ':message_id' => $messageId
                ]);
                
                // NB if in production...send email reply
                $to = $message['email'];
                $subject = "Reply: " . $message['subject'];
                $headers = "From: library@bss.org\r\n";
                $headers .= "Reply-To: library@bss.org\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                
                $body = "Dear " . $message['fullname'] . ",\n\n";
                $body .= "Thank you for contacting BSS Library.\n\n";
                $body .= "Your message:\n" . $message['message'] . "\n\n";
                $body .= "Our reply:\n" . $replyContent . "\n\n";
                $body .= "Best regards,\n";
                $body .= "BSS Library Team";
                
                // Uncomment to send email in production
                // @mail($to, $subject, $body, $headers);
                
                $successMessage = "✅ Reply sent successfully!";
            }
            
        } catch(PDOException $e) {
            $errorMessage = "❌ Error: " . $e->getMessage();
        }
    }
}

// ----Get all messages with filer ----
//Initialise variables with default values
$messages = [];
$totalMessages = 0;
$pendingMessages = 0;
$repliedMessages = 0;
$subjectLabels = [
    'general' => 'General Enquiry',
    'membership' => 'Membership Enquiry',
    'suggestion' => 'Suggestion',
    'book-suggestion' => 'Suggest A Book',
    'book_request' => 'Book Request',
    'event_info' => 'Event Information',
    'complaint' => 'Complaint',
    'donation' => 'Book Donation'
];

try {
    $db = getDB();
    
    // build query based on filter
    $sql = "
        SELECT 
            m.*,
            mem.full_name AS member_name,
            lib.full_name AS replied_by_name
        FROM contact_messages m
        LEFT JOIN members mem ON m.member_id = mem.member_id
        LEFT JOIN librarians lib ON m.replied_by = lib.librarian_id
    ";
    
    if ($filter === 'pending') {
        $sql .= " WHERE m.replied = 0";  // FALSE = 0
    } elseif ($filter === 'replied') {
        $sql .= " WHERE m.replied = 1";  // TRUE = 1
    }
    
    $sql .= " ORDER BY m.created_at DESC";
    
    $stmt = $db->query($sql);
    $messages = $stmt->fetchAll();
    
    //get stats... with error handling
    $stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages");
    $result = $stmt->fetch();
    $totalMessages = $result ? $result['total'] : 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages WHERE replied = 0");
    $result = $stmt->fetch();
    $pendingMessages = $result ? $result['total'] : 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages WHERE replied = 1");
    $result = $stmt->fetch();
    $repliedMessages = $result ? $result['total'] : 0;
    
} catch(PDOException $e) {
    error_log("Messages error: " . $e->getMessage());
    $errorMessage = "❌ Unable to load messages: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Library System</title>
    
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/messages.css">
</head>
<body>
    <div class="admin-container">
        
        <!-- sidebar -->
        <nav class="sidebar">
            <h2>📚 Library Admin</h2>
            <ul>
                <li><a href="dashboard.php">📊 Dashboard</a></li>
                <li><a href="../members.php">👥 Members</a></li>
                <li><a href="messages.php" class="active">✉️ Messages</a></li>
                <li><a href="settings.php">⚙️ Settings</a></li>
                <li><a href="../logout.php" class="logout-link">🚪 Logout</a></li>
            </ul>
        </nav>
        
        <!---main Content- -->
        <main class="main-content">
            <div class="messages-container">
                
                <!----header -->
                <div class="messages-header">
                    <h1>✉️ Contact Messages</h1>
                    <div class="stats">
                        <span>📨 Total: <?php echo $totalMessages; ?></span>
                        <span>⏳ Pending: <?php echo $pendingMessages; ?></span>
                        <span>✅ Replied: <?php echo $repliedMessages; ?></span>
                    </div>
                </div>
                
                <!---Messages --->
                <?php if ($successMessage): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>
                <?php if ($errorMessage): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>
                
                <!--- filter tabs--->
                <div class="filter-tabs">
                    <a href="?filter=all" class="<?php echo $filter === 'all' ? 'active' : ''; ?>">
                        📋 All <span class="count"><?php echo $totalMessages; ?></span>
                    </a>
                    <a href="?filter=pending" class="<?php echo $filter === 'pending' ? 'active' : ''; ?>">
                        ⏳ Pending <span class="count"><?php echo $pendingMessages; ?></span>
                    </a>
                    <a href="?filter=replied" class="<?php echo $filter === 'replied' ? 'active' : ''; ?>">
                        ✅ Replied <span class="count"><?php echo $repliedMessages; ?></span>
                    </a>
                </div>
                
                <!---message list--->
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="message-item <?php echo $message['replied'] ? 'replied' : ''; ?>">
                            
                            <!---message header--->
                            <div class="message-header-info">
                                <div>
                                    <span class="message-from">
                                        👤 <?php echo htmlspecialchars($message['fullname']); ?>
                                        <?php if ($message['member_name']): ?>
                                            <span class="member-badge">
                                                🆔 <?php echo htmlspecialchars($message['member_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="badge <?php echo $message['replied'] ? 'badge-replied' : 'badge-pending'; ?>">
                                        <?php echo $message['replied'] ? '✅ Replied' : '⏳ Pending'; ?>
                                    </span>
                                </div>
                                <span class="message-date">
                                    📅 <?php echo date('M d, Y h:i A', strtotime($message['created_at'])); ?>
                                </span>
                            </div>
                            
                            <!---subject -->
                            <div class="message-subject">
                                📌 <?php echo htmlspecialchars($subjectLabels[$message['subject']] ?? $message['subject']); ?>
                            </div>
                            
                            <!--message body--->
                            <div class="message-body">
                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                            </div>
                            
                            <!--contact info--->
                            <div class="message-contact">
                                <span class="contact-item">📧 <?php echo htmlspecialchars($message['email']); ?></span>
                                <?php if ($message['phone']): ?>
                                    <span class="contact-item">📱 <?php echo htmlspecialchars($message['phone']); ?></span>
                                <?php endif; ?>
                                <?php if ($message['reply_wanted'] === 'Yes'): ?>
                                    <span class="contact-item reply-wanted">🔔 Reply requested</span>
                                <?php else: ?>
                                    <span class="contact-item no-reply">🔕 No reply needed</span>
                                <?php endif; ?>
                                <?php if ($message['replied_by_name']): ?>
                                    <span class="contact-item">✍️ Replied by: <?php echo htmlspecialchars($message['replied_by_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <!---reply notes ...if replied-->
                            <?php if ($message['replied'] && $message['reply_notes']): ?>
                                <div class="reply-notes">
                                    <strong>📝 Reply Notes:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($message['reply_notes'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!---actions-->
                            <div class="message-actions">
                                <?php if (!$message['replied']): ?>
                                    <button class="btn btn-primary btn-sm toggle-reply" 
                                            onclick="toggleReply(<?php echo $message['message_id']; ?>)">
                                        ✉️ Reply
                                    </button>
                                    <a href="?reply_id=<?php echo $message['message_id']; ?>" 
                                       class="btn btn-success btn-sm">
                                        ✅ Mark Replied (No Email)
                                    </a>
                                <?php else: ?>
                                    <a href="?unreply_id=<?php echo $message['message_id']; ?>" 
                                       class="btn btn-warning btn-sm">
                                        🔄 Mark Unreplied
                                    </a>
                                <?php endif; ?>
                                
                                <a href="?delete_id=<?php echo $message['message_id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('⚠️ Delete this message?\n\nThis cannot be undone!')">
                                    🗑️ Delete
                                </a>
                            </div>
                            
                            <!---reply form--->
                            <div id="replyForm_<?php echo $message['message_id']; ?>" class="reply-form">
                                <form method="POST" action="">
                                    <input type="hidden" name="message_id" value="<?php echo $message['message_id']; ?>">
                                    
                                    <label for="reply_<?php echo $message['message_id']; ?>" style="font-weight: 600; display: block; margin-bottom: 8px;">
                                        Your Reply:
                                    </label>
                                    <textarea id="reply_<?php echo $message['message_id']; ?>" 
                                              name="reply_content" 
                                              placeholder="Type your reply here..."
                                              required></textarea>
                                    
                                    <div class="form-actions">
                                        <button type="submit" name="reply_message" class="btn btn-primary">
                                            📤 Send Reply & Mark Replied
                                        </button>
                                        <button type="button" class="btn btn-secondary" 
                                                onclick="toggleReply(<?php echo $message['message_id']; ?>)">
                                            ❌ Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                        </div>
                    <?php endforeach; ?>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <h3>No Messages Found</h3>
                        <p>There are no messages to display.</p>
                    </div>
                <?php endif; ?>
                
            </div>
        </main>
    </div>
    
    <!-- JavaScript -->
    <script>
        function toggleReply(messageId) {
            const form = document.getElementById('replyForm_' + messageId);
            if (form.classList.contains('show')) {
                form.classList.remove('show');
            } else {
                form.classList.add('show');
                // focus on the textarea
                const textarea = form.querySelector('textarea');
                if (textarea) {
                    setTimeout(function() {
                        textarea.focus();
                    }, 100);
                }
            }
        }
    </script>
    
</body>
</html>
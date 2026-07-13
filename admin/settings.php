<?php
/**
 * admin/settings.php
 * System settings management
 */

//--- Session and Authentication----
session_start();

// check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

//--- DB Connection---
require_once '../config/database.php';

// handle form submission
$successMessage = '';
$errorMessage = '';
$showAlert = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        // get and sanitize form data
        $libraryName = htmlspecialchars(trim($_POST['library_name'] ?? ''));
        $libraryEmail = filter_var(trim($_POST['library_email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $libraryPhone = htmlspecialchars(trim($_POST['library_phone'] ?? ''));
        $libraryAddress = htmlspecialchars(trim($_POST['library_address'] ?? ''));
        $maxBorrowDays = (int)($_POST['max_borrow_days'] ?? 14);
        $maxBooksPerMember = (int)($_POST['max_books_per_member'] ?? 5);
        $dailyFineRate = (float)($_POST['daily_fine_rate'] ?? 10);
        $currency = htmlspecialchars(trim($_POST['currency'] ?? 'GHS'));
        $timezone = htmlspecialchars(trim($_POST['timezone'] ?? 'Africa/Accra'));
        
        // validate email
        if (!filter_var($libraryEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }
        
        // validate numbers
        if ($maxBorrowDays < 1 || $maxBorrowDays > 30) {
            throw new Exception("Max borrow days must be between 1 and 30.");
        }
        if ($maxBooksPerMember < 1 || $maxBooksPerMember > 5) {
            throw new Exception("Max books per member must be between 1 and 5.");
        }
        if ($dailyFineRate < 0) {
            throw new Exception("Daily fine rate cannot be negative.");
        }
        
        // update settings using INSERT ... ON DUPLICATE KEY UPDATE
        $settings = [
            'library_name' => ['value' => $libraryName, 'type' => 'string', 'category' => 'general'],
            'library_email' => ['value' => $libraryEmail, 'type' => 'string', 'category' => 'contact'],
            'library_phone' => ['value' => $libraryPhone, 'type' => 'string', 'category' => 'contact'],
            'library_address' => ['value' => $libraryAddress, 'type' => 'string', 'category' => 'general'],
            'max_borrow_days' => ['value' => $maxBorrowDays, 'type' => 'number', 'category' => 'borrowing'],
            'max_books_per_member' => ['value' => $maxBooksPerMember, 'type' => 'number', 'category' => 'borrowing'],
            'daily_fine_rate' => ['value' => $dailyFineRate, 'type' => 'number', 'category' => 'fines'],
            'currency' => ['value' => $currency, 'type' => 'string', 'category' => 'general'],
            'timezone' => ['value' => $timezone, 'type' => 'string', 'category' => 'general']
        ];
        
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_type, category) 
            VALUES (:key, :value, :type, :category)
            ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                setting_type = VALUES(setting_type),
                category = VALUES(category)
        ");
        
        foreach ($settings as $key => $data) {
            $stmt->execute([
                ':key' => $key,
                ':value' => $data['value'],
                ':type' => $data['type'],
                ':category' => $data['category']
            ]);
        }
        
        // set timezone for this session
        date_default_timezone_set($timezone);
        
        $successMessage = "✅ Settings saved successfully!";
        $showAlert = true;
        
    } catch (Exception $e) {
        $errorMessage = "❌ Error: " . $e->getMessage();
        $showAlert = true;
    } catch (PDOException $e) {
        error_log("Settings error: " . $e->getMessage());
        $errorMessage = "❌ Database error. Please try again later.";
        $showAlert = true;
    }
}

// ----lLoad Current Settings----
$settings = [];

try {
    $db = getDB();
    
    // load settings from database
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // set defaults for missing settings
    $defaults = [
        'library_name' => 'BSS Library',
        'library_email' => 'library@bss.org',
        'library_phone' => '+244 000 123456',
        'library_address' => '123 Adenta Street, Accra, Ghana',
        'max_borrow_days' => 14,
        'max_books_per_member' => 5,
        'daily_fine_rate' => 10,
        'currency' => 'GHS',
        'timezone' => 'Africa/Accra'
    ];
    
    // merge with defaults
    foreach ($defaults as $key => $value) {
        if (!isset($settings[$key])) {
            $settings[$key] = $value;
        }
    }
    
} catch (PDOException $e) {
    error_log("Settings load error: " . $e->getMessage());
    // use defaults if database fails
    $settings = [
        'library_name' => 'BSS Library',
        'library_email' => 'library@bss.org',
        'library_phone' => '+244 000 123456',
        'library_address' => '123 Adenta Street, Accra, Ghana',
        'max_borrow_days' => 14,
        'max_books_per_member' => 5,
        'daily_fine_rate' => 10,
        'currency' => 'GHS',
        'timezone' => 'Africa/Accra'
    ];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - BSS Library System</title>
    
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/settings.css">
</head>
<body>
    <div class="admin-container">
        
        <!--sidebar -->
        <nav class="sidebar">
            <h2>📚 Library Admin</h2>
            <ul>
                <li><a href="dashboard.php">📊 Dashboard</a></li>
                <li><a href="../members.php">👥 Members</a></li>
                <li><a href="messages.php">✉️ Messages</a></li>
                <li><a href="settings.php" class="active">⚙️ Settings</a></li>
                <li><a href="../logout.php" class="logout-link">🚪 Logout</a></li>
            </ul>
        </nav>
        
        <!---main content -->
        <main class="main-content">
            <div class="settings-container">
                
                <!---header -->
                <div class="settings-header">
                    <h1>⚙️ System Settings</h1>
                    <p>Configure your library system preferences and rules.</p>
                </div>
                
                <!---alert messages -->
                <?php if ($showAlert): ?>
                    <div id="alertMessage" class="alert <?php echo $successMessage ? 'alert-success show' : 'alert-error show'; ?>">
                        <?php echo htmlspecialchars($successMessage ?: $errorMessage); ?>
                    </div>
                <?php endif; ?>
                
                <!---settings form -->
                <form method="POST" action="" class="settings-form" id="settingsForm">
                    
                    <!---section 1... Library Info. -->
                    <div class="form-section">
                        <h3><span class="icon">📚</span> Library Information</h3>
                        
                        <div class="form-group">
                            <label for="library_name">Library Name <span class="required">*</span></label>
                            <input type="text" 
                                   id="library_name" 
                                   name="library_name" 
                                   value="<?php echo htmlspecialchars($settings['library_name']); ?>" 
                                   placeholder="Enter library name"
                                   required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="library_email">Library Email <span class="required">*</span></label>
                                <input type="email" 
                                       id="library_email" 
                                       name="library_email" 
                                       value="<?php echo htmlspecialchars($settings['library_email']); ?>" 
                                       placeholder="library@example.com"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="library_phone">Library Phone</label>
                                <input type="text" 
                                       id="library_phone" 
                                       name="library_phone" 
                                       value="<?php echo htmlspecialchars($settings['library_phone']); ?>" 
                                       placeholder="+123 456 7890">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="library_address">Library Address</label>
                            <textarea id="library_address" 
                                      name="library_address" 
                                      rows="2"
                                      placeholder="Enter library physical address"><?php echo htmlspecialchars($settings['library_address']); ?></textarea>
                        </div>
                    </div>
                    
                    <!---section 2...borrowing rules -->
                    <div class="form-section">
                        <h3><span class="icon">📖</span> Borrowing Rules</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="max_borrow_days">Maximum Borrow Days <span class="required">*</span></label>
                                <input type="number" 
                                       id="max_borrow_days" 
                                       name="max_borrow_days" 
                                       value="<?php echo $settings['max_borrow_days']; ?>" 
                                       min="1" 
                                       max="90" 
                                       required>
                                <span class="help-text">Number of days a member can borrow a book (1-90)</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_books_per_member">Max Books Per Member <span class="required">*</span></label>
                                <input type="number" 
                                       id="max_books_per_member" 
                                       name="max_books_per_member" 
                                       value="<?php echo $settings['max_books_per_member']; ?>" 
                                       min="1" 
                                       max="20" 
                                       required>
                                <span class="help-text">Maximum books a member can borrow at once (1-20)</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- section 3...fine settings--->
                    <div class="form-section">
                        <h3><span class="icon">💰</span> Fine Settings</h3>
                        
                        <div class="form-group">
                            <label for="daily_fine_rate">Daily Fine Rate <span class="required">*</span></label>
                            <input type="number" 
                                   id="daily_fine_rate" 
                                   name="daily_fine_rate" 
                                   value="<?php echo $settings['daily_fine_rate']; ?>" 
                                   min="0" 
                                   step="0.50" 
                                   required>
                            <span class="help-text">Amount charged per day for overdue books in <?php echo htmlspecialchars($settings['currency']); ?></span>
                        </div>
                    </div>
                    
                    <!---section 4...general settings -->
                    <div class="form-section">
                        <h3><span class="icon">🌍</span> General Settings</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="currency">Currency <span class="required">*</span></label>
                                <select id="currency" name="currency" required>
                                    <option value="GHS" <?php echo $settings['currency'] === 'GHS' ? 'selected' : ''; ?>>🇬🇭 GHS (Ghana Cedi)</option>
                                    <option value="KES" <?php echo $settings['currency'] === 'KES' ? 'selected' : ''; ?>>🇰🇪 KES (Kenyan Shilling)</option>
                                    <option value="USD" <?php echo $settings['currency'] === 'USD' ? 'selected' : ''; ?>>🇺🇸 USD (US Dollar)</option>
                                    <option value="EUR" <?php echo $settings['currency'] === 'EUR' ? 'selected' : ''; ?>>🇪🇺 EUR (Euro)</option>
                                    <option value="GBP" <?php echo $settings['currency'] === 'GBP' ? 'selected' : ''; ?>>🇬🇧 GBP (British Pound)</option>
                                    <option value="NGN" <?php echo $settings['currency'] === 'NGN' ? 'selected' : ''; ?>>🇳🇬 NGN (Nigerian Naira)</option>
                                    <option value="ZAR" <?php echo $settings['currency'] === 'ZAR' ? 'selected' : ''; ?>>🇿🇦 ZAR (South African Rand)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="timezone">Timezone <span class="required">*</span></label>
                                <select id="timezone" name="timezone" required>
                                    <option value="Africa/Accra" <?php echo $settings['timezone'] === 'Africa/Accra' ? 'selected' : ''; ?>>🇬🇭 Africa/Accra</option>
                                    <option value="Africa/Nairobi" <?php echo $settings['timezone'] === 'Africa/Nairobi' ? 'selected' : ''; ?>>🇰🇪 Africa/Nairobi</option>
                                    <option value="Africa/Lagos" <?php echo $settings['timezone'] === 'Africa/Lagos' ? 'selected' : ''; ?>>🇳🇬 Africa/Lagos</option>
                                    <option value="Africa/Johannesburg" <?php echo $settings['timezone'] === 'Africa/Johannesburg' ? 'selected' : ''; ?>>🇿🇦 Africa/Johannesburg</option>
                                    <option value="Africa/Cairo" <?php echo $settings['timezone'] === 'Africa/Cairo' ? 'selected' : ''; ?>>🇪🇬 Africa/Cairo</option>
                                    <option value="UTC" <?php echo $settings['timezone'] === 'UTC' ? 'selected' : ''; ?>>🌐 UTC</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!--form actions--->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="saveBtn">
                            💾 Save Settings
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            🔄 Reset Changes
                        </button>
                    </div>
                    
                </form>
                
            </div>
        </main>
    </div>
    
    <!-- JavaScript--->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('settingsForm');
            const saveBtn = document.getElementById('saveBtn');
            const alertMessage = document.getElementById('alertMessage');
            
            // Auto-hide alert after 5 seconds
            if (alertMessage) {
                setTimeout(function() {
                    alertMessage.classList.remove('show');
                }, 5000);
            }
            
            // form validation before submit
            form.addEventListener('submit', function(e) {
                const email = document.getElementById('library_email');
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                // validate email
                if (!emailPattern.test(email.value)) {
                    e.preventDefault();
                    email.classList.add('error');
                    showAlert('Please enter a valid email address.', 'error');
                    email.focus();
                    return false;
                }
                
                // memove error class on focus
                email.addEventListener('focus', function() {
                    this.classList.remove('error');
                });
                
                // show loading state
                saveBtn.disabled = true;
                saveBtn.textContent = '⏳ Saving...';
            });
            
            function showAlert(message, type) {
                const alertDiv = document.getElementById('alertMessage') || createAlert();
                alertDiv.textContent = message;
                alertDiv.className = 'alert alert-' + (type === 'error' ? 'error' : 'success') + ' show';
            }
            
            function createAlert() {
                const div = document.createElement('div');
                div.id = 'alertMessage';
                div.className = 'alert';
                const container = document.querySelector('.settings-container');
                container.insertBefore(div, container.querySelector('form'));
                return div;
            }
            
            // Reset button - reload page to reset form values
            document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Reset all changes? Unsaved changes will be lost.')) {
                    window.location.reload();
                }
            });
        });
    </script>
    
</body>
</html>
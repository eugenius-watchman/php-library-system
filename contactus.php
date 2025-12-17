<?php
require_once 'config/database.php';


// check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    // honeypot check ...if this field is filled, then its likely a bot
    if(!empty($_POST['website'])) {
        die("Spam detected!");
    }

    // sanitise and validate user input
    $fullname = htmlspecialchars(trim($_POST['fullname'])); //r
    $phone = htmlspecialchars(trim($_POST['phone']));
    $email = filter_var(trim($_POST['useremail']), FILTER_SANITIZE_EMAIL);//r
    $memberid = htmlspecialchars(trim($_POST['memberid']));
    $subject = htmlspecialchars(trim($_POST['subject']));
    $message = htmlspecialchars(trim($_POST['usermessage'])); //r
    $replywanted = isset($_POST['replywanted']) ? 'Yes' : 'No';


    // validate required fields
    if (empty($fullname) || empty($email) || empty($message)) {
        die("Please fill all required fields.");
    }

    // validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email format");
    }

    // validate phone numver ...if provided, must be 10 digits
    $formattedPhone = "";
    if (!empty($phone)) {
        // remove all non-digit characters
        $phoneDigits = preg_replace('/\D/', '', $phone);

        // check if its exactly 10 digits
        if (strlen($phoneDigits) != 10) {
            die("Phone number must be exactly 10 digits.");
        }

        // format as xxx-xxx-xxxx
        $formattedPhone = substr($phoneDigits, 0, 3) . '-' . 
                          substr($phoneDigits, 3, 3) . '-' . 
                          substr($phoneDigits, 6, 4);
    } else {
        $formattedPhone = "";
    }

    // ===== Save To DB (Primary Storage) =====
    $dbSuccess = false;
    $messageId = null;
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO contact_messages
            (fullname, email, phone, member_id, subject, message, reply_wanted)
            VALUES (:fullname, :email, :phone, :member_id, :subject, :message, :reply_wanted)
        ");

        // convert empty memberid to NULL for database
        $dbMemberId = !empty($memberid) ? $memberid : null;

        $stmt->execute([
            ':fullname' => $fullname,
            ':email' => $email,
            ':phone' => $formattedPhone,
            ':member_id' => $dbMemberId,
            ':subject' => $subject,
            ':message' => $message,
            ':reply_wanted' => $replywanted
        ]);
        
        $dbSuccess = true;
        $messageId = $db->lastInsertId();

    } catch(PDOException $e) {
        $dbSuccess = false;
        error_log("Databae error: " . $e->getMessage());

        // continue to JSON fallback
    }

    // ==== Fallback: Save to JSON File ====
    $jsonSuccess = false;
    if (!$dbSuccess) { // save only to JSON ...if db failed
        // create submission directory if it dne
        // submission directory 
    $submissionDir = 'submissions';
    if (!file_exists($submissionDir)) {
        mkdir($submissionDir, 0755, true);
    }

    // create array with form data for JSON storage
    $data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'fullname' => $fullname,
        'email' => $email,
        'phone' => $formattedPhone,
        'memberid' => $memberid,
        'subject' => $subject,
        'message' => $message,
        'replywanted' => $replywanted
    ];

    // save to JSON file
        $jsonFile = 'submissions/contacts.json';
        if (file_put_contents($jsonFile, json_encode($data) . "\n", FILE_APPEND | LOCK_EX)) {
            $jsonSuccess = true;
        } else {
            $jsonSuccess = false;
            error_log("Failed to write to $jsonFile. Check permissions.");
        }
    }
    // map subject values to readable labels
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

    // Null coalescing operator (??) returns right side if left is null/undefined
    $subjectText = $subjectLabels[$subject] ?? $subject;

    // create email content for librarian
    $to = "librarian@bss.org";
    $emailSubject = "BSS Library Contact: $subjectText";

    // build plain text email body
    $emailBody = "CONTACT FORM SUBMISSION\n";
    $emailBody .= "************************\n";
    $emailBody .= "Full Name: $fullname \n";
    $emailBody .= "Email: $email\n";
    $emailBody .= "Phone: " . ($formattedPhone ?: "Not provided") . "\n";
    $emailBody .= "Member ID: " . ($memberid ?: "Not provided") . "\n";
    $emailBody .= "Subject: $subjectText\n";
    $emailBody .= "Message:\n$message\n";
    $emailBody .= "Reply Wanted: $replywanted\n";
    $emailBody .= "\nSubmitted on: " . date('Y-m-d H:i:s');

    
     // \r\n is windows line ending ...required for email headers
    $headers = "From: noreply@bss.org\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();


    // send email
    $emailSent = @mail($to, $emailSubject, $emailBody, $headers);

    // display message
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Form Submission</title>
        <link rel='stylesheet' href='css/contactus.css'>
        <style>
            .container { max-width: 600px; margin: 50px auto; padding: 20px; }
            .success { background: #d4edda; color: #155724; padding: 20px; border-radius: 5px; }
            .warning { background: #fff3cd; color: #856404; padding: 20px; border-radius: 5px; }
            .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>";
    
    if ($jsonSuccess) {
        echo "<div class='success'>
                <h1>✓ Message Saved Successfully!</h1>
                <p>Thank you, $fullname. Your message has been saved to our system.</p>";
        
        if ($emailSent) {
            echo "<p>A notification has been sent to our library staff.</p>";
        } else {
            echo "<div class='warning'>
                    <p><strong>Note:</strong> Your message was saved, but email notification failed.</p>
                    <p>Our staff will still review your message from our records.</p>
                  </div>";
        }
        
        echo "<p>We will respond to you at <strong>$email</strong> if a reply was requested.</p>
              <p><a href='index.php'>Return to Homepage</a> | <a href='contactus.html'>Send Another Message</a></p>
              </div>";
    } else {
        echo "<div class='error'>
                <h1>⚠️ System Error</h1>
                <p>Sorry, there was an error saving your message. Please try again later.</p>
                <p>You can also contact us directly at library@bss.org</p>
                <p><a href='contactus.html'>Go Back</a> | <a href='index.php'>Homepage</a></p>
              </div>";
    }
    
    echo "</div></body></html>";
    
    } else {
        // when someone tries to access the page directly without submitting the form
        header("Location: index.php");
        exit();
    }

?>
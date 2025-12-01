<?php
// check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    // honeypot check ...if this field is filled, then its likely a bot
    if(!empty($_POST['website'])) {
        die("Spam detected!");
    }

    // sanitise and validate user input
    $fullname = htmlspecialchars(trim($_POST['fullname']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $email = filter_var(trim($_POST['useremail']), FILTER_SANITIZE_EMAIL);
    $memberid = htmlspecialchars(trim($_POST['memberid']));
    $subject = htmlspecialchars(trim($_POST['subject']));
    $message = htmlspecialchars(trim($_POST['usermessage']));
    $replywanted = isset($_POST['replywanted']) ? 'Yes' : 'No';


    // validate required fields
    if (empty($fullname) || empty($email) || empty($message)) {
        die("Please fill all required fields.");
    }

    // validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email format");
    }

    // create array with form data for JSON storage
    $data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'fullname' => $fullname,
        'email' => $email,
        'phone' => $phone,
        'memberid' => $memberid,
        'subject' => $subject,
        'message' => $message,
        'replywanted' => $replywanted
    ];

    // save to JSON file
    $jsonFile = 'submission/contacts.json';
    file_put_contents($jsonFile, json_encode($data) . "\n", FILE_APPEND | LOCK_EX);
    echo "<p>Your message has been submitted successfully!</p>";

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
    $emailBody .= "Phone: " . ($phone ?: "Not provided") . "\n";
    $emailBody .= "Member ID" . ($memberid ?: "Not provided") . "\n";
    $emailBody .= "Subject: $subjectText\n";
    $emailBody .= "Message:\n$message\n";
    $emailBody .= "Reply Wanted: $replywanted\n";
    $emailBody .= "\nSubmitted on: " . date('Y-m-d H:i:s');

    
     // \r\n is windows line ending ...required for email headers
    $headers = "From: noreply@bss.org\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();


    // send email
    if (mail($to, $emailSubject, $emailBody, $headers)) {
        // success - display thank you message
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Message Sent</title>
            <link rel='stylesheet' href='css/contactus.css'>
        </head>
        <body>
            <div class='container'>
                <h1>Thank You!</h1>
                <p>Your message has been successfully sent. We'll get back to you soon.</p>
                <p>Your message has been logged for our records.</p>
                <p><a href='index.html'>Return to Homepage</a></p>
            </div>
        </body>
        </html>";
    } else {
        // Email failed - but data was already saved to JSON
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Partial Success</title>
        </head>
        <body>
            <h1>Message Saved!</h1>
            <p>Your message has been saved to our system, but there was an issue sending the email notification.</p>
            <p>Our staff will still review your message. Thank you!</p>
            <p><a href='index.html'>Return to Homepage</a></p>
        </body>
        </html>";
    }
} else {
    // when some one tries to access the page directly without submitting the form
    header("Location: index.html");
    exit();
}

?>
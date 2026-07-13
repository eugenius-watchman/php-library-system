<?php
/*
* logout.php file
*
*/

session_start();

//-- destroy session---
// clear all session var
$_SESSION = array();

// delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// destroy session
session_destroy();

//---Redirect to login page---
// redirect with logout message
header('Location: login.php?logout=success');

?>
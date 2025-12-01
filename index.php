<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Home Page</title>
</head>
<body>
    <img src="images/library.jpg" width="300" height="200">
    <?php
        date_default_timezone_set('UTC');
        echo "time is " . date("h:i:s") . "<br>";
        echo "Eugene"
    ?>
    <hr>
    <h3>Welcome to the BSS Library!</h3>
    <a href="booksearch.html">Browse our books...</a>
</body>
</html>

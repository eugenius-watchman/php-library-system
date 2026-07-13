<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Search - Library System</title>

    <link rel="stylesheet" href="css/booksearch.css">
</head>
<body>
    <!-- SEARCH FORM SECTION -->
    <!-- search box -->
    <div class="search-container">
       <h1>📚 Book Search</h1>

       <form method="GET" action="" class="search-form">
          
            <!-- Title Input Group -->
             <div class="form-group">
                <label for="searchtitle">Book Title</label>
                <input type="text"
                        name="searchtitle"
                        id="searchtitle"
                        placeholder="Enter book title..."
                        value="<?php echo isset($_GET['searchtitle']) ? htmlspecialchars($_GET['searchtitle']) : ''; ?>"
                >
             </div>

             <!-- Author Input Group -->
              <div class="form-group">
                <label for="searchauthor">Author</label>
                <input type="text"
                        name="searchauthor"
                        id="searchauthor"
                        placeholder="Enter author name..."
                        value="<?php echo isset($_GET['searchauthor']) ? htmlspecialchars($_GET['searchauthor']) : ''; ?>"
                >
              </div>

              <!-- Search Button -->
               <button type="submit" class="btn-search">🔍 Search</button>

              <!-- Clear Button (link with ?clear=1 parameter)-->
               <a href="?clear=1" class="btn-clear">✕ Clear</a>
            
       </form>
    </div>

<?php
 // Search Logic Section 

require_once 'config/database.php';

// get search parameters from url
$searchTitle = isset($_GET['searchtitle']) ? trim($_GET['searchtitle']) : '';
$searchAuthor = isset($_GET['searchauthor']) ? trim($_GET['searchauthor']) : '';

// handle clear button
$clearSearch = isset($_GET['clear']);
if($clearSearch){
    $searchTitle = '';
    $searchAuthor = '';
    header('Location: booksearch.php');
    exit();
}

// Validate Search Input
$hasSearch = !empty($searchTitle) || !empty($searchAuthor);
$errorMessage = '';

if($hasSearch) {
    // check min character length...at least 2 chars
    if(!empty($searchTitle) && strlen($searchTitle) < 2) {
        $errorMessage = 'Please enter at least 2 characters for the title search.';
    }
    if(!empty($searchAuthor) && strlen($searchAuthor) < 2) {
        $errorMessage .= ' Please enter at least 2 characters for the author search.';
    }
}
?>

<!--Result Display Section -->
<div class="results-container">
    <?php
    // What to Display
    // no search perfomed yet ... both title and author are empty
    if(empty($searchTitle) && empty($searchAuthor) && !isset($_GET['searchtitle']) && !isset($_GET['searchauthor'])):
    ?>

    <!-- No search yet ...display friendly message -->
     <div class="no-results">
        <p style="font-size: 48px; margin-bottom: 20px;">🔎</p>
        <h3>Search for Books</h3>
        <p>Enter a title or author above to find books in our library.</p>
        <p style="font-size: 14px; color: #aaa; margin-top: 10px;">
            Tip: You can search by title, author or both.
        </p>
     </div>

     <?php
      // Validation Error ...if $errorMessage is not empty
      elseif($errorMessage):
     ?>

     <!-- show validation error message -->
      <div class="error-message">
        <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($errorMessage);?>
      </div>
    
      
      <?php
        // Search perfomed with valid input ...$hasSearch is true ...$errorMessage is empty
        elseif($hasSearch):
      ?>

        <!-- Start Search Results Logic -->
         <?php
           try {
            // Get DB Connection ...use PDO connection object to query DB
            $db = getDB();

            // build search query
            $searchTerms = []; // for display
            $params = []; // for security

            // Base query... SELECT all books
            $sql = "SELECT * FROM books WHERE 1=1";
           

           // add title search condition ...if title provided
           if (!empty($searchTitle)) {
            $sql .= " AND title LIKE :title";
            $params[':title'] = '%' . $searchTitle . '%';
            $searchTerms[] = "Title: '" . htmlspecialchars($searchTitle) . "'";
           }

           // add author search condition ... if author provided
           if (!empty($searchAuthor)) {
            $sql .=" AND author LIKE :author";
            $params[':author'] = '%' . $searchAuthor . '%';
            $searchTerms[] = "Author: '" . htmlspecialchars($searchAuthor) . "'";
           }

           // Order by Relivance
           // CASE statement ranking ...1. exact title macth 2. exact author match 3. everything else
           // sort by title alphebetically within each ranking

           $sql .= " ORDER BY
                CASE
                    WHEN title LIKE :title_exact THEN 1
                    WHEN author LIKE :author_exact THEN 2
                    ELSE 3
                END, title ASC";
           
            // add exact match parameters for ordering(without % wildcards) 
            // if search is empty...use empty string
            if(!empty($searchTitle)) {
                $params[':title_exact'] = $searchTitle;
            } else {
                $params[':title_exact'] = '';
            }

            if(!empty($searchAuthor)) {
                $params[':author_exact'] = $searchAuthor;
            } else {
                $params[':author_exact'] = '';
            }

        // Execute The Query
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            $resultCount = count($results);
         ?>

         <!-- Display Result Header -->
          <div class="results-header">
            <h2>Search Results</h2>
            <span class="results-count">
                <?php
                // display number of book(s) found
                echo $resultCount;
                echo $resultCount !== 1 ? ' books found' : ' book found';
                ?>
                <?php if (!empty($searchTerms)): ?>
                    <!-- display what was searched for-->
                    <br><small>Searching: <?php echo implode(' | ', $searchTerms); 
                ?></small>
                <?php endif; ?>
            </span>
          </div>

          
          <?php if ($resultCount > 0):?>
            
            <!-- BOOK GRID: Display each book card -->
            <div class="book-grid">

                <?php
                /**
                 * Loop through results ..using for each
                 * Each book is an assoc. array with col name as keys
                 */
                foreach($results as $book):
                ?>
                    <div class="book-card">

                        <!-- Book Title-->
                        <div class="book-title">
                            <?php
                            // htmlspecialchars: Prevent XSS by escaping HTML
                            echo htmlspecialchars($book['title']); 
                            ?>
                        </div>
                        
                        <!-- Book Author -->
                        <div class="book-author">
                            by <?php echo htmlspecialchars($book['author']); ?>
                        </div>

                        <!-- Book Details -->
                         <div class="book-details">
                            <!-- category (or 'uncategorised if empty)-->
                            <span>📖 <?php echo htmlspecialchars($book['category'] ?: 'Uncategorized'); ?></span>

                            <!-- publication year (or 'N/A' if empty) -->
                            <span>📅 <?php echo htmlspecialchars($book['publication_year'] ?: 'N/A'); ?></span>
                            <br>

                            <!-- availability -->
                            <span>📚 Available: <?php echo htmlspecialchars($book['available_copies']); ?>/
                            <?php echo htmlspecialchars($book['total_copies']); ?></span>

                            <!-- Status Badge-->
                            <span>
                                <?php if ($book['available_copies'] > 0): ?>
                                    <!-- Available: Green badge -->
                                    <span class="book-status status-available">Available</span>
                                <?php else: ?>
                                    <!-- Not Available: Yellow badge -->
                                    <span class="book-status status-borrowed">Borrowed</span>
                                <?php endif; ?>
                            </span>
                        </div>                       
                    </div>
                <?php endforeach; ?>
            
            </div>

          <?php else: ?>

                <!-- No Results found -->
                <div class="no-results">
                    <p style="font-size: 48px; margin-bottom: 20px;">😕</p>
                    <h3>No Books Found</h3>
                    <p>We couldn't find any books matching your search.</p>
                    <p style="font-size: 14px; color: #aaa; margin-top: 10px;">
                        Try using fewer words or checking your spelling.
                    </p>
                    <p style="font-size: 14px; color: #aaa;">
                        Searched for: <?php echo implode(' | ', $searchTerms); ?>
                    </p>
                </div>

          <?php endif; ?>

          <?php
          /**
           * Handle DB Errors
           */
           } catch(PDOException $e) {
            // Database Error ... show user-friendly message
            ?>
            <div class="error-message">
                <strong>⚠️ Database Error:</strong>
                Unable to search books. Please try again later.

                <?php if (isset($_GET['debug'])): ?>
                    <!-- If ?debug=1 is in URL, show technical error -->
                    <br><small>Error: <?php echo htmlspecialchars($e->getMessage())?></small>
                <?php endif; ?>

            </div>
            <?php
           }
          ?>
    <?php endif; ?>
        

</div>
    
</body>
</html>
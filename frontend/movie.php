<?php
// Shows movie details and action buttons from /processors
require_once(__DIR__.'/path.inc');
require_once(__DIR__.'/get_host_info.inc');
require_once(__DIR__.'/rabbitMQLib.inc');

// Get movie ID from URL
if (!isset($_GET['id'])) {
    die("Error: No movie ID provided.");
}
$movieId = $_GET['id'];

// Get movie details from backend
$client = new rabbitMQClient(__DIR__."/rabbitMQ.ini", "Server");
$request = array(
    "type" => "get_movie_details",
    "movie_id" => $movieId
);

// Will either come from DB or DMZ
$movie = $client->send_request($request);

// Check if movie not found
if (empty($movie) || isset($movie['status']) && $movie['status'] == 'error') {
    die("Movie not found.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $movie['title']; ?> - IT490 Vault</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: auto; }
        .movie-header { display: flex; gap: 20px; margin-bottom: 20px; }
        .movie-poster { max-width: 300px; border-radius: 8px; }
        .action-box { border: 1px solid #ccc; padding: 15px; border-radius: 8px; margin-top: 20px; }
        #actionStatus { color: green; font-weight: bold; margin-top: 10px; }
    </style>
</head>
<body>

    <div class="movie-header">
        <?php if (!empty($movie['poster_path'])): ?>
            <img class="movie-poster" src="https://image.tmdb.org/t/p/w500<?php echo $movie['poster_path']; ?>" alt="Poster">
        <?php endif; ?>
        
        <div>
            <h1><?php echo $movie['title']; ?></h1>
            <p><strong>Release Date:</strong> <?php echo $movie['release_date']; ?></p>
            <p><strong>Overview:</strong> <?php echo $movie['overview']; ?></p>
        </div>
    </div>

    <div class="action-box">
        <h3>My Vault Actions</h3>
        
        <button onclick="addToWatchlist(<?php echo $movieId; ?>)">Add to Watchlist</button>
        <hr>
        
        <label><input type="checkbox" id="hasSeen"> I have seen this</label><br><br>
        <label><input type="checkbox" id="isOwned"> I own this</label><br><br>
        <button onclick="addToLibrary(<?php echo $movieId; ?>)">Save to My Library</button>

        <div id="actionStatus"></div>
    </div>

    <script>
    // Remember to get username from session, update later
    const username = "CURRENT_USER"; 

    function addToWatchlist(movieId) {
        var request = new XMLHttpRequest();
        request.open("POST", "library_action.php", true);
        request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        request.onreadystatechange = function () {
            if ((this.readyState == 4) && (this.status == 200)) {
                var response = JSON.parse(this.responseText);
                document.getElementById("actionStatus").innerHTML = response.message;
            }
        };
        request.send("action=add_to_watchlist&username=" + username + "&movie_id=" + movieId);
    }

    function addToLibrary(movieId) {
        var seen = document.getElementById("hasSeen").checked;
        var owned = document.getElementById("isOwned").checked;
        
        var request = new XMLHttpRequest();
        request.open("POST", "library_action.php", true);
        request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        request.onreadystatechange = function () {
            if ((this.readyState == 4) && (this.status == 200)) {
                var response = JSON.parse(this.responseText);
                document.getElementById("actionStatus").innerHTML = response.message;
            }
        };
        request.send("action=add_to_library&username=" + username + "&movie_id=" + movieId + "&has_seen=" + seen + "&is_owned=" + owned);
    }

    <div class="action-box">
        <h3>Leave a Review</h3>
        <label for="rating">Rating:</label>
        <select id="rating">
            <option value="5">5 Stars - Masterpiece</option>
            <option value="4">4 Stars - Great</option>
            <option value="3">3 Stars - Good</option>
            <option value="2">2 Stars - Bad</option>
            <option value="1">1 Star - Terrible</option>
        </select>
        <br><br>
        
        <label for="reviewText">Review:</label><br>
        <textarea id="reviewText" rows="4" style="width: 100%;" placeholder="What did you think of the movie?"></textarea>
        <br><br>
        
        <button onclick="submitReview(<?php echo $movieId; ?>)">Submit Review</button>
        <div id="reviewStatus" style="color: blue; font-weight: bold; margin-top: 10px;"></div>
    </div>

    function submitReview(movieId) {
        var rating = document.getElementById("rating").value;
        var text = document.getElementById("reviewText").value;
        
        var request = new XMLHttpRequest();
        request.open("POST", "library_action.php", true);
        request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        request.onreadystatechange = function () {
            if ((this.readyState == 4) && (this.status == 200)) {
                var response = JSON.parse(this.responseText);
                document.getElementById("reviewStatus").innerHTML = response.message;
            }
        };
        
        // Update "current user" to actual user when backend logic is done for sessions - Ryann
        request.send("action=add_review&username=" + username + "&movie_id=" + movieId + "&rating=" + rating + "&review_text=" + encodeURIComponent(text));
    }
    </script>

</body>
</html>
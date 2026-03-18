<?php
// Dashboard page, checks session cookie and loads user data
require_once(__DIR__.'/../path.inc');
require_once(__DIR__.'/../get_host_info.inc');
require_once(__DIR__.'/../rabbitMQLib.inc');

$session_id = $_COOKIE['session_id'] ?? '';
if (empty($session_id)) {
    header('Location: index.html');
    exit;
}

$client = new rabbitMQClient(__DIR__."/../rabbitMQ.ini", "Server");
$user_response = $client->send_request(array("type" => "get_username", "session_id" => $session_id));

if (empty($user_response['username']) || ($user_response['status'] ?? '') !== 'success') {
    setcookie("session_id", "", time() - 3600, "/");
    header('Location: index.html');
    exit;
}

$current_username = $user_response['username'];

// Get upcoming movies from db (cron on DMZ fills this nightly)
$upcoming_movies = $client->send_request(array("type" => "get_upcoming_movies"));
if (!is_array($upcoming_movies) || isset($upcoming_movies['status'])) {
	$upcoming_movies = array();
}

// Get recs for this user from db
$recommendations = $client->send_request(array("type" => "get_recommendations", "username" => $current_username));
if (!is_array($recommendations) || isset($recommendations['status'])) {
	$recommendations = array();
}

// Fetch User Alerts from cron/db
$alerts = $client->send_request(array("type" => "get_alerts", "username" => $current_username));
if (!is_array($alerts) || isset($alerts['status'])) {
	$alerts = array();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Vault Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1000px; margin: auto; background-color: #f4f4f9;}
        .movie-grid { display: flex; gap: 15px; overflow-x: auto; padding-bottom: 10px; }
        .movie-card { min-width: 150px; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; }
        .movie-card img { width: 100%; border-radius: 5px; }
        .search-box { margin-bottom: 30px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        input[type="text"] { padding: 10px; width: 70%; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>

    <h1>Welcome back, <?php echo htmlspecialchars($current_username, ENT_QUOTES, 'UTF-8'); ?>! <a href="processors/logout.php" style="font-size: 0.5em; font-weight: normal;">Log out</a></h1>

    <?php if (!empty($alerts)): ?>
        <div style="background-color: #fff3cd; border-left: 5px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <h3 style="margin-top: 0; color: #856404;">New Alerts</h3>
            <ul style="margin-bottom: 0; color: #856404;">
                <?php foreach ($alerts as $alert): ?>
                    <li><strong><?php echo $alert['created_at']; ?>:</strong> <?php echo $alert['message']; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="search-box">
        <h3>Find a Movie</h3>
        <input type="text" id="movieSearchBox" placeholder="Search for Batman, Inception...">
        <button onclick="searchMovies()">Search</button>
        <div id="searchResults" style="margin-top: 15px;"></div>
    </div>

    <h2>Recommended For You</h2>
    <div class="movie-grid">
        <?php if (!empty($recommendations) && !isset($recommendations['status'])): ?>
            <?php foreach ($recommendations as $movie): ?>
                <div class="movie-card">
                    <img src="https://image.tmdb.org/t/p/w200<?php echo $movie['poster_path']; ?>" alt="Poster">
                    <h4><?php echo $movie['title']; ?></h4>
                    <a href="movie.php?id=<?php echo $movie['movie_id']; ?>">View Details</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Rate some movies to get personalized recommendations!</p>
        <?php endif; ?>
    </div>

    <hr>

    <h2>New & Upcoming Releases</h2>
    <div class="movie-grid">
        <?php if (!empty($upcoming_movies)): ?>
            <?php foreach ($upcoming_movies as $movie): ?>
                <div class="movie-card">
                    <img src="https://image.tmdb.org/t/p/w200<?php echo $movie['poster_path']; ?>" alt="Poster">
                    <h4><?php echo $movie['title']; ?></h4>
                    <a href="movie.php?id=<?php echo $movie['movie_id']; ?>">View Details</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    function searchMovies() {
        var query = document.getElementById("movieSearchBox").value;
        document.getElementById("searchResults").innerHTML = "Searching...";

        var request = new XMLHttpRequest();
        request.open("POST", "processors/search.php", true);
        request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        request.onreadystatechange = function () {
            if ((this.readyState == 4) && (this.status == 200)) {
                try {
                    var data = JSON.parse(this.responseText);
                    if (!Array.isArray(data)) {
                        document.getElementById("searchResults").innerHTML = data.message || "Search failed. Try again.";
                        return;
                    }
                    var output = "<ul>";
                    for (var i = 0; i < data.length; i++) {
                        output += "<li><a href='movie.php?id=" + data[i].id + "'>" + data[i].title + " (" + (data[i].release_date || "") + ")</a></li>";
                    }
                    output += "</ul>";
                    document.getElementById("searchResults").innerHTML = output;
                } catch (e) {
                    document.getElementById("searchResults").innerHTML = "Search failed. Try again.";
                }
            }
        };
        request.send("search_query=" + encodeURIComponent(query));
    }
    </script>

</body>
</html>

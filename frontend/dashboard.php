<?php
// Login landing page that professor required of us during authentication
require_once(__DIR__.'/path.inc');
require_once(__DIR__.'/get_host_info.inc');
require_once(__DIR__.'/rabbitMQLib.inc');

// Update by verifying session
$current_username = "CURRENT_USER"; 

$client = new rabbitMQClient(__DIR__."/rabbitMQ.ini", "Server");

// Fetch upcoming movies from DMZ
$upcoming_request = array("type" => "get_upcoming_movies");
$upcoming_movies = $client->send_request($upcoming_request);

// Get user recommendations from local database
$rec_request = array("type" => "get_recommendations", "username" => $current_username);
$recommendations = $client->send_request($rec_request);

// Fetch User Alerts from cron/db
$alert_request = array("type" => "get_alerts", "username" => $current_username);
$alerts = $client->send_request($alert_request);
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

    <h1>Welcome back, <?php echo $current_username; ?>!</h1>

    <?php if (!empty($alerts)): ?>
        <div style="background-color: #fff3cd; border-left: 5px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <h3 style="margin-top: 0; color: #856404;">🔔 New Alerts</h3>
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
                    <a href="movie.php?id=<?php echo $movie['id']; ?>">View Details</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    function searchMovies() {
        var query = document.getElementById("movieSearchBox").value;
        document.getElementById("searchResults").innerHTML = "Searching...";

        var request = new XMLHttpRequest();
        request.open("POST", "search.php", true);
        request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        request.onreadystatechange = function () {
            if ((this.readyState == 4) && (this.status == 200)) {
                var movies = JSON.parse(this.responseText);
                var output = "<ul>";
                for (var i = 0; i < movies.length; i++) {
                    output += "<li><a href='movie.php?id=" + movies[i].id + "'>" + movies[i].title + " (" + movies[i].release_date + ")</a></li>";
                }
                output += "</ul>";
                document.getElementById("searchResults").innerHTML = output;
            }
        };
        request.send("search_query=" + encodeURIComponent(query));
    }
    </script>

</body>
</html>
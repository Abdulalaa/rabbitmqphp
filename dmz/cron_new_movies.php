#!/usr/bin/php
<?php
// Automated cron script to fetch daily new movies from TMDB
require_once(__DIR__.'/../path.inc');
require_once(__DIR__.'/../get_host_info.inc');
require_once(__DIR__.'/../rabbitMQLib.inc');

// Fetch new movies from TMDB
$apiKey = "YOUR_API_KEY";
$url = "https://api.themoviedb.org/3/movie/upcoming?api_key={$apiKey}&language=en-US&page=1";

echo "CRON: Waking up to fetch daily new movies...\n";
$jsonResponse = @file_get_contents($url);

if ($jsonResponse === FALSE) {
    echo "CRON: TMDB API is unreachable. Going back to sleep.\n";
    exit(1);
}

$phpArray = json_decode($jsonResponse, true);
$newMovies = $phpArray['results'] ?? [];

// Connect to backend and send list of new movies
$client = new rabbitMQClient(__DIR__."/../rabbitMQ.ini", "Server");

$request = array();
$request['type'] = "process_daily_movies";
$request['movies'] = $newMovies;

echo "CRON: Sending " . count($newMovies) . " movies to the Backend for processing...\n";
$response = $client->send_request($request);

echo "CRON: Backend replied: " . $response['message'] . "\n";
exit(0);
?>
#!/usr/bin/php
<?php
// Automated cron script to fetch daily new movies from TMDB
require_once(__DIR__.'/../path.inc');
require_once(__DIR__.'/../get_host_info.inc');
require_once(__DIR__.'/../rabbitMQLib.inc');
require_once(__DIR__.'/../logPublisher.inc');

// Securely load the API key from the ignored .ini file
$config = parse_ini_file(__DIR__.'/api_config.ini', true);
$apiKey = $config['TMDB']['api_key'];

// Fetch new movies from TMDB
$url = "https://api.themoviedb.org/3/movie/upcoming?api_key={$apiKey}&language=en-US&page=1";

echo "CRON: Waking up to fetch daily new movies...\n";
$jsonResponse = @file_get_contents($url);

if ($jsonResponse === FALSE) {
    sendLog("CRON: TMDB upcoming-movies API is unreachable");
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

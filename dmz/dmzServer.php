#!/usr/bin/php
<?php
// Import core libraries for dmz listener to interact w/
// Stored in root dir = __DIR__/../filename
require_once(__DIR__.'/../path.inc');
require_once(__DIR__.'/../get_host_info.inc');
require_once(__DIR__.'/../rabbitMQLib.inc');

// Read the secure config file
$config = parse_ini_file(__DIR__.'/api_config.ini', true);

// Shared curl helper with timeouts — replaces file_get_contents to prevent indefinite hanging
// Returns raw JSON string on success, false on failure
function tmdbRequest($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);
    if ($response === false)
    {
        echo "CURL error: {$error}".PHP_EOL;
        return false;
    }
    return $response;
}

// Function to fetch movie data from The Movie Database API
// $searchQuery is string the user types in search box
function fetchMoviesFromAPI($searchQuery)
{
    global $config;
    
    $apiKey    = $config['TMDB']['api_key'];
    $safeQuery = urlencode($searchQuery);
    $url       = "https://api.themoviedb.org/3/search/movie?api_key={$apiKey}&query={$safeQuery}";
    
    echo "Fetching movies from API for query: {$searchQuery}".PHP_EOL;

    $jsonResponse = tmdbRequest($url);

    if ($jsonResponse === false)
    {
        echo "Error fetching movies from API".PHP_EOL;
        return [];
    }

    $phpArray = json_decode($jsonResponse, true);
    return $phpArray['results'] ?? [];
}

// Function to get details of a specific movie (for view movies deliverable)
// $movieId is the tmdb movie id
function getMovieDetails($movieId)
{
    global $config;
    
    $apiKey = $config['TMDB']['api_key'];
    $url    = "https://api.themoviedb.org/3/movie/{$movieId}?api_key={$apiKey}";

    echo "Fetching details for Movie ID {$movieId}".PHP_EOL;

    $jsonResponse = tmdbRequest($url);
    if ($jsonResponse === false) return [];

    return json_decode($jsonResponse, true) ?? [];
}

// Function to fetch brand new/upcoming movies (for view movies deliverable)
function getUpcomingMovies()
{
    global $config;
    
    $apiKey = $config['TMDB']['api_key']; 
    $url    = "https://api.themoviedb.org/3/movie/upcoming?api_key={$apiKey}&language=en-US&page=1";

    echo "Fetching list of upcoming movies".PHP_EOL;

    $jsonResponse = tmdbRequest($url);
    if ($jsonResponse === false) return [];

    $phpArray = json_decode($jsonResponse, true);
    return $phpArray['results'] ?? [];
}

// Switchboard function for mapping requests to appropriate functions
// $request is php array sent from backend
// Similar to requestProcessor in backend/rabbitMQServer.php used in listeners 
function requestProcessor($request)
{
    // Terminal verification for request received
    echo "DMZ Server received request".PHP_EOL;
    var_dump($request);

    // If no type provided, reject message for safety/crash prevention
    if(!isset($request['type']))
    {
        return "ERROR: unsupported message type";
    }

    // Switch statement for routing based on request['type']
    switch ($request['type'])
    {
        case "search_movies":
            // Call fetch movie function and pass search query
            return fetchMoviesFromAPI($request['query']);
        
        case "get_movie_details":
            // Call get movie details function and pass movie id to it
            return getMovieDetails($request['movie_id'] ?? $request['movieId'] ?? null);

        case "get_upcoming_movies":
            // Call get upcoming movies function with no parameters
            return getUpcomingMovies();
    }

    // Return ignored message for type not found
    return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

// Listener, rabbitMQ.ini for vpn IP and port conf
$server = new rabbitMQServer(__DIR__."/../rabbitMQ.ini","DMZ");

// Infinite listening loop
// Message received, processed in requestProcessor, return array arrives, sends back over to queue 
$server->process_requests('requestProcessor');

echo "DMZ Server is now shut down".PHP_EOL;
exit();
?>

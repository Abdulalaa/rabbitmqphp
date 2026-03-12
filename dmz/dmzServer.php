#!/usr/bin/php
<?php
// Import core libraries for dmz listener to interact w/
// Stored in root dir = __DIR__/../filename
require_once(__DIR__.'/../path.inc');
require_once(__DIR__.'/../get_host_info.inc');
require_once(__DIR__.'/../rabbitMQLib.inc');

// Read the secure config file
$config = parse_ini_file(__DIR__.'/api_config.ini', true);

// Function to fetch movie data from The Movie Database API
// $searchQuery is string the user types in search box
function fetchMoviesFromAPI($searchQuery)
{
    global $config;
    
    // Store TMDB API key from secure config file
    $apiKey = $config['TMDB']['api_key'];

    // Sanitize search query + URL encode for safe HTTP
    // Prevents url breaking or security issues
    $safeQuery = urlencode($searchQuery);

    // URL variable for TMDB movie data endpoint
    // V3 authentication instead of V4 for now
    $url = "https://api.themoviedb.org/3/search/movie?api_key={$apiKey}&query={$safeQuery}";
    
    // Terminal verification message
    echo "Fetching movies from API for query: {$searchQuery}".PHP_EOL;

    // Send HTTP GET request to API using file_get_contents()
    // @ for suppressing PHP warnings for temporary network hiccups since they sometimes happen but they're not a big deal
    $jsonResponse = @file_get_contents($url);

    // If API is down or network drops return empty array to prevent crashes
    if ($jsonResponse === false)
    {
        echo "Error fetching movies from API".PHP_EOL;
        return [];
    }

    // Convert returned JSON to php array
    // true forces associative array so rabbitmq can easily parse it
    $phpArray = json_decode($jsonResponse, true);

    // Return only results array of movies, ignore other unimportant data like page count and metadata
    // ?? [] to return empty array if results are empty
    return $phpArray['results'] ?? [];
}

// Function to get details of a specific movie (for view movies deliverable)
// $movieId is the tmdb movie id
function getMovieDetails($movieId)
{
    global $config;
    
    $apiKey = $config['TMDB']['api_key'];
    
    // URL variable for TMDB movie details endpoint
    $url = "https://api.themoviedb.org/3/movie/{$movieId}?api_key={$apiKey}";

    // Terminal verification message
    echo "Fetching details for Movie ID {$movieId}".PHP_EOL;

    // Send HTTP GET request to API using file_get_contents()
    // @ for suppressing PHP warnings for temporary network hiccups since they sometimes happen but they're not a big deal
    $jsonResponse = @file_get_contents($url);
    
    if ($jsonResponse === false) return [];

    // Return the single movie's data array
    return json_decode($jsonResponse, true) ?? [];
}

// Function to fetch brand new/upcoming movies (for view movies deliverable)
function getUpcomingMovies()
{
    global $config;
    
    $apiKey = $config['TMDB']['api_key']; 
    
    // URL variable for TMDB upcoming movies endpoint
    $url = "https://api.themoviedb.org/3/movie/upcoming?api_key={$apiKey}&language=en-US&page=1";

    // Terminal verification message
    echo "Fetching list of upcoming movies".PHP_EOL;

    // Send HTTP GET request to API using file_get_contents()
    // @ for suppressing PHP warnings for temporary network hiccups since they sometimes happen but they're not a big deal
    $jsonResponse = @file_get_contents($url);
    
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
            return getMovieDetails($request['movieId']);

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

#!/usr/bin/php
<?php
// Import the contents of core libraries listener interacts w/ 
// Core libraries stored in root dir = __DIR__/../filename
require_once(__DIR__.'/../path.inc');
require_once(__DIR__.'/../get_host_info.inc');
require_once(__DIR__.'/../rabbitMQLib.inc');
require_once('login.php.inc');
require_once('movie_db.php.inc'); // Wrapper functions for database logic


// Wrapper function for login (sends request to database listener for actual sql logic/connection)
function doLogin($username,$password)
{
	// New instance of db class
	$login = new loginDB();
	// Attempt login using library
    	return $login->validateLogin($username,$password);
}

// Wrapper function for registration
function doRegister($username, $password)
{
	// New instance
	$login = new loginDB();
	// Attempt registration using library
	return $login->registerUser($username, $password);
}

// Wrapper function for logout
function doLogout($username)
{
	// New instance
	$login = new loginDB();
	// Attempt logout using library
	return $login->logoutUser($username);
}

// Wrapper function for validating session
function doValidate($session_id)
{
	// New instance
	$login = new loginDB();
	// Attempt session validation
	return $login->validateSession($session_id);
}

// Wrapper to get username from session token (for frontend pages)
function doGetUsername($session_id)
{
	$login = new loginDB();
	return $login->getUsernameFromSession($session_id);
}

// Wrapper to logout by session token (no username needed)
function doLogoutBySession($session_id)
{
	$login = new loginDB();
	return $login->logoutBySession($session_id);
}

// Wrapper function for asking DMZ server for movie data
function askDMZ($request)
{
	// client connection for DMZ queue
	$dmzClient = new rabbitMQClient(__DIR__."/../rabbitMQ.ini","DMZ");

	// Terminal verification message
	echo "Asking DMZ server".PHP_EOL;
	var_dump($request);

	// Send request to DMZ queue and wait for response
	$response = $dmzClient->send_request($request);

	echo "Backend received response from DMZ".PHP_EOL;
	return $response;
}

// Cache wrapper for movie deftails
function getSmartMovieDetails($movieId) {
    $db = new MovieDB();
    
    // Check cache first
    $cachedMovie = $db->checkCache($movieId);
    
    if ($cachedMovie) {
        echo "Backend: Served $movieId from Local Cache. Saved an API call!\n";
        return $cachedMovie;
    }
    // Ask DMZ if not in cache
    $request = array("type" => "get_movie_details", "movie_id" => $movieId);
    $dmzMovieData = askDMZ($request);
    // Save to cache if not alr in cache
    if (!empty($dmzMovieData)) {
        $db->cacheMovie($dmzMovieData);
    }
    return $dmzMovieData;
}

// Switch Statement function for routing requests to appropriate functions
// $request is php array sent from frontend
function requestProcessor($request)
{
	// echo received request and show request message in terminal
	echo "received request".PHP_EOL;
  	var_dump($request);

	// If no type provided, reject message for safety/crash prevention
	if(!isset($request['type']))
  	{
    		return "ERROR: unsupported message type";
	}

	$db = new MovieDB(); // Instantiate our new database class

	// Switch statement for routing
  	switch ($request['type'])
  	{
		// Database login/registration/session logic
    	case "login":
      		return doLogin($request['username'], $request['password']);
		case "register":
		  	return doRegister($request['username'], $request['password']);
		case "validate_session":
			return doValidate($request['sessionId']);
		case "get_username":
			return doGetUsername($request['session_id']);
		case "logout":
			return doLogout($request['username']);
		case "logout_by_session":
			return doLogoutBySession($request['session_id']);

		// Cache routes
		case "get_movie_details":
			// Check db before DMZ
			return getSmartMovieDetails($request['movie_id']);

		// API/DMZ movie data logic
		case "search_movies":
		case "get_upcoming_movies":
			// Still need to add caching logic 
			return askDMZ($request);

		// Database movie table logic
		case "add_to_library":
			return $db->addToLibrary($request['username'], $request['movie_id'], $request['has_seen'], $request['is_owned']);
		case "add_to_watchlist":
			return $db->addToWatchlist($request['username'], $request['movie_id']);
		// Updated add review logic after debugging movie_db php inc problems
		case "add_review":
			return $db->addReview($request['username'], $request['movie_id'], $request['rating'], $request['review_text']);
		case "get_recommendations":
			return $db->getRecommendations($request['username']);
			// Cron script at 2 am to fetch new movies
		case "process_daily_movies":
			return $db->processDailyMovies($request['movies']);
		case "get_alerts":
			return $db->getUserAlerts($request['username']);
	}
	// Success message
  	return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

// Create listener, rabbitMQ.ini for vpn IP and port conf
$server = new rabbitMQServer(__DIR__."/../rabbitMQ.ini","Server");

// Infinite listening loop
// Message received, processed in requestProcessor, return array arrives, sends back over to webserver
$server->process_requests('requestProcessor');

echo "Backend Listener is now shut down".PHP_EOL;
exit();
?>

#!/usr/bin/php
<?php
// core libs in root dir
require_once(__DIR__.'/../path.inc');
require_once(__DIR__.'/../get_host_info.inc');
require_once(__DIR__.'/../rabbitMQLib.inc');
require_once('login.php.inc');
require_once('movie_db.php.inc');


function doLogin($username,$password)
{
	$login = new loginDB();
    	return $login->validateLogin($username,$password);
}

function doRegister($username, $password)
{
	$login = new loginDB();
	return $login->registerUser($username, $password);
}

function doLogout($username)
{
	$login = new loginDB();
	return $login->logoutUser($username);
}

function doValidate($session_id)
{
	$login = new loginDB();
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

// asks DMZ for movie data
function askDMZ($request)
{
	$dmzClient = new rabbitMQClient(__DIR__."/../rabbitMQ.ini","DMZ");
	echo "Asking DMZ server".PHP_EOL;
	var_dump($request);
	$response = $dmzClient->send_request($request);
	echo "Backend received response from DMZ".PHP_EOL;
	return $response;
}

// check cache first, ask DMZ if not cached
function getSmartMovieDetails($movieId) {
    $db = new MovieDB();
    $cachedMovie = $db->checkCache($movieId);
    if ($cachedMovie) {
        echo "Backend: Served $movieId from Local Cache. Saved an API call!\n";
        return $cachedMovie;
    }
    $request = array("type" => "get_movie_details", "movie_id" => $movieId);
    $dmzMovieData = askDMZ($request);
    if (!empty($dmzMovieData)) {
        $db->cacheMovie($dmzMovieData);
    }
    return $dmzMovieData;
}

// routes incoming requests to the right function
function requestProcessor($request)
{
	echo "received request".PHP_EOL;
  	var_dump($request);

	if(!isset($request['type']))
  	{
    		return "ERROR: unsupported message type";
	}

	$db = new MovieDB();

  	switch ($request['type'])
  	{
    	case "login":
      		return doLogin($request['username'], $request['password']);
		case "register":
		  	return doRegister($request['username'], $request['password']);
		case "validate_session":
			return doValidate($request['session_id']);
		case "get_username":
			return doGetUsername($request['session_id']);
		case "logout":
			return doLogout($request['username']);
		case "logout_by_session":
			return doLogoutBySession($request['session_id']);

		case "get_movie_details":
			return getSmartMovieDetails($request['movie_id']);

		// Upcoming movies stored by cron, just pull from db
		case "get_upcoming_movies":
			return $db->getUpcomingMovies();

		case "search_movies":
			return askDMZ($request);

		case "add_to_library":
			return $db->addToLibrary($request['username'], $request['movie_id'], $request['has_seen'], $request['is_owned']);
		case "add_to_watchlist":
			return $db->addToWatchlist($request['username'], $request['movie_id']);
		case "add_review":
			return $db->addReview($request['username'], $request['movie_id'], $request['rating'], $request['review_text']);
		case "get_recommendations":
			return $db->getRecommendations($request['username']);
		case "process_daily_movies":
			return $db->processDailyMovies($request['movies']);
		case "get_alerts":
			return $db->getUserAlerts($request['username']);
		case "get_watchlist":
			return $db->getWatchlist($request['username']);
		case "get_library":
			return $db->getLibrary($request['username']);
	}
  	return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

$server = new rabbitMQServer(__DIR__."/../rabbitMQ.ini","Server");
$server->process_requests('requestProcessor');

echo "Backend Listener is now shut down".PHP_EOL;
exit();
?>

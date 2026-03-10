#!/usr/bin/php
<?php
// Import the contents of core libraries listener interacts w/ 
// Core libraries stored in root dir = __DIR__/../filename
require_once(__DIR__.'/../path.inc');
require_once(__DIR__.'/../get_host_info.inc');
require_once(__DIR__.'/../rabbitMQLib.inc');
require_once('login.php.inc');

// All db logic done in login.php.inc library

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


// Wrapper function for asking DMZ server for movie data
function askDMZ($request)
{
	// Create client connection for DMZ queue
	$dmzClient = new rabbitMQClient(__DIR__."/../rabbitMQ.ini","DMZ");

	// Terminal verification message
	echo "Asking DMZ server".PHP_EOL;
	var_dump($request);

	// Send request to DMZ queue and wait for response
	$response = $dmzClient->send_request($request);

	echo "Backend received response from DMZ".PHP_EOL;
	return $response;
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
		case "logout":
			return doLogout($request['username']);

		// API/DMZ movie data logic
		case "search_movies":
		case "get_movie_details":
		case "get_upcoming_movies":
			// Still need to add caching logic later but directly pass to DMZ for now
			return askDMZ($request);


		// Database movie table logic
		case "add_to_library":
		case "add_to_watchlist":
		case "add_review":
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


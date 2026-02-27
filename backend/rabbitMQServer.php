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
	// Pass un and pw to db
    	return $login->validateLogin($username,$password);
}

// Wrapper function for registration
function doRegister($username, $password)
{
	// New instance
	$login = new loginDB();
	// Pass un and pw to db
	return $login->registerUser($username, $password);
}


// Switch Statement function for mapping request to correct loginDB function in login.php.inc
// $request is a PHP array
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
    		case "login":
      		  	return doLogin($request['username'], $request['password']);
		case "register":
		  	return doRegister($request['username'], $request['password']);
		case "validate_session":
		  	// Still need to research and implement (request and return token from db?)
	}
	// Success message
  	return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

// Create listener, rabbitMQ.ini for vpn IP and port conf
$server = new rabbitMQServer("testRabbitMQ.ini","testServer");

// Infinite listening loop
// Message received, processed in requestProcessor, return array arrives, sends back over to webserver
$server->process_requests('requestProcessor');

echo "Backend Listener is now shut down".PHP_EOL;
exit();
?>


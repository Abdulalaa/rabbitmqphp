<?php

// Combined login.php + rabbitMQClient.php - login.php fetches POST data from webpage, rabbitMQClient connects to RabbitMQ
// Combining them simplfiied them into a single bridge that connected browser/form to RabbitMQ
// Import RabbitMQ library/config files from parent directory
// Similar to RabbitMQServer in backend
require_once(__DIR__.'/../path.inc');
require_once(__DIR__.'/../get_host_info.inc');
require_once(__DIR__.'/../rabbitMQLib.inc');

// Get data from webpage (index.html)
// Kill script if any attempt to load this file directly into browser without filling in forms for security
// Check if POST isn't set or empty
if (!isset($_POST) || empty($_POST))
{
	echo "Error: No form data received";
	exit(0);
}

// Get variables sent from index.html AJAX query string
$type = $_POST["type"]; // Either login or register
$username = $_POST["username"];
$password = $_POST["password"];

// Initialize RabbitMQ client connection (using ini)
$client = new rabbitMQClient(__DIR__."/../rabbitMQ.ini", "Server");

// Empty array to hold data
$request = array();

// Package the data for backend in request array
$request['type'] = $type;
$request['username'] = $username;
$request['password'] = $password;

// Send request to queue
$response = $client->send_request($request);

// Handle database's reply (logout, login, register, invalid, error, etc)


// Logout
if ($type == 'logout') 
{
	// Delete browser cookier (session token), set expiration date to past
	setcookie("session_id", "", time() - 3600, "/");
	echo "Successfully logged out";
	exit(0);
}

// Login
// Save session id received after successful login
if ($response['status'] == 'success' && $type == 'login')
{
	// Set and save session cookie
	setcookie("session_id", $response['session_id'], time() + 86400, "/");
}

// Everything else will be echoed as a message from request array (registration success, error, invalid login, etc)
echo $response['message'];
exit(0);
?>

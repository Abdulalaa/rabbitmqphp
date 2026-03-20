<?php

// bridge between login form and RabbitMQ
require_once(__DIR__.'/../../path.inc');
require_once(__DIR__.'/../../get_host_info.inc');
require_once(__DIR__.'/../../rabbitMQLib.inc');

if (!isset($_POST) || empty($_POST))
{
	echo "Error: No form data received";
	exit(0);
}

$type = $_POST["type"]; 
$username = $_POST["username"];
$password = $_POST["password"];

$client = new rabbitMQClient(__DIR__."/../../rabbitMQ.ini", "Server");

$request = array();
$request['type'] = $type;
$request['username'] = $username;
$request['password'] = $password;

$response = $client->send_request($request);

// Logout
if ($type == 'logout') 
{
	setcookie("session_id", "", time() - 3600, "/");
	echo "Successfully logged out";
	exit(0);
}

// Login success: set cookie and tell frontend to redirect to dashboard
if ($response['status'] == 'success' && $type == 'login')
{
	setcookie("session_id", $response['session_id'], time() + 86400, "/");
	header('Content-Type: application/json');
	echo json_encode(array("redirect" => "dashboard.php"));
	exit(0);
}

// Everything else: echo message (registration success, error, invalid login, etc)
echo $response['message'];
exit(0);
?>

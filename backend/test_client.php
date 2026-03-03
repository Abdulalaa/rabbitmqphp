#!/usr/bin/php
<?php
require_once(__DIR__.'/../path.inc');
require_once(__DIR__.'/../get_host_info.inc');
require_once(__DIR__.'/../rabbitMQLib.inc');

// Connect to your RabbitMQ server using the cleaned-up .ini file
$client = new rabbitMQClient(__DIR__."/../rabbitMQ.ini", "Server");

$request = array();

// CHANGE THIS to test different routes ("login", "register", etc.)
$request['type'] = "register"; 
$request['username'] = "Z_test_user";
$request['password'] = "supersecret";

echo "Throwing manual request into the RabbitMQ Queue...\n";
$response = $client->send_request($request);

echo "\n--- REPLY FROM DATABASE ---\n";
print_r($response);
echo "\n---------------------------\n";
?>

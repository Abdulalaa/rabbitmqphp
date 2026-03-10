<?php
// middleman between browser and RabbitMQ for movie searches
// fetches POST data from webpage, sends to RabbitMQ queue, and returns response to browser
// Import core libraries in parent dir for rabbitMQ interaction
require_once(__DIR__.'/../path.inc');
require_once(__DIR__.'/../get_host_info.inc');
require_once(__DIR__.'/../rabbitMQLib.inc');

// Prevent direct access to this file from the browser
if (!isset($_POST) || empty($_POST)) {
    echo json_encode(["status" => "error", "message" => "No search data received"]);
    exit(0);
}

// Grab the search string sent from the Javascript/HTML
$searchQuery = $_POST["search_query"];

// Connect to the RabbitMQ server
$client = new rabbitMQClient(__DIR__."/../rabbitMQ.ini", "Server");

// Prepare request array w/ type and query
$request = array();
$request['type'] = "search_movies";
$request['query'] = $searchQuery;

// Send request to queue and wait for DMZ to send data back
$response = $client->send_request($request);

// Turn returned php array into JSON string for js to read
echo json_encode($response);
exit(0);
?>
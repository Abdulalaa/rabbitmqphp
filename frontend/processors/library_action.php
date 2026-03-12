<?php
// PHP file that is bridge between browser and RabbitMQ for library actions
// Fetches POST data from webpage, sends to RabbitMQ queue, and returns response to browser
// This file is similar to search.php and login.php but for library actions
// Need same libraries required as other processor files we wrote
// I need to double check that the directory path is right since we moved processors into subfolder
require_once(__DIR__.'/path.inc');
require_once(__DIR__.'/get_host_info.inc');
require_once(__DIR__.'/rabbitMQLib.inc');

// Prevent direct access to this file from the browser
if (!isset($_POST) || empty($_POST)) {
    echo json_encode(["status" => "error", "message" => "No data received"]);
    exit(0);
}

// Connect to the RabbitMQ
$client = new rabbitMQClient(__DIR__."/rabbitMQ.ini", "Server");

// Package the request array
$request = array();

// POST data is gonna be either "add_to_watchlist" or "add_to_library"
$request['type'] = $_POST['action']; 

// Need to know who is adding the movie and which movie it is
$request['username'] = $_POST['username']; 
$request['movie_id'] = $_POST['movie_id'];

// If adding to library, need to send the checkboxes (Seen/Owned)
if ($request['type'] == "add_to_library") {
    // Convert JS T/F to PHP booleans
    $request['has_seen'] = ($_POST['has_seen'] === 'true') ? true : false;
    $request['is_owned'] = ($_POST['is_owned'] === 'true') ? true : false;
}

// Get star rating and review text for reviews
if ($request['type'] == "add_review") {
    $request['rating'] = $_POST['rating'];
    $request['review_text'] = $_POST['review_text'];
}

// Send req to RabbitMQ 
$response = $client->send_request($request);

// Send response to browser for user
echo json_encode($response);
exit(0);
?>
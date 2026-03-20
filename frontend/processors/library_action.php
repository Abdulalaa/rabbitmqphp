<?php
// bridge between movie action buttons and RabbitMQ
require_once(__DIR__.'/../../path.inc');
require_once(__DIR__.'/../../get_host_info.inc');
require_once(__DIR__.'/../../rabbitMQLib.inc');

// Prevent direct access to this file from the browser
if (!isset($_POST) || empty($_POST)) {
    echo json_encode(["status" => "error", "message" => "No data received"]);
    exit(0);
}

$client = new rabbitMQClient(__DIR__."/../../rabbitMQ.ini", "Server");

$request = array();
$request['type'] = $_POST['action']; 
$request['username'] = $_POST['username']; 
$request['movie_id'] = $_POST['movie_id'];

// send seen/owned checkboxes for library adds
if ($request['type'] == "add_to_library") {
    $request['has_seen'] = ($_POST['has_seen'] === 'true') ? true : false;
    $request['is_owned'] = ($_POST['is_owned'] === 'true') ? true : false;
}

// send rating + review text for reviews
if ($request['type'] == "add_review") {
    $request['rating'] = $_POST['rating'];
    $request['review_text'] = $_POST['review_text'];
}

$response = $client->send_request($request);
echo json_encode($response);
exit(0);
?>
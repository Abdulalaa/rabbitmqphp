<?php
// Tell backend to clear session token, delete cookie, redirect to index
require_once(__DIR__.'/../../path.inc');
require_once(__DIR__.'/../../get_host_info.inc');
require_once(__DIR__.'/../../rabbitMQLib.inc');

$session_id = $_COOKIE['session_id'] ?? '';
if (!empty($session_id)) {
    $client = new rabbitMQClient(__DIR__."/../../rabbitMQ.ini", "Server");
    $client->send_request(array("type" => "logout_by_session", "session_id" => $session_id));
}
setcookie("session_id", "", time() - 3600, "/");
header('Location: ../index.html');
exit;

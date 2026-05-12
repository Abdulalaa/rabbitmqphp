<?php
require_once(__DIR__.'/rabbitMQLib.inc');

function sendLog($errorMessage)
{
    try
    {
        $client = new rabbitMQClient(__DIR__."/rabbitMQ.ini", "Logger");
        $request = array(
            'type'      => "log_error",
            'timestamp' => date('Y-m-d H:i:s'),
            'machine'   => gethostname(),
            'error'     => $errorMessage,
        );
        $client->publish($request);
    }
    catch (Exception $e)
    {
        error_log("sendLog failed: " . $e->getMessage());
    }
}
?>

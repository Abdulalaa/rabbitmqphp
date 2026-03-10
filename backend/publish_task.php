<?php
// publish_task.php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// 1. Capture the JSON payload from the frontend (app.js)
$input = file_get_contents('php://input');
$taskData = json_decode($input, true);

if (!$taskData) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid payload"]);
    exit;
}

try {
    // 2. Connect to the RabbitMQ cluster hosted on your Ubuntu VM
    // Replace 'YOUR_UBUNTU_VM_IP' with the actual IP address of your VirtualBox VM
    $connection = new AMQPStreamConnection('YOUR_UBUNTU_VM_IP', 5672, 'test', 'test');
    $channel = $connection->channel();

    // 3. Declare the queue (must match the worker)
    $queueName = 'movie_tasks';
    $channel->queue_declare($queueName, false, true, false, false);

    // 4. Package and publish the message
    $msg = new AMQPMessage(
        json_encode($taskData),
        ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT] // Keep messages safe if RabbitMQ restarts
    );

    $channel->basic_publish($msg, '', $queueName);

    // 5. Clean up and respond to the frontend
    $channel->close();
    $connection->close();

    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Task queued"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "RabbitMQ Connection Failed: " . $e->getMessage()]);
}
?>

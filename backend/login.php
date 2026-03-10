<?php
// login.php (RabbitMQ RPC Client)

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class AuthRpcClient {
    private $connection;
    private $channel;
    private $callback_queue;
    private $response;
    private $corr_id;

    public function __construct() {
        // Connect to the RabbitMQ cluster
        // Replace '127.0.0.1' with your RabbitMQ server IP
        $this->connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest');
        $this->channel = $this->connection->channel();

        // Create an exclusive, temporary callback queue for the backend to send the response to
        list($this->callback_queue, ,) = $this->channel->queue_declare(
            "", false, false, true, false
        );

        $this->channel->basic_consume(
            $this->callback_queue, '', false, true, false, false,
            array($this, 'onResponse')
        );
    }

    public function onResponse($rep) {
        // Ensure the response matches the specific request we just sent
        if ($rep->get('correlation_id') == $this->corr_id) {
            $this->response = $rep->body;
        }
    }

    public function call($payload) {
        $this->response = null;
        $this->corr_id = uniqid(); // Generate a unique ID for this request

        $msg = new AMQPMessage(
            (string) $payload,
            array(
                'correlation_id' => $this->corr_id,
                'reply_to' => $this->callback_queue // Tell the worker where to send the reply
            )
        );

        // Publish to the authentication queue
        $this->channel->basic_publish($msg, '', 'auth_queue');

        // Wait until the worker sends the response back
        while (!$this->response) {
            $this->channel->wait();
        }

        return $this->response;
    }
}

// --- 1. Capture POST data from the frontend AJAX request ---
$type = $_POST['type'] ?? '';
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

// Handle logout instantly without hitting the message broker
if ($type === 'logout') {
    echo "Logout successful.";
    exit;
}

if (empty($username) || empty($password)) {
    echo "Error: Please fill in all fields.";
    exit;
}

// --- 2. Package the data for RabbitMQ ---
$requestPayload = json_encode([
    'type' => $type,
    'username' => $username,
    'password' => $password
]);

// --- 3. Send via RPC and echo the worker's reply ---
try {
    $authClient = new AuthRpcClient();
    $response = $authClient->call($requestPayload);
    
    // Output the response exactly as the frontend expects it (e.g., "Login success!")
    echo $response;
} catch (Exception $e) {
    echo "Error: Authentication service is temporarily unavailable.";
}
?>
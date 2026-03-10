<?php
// auth_worker.php (RabbitMQ RPC Server)

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// 1. Database Configuration
$dbHost = '127.0.0.1'; 
$dbUser = 'root';
$dbPass = 'your_password';
$dbName = 'movie_platform';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo " [x] Connected to MySQL Database.\n";
} catch (PDOException $e) {
    die(" [!] Database connection failed: " . $e->getMessage() . "\n");
}

// 2. RabbitMQ Configuration
$connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel->queue_declare('auth_queue', false, false, false, false);

echo " [x] Awaiting RPC authentication requests. To exit press CTRL+C\n";

// 3. The Callback Function
$callback = function ($req) use ($pdo) {
    $data = json_decode($req->body, true);
    $type = $data['type'] ?? '';
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    
    echo " [.] Processing '{$type}' request for user '{$username}'\n";
    $responseText = "";

    try {
        if ($type === 'register') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            
            if ($stmt->fetch()) {
                $responseText = "Error: Username is already taken.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $insertStmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
                
                if ($insertStmt->execute([':username' => $username, ':password' => $hashedPassword])) {
                    $responseText = "Registration success! You are now logged in.";
                } else {
                    $responseText = "Error: Could not register user.";
                }
            }
        } elseif ($type === 'login') {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $responseText = "Login success!";
            } else {
                $responseText = "Error: Invalid username or password.";
            }
        } else {
            $responseText = "Error: Unknown request type.";
        }
    } catch (Exception $e) {
        $responseText = "Error: Database failure during authentication.";
        echo " [!] DB Error: " . $e->getMessage() . "\n";
    }

    // 4. Package the response and send it back to the specific reply_to queue
    $msg = new AMQPMessage(
        (string) $responseText,
        array('correlation_id' => $req->get('correlation_id'))
    );

    $req->delivery_info['channel']->basic_publish(
        $msg,
        '',
        $req->get('reply_to')
    );

    // 5. Acknowledge the original request message so RabbitMQ removes it from auth_queue
    $req->ack();
};

// Only give the worker one message at a time
$channel->basic_qos(null, 1, null);
$channel->basic_consume('auth_queue', '', false, false, false, false, $callback);

while ($channel->is_open()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
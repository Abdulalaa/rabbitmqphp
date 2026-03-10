<?php
// worker.php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

// --- Database Configuration ---
$dbHost = '127.0.0.1'; // Update if your MySQL is on a different VM
$dbUser = 'root';
$dbPass = 'your_password';
$dbName = 'movie_platform';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// --- RabbitMQ Configuration ---
// Connect to the RabbitMQ cluster on your Ubuntu VM
$connection = new AMQPStreamConnection('YOUR_UBUNTU_VM_IP', 5672, 'test', 'test');
$channel = $connection->channel();

$queueName = 'movie_tasks';
$channel->queue_declare($queueName, false, true, false, false);

echo " [*] Worker is ready and waiting for messages. To exit press CTRL+C\n";

$callback = function ($msg) use ($pdo) {
    $data = json_decode($msg->body, true);
    $action = $data['action'] ?? null;
    
    echo " [x] Received task: " . $action . " for movie ID: " . $data['movie_id'] . "\n";

    try {
        if ($action === 'update_library') {
            // Insert or update the user's library status (seen, owned, watchlist)
            $stmt = $pdo->prepare("
                INSERT INTO user_library (user_id, tmdb_movie_id, status) 
                VALUES (:user_id, :movie_id, :status)
                ON DUPLICATE KEY UPDATE status = :status
            ");
            $stmt->execute([
                ':user_id' => $data['user_id'],
                ':movie_id' => $data['movie_id'],
                ':status' => $data['status']
            ]);
            echo " [✔] Library updated.\n";
        } 
        
        elseif ($action === 'submit_review') {
            // Insert a new rating and review
            $stmt = $pdo->prepare("
                INSERT INTO reviews (user_id, tmdb_movie_id, rating, review_text) 
                VALUES (:user_id, :movie_id, :rating, :review)
            ");
            $stmt->execute([
                ':user_id' => $data['user_id'],
                ':movie_id' => $data['movie_id'],
                ':rating' => $data['rating'],
                ':review' => $data['review']
            ]);
            echo " [✔] Review submitted.\n";
        }

        // Acknowledge the message so RabbitMQ knows it's complete and can remove it from the queue
        $msg->ack();

    } catch (Exception $e) {
        echo " [!] Error processing task: " . $e->getMessage() . "\n";
        // Do not ack the message here, so it gets re-queued if there's a temporary DB failure
    }
};

// Ensure fair dispatch: wait until the worker has processed and acknowledged the previous message
$channel->basic_qos(null, 1, null);
$channel->basic_consume($queueName, '', false, false, false, false, $callback);

while ($channel->is_open()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
<?php
require_once('get_host_info.inc');

class rabbitMQServer {
    private $machine = "";
    public  $BROKER_HOST;
    private $BROKER_PORT;
    private $USER;
    private $PASSWORD;
    private $VHOST;
    private $exchange;
    private $queue;
    private $routing_key = '*';
    private $exchange_type = "topic";

    function __construct($machine, $server = "rabbitMQ") {
        $this->machine = getHostInfo(array($machine));
        $this->BROKER_HOST   = $this->machine[$server]["BROKER_HOST"];
        $this->BROKER_PORT   = $this->machine[$server]["BROKER_PORT"];
        $this->USER     = $this->machine[$server]["USER"];
        $this->PASSWORD = $this->machine[$server]["PASSWORD"];
        $this->VHOST = $this->machine[$server]["VHOST"];
        $this->exchange = $this->machine[$server]["EXCHANGE"];
        $this->queue = $this->machine[$server]["QUEUE"];
    }

    function process_message($msg) {
        if ($msg->getRoutingKey() !== "*") return;
        $this->conn_queue->ack($msg->getDeliveryTag());

        try {
            if ($msg->getReplyTo()) {
                $payload = json_decode($msg->getBody(), true);
                $response = isset($this->callback) ? call_user_func($this->callback, $payload) : "No callback";

                $params = array('host'=>$this->BROKER_HOST, 'port'=>$this->BROKER_PORT, 'login'=>$this->USER, 'password'=>$this->PASSWORD, 'vhost'=>$this->VHOST);
                $conn = new AMQPConnection($params);
                $conn->connect();
                $channel = new AMQPChannel($conn);
                $exchange = new AMQPExchange($channel);
                $exchange->setName($this->exchange);
                
                // FIX: Use the specific reply_to queue as the routing key (Direct Routing)
                $exchange->publish(json_encode($response), $msg->getReplyTo(), AMQP_NOPARAM, array('correlation_id' => $msg->getCorrelationId()));
                
                $conn->disconnect(); // Clean up response connection
            }
        } catch(Exception $e) { echo "Server Error: " . $e->getMessage() . PHP_EOL; }
    }

    function process_requests($callback) {
        $this->callback = $callback;
        $params = array('host'=>$this->BROKER_HOST, 'port'=>$this->BROKER_PORT, 'login'=>$this->USER, 'password'=>$this->PASSWORD, 'vhost'=>$this->VHOST);
        $conn = new AMQPConnection($params);
        $conn->connect();
        $channel = new AMQPChannel($conn);
        $exchange = new AMQPExchange($channel);
        $exchange->setName($this->exchange);
        $this->conn_queue = new AMQPQueue($channel);
        $this->conn_queue->setName($this->queue);
        $this->conn_queue->bind($exchange->getName(), $this->routing_key);
        $this->conn_queue->consume(array($this, 'process_message'));
        while (count($channel->callbacks)) { $channel->wait(); }
    }
}

class rabbitMQClient {
    private $machine = "";
    private $response_queue = array();
    private $exchange;
    private $queue;

    function __construct($machine, $server = "rabbitMQ") {
        $this->machine = getHostInfo(array($machine));
        $this->BROKER_HOST = $this->machine[$server]["BROKER_HOST"];
        $this->BROKER_PORT = $this->machine[$server]["BROKER_PORT"];
        $this->USER = $this->machine[$server]["USER"];
        $this->PASSWORD = $this->machine[$server]["PASSWORD"];
        $this->VHOST = $this->machine[$server]["VHOST"];
        $this->exchange = $this->machine[$server]["EXCHANGE"];
        $this->queue = $this->machine[$server]["QUEUE"];
    }

    function process_response($response) {
        $uid = $response->getCorrelationId();
        if (!isset($this->response_queue[$uid])) return true;
        $this->conn_queue->ack($response->getDeliveryTag());
        $this->response_queue[$uid] = json_decode($response->getBody(), true);
        return false; // Stop consume loop
    }

    function send_request($message) {
        $uid = uniqid();
        $this->response_queue[$uid] = "waiting";
        $params = array('host'=>$this->BROKER_HOST, 'port'=>$this->BROKER_PORT, 'login'=>$this->USER, 'password'=>$this->PASSWORD, 'vhost'=>$this->VHOST);
        $conn = new AMQPConnection($params);
        $conn->connect();
        $channel = new AMQPChannel($conn);
        $exchange = new AMQPExchange($channel);
        $exchange->setName($this->exchange);

        $callback_queue = new AMQPQueue($channel);
        $callback_queue->setName($this->queue . "_resp_" . $uid);
        $callback_queue->setFlags(AMQP_AUTODELETE);
        $callback_queue->declare();
        

        $callback_queue->bind($exchange->getName(), $callback_queue->getName());

        $this->conn_queue = $callback_queue;
        $exchange->publish(json_encode($message), "*", AMQP_NOPARAM, array('reply_to' => $callback_queue->getName(), 'correlation_id' => $uid));
        
        $callback_queue->consume(array($this, 'process_response'));
        $response = $this->response_queue[$uid];
        unset($this->response_queue[$uid]);
        $conn->disconnect();
        return $response;
    }
}
?>
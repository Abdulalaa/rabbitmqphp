#!/usr/bin/php
<?php
require_once(__DIR__ . '/path.inc');

$LOG_FILE = '/var/log/system_errors.log';
$INI_PATH = __DIR__ . '/rabbitMQ.ini';

$ini = parse_ini_file($INI_PATH, true);
if ($ini === false || !isset($ini['Logger']))
{
    echo "FATAL: [Logger] section not found in $INI_PATH\n";
    exit(1);
}

$cfg      = $ini['Logger'];
$hostname = gethostname();
$queueName = $cfg['QUEUE'] . '_' . $hostname;

echo "Log Server starting on: $hostname\n";
echo "Queue : $queueName\n";
echo "Log   : $LOG_FILE\n";

file_put_contents(
    $LOG_FILE,
    "[" . date('Y-m-d H:i:s') . "] | Machine: $hostname | INFO: Log Server started\n",
    FILE_APPEND | LOCK_EX
);

$handleMessage = function(AMQPEnvelope $msg) use ($LOG_FILE, $hostname)
{
    $body = $msg->getBody();
    $data = json_decode($body, true);

    if ($data && isset($data['timestamp'], $data['machine'], $data['error']))
    {
        $line = "[{$data['timestamp']}] | Machine: {$data['machine']} | ERROR: {$data['error']}\n";
    }
    else
    {
        $line = "[" . date('Y-m-d H:i:s') . "] | Machine: $hostname | RAW: $body\n";
    }

    file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    echo $line;

    return true;
};

try
{
    $params = array(
        'host'     => trim($cfg['BROKER_HOST']),
        'port'     => (int) $cfg['BROKER_PORT'],
        'login'    => $cfg['USER'],
        'password' => $cfg['PASSWORD'],
        'vhost'    => $cfg['VHOST'],
    );

    $conn = new AMQPConnection($params);
    $conn->connect();

    $channel = new AMQPChannel($conn);

    $exchange = new AMQPExchange($channel);
    $exchange->setName($cfg['EXCHANGE']);
    $exchange->setType(AMQP_EX_TYPE_FANOUT);
    $exchange->setFlags(AMQP_DURABLE);
    $exchange->declare();

    $queue = new AMQPQueue($channel);
    $queue->setName($queueName);
    $queue->setFlags(AMQP_DURABLE);
    $queue->declare();

    $queue->bind($cfg['EXCHANGE'], '');

    echo "Listening for log messages...\n";

    $queue->consume($handleMessage, AMQP_AUTOACK);
}
catch (Exception $e)
{
    $errLine = "[" . date('Y-m-d H:i:s') . "] | Machine: $hostname | FATAL: logServer crashed: " . $e->getMessage() . "\n";
    file_put_contents($LOG_FILE, $errLine, FILE_APPEND | LOCK_EX);
    echo $errLine;
    exit(1);
}

echo "Log Server shut down.\n";
exit(0);
?>

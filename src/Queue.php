<?php
namespace UploaderBot;


use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Queue
{
    private $connection;
    private $channel;
    private $name;

    /**
     * @param $config
     * @param string $name
     */
    function __construct($config, $name)
    {
        $this->connection = new AMQPStreamConnection(
            $config['host'],
            $config['port'],
            $config['user'],
            $config['password']
        );
        $this->channel = $this->connection->channel();
        $this->name = $name;
        $this->channel->queue_declare($name, false, false, false, false);
    }

    /**
     *
     */
    function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }

    /**
     * @param mixed $param
     */
    public function enqueue($param)
    {
        $this->channel->basic_publish(
            new AMQPMessage($param),
            '',
            $this->name
        );
    }

    /**
     * @param $callback
     * @param $limit
     */
    public function dequeue($callback, $limit)
    {
        $limit = (int)$limit;
        $counter = 0;

        $c = function ($message) use ($callback, &$counter, $limit) {
            $result = false;

            if ($limit == 0 || $counter < $limit) {
                $callback($message->body);
                $counter++;
                $result = true;
            }

            return $result;
        };

        while ($message = $this->channel->basic_get($this->name, true)) {
            if (!$c($message)) {
                break;
            }
        }
    }

    /**
     * @return int
     */
    public function size()
    {
        $counter = 0;
        while ($this->channel->basic_get($this->name, false)) {
            $counter++;
        }
        return $counter;
    }
} 
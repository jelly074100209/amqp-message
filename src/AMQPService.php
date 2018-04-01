<?php
/**
 * Created by PhpStorm.
 * User: jss
 * Date: 18-3-31
 * Time: 下午9:28
 */

namespace AmqpMessage;


use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class AMQPService {

    /** @var string $host 主机地址 */
    protected $host;

    /** @var integer $port 端口 */
    protected $port;

    /** @var string $username username */
    protected $username;

    /** @var string $password password */
    protected $password;

    /** @var string $virtual_host v_host of broker */
    protected $virtual_host;

    /** @var string $exchange_name */
    protected $exchange_name;

    /** @var string $queue_name */
    protected $queue_name;

    /** @var string $routing_key */
    protected $routing_key;

    /** @var AMQPStreamConnection $connection */
    protected $connection;

    /** @var AMQPChannel $channel */
    protected $channel;

    /** @var array $exchange_params */
    protected $exchange_params;

    /** @var array $queue_params */
    protected $queue_params;

    /** @var string $mode topic|queue */
    protected $mode;


    public function __construct($config = []) {
        //设置主机信息
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->username = $config['username'];

        //设置交换机-路由键-队列映射
        $this->virtual_host = $config['virtual_host'];
        $this->exchange_name = $config['exchange_name'];
        $this->routing_key = $config['routing_key'];
        $this->queue_name = $config['queue_name'];

        //设置交换机，队列参数
        $this->exchange_params = $config['exchange_params'];
        $this->queue_params = $config['queue_params'];

        //交换机默认topic mode
        $this->mode = $config['mode'] ?: 'topic';

        //connect to broker
        $this->connection();
    }


    public function publish($message, $delay = 0) {
        if ($delay) {
            $delay = intval($delay) * 1000;
            $delay_exchange_name = $this->exchange_name . '.' . $delay;
            $delay_queue_name = $this->queue_name . '.' . $delay;

            $dead_exchange_name = $this->exchange_name . '.dead';
            $this->channel->exchange_declare($dead_exchange_name, $this->mode, false, true, false);
            $this->channel->exchange_declare($delay_exchange_name, $this->mode, false, true, false);

            $table = new AMQPTable();
            $table->set('x-dead-letter-exchange', $dead_exchange_name);
            $table->set('x-dead-letter-routing-key', $this->routing_key);
            $table->set('x-message-ttl', $delay);
            $this->channel->queue_declare($delay_queue_name, false, true, false, false, false, $table);
            $this->channel->queue_bind($delay_queue_name, $delay_exchange_name, $this->routing_key);

            $this->channel->queue_declare($this->queue_name, false, true, false, false, false);
            $this->channel->queue_bind($this->queue_name, $dead_exchange_name, $this->routing_key);

            $message = new AMQPMessage($message, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
            $this->channel->basic_publish($message, $delay_exchange_name);

        } else {
            $this->channel->queue_declare($this->queue_name, false, true, false, false);
            $message = new AMQPMessage($message, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

            $this->channel->basic_publish($message, $this->exchange_name, $this->queue_name);
        }
    }

    public function consume($delay = 0) {
        if ($delay) {
            $delay_exchange_name = $this->exchange_name . '.' . $delay;
            $this->channel->exchange_declare($delay_exchange_name, $this->mode, false, true, false);

            $dead_exchange_name = $this->exchange_name . '.dead';
            $this->channel->exchange_declare($dead_exchange_name, $this->mode, false, true, false);

            $this->channel->queue_declare($this->queue_name, false, true, false, false);
            $this->channel->queue_bind($this->queue_name, $dead_exchange_name, $this->routing_key);

        } else {
            $this->channel->queue_declare($this->queue_name, false, true, false, false);
        }
        //callback
        $callback = function (AMQPMessage $message) {
            $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
        };

        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume($this->queue_name, '', false, false, false, false, $callback);

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    private function connection() {
        $this->connection = new AMQPStreamConnection(
            $this->host,
            $this->port,
            $this->username,
            $this->password,
            $this->virtual_host,
            false,
            'AMQPLAIN',
            null,
            'en_US',
            3.0,
            61.0,
            null,
            false,
            5
        );
        $this->channel = $this->connection->channel();
    }

    public function __destruct() {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
    }
}
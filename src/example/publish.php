<?php
/**
 * Created by PhpStorm.
 * User: jss
 * Date: 18-4-2
 * Time: 上午9:52
 */

$config = [
    'host' => '192.168.1.99',
    'port' => 15672,
    'username' => 'guest',
    'password' => 'guest',

    'virtual_host' => '/',
    'exchange_name' => 'exchange_name_test',
    'routing_key' => 'routing_key_test',
    'queue_name' => 'queue_name_test',

    'exchange_params' => [

    ],
    'queue_params' => [

    ],
    'mode' => 'topic'
];

$publisher = new \AmqpMessage\AMQPService();
$message = [
    'header' => [
        'version' => "4.0",
    ],
    'body' => [
        "name" => "hello"
    ]
];
$publisher->publish($message);

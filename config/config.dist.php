<?php
namespace Imbo\AmqpPublisher;

return [
    'eventListeners' => [
        'amqp-publisher' => [
            'listener' => new AmqpPublisher([
                // Note: All of these are the default parameters - anything defined here will be
                // merged with the default values listed below. In other words, you only need to
                // define the values that differ for your setup.
                'connection' => [
                    'host'     => 'localhost',
                    'port'     => 5672,
                    'user'     => 'guest',
                    'password' => 'guest',
                    'vhost'    => '/',
                ],

                'exchange' => [
                    'name'       => 'imbo',
                    'type'       => 'fanout',
                    'passive'    => false,
                    'durable'    => false,
                    'autoDelete' => false,
                ],

                // Note: Queue settings will only be used if the exchange type is not `fanout`
                'queue' => [
                    'name'       => false,
                    'passive'    => false,
                    'durable'    => true,
                    'exclusive'  => false,
                    'autoDelete' => false,
                    'routingKey' => '',
                ],

                // Which events should be published to the queue? A wildcard character (`*`)
                // present in this array will publish all "resource events". To change this,
                // simply set the array to contain the events you want to publish, for instance
                // ['metadata.put', 'metadata.post', 'metadata.delete', 'images.post']
                'events' => ['*'],
            ])
        ],
    ],
];

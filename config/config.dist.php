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

                /**
                 * Which events should be published to the queue? By default, it will publish all
                 * user-level write events. This prevents us from spamming the queue with all
                 * image requests, for instance.
                 *
                 * A wildcard character (`*`) in this array will publish all "resource events",
                 * but note that this could potentially trigger *a lot* of messages, as well as
                 * potentially exposing sensitive information to the queue (key pairs added through
                 * the API, for instance).
                 */
                'events' => [
                    'image.delete',
                    'images.post',
                    'metadata.put',
                    'metadata.post',
                    'metadata.delete',
                    'shorturl.delete',
                    'shorturls.post',
                    'shorturls.delete',
                ],
            ])
        ],
    ],
];

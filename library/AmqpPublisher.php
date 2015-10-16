<?php

namespace Imbo\AmqpPublisher;

use Imbo\EventListener\ListenerInterface,
    Imbo\EventManager\EventInterface,
    Imbo\Exception\InvalidArgumentException,
    Imbo\Model\Image as ImageModel,
    Imbo\Http\Request\Request,
    Imbo\Http\Response\Response,
    PhpAmqpLib\Connection\AMQPStreamConnection,
    PhpAmqpLib\Message\AMQPMessage,
    DateTime;

/**
 * AMQP Publisher event listener
 *
 * @author Espen Hovlandsdal <espen@hovlandsdal.com>
 */
class AmqpPublisher implements ListenerInterface {
    /**
     * Delivery modes (duplicated here because AmqpLib doesn't have them in a released version yet)
     */
    const DELIVERY_MODE_NON_PERSISTENT = 1;
    const DELIVERY_MODE_PERSISTENT = 2;

    /**
     * Events that this listener should publish to the queue
     *
     * @var array
     */
    protected $events = ['*'];

    /**
     * Connection configuration
     *
     * @var array
     */
    protected $connectionConfig = [
        'host'     => 'localhost',
        'port'     => 5672,
        'user'     => 'guest',
        'password' => 'guest',
        'vhost'    => '/',
    ];

    /**
     * Exchange configuration
     *
     * @var array
     */
    protected $exchangeConfig = [
        'name'       => 'imbo',
        'type'       => 'fanout',
        'passive'    => false,
        'durable'    => false,
        'autoDelete' => false,
    ];

    /**
     * Queue configuration (only used if exchange is not of type `fanout`)
     *
     * @var array
     */
    protected $queueConfig = [
        'name'       => false,
        'passive'    => false,
        'durable'    => true,
        'exclusive'  => false,
        'autoDelete' => false,
        'routingKey' => '',
    ];

    /**
     * AMQP connection
     *
     * @var AMQPStreamConnection
     */
    protected $connection;

    /**
     * AMQP channel
     *
     * @var AMQPChannel
     */
    protected $channel;

    /**
     * Whether we have a declared queue we need to assert and publish to or not
     *
     * @var boolean
     */
    protected $declaredQueue = false;

    /**
     * Whether the publisher should use persistent delivery mode or not
     * (`true` if queue or exchange is declared as durable, false otherwise)
     *
     * @var boolean
     */
    protected $persistentDelivery = false;

    /**
     * Constructor
     *
     * @param array $params
     */
    public function __construct($params = null) {
        $this->initConfiguration($params ?: []);
    }

    /**
     * Get the events this listener subscribes to
     *
     * @return array
     */
    public static function getSubscribedEvents() {
        return [
            'response.send' => ['publishEvent' => -100000],
        ];
    }

    /**
     * Publish an event
     *
     * @param Imbo\EventManager\EventInterface $event The current event
     */
    public function publishEvent(EventInterface $event) {
        $request = $event->getRequest();
        $routeName = (string) $request->getRoute();
        $methodName = strtolower($request->getMethod());
        $eventName = $routeName . '.' . $methodName;

        // Don't publish for messages we don't subscribe to
        if (!in_array('*', $this->events) && !in_array($eventName, $this->events)) {
            return;
        }

        // Collect data and construct the message body
        $body = $this->constructMessageBody(
            $eventName,
            $event->getRequest(),
            $event->getResponse(),
            $event
        );

        // Construct the message
        $msg = new AMQPMessage(json_encode($body), [
            'content_type'  => 'application/json',
            'delivery_mode' => (
                $this->persistentDelivery ?
                self::DELIVERY_MODE_PERSISTENT :
                self::DELIVERY_MODE_NON_PERSISTENT
            ),
        ]);

        $this->initConnection();
        $this->channel->basic_publish($msg, $this->exchangeConfig['name']);
        $this->closeConnections();
    }

    /**
     * Construct a message body with the data we need
     *
     * @param string $eventName Event that was triggered
     * @param Request $request Request that triggered this event
     * @param Response $response Response for this request
     * @param Imbo\EventManager\EventInterface $eventName Current event
     * @return array
     */
    public function constructMessageBody($eventName, Request $request, Response $response, EventInterface $event) {
        // Construct the basics
        $message = [
            'eventName' => $eventName,

            'request'   => [
                'date'      => date('c'),
                'user'      => $request->getUser(),
                'publicKey' => $request->getPublicKey(),
                'extension' => $request->getExtension(),
                'url'       => $request->getRawUri(),
                'clientIp'  => $request->getClientIp(),
            ],

            'response' => [
                'statusCode' => $response->getStatusCode(),
            ],
        ];

        // Include any JSON request body in the message
        if ($request->getContentType() === 'application/json') {
            $message['request']['body'] = $request->getContent();
        }

        // See if we've got an image identifier for this request
        $imageIdentifier = $request->getImageIdentifier();

        // The imageIdentifier was not part of the URL, see if we have it in the response model
        if (!$imageIdentifier) {
            $responseData = $response->getModel()->getData();

            if (isset($responseData['imageIdentifier'])) {
                $imageIdentifier = $responseData['imageIdentifier'];
            } else {
                return $message;
            }
        }

        // Get image information
        $image = $this->getImageData($event, $imageIdentifier);

        // Construct an array of all the image information we have
        $message['image'] = [
            'identifier' => $imageIdentifier,
            'size'       => $image->getFilesize(),
            'extension'  => $image->getExtension(),
            'mime'       => $image->getMimeType(),
            'added'      => $image->getAddedDate()->format(DateTime::ATOM),
            'updated'    => $image->getUpdatedDate()->format(DateTime::ATOM),
            'width'      => $image->getWidth(),
            'height'     => $image->getHeight(),
            'metadata'   => $image->getMetadata(),
        ];

        return $message;
    }

    /**
     * Close the channel and connections to AMQP
     *
     * @return void
     */
    public function closeConnections() {
        if ($this->channel) {
            $this->channel->close();
        }

        if ($this->connection) {
            $this->connection->close();
        }
    }

    /**
     * Initialize and validate the passed configuration parameters
     *
     * @param  array $params
     * @return void
     */
    protected function initConfiguration($params) {
        if (isset($params['events'])) {
            if (!is_array($params['events'])) {
                throw new InvalidArgumentException('`events` must be an array of events', 500);
            }

            $this->events = $params['events'];
        }

        if (isset($params['connection'])) {
            if (!is_array($params['connection'])) {
                throw new InvalidArgumentException('`connection` must be an array of configuration details', 500);
            }

            $this->connectionConfig = array_merge($this->connectionConfig, $params['connection']);
        }

        if (isset($params['exchange'])) {
            if (is_string($params['exchange'])) {
                $params['exchange'] = ['name' => $params['exchange']];
            } else if (!is_array($params['exchange'])) {
                throw new InvalidArgumentException('`exchange` must be an array of configuration details', 500);
            }

            $this->exchangeConfig = array_merge($this->exchangeConfig, $params['exchange']);
        }

        if (isset($params['queue'])) {
            if ($this->exchangeConfig['type'] === 'fanout') {
                throw new InvalidArgumentException('Queue options should not be used when exchange type is `fanout`', 500);
            }

            if (is_string($params['queue'])) {
                $params['queue'] = ['name' => $params['queue']];
            } else if (!is_array($params['queue'])) {
                throw new InvalidArgumentException('`queue` must be an array of configuration details', 500);
            } else if (!is_string($params['queue'])) {
                throw new InvalidArgumentException('`queue.name` must be set to a valid string', 500);
            }

            $this->queueConfig = array_merge($this->queueConfig, $params['queue']);
        }

        $this->declaredQueue = $this->exchangeConfig['type'] !== 'fanout' && isset($params['queue']['name']);
    }

    /**
     * Initialize connection, queue and exchange
     *
     * @return void
     */
    protected function initConnection() {
        // Only initialize once
        if ($this->connection) {
            return;
        }

        $this->connection = new AMQPStreamConnection(
            $this->connectionConfig['host'],
            $this->connectionConfig['port'],
            $this->connectionConfig['user'],
            $this->connectionConfig['password'],
            $this->connectionConfig['vhost']
        );

        $this->channel = $this->connection->channel();

        if ($this->declaredQueue) {
            $this->channel->queue_declare(
                $this->queueConfig['name'],
                $this->queueConfig['passive'],
                $this->queueConfig['durable'],
                $this->queueConfig['exclusive'],
                $this->queueConfig['autoDelete']
            );
        }

        $this->channel->exchange_declare(
            $this->exchangeConfig['name'],
            $this->exchangeConfig['type'],
            $this->exchangeConfig['passive'],
            $this->exchangeConfig['durable'],
            $this->exchangeConfig['autoDelete']
        );

        if ($this->declaredQueue) {
            $this->channel->queue_bind(
                $this->queueConfig['name'],
                $this->exchangeConfig['name'],
                $this->queueConfig['routingKey']
            );
        }

        $this->persistentDelivery = (
            $this->exchangeConfig['durable'] ||
            $this->queueConfig['durable']
        );
    }

    /**
     * Get image data for a given image identifier
     *
     * @param Imbo\EventListener\ListenerInterface $event
     * @param array $imageIdentifier Image identifier of image to get data about
     * @return Imbo\Model\Image
     */
    protected function getImageData($event, $imageIdentifier) {
        $image = new ImageModel();

        $event->getDatabase()->load(
            $event->getRequest()->getUser(),
            $imageIdentifier,
            $image
        );

        // Get image metadata
        $metadata = $event->getDatabase()->getMetadata(
            $event->getRequest()->getUser(),
            $imageIdentifier
        );

        // Set image metadata on the image model
        $image->setMetadata($metadata);

        return $image;
    }
}

<?php
namespace ImboUnitTest\Plugin\AmqpPublisher;

use Imbo\Plugin\AmqpPublisher\EventListener;

/**
 * @covers Imbo\Plugin\AmqpPublisher\EventListener
 */
class EventListenerTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var EventListener
     */
    private $listener;

    /**
     * Set up the publisher
     */
    public function setUp() {
        $this->listener = new EventListener();
    }

    /**
     * @covers Imbo\Plugin\AmqpPublisher\EventListener::getSubscribedEvents
     */
    public function testSubscribesToTheCorrectEvent() {
        $events = EventListener::getSubscribedEvents();
        $this->assertInternalType('array', $events);
        $this->assertArrayHasKey('response.send', $events);
    }

    /**
     * @covers Imbo\Plugin\AmqpPublisher\EventListener::constructMessageBody
     */
    public function testCanConstructAMessageBody() {
        $eventName = 'image.get';
        $user = 'christer';
        $publicKey = 'publicKey';
        $extension = 'png';
        $url = 'http://someurl';
        $clientIp = '1.2.3.4';
        $requestBody = '{"foo":"bar"}';
        $imageIdentifier = 'image id';
        $metadata = [
            'some' => 'data',
            'foo' => [
                'bar' => 1,
            ],
        ];

        $statusCode = 200;

        $database = $this->getMock('Imbo\Database\DatabaseInterface');
        $database->expects($this->once())->method('load')->with($user, $imageIdentifier, $this->isInstanceOf('Imbo\Model\Image'));
        $database->expects($this->once())->method('getMetadata')->with($user, $imageIdentifier)->will($this->returnValue($metadata));

        $event = $this->getMock('Imbo\EventManager\Event');
        $event->expects($this->once())->method('getDatabase')->will($this->returnValue($database));
        $model = $this->getMock('Imbo\Model\Image');

        $request = $this->getMock('Imbo\Http\Request\Request');
        $request->expects($this->any())->method('getUser')->will($this->returnValue($user));
        $request->expects($this->once())->method('getPublicKey')->will($this->returnValue($publicKey));
        $request->expects($this->once())->method('getExtension')->will($this->returnValue($extension));
        $request->expects($this->once())->method('getRawUri')->will($this->returnValue($url));
        $request->expects($this->once())->method('getClientIp')->will($this->returnValue($clientIp));
        $request->expects($this->once())->method('getContentType')->will($this->returnValue('application/json'));
        $request->expects($this->once())->method('getContent')->will($this->returnValue($requestBody));
        $request->expects($this->once())->method('getImageIdentifier')->will($this->returnValue($imageIdentifier));

        $response = $this->getMock('Imbo\Http\Response\Response');
        $response->expects($this->any())->method('getModel')->will($this->returnValue($model));
        $response->expects($this->any())->method('getStatusCode')->will($this->returnValue($statusCode));

        $event->expects($this->any())->method('getRequest')->will($this->returnValue($request));
        $event->expects($this->any())->method('getResponse')->will($this->returnValue($response));

        $message = $this->listener->constructMessageBody($eventName, $event);

        $this->assertInternalType('array', $message);

        foreach (['eventName', 'request', 'response'] as $key) {
            $this->assertArrayHasKey($key, $message);
        }

        $this->assertSame($message['eventName'], $eventName);

        $this->assertSame($message['request']['user'], $user);
        $this->assertSame($message['request']['publicKey'], $publicKey);
        $this->assertSame($message['request']['extension'], $extension);
        $this->assertSame($message['request']['url'], $url);
        $this->assertSame($message['request']['clientIp'], $clientIp);
        $this->assertSame($message['request']['body'], $requestBody);

        $this->assertSame($message['response']['statusCode'], $statusCode);

        $this->assertSame($message['image']['imageIdentifier'], $imageIdentifier);
        $this->assertSame($message['image']['user'], $user);
        $this->assertSame($message['image']['metadata'], $metadata);
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     * @expectedExceptionMessage error message
     * @covers Imbo\Plugin\AmqpPublisher\EventListener::constructMessageBody
     */
    public function testWillTriggerErrorWhenConstructingAMessageWhenResponseHasAnErrorModel() {
        $model = $this->getMock('Imbo\Model\Error');
        $model->expects($this->once())->method('getErrorMessage')->will($this->returnValue('error message'));

        $response = $this->getMock('Imbo\Http\Response\Response');
        $response->expects($this->any())->method('getModel')->will($this->returnValue($model));

        $event = $this->getMock('Imbo\EventManager\EventInterface');
        $event->expects($this->once())->method('getResponse')->will($this->returnValue($response));

        $this->listener->constructMessageBody('image.get', $event);
    }

    /**
     * @covers Imbo\Plugin\AmqpPublisher\EventListener::constructMessageBody
     */
    public function testCanConstructAMessageBodyWithNoImage() {
        $eventName = 'status.get';

        $user = 'user';
        $publicKey = 'publicKey';
        $extension = 'png';
        $url = 'someurl';
        $ip = '127.0.0.1';

        $request = $this->getMock('Imbo\Http\Request\Request');
        $request->expects($this->once())->method('getUser')->will($this->returnValue($user));
        $request->expects($this->once())->method('getPublicKey')->will($this->returnValue($publicKey));
        $request->expects($this->once())->method('getExtension')->will($this->returnValue($extension));
        $request->expects($this->once())->method('getRawUri')->will($this->returnValue($url));
        $request->expects($this->once())->method('getClientIp')->will($this->returnValue($ip));
        $request->expects($this->once())->method('getImageIdentifier')->will($this->returnValue(null));

        $model = $this->getMock('Imbo\Model\Stats');
        $model->expects($this->once())->method('getData')->will($this->returnValue([]));

        $response = $this->getMock('Imbo\Http\Response\Response');
        $response->expects($this->any())->method('getModel')->will($this->returnValue($model));
        $response->expects($this->any())->method('getStatusCode')->will($this->returnValue(200));

        $event = $this->getMock('Imbo\EventManager\Event');
        $event->expects($this->any())->method('getRequest')->will($this->returnValue($request));
        $event->expects($this->any())->method('getResponse')->will($this->returnValue($response));

        $message = $this->listener->constructMessageBody($eventName, $event);

        $this->assertInternalType('array', $message);
        $this->assertArrayNotHasKey('image', $message);

        $this->assertSame($message['eventName'], $eventName);

        $this->assertSame($message['request']['user'], $user);
        $this->assertSame($message['request']['publicKey'], $publicKey);
        $this->assertSame($message['request']['extension'], $extension);
        $this->assertSame($message['request']['url'], $url);
        $this->assertSame($message['request']['clientIp'], $ip);

        $this->assertSame($message['response']['statusCode'], 200);
    }

    /**
     * @covers Imbo\Plugin\AmqpPublisher\EventListener::constructMessageBody
     */
    public function testCanConstructAMessageBodyWithImageIdentifierFoundInModel() {
        $eventName = 'images.post';

        $request = $this->getMock('Imbo\Http\Request\Request');
        $request->expects($this->any())->method('getUser')->will($this->returnValue('user'));
        $request->expects($this->once())->method('getPublicKey')->will($this->returnValue('publicKey'));
        $request->expects($this->once())->method('getImageIdentifier')->will($this->returnValue(null));

        $model = $this->getMock('Imbo\Model\Image');
        $model->expects($this->once())->method('getData')->will($this->returnValue(['imageIdentifier' => 'some id']));

        $response = $this->getMock('Imbo\Http\Response\Response');
        $response->expects($this->any())->method('getModel')->will($this->returnValue($model));

        $database = $this->getMock('Imbo\Database\DatabaseInterface');
        $database->expects($this->once())->method('load');
        $database->expects($this->once())->method('getMetadata')->will($this->returnValue([]));

        $event = $this->getMock('Imbo\EventManager\Event');
        $event->expects($this->any())->method('getRequest')->will($this->returnValue($request));
        $event->expects($this->any())->method('getResponse')->will($this->returnValue($response));
        $event->expects($this->any())->method('getDatabase')->will($this->returnValue($database));

        $message = $this->listener->constructMessageBody($eventName, $event);

        $this->assertInternalType('array', $message);
        $this->assertArrayHasKey('image', $message);

        $image = $message['image'];

        $this->assertSame('user', $image['user']);
        $this->assertSame('some id', $image['imageIdentifier']);
    }
}

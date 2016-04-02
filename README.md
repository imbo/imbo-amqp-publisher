[![Current build Status](https://secure.travis-ci.org/imbo/imbo-amqp-publisher.png)](http://travis-ci.org/imbo/imbo-amqp-publisher)

# Imbo AMQP publisher plugin
AMQP publisher plugin for Imbo that can be used to publish events to a queue.

## Installation
### Setting up the dependencies
If you've installed Imbo through composer, getting the AMQP publisher up and running is really simple. Simply add `imbo/imbo-amqp-publisher` as a dependency and run `composer update`.

```json
{
    "require": {
        "imbo/imbo-amqp-publisher": "dev-master",
    }
}
```

### Configuring Imbo
Once you've got the dependencies installed, you need to configure the plugin. An example configuration file can be found in `config/config.dist.php`. Simply copy the file to your Imbo `config` folder, adjust the parameters and name it `amqp-publisher.php` for instance. Imbo should pick it up automatically and start publishing to your configured AMQP host.

## A word of warning
Be careful not to have a consumer use the same resource as the messages it received a message from did. For example, if the consumer receives a message with the `image.get` event and then proceeds to load that image, this will trigger another `image.get` event to be fired, and you've got an infinite loop going.

You should also be careful about who has access to the AMQP server/queue, as the messages can *potentially* include sensitive information. On the same note, make sure to only subscribe to events you actually want to publish - for instance, the `keys.put` resource would expose private keys, which is not something you usually want.

# License
Copyright (c) 2015-2016, [Espen Hovlandsdal](mailto:espen@hovlandsdal.com), Licensed under the MIT License.

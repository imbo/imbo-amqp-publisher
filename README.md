[![Current build Status](https://secure.travis-ci.org/imbo/imbo-amqp-publisher.png)](http://travis-ci.org/imbo/imbo-amqp-publisher)

# imbo-amqp-publisher

AMQP plugin for Imbo, publishes events to a queue

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

Once you've got the dependencies installed, you need to configure the plugin. An example configuration file can be found in `config/config.dist.php`. Simply copy the file to your Imbo `config` folder, adjust the parameters and name it `amqp-publisher.php`, for instance. Imbo should pick it up automatically and start publishing to your configured AMQP host.

# License

Copyright (c) 2015, [Espen Hovlandsdal](mailto:espen@hovlandsdal.com)

Licensed under the MIT License

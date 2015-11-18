# INSTALLATION

net2rent PHPConnector requires no special installation steps. Simply download
the connector, extract it to the folder you would like to keep it in,
and add the library directory to your PHP `include_path` or use composer to
install and update the library (recommended).


## SYSTEM REQUIREMENTS
-------------------

net2rent PHPConnector requires PHP 5.3.3 or later.


### USING COMPOSER (recommended)
----------------------------
The recommended way to use the latest version of this project is to use `composer`
to install dependencies creating (or updating) the `composer.json` file:

    cd /myproject/dir
    echo '{
        "name": "mycompany/myproject",
        "description": "Your project desription",
        "minimum-stability": "dev",
        "homepage": "http://www.myproject.com/",
        "require": {
            "php": ">=5.3.3",
            "net2rent/php-connector": "dev-master"
        }
    }' > composer.json
    curl -s https://getcomposer.org/installer | php --
    php composer.phar install

Alternately, you can update your `composer.json` and update libraries:

    cd /myproject/dir
    php composer.phar self-update
    php composer.phar require net2rent/php-connector:dev-master

(The `self-update` directive is to ensure you have an up-to-date `composer.phar`
available.)


### USING LIBRARY
----------------------------

Once you have a copy of net2rent PHPconnector available, your application will
need to access the framework classes. Though there are several ways to
achieve this, your PHP `include_path` needs to contain the path to the
net2rent PHPconnector classes under the `/library` directory in this
distribution. You can find out more about the PHP `include_path`
configuration directive here:

http://www.php.net/manual/en/ini.core.php#ini.include-path

Instructions on how to change PHP configuration directives can be found
here:

http://www.php.net/manual/en/configuration.changes.php

Or you can do available with composer `autoload.php` script including it (recommended).


## GETTING STARTED

Once you have a copy of net2rent PHPconnector classes available to instantiate it,
you have to instantiate Connector class with the configuration provided by your net2rent
assesor.

    $connector = new Net2rent\Connector(array(
        'apiBaseUrl' => 'https://hub.net2rent.com',
        'apiUser' => 'user-of-net2rent',
        'apiPassword' => 'password-of-net2rent',
        'lg' => 'en'
    ));

And you are ready to use the public methods as:

    try {
        $properties = $connector->getProperties(array(
            'page_number'   => 1,
            'page_size'     => 15
        ));
    }
    catch(Exception $e) {
        switch($e->getCode()) {
            case 401:
                // handle forbidden error
                break;
            default:
                // handle generic error
        }
    }

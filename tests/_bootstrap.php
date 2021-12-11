<?php

use Codeception\Util\Autoload;

// This is global bootstrap for autoloading

error_reporting(-1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
setlocale(LC_ALL, 'en_US.utf-8');
date_default_timezone_set('UTC');

$root = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR;
defined('TESTS_PATH')   || define('TESTS_PATH', $root);

define("BASE_PATH", dirname(__DIR__));
define("VENDOR_DIR", BASE_PATH . '/vendor');

define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'testing');
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

echo "ENV: " . APPLICATION_ENV . PHP_EOL;
echo "DB_HOST: " . getenv('DB_HOST') . ":" . getenv('DB_PORT') . PHP_EOL;


Autoload::addNamespace(
    'Codeception\Module',
    BASE_PATH . '/src/Codeception/Module'
);
Autoload::addNamespace(
    'Codeception\Lib\Connector\Phalcon5',
    BASE_PATH . '/src/Codeception/Lib/Connector/Phalcon5'
);
Autoload::addNamespace(
    'App',
    BASE_PATH . '/tests/_data/App'
);

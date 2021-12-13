<?php

use Phalcon\DI\FactoryDefault;
use Phalcon\Mvc\Micro;

$di = new FactoryDefault();
return new Micro($di);

<?php

use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\DI\FactoryDefault;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Url as UrlProvider;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt;
use Phalcon\Session\Adapter\Stream;
use Phalcon\Session\Manager;

$di = new FactoryDefault();
$di->setShared(
    'session',
    function () {
        $session = new Manager();
        $files   = new Stream(
            [
                'savePath' => '/tmp',
            ]
        );
        $session->setAdapter($files);
        $session->start();

        return $session;
    }
);

$di->set(
    'db',
    function () {
        return new Mysql(
            [
                'host'     => getenv('DB_HOST'),
                'port'     => getenv('DB_PORT'),
                'username' => getenv('DB_USERNAME'),
                'password' => getenv('DB_PASSWORD'),
                'dbname'   => getenv('DB_NAME'),
            ]
        );
    }
);

/**
 * Setting the View
 */
$di->setShared('view', function () use ($di) {
    $view = new View();
//    $view->setViewsDir(BASE_PATH . '/_data/App/Views/');
//    $view->registerEngines(
//        [
//            ".volt"  => "voltService"
//        ]
//    );
//    $eventsManager = $di->get('eventsManager');
//    $eventsManager->attach('view', function ($event, $view) use ($di) {
//        /**
//         * @var \Phalcon\Events\Event $event
//         * @var \Phalcon\Mvc\View $view
//         */
//        if ($event->getType() == 'notFoundView') {
//            $message = sprintf('View not found - %s', $view->getActiveRenderPath());
//            throw new Exception($message);
//        }
//    });
//    $view->setEventsManager($eventsManager);
    return $view;
});

/**
 * Volt Service
 */
$di->set(
    'voltService',
    function ($view) use ($di) {
        $volt = new Volt($view, $di);

        $volt->setOptions(
            [
                'compiledPath'      => BASE_PATH . '/_output/compiled-templates/',
                'compiledExtension' => '.compiled',
            ]
        );

        return $volt;
    }
);

/**
 * The URL component is used to generate all kind of urls in the application
 */
$di->setShared('url', function () {
    $url = new UrlProvider();
    $url->setBaseUri('/');
    return $url;
});


$di->set('datetime', function () {
    return new DateTime();
});


$router = $di->getRouter();

$router->add('/', [
    'controller' => 'App\Controllers\Index',
    'action'     => 'index'
])
       ->setName('front.index')
;

$router->add('/datetime', [
    'controller' => 'App\Controllers\Datetime',
    'action'     => 'index'
])
       ->setName('front.datetime')
;

$router->add('/datetime/spl', [
    'controller' => 'App\Controllers\Datetime',
    'action'     => 'spl'
])
       ->setName('front.spl')
;


return new Application($di);

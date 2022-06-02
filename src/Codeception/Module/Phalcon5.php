<?php

declare(strict_types=1);

namespace Codeception\Module;

use Codeception\Configuration;
use Codeception\Exception\ModuleConfigException;
use Codeception\Exception\ModuleException;
use Codeception\Lib\Connector\Phalcon5 as PhalconConnector;
use Codeception\Lib\Connector\Phalcon5\MemorySession as MemorySession;
use Codeception\Lib\Connector\Phalcon5\SessionManager;
use Codeception\Lib\Framework;
use Codeception\Lib\Interfaces\ActiveRecord;
use Codeception\Lib\Interfaces\PartedModule;
use Codeception\TestInterface;
use Codeception\Util\ReflectionHelper;
use Exception;
use PDOException;
use Phalcon\Di\Di;
use Phalcon\Di\DiInterface;
use Phalcon\Di\Injectable;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Micro;
use Phalcon\Mvc\Model as PhalconModel;
use Phalcon\Mvc\Model\MessageInterface;
use Phalcon\Mvc\ResultsetInterface;
use Phalcon\Mvc\Router\RouteInterface;
use Phalcon\Mvc\RouterInterface;
use Phalcon\Mvc\Url;

/**
 * This module provides integration with [Phalcon framework](https://www.phalcon.io/) (4.x).
 * Please try it and leave your feedback.
 *
 * ## Status
 *
 * * Maintainer: **Ruud Boon**
 * * Stability: **stable**
 * * Contact: team@phalcon.io
 *
 * ## Config
 *
 * The following configurations are required for this module:
 *
 * * bootstrap: `string`, default `app/config/bootstrap.php` - relative path to app.php config file
 * * cleanup: `boolean`, default `true` - all database queries will be run in a transaction,
 *   which will be rolled back at the end of each test
 * * savepoints: `boolean`, default `true` - use savepoints to emulate nested transactions
 *
 * The application bootstrap file must return Application object but not call its handle() method.
 *
 * ## API
 *
 * * di - `Phalcon\Di\Injectable` instance
 * * client - `BrowserKit` client
 *
 * ## Parts
 *
 * By default all available methods are loaded, but you can specify parts to select only needed
 * actions and avoid conflicts.
 *
 * * `orm` - include only `haveRecord/grabRecord/seeRecord/dontSeeRecord` actions.
 * * `services` - allows to use `grabServiceFromContainer` and `addServiceToContainer`.
 *
 * See [WebDriver module](https://codeception.com/docs/modules/WebDriver#Loading-Parts-from-other-Modules)
 * for general information on how to load parts of a framework module.
 *
 * Usage example:
 *
 * Sample bootstrap (`app/config/bootstrap.php`):
 *
 * ``` php
 * <?php
 * $di = new \Phalcon\DI\FactoryDefault();
 * return new \Phalcon\Mvc\Application($di);
 * ?>
 * ```
 *
 * ```yaml
 * actor: AcceptanceTester
 * modules:
 *     enabled:
 *         - Phalcon5:
 *             part: services
 *             bootstrap: 'app/config/bootstrap.php'
 *             cleanup: true
 *             savepoints: true
 *         - WebDriver:
 *             url: http://your-url.com
 *             browser: phantomjs
 * ```
 */
class Phalcon5 extends Framework implements ActiveRecord, PartedModule
{
    /**
     * @var array
     */
    protected $config = [
        'bootstrap'  => 'app/config/bootstrap.php',
        'cleanup'    => true,
        'savepoints' => true,
        'session'    => MemorySession::class
    ];

    /**
     * Phalcon bootstrap file path
     */
    protected $bootstrapFile = null;

    /**
     * Dependency injection container
     *
     * @var DiInterface
     */
    public $di = null;

    /**
     * Phalcon Connector
     *
     * @var PhalconConnector
     */
    public $client;

    /**
     * HOOK: used after configuration is loaded
     *
     * @throws ModuleConfigException
     */
    public function _initialize()
    {
        $this->bootstrapFile = Configuration::projectDir() . $this->config['bootstrap'];

        if (!file_exists($this->bootstrapFile)) {
            throw new ModuleConfigException(
                __CLASS__,
                "Bootstrap file does not exist in " . $this->bootstrapFile . "\n"
                . "Please create the bootstrap file that returns Application object\n"
                . "And specify path to it with 'bootstrap' config\n\n"
                . "Sample bootstrap: \n\n<?php\n"
                . '$di = new \Phalcon\DI\FactoryDefault();' . "\n"
                . 'return new \Phalcon\Mvc\Application($di);'
            );
        }

        $this->client = new PhalconConnector();
    }

    /**
     * HOOK: before scenario
     *
     * @param TestInterface $test
     *
     * @throws ModuleException
     */
    public function _before(TestInterface $test)
    {
        /** @noinspection PhpIncludeInspection */
        $application = require $this->bootstrapFile;
        if (!$application instanceof Injectable) {
            throw new ModuleException(__CLASS__, 'Bootstrap must return \Phalcon\Di\Injectable object');
        }

        $this->di = $application->getDI();

        Di::reset();
        Di::setDefault($this->di);

        if ($this->di->has('session')) {
            /** @var Manager $manager */
            $manager = $this->di->get(SessionManager::class);
            $manager->setAdapter(
                $this->di->get($this->config['session'])
            );

            // Destroy existing sessions of previous tests
            $this->di['session'] = $manager;
        }

        if ($this->di->has('cookies')) {
            $this->di['cookies']->useEncryption(false);
        }

        if ($this->config['cleanup'] && $this->di->has('db')) {
            if ($this->config['savepoints']) {
                $this->di['db']->setNestedTransactionsWithSavepoints(true);
            }
            $this->di['db']->begin();
            $this->debugSection('Database', 'Transaction started');
        }
        $this->client->setApplication($application);
    }

    /**
     * HOOK: after scenario
     *
     * @param TestInterface $test
     */
    public function _after(TestInterface $test)
    {
        if ($this->config['cleanup'] && isset($this->di['db'])) {
            $db = $this->di['db'];
            while (($db) && $db->isUnderTransaction()) {
                $level = $db->getTransactionLevel();
                try {
                    $db->rollback(true);
                    $this->debugSection('Database', 'Transaction cancelled; all changes reverted.');
                } catch (PDOException $e) {
                }
                if ($level == $db->getTransactionLevel()) {
                    break;
                }
            }
            if ($db) {
                $db->close();
            }
        }
        $this->di = null;
        Di::reset();

        $_SESSION = $_FILES = $_GET = $_POST = $_COOKIE = $_REQUEST = [];
    }

    public function _parts()
    {
        return ['orm', 'services'];
    }

    /**
     * Provides access the Phalcon application object.
     *
     * @return Application|Micro
     * @see \Codeception\Lib\Connector\Phalcon::getApplication
     */
    public function getApplication()
    {
        return $this->client->getApplication();
    }

    /**
     * Sets value to session. Use for authorization.
     *
     * @param string $key
     * @param mixed  $val
     */
    public function haveInSession(string $key, $val): void
    {
        $this->di->get('session')
                 ->set($key, $val)
        ;
        $this->debugSection('Session', json_encode($this->di['session']->getAdapter()
                                                                       ->toArray()));
    }

    /**
     * Checks that session contains value.
     * If value is `null` checks that session has key.
     *
     * ``` php
     * <?php
     * $I->seeInSession('key');
     * $I->seeInSession('key', 'value');
     * ```
     *
     * @param string $key
     * @param mixed  $value
     */
    public function seeInSession(string $key, $value = null): void
    {
        $this->debugSection('Session', json_encode($this->di['session']->getAdapter()
                                                                       ->toArray()));

        if (is_array($key)) {
            $this->seeSessionHasValues($key);
            return;
        }

        if (!$this->di['session']->has($key)) {
            $this->fail("No session variable with key '$key'");
        }

        if (is_null($value)) {
            $this->assertTrue($this->di['session']->has($key));
        } else {
            $this->assertEquals($value, $this->di['session']->get($key));
        }
    }

    /**
     * Assert that the session has a given list of values.
     *
     * ``` php
     * <?php
     * $I->seeSessionHasValues(['key1', 'key2']);
     * $I->seeSessionHasValues(['key1' => 'value1', 'key2' => 'value2']);
     * ```
     *
     * @param array $bindings
     *
     * @return void
     */
    public function seeSessionHasValues(array $bindings): void
    {
        foreach ($bindings as $key => $value) {
            if (is_int($key)) {
                $this->seeInSession($value);
            } else {
                $this->seeInSession($key, $value);
            }
        }
    }

    /**
     * Inserts record into the database.
     *
     * ``` php
     * <?php
     * $user_id = $I->haveRecord('App\Models\Users', ['name' => 'Phalcon']);
     * $I->haveRecord('App\Models\Categories', ['name' => 'Testing']');
     * ```
     *
     * @param string $model      Model name
     * @param array  $attributes Model attributes
     *
     * @return mixed
     * @part orm
     */
    public function haveRecord($model, $attributes = [])
    {
        $record = $this->getModelRecord($model);
        $record->assign($attributes);
        $res   = $record->save();
        $field = function ($field) {
            if (is_array($field)) {
                return implode(', ', $field);
            }

            return $field;
        };

        if (!$res) {
            $messages = $record->getMessages();
            $errors   = [];
            foreach ($messages as $message) {
                /** @var MessageInterface $message */
                $errors[] = sprintf(
                    '[%s] %s: %s',
                    $message->getType(),
                    $field($message->getField()),
                    $message->getMessage()
                );
            }

            $this->fail(sprintf("Record %s was not saved. Messages: \n%s", $model, implode(PHP_EOL, $errors)));

            return null;
        }

        $this->debugSection($model, json_encode($record));

        return $this->getModelIdentity($record);
    }

    /**
     * Checks that record exists in database.
     *
     * ``` php
     * <?php
     * $I->seeRecord('App\Models\Categories', ['name' => 'Testing']);
     * ```
     *
     * @param string $model      Model name
     * @param array  $attributes Model attributes
     *
     * @part orm
     */
    public function seeRecord($model, $attributes = [])
    {
        $record = $this->findRecord($model, $attributes);
        if (!$record) {
            $this->fail("Couldn't find $model with " . json_encode($attributes));
        }
        $this->debugSection($model, json_encode($record));
    }

    /**
     * Checks that number of records exists in database.
     *
     * ``` php
     * <?php
     * $I->seeNumberOfRecords('App\Models\Categories', 3, ['name' => 'Testing']);
     * ```
     *
     * @param string $model      Model name
     * @param int    $number     int number of records
     * @param array  $attributes Model attributes
     *
     * @part orm
     */
    public function seeNumberOfRecords(string $model, int $number, array $attributes = []): void
    {
        $records = $this->findRecords($model, $attributes);
        if ($records->count() != $number) {
            $this->fail(sprintf(
                "Couldn't find %s records of %s with %s. Found: %s records.",
                $number,
                $model,
                json_encode($attributes),
                $records->count()
            ));
        }
        $this->debugSection($model, json_encode($records));
    }

    /**
     * Checks that record does not exist in database.
     *
     * ``` php
     * <?php
     * $I->dontSeeRecord('App\Models\Categories', ['name' => 'Testing']);
     * ```
     *
     * @param string $model      Model name
     * @param array  $attributes Model attributes
     *
     * @part orm
     */
    public function dontSeeRecord($model, $attributes = [])
    {
        $record = $this->findRecord($model, $attributes);
        $this->debugSection($model, json_encode($record));
        if ($record) {
            $this->fail("Unexpectedly managed to find $model with " . json_encode($attributes));
        }
    }

    /**
     * Retrieves record from database
     *
     * ``` php
     * <?php
     * $category = $I->grabRecord('App\Models\Categories', ['name' => 'Testing']);
     * ```
     *
     * @param string $model      Model name
     * @param array  $attributes Model attributes
     *
     * @return mixed
     * @part orm
     */
    public function grabRecord($model, $attributes = [])
    {
        return $this->findRecord($model, $attributes);
    }

    /**
     * Resolves the service based on its configuration from Phalcon's DI container
     * Recommended to use for unit testing.
     *
     * @param string $service    Service name
     * @param array  $parameters Parameters [Optional]
     *
     * @return mixed
     * @part services
     */
    public function grabServiceFromContainer(string $service, array $parameters = [])
    {
        if (!$this->di->has($service)) {
            $this->fail("Service $service is not available in container");
        }

        return $this->di->get($service, $parameters);
    }

    /**
     * Registers a service in the services container and resolve it. This record will be erased after the test.
     * Recommended to use for unit testing.
     *
     * ``` php
     * <?php
     * $filter = $I->addServiceToContainer('filter', ['className' => '\Phalcon\Filter']);
     * $filter = $I->addServiceToContainer('answer', function () {
     *      return rand(0, 1) ? 'Yes' : 'No';
     * }, true);
     * ```
     *
     * @param string  $name
     * @param mixed   $definition
     * @param boolean $shared
     *
     * @return mixed|null
     * @part services
     */
    public function addServiceToContainer(string $name, $definition, bool $shared = false)
    {
        try {
            $service = $this->di->set($name, $definition, $shared);
            return $service->resolve();
        } catch (Exception $e) {
            $this->fail($e->getMessage());

            return null;
        }
    }

    /**
     * Opens web page using route name and parameters.
     *
     * ``` php
     * <?php
     * $I->amOnRoute('posts.create');
     * ```
     *
     * @param string $routeName
     * @param array  $params
     */
    public function amOnRoute(string $routeName, array $params = []): void
    {
        if (!$this->di->has('url')) {
            $this->fail('Unable to resolve "url" service.');
        }

        /** @var Url $url */
        $url = $this->di->getShared('url');

        $urlParams = ['for' => $routeName];

        if ($params) {
            $urlParams += $params;
        }
        $this->amOnPage($url->get($urlParams, null, true));
    }

    /**
     * Checks that current url matches route
     *
     * ``` php
     * <?php
     * $I->seeCurrentRouteIs('posts.index');
     * ```
     *
     * @param string $routeName
     */
    public function seeCurrentRouteIs(string $routeName): void
    {
        if (!$this->di->has('url')) {
            $this->fail('Unable to resolve "url" service.');
        }

        /** @var Url $url */
        $url = $this->di->getShared('url');
        $this->seeCurrentUrlEquals($url->get(['for' => $routeName], null, true));
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param string $model      Model name
     * @param array  $attributes Model attributes
     *
     * @return PhalconModel
     */
    protected function findRecord(string $model, array $attributes = [])
    {
        $this->getModelRecord($model);
        $conditions = [];
        $bind       = [];
        foreach ($attributes as $key => $value) {
            if ($value === null) {
                $conditions[] = "[$key] IS NULL";
            } else {
                $conditions[] = "[$key] = :$key:";
                $bind[$key]   = $value;
            }
        }
        $query = implode(' AND ', $conditions);
        $this->debugSection('Query', $query);
        return call_user_func_array([$model, 'findFirst'], [
            [
                'conditions' => $query,
                'bind'       => $bind,
            ]
        ]);
    }

    /**
     * Allows to query the many records that match the specified conditions
     *
     * @param string $model      Model name
     * @param array  $attributes Model attributes
     *
     * @return ResultsetInterface
     */
    protected function findRecords(string $model, array $attributes = [])
    {
        $this->getModelRecord($model);
        $conditions = [];
        $bind       = [];
        foreach ($attributes as $key => $value) {
            if ($value === null) {
                $conditions[] = "[$key] IS NULL";
            } else {
                $conditions[] = "[$key] = :$key:";
                $bind[$key]   = $value;
            }
        }
        $query = implode(' AND ', $conditions);
        $this->debugSection('Query', $query);
        return call_user_func_array([$model, 'find'], [
            [
                'conditions' => $query,
                'bind'       => $bind,
            ]
        ]);
    }

    /**
     * Get Model Record
     *
     * @param $model
     *
     * @return PhalconModel
     * @throws ModuleException
     */
    protected function getModelRecord($model)
    {
        if (!class_exists($model)) {
            throw new ModuleException(__CLASS__, "Model $model does not exist");
        }

        $record = new $model();
        if (!$record instanceof PhalconModel) {
            throw new ModuleException(__CLASS__, "Model $model is not instance of \\Phalcon\\Mvc\\Model");
        }

        return $record;
    }

    /**
     * Get identity.
     *
     * @param PhalconModel $model
     *
     * @return mixed
     */
    protected function getModelIdentity(PhalconModel $model)
    {
        if (property_exists($model, 'id')) {
            return $model->id;
        }

        if (!$this->di->has('modelsMetadata')) {
            return null;
        }

        $primaryKeys = $this->di->get('modelsMetadata')
                                ->getPrimaryKeyAttributes($model);

        switch (count($primaryKeys)) {
            case 0:
                return null;
            case 1:
                $primaryKey = $primaryKeys[0];

                // if columnMap is used for model, map database column name to model property name
                $columnMap = $this->di->get('modelsMetadata')
                                      ->getColumnMap($model);
                if ($columnMap) {
                    $primaryKey = $columnMap[$primaryKey];
                }

                if (property_exists($model, $primaryKey)) {
                    return $model->{$primaryKey};
                }

                return null;

            default:
                return array_intersect_key(get_object_vars($model), array_flip($primaryKeys));
        }
    }

    /**
     * Returns a list of recognized domain names
     *
     * @return array
     */
    protected function getInternalDomains()
    {
        $internalDomains = [$this->getApplicationDomainRegex()];

        /** @var RouterInterface $router */
        $router = $this->di->get('router');

        if ($router instanceof RouterInterface) {
            /** @var RouteInterface[] $routes */
            $routes = $router->getRoutes();

            foreach ($routes as $route) {
                if ($route instanceof RouteInterface) {
                    $hostName = $route->getHostname();
                    if (!empty($hostName)) {
                        $internalDomains[] = '/^' . str_replace('.', '\.', $route->getHostname()) . '$/';
                    }
                }
            }
        }

        return array_unique($internalDomains);
    }

    private function getApplicationDomainRegex(): string
    {
        $server = ReflectionHelper::readPrivateProperty($this->client, 'server');
        $domain = $server['HTTP_HOST'];

        return '/^' . str_replace('.', '\.', $domain) . '$/';
    }
}

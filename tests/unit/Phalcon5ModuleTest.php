<?php

declare(strict_types=1);

use Codeception\Exception\ModuleConfigException;
use Codeception\Module\Phalcon5;
use Codeception\Test\Unit;
use Codeception\Util\Stub;

final class Phalcon5ModuleTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    protected function _setUp()
    {
    }

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    protected function getPhalconModule()
    {
        $container = Stub::make('Codeception\Lib\ModuleContainer');
        $module    = new Phalcon5($container);
        $module->_setConfig([
            'bootstrap'  => 'tests/_data/bootstrap.php',
            'cleanup'    => true,
            'savepoints' => true,
            'session'    => 'Codeception\Lib\Connector\Phalcon5\MemorySession'
        ]);
        $module->_initialize();
        return $module;
    }

    protected function getPhalconModuleMicro()
    {
        $container = Stub::make('Codeception\Lib\ModuleContainer');
        $module    = new Phalcon5($container);
        $module->_setConfig([
            'bootstrap'  => 'tests/_data/bootstrap-micro.php',
            'cleanup'    => true,
            'savepoints' => true,
            'session'    => PhalconConnector\MemorySession::class
        ]);
        $module->_initialize();
        return $module;
    }

    public function testConstruct()
    {
        $container = Stub::make('Codeception\Lib\ModuleContainer');
        $module    = new Phalcon5($container);
        $this->assertInstanceOf('Codeception\Module\Phalcon5', $module);
    }

    public function testInitialize()
    {
        $module = $this->getPhalconModule();
        $this->assertInstanceOf('Codeception\Lib\Connector\Phalcon5', $module->client);
    }

    public function testBefore()
    {
        $module = $this->getPhalconModule();
        $test   = new Codeception\Test\Unit();
        $module->_before($test);
        $this->assertInstanceOf('Phalcon\Di\Di', $module->di);
    }

    public function testAfter()
    {
        $module = $this->getPhalconModule();
        $test   = new Codeception\Test\Unit();
        $module->_before($test);
        $module->_after($test);
        $this->assertNull($module->di);
    }

    public function testParts()
    {
        $module = $this->getPhalconModule();
        $this->assertEquals(['orm', 'services'], $module->_parts());
    }

    public function testGetApplication()
    {
        $module = $this->getPhalconModule();
        $test   = new Codeception\Test\Unit();
        $module->_before($test);
        $this->assertInstanceOf('Phalcon\Mvc\Application', $module->getApplication());

        $module = $this->getPhalconModuleMicro();
        $test   = new Codeception\Test\Unit();
        $module->_before($test);
        $this->assertInstanceOf('Phalcon\Mvc\Micro', $module->getApplication());

        $module->_after($test);
    }

    public function testSession()
    {
        $module = $this->getPhalconModule();
        $test   = new Codeception\Test\Unit();
        $module->_before($test);
        $key   = "phalcon";
        $value = "Rocks!";
        $module->haveInSession($key, $value);
        $module->seeInSession($key, $value);
        $module->seeSessionHasValues([$key => $value]);
        $module->_after($test);
    }

    public function testRecords()
    {
        $module = $this->getPhalconModule();
        $test   = new Codeception\Test\Unit();
        $module->_before($test);

        $module->haveRecord('App\Models\Articles', ['title' => 'phalcon']);
        $module->seeRecord('App\Models\Articles', ['title' => 'phalcon']);
        $module->seeNumberOfRecords('App\Models\Articles', 1);
        $module->haveRecord('App\Models\Articles', ['title' => 'phalcon']);
        $module->seeNumberOfRecords('App\Models\Articles', 2);
        $module->dontSeeRecord('App\Models\Articles', ['title' => 'wordpress']);

        $record = $module->grabRecord('App\Models\Articles', ['title' => 'phalcon']);
        $this->assertInstanceOf('Phalcon\Mvc\Model', $record);

        $module->_after($test);
    }

    public function testContainerMethods()
    {
        $module = $this->getPhalconModule();
        $test   = new Codeception\Test\Unit();
        $module->_before($test);

        $session = $module->grabServiceFromContainer('session');
        $this->assertInstanceOf('Phalcon\Session\Manager', $session);
        $this->assertInstanceOf('Codeception\Lib\Connector\Phalcon5\MemorySession', $session->getAdapter());

        $testService = $module->addServiceToContainer('std', function () {
            return new stdClass();
        }, true);
        $this->assertInstanceOf('stdClass', $module->grabServiceFromContainer('std'));
        $this->assertInstanceOf('stdClass', $testService);
        $module->_after($test);
    }

    public function testReplaceService()
    {
        $module = $this->getPhalconModule();
        $test   = new Codeception\Test\Unit();
        $module->_before($test);
        $diHash = spl_object_hash($module->di);

        $datetime = $module->grabServiceFromContainer('datetime');
        $this->assertInstanceOf('DateTime', $datetime);
        $this->assertEquals($diHash, spl_object_hash($module->di));

        $std = $module->addServiceToContainer('datetime', function () {
            return new stdClass();
        }, false);
        $this->assertInstanceOf('stdClass', $std);
        $this->assertInstanceOf('stdClass', $module->grabServiceFromContainer('datetime'));
        $this->assertEquals($diHash, spl_object_hash($module->di));
        $module->amOnPage('/datetime/spl');
        $module->see($diHash);
        $module->amOnPage('/datetime');
        $module->see('class: stdClass');
        $module->_after($test);
    }

    public function testRoutes()
    {
        $module = $this->getPhalconModule();
        $test   = new Codeception\Test\Unit();
        $module->_before($test);

        $module->amOnRoute('front.index');
        $module->seeCurrentRouteIs('front.index');
        $module->_after($test);
    }
}

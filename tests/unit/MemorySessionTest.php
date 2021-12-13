<?php

declare(strict_types=1);

use Codeception\Lib\Connector\Phalcon5\MemorySession;
use Codeception\Test\Unit;
use Codeception\Util\Autoload;

final class MemorySessionTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    protected function _setUp()
    {
        Autoload::addNamespace(
            'Codeception\Lib\Connector\Phalcon5',
            BASE_PATH . '/src/Codeception/Lib/Connector/Phalcon5'
        );
    }

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    public function testConstruct()
    {
        $session = new MemorySession();
        $this->assertInstanceOf('SessionHandlerInterface', $session);
        $this->assertNotEmpty($session->getId());
    }


    public function testStart()
    {
        $session = new MemorySession();
        $this->assertTrue($session->start());
        $this->assertEquals(PHP_SESSION_ACTIVE, $session->status());

        //Should already be started
        $this->assertFalse($session->start());
    }

    public function testSetOptions()
    {
        $session = new MemorySession();
        $data    = [
            'uniqueId' => "test",
            'name'     => "Ruud",
        ];
        $session->setOptions($data);
        $this->assertEquals($data, $session->getOptions());
        $this->assertEquals($data['uniqueId'], $session->getId());
    }

    public function testName()
    {
        $session = new MemorySession();
        $name    = "phalcon";
        $session->setName($name);
        $this->assertEquals($name, $session->getName());
    }

    public function testSetters()
    {
        $session = new MemorySession();
        $session->start();
        $user          = "phalcon";
        $session->user = $user;
        $this->assertEquals($user, $session->user);
        $session->destroy($session->getId());
        $this->assertNull($session->user);
    }
}

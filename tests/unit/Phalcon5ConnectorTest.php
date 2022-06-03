<?php

declare(strict_types=1);

use Codeception\Lib\Connector\Phalcon5 as PhalconConnector;
use Codeception\Module\Phalcon5;
use Codeception\Test\Unit;
use Codeception\Stub;
use Symfony\Component\BrowserKit\Request;

final class Phalcon5ConnectorTest extends Unit
{
    protected function getPhalconModule(): Phalcon5
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

    public function testConstruct(): void
    {
        $connector = new PhalconConnector();
        $this->assertInstanceOf(PhalconConnector::class, $connector);
    }

    public function testDoRequest(): void
    {
        $module = $this->getPhalconModule();
        $test   = new Codeception\Test\Unit();
        $module->_before($test);

        $connector = $module->client;

        // parameters for Request object
        $uri     = '/';
        $method  = 'GET';
        $params  = [
            'first'  => 'one',
            'second' => 'two'
        ];
        $files   = [
            'file' => [
                'name'     => 'SomeFile.ext',
                'tmp_name' => 'SomeFile.ext',
                'error'    => false
            ]
        ];
        $cookies = [
            'token' => 'asdev257'
        ];
        $server  = [
            'HTTP_HOST'   => 'localhost',
            'SERVER_NAME' => 'my pc',
            'SERVER_ADDR' => '127.0.0.1',
        ];
        $content = "this is request content";

        $request = new Request(
            $uri,
            $method,
            $params,
            $files,
            $cookies,
            $server,
            $content
        );

        // send request
        $response = $connector->doRequest($request);
        $this->assertSame(200, $response->getStatusCode());

        /** @var Phalcon\Http\Request $requestService */
        $requestService = $module->grabServiceFromContainer('request');

        // assert request uri
        $this->assertSame($uri, $requestService->getURI());

        // assert request method
        $this->assertSame($method, $requestService->getMethod());

        // assert reques paramters
        $this->assertSame($params['first'], $requestService->get('first'));
        $this->assertSame($params['second'], $requestService->get('second'));

        // assert uploaded file
        $this->assertTrue($requestService->hasFiles());
        /** @var Phalcon\Http\Request\File $uploadedFile */
        $uploadedFile = $requestService->getUploadedFiles()[0];
        $this->assertSame($files['file']['name'], $uploadedFile->getName());
        $this->assertSame($files['file']['tmp_name'], $uploadedFile->getTempName());
        $this->assertSame('ext', $uploadedFile->getExtension());

        // assert server parameter
        $this->assertSame($server['HTTP_HOST'], $requestService->getServer('HTTP_HOST'));

        // assert request body
        $this->assertSame($content, $requestService->getRawBody());

        $module->_after($test);
    }
}

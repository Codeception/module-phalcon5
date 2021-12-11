<?php

declare(strict_types=1);

namespace Codeception\Lib\Connector;

use Closure;
use Codeception\Lib\Connector\Shared\PhpSuperGlobalsConverter;
use Codeception\Util\Stub;
use Phalcon\Di\Di;
use Phalcon\Http;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Micro as MicroApplication;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;

class Phalcon5 extends AbstractBrowser
{
    use PhpSuperGlobalsConverter;

    /**
     * Phalcon Application
     * @var mixed
     */
    private $application;

    /**
     * Set Phalcon Application by \Phalcon\DI\Injectable, Closure or bootstrap file path
     *
     * @param mixed $application
     */
    public function setApplication($application): void
    {
        $this->application = $application;
    }

    /**
     * Get Phalcon Application
     *
     * @return Application|MicroApplication
     */
    public function getApplication()
    {
        $application = $this->application;
        if ($application instanceof Closure) {
            return $application();
        }

        if (is_string($application)) {
            /** @noinspection PhpIncludeInspection */
            return require $application;
        }

        return $application;
    }

    /**
     * Makes a request.
     *
     * @param Request $request
     *
     * @throws RuntimeException|ReflectionException
     */
    public function doRequest($request): Response
    {
        $application = $this->getApplication();
        if (!$application instanceof Application && !$application instanceof MicroApplication) {
            throw new RuntimeException('Unsupported application class.');
        }

        $di = $application->getDI();
        /** @var Http\Request $phRequest */
        if ($di->has('request')) {
            $phRequest = $di->get('request');
        }

        if (!$phRequest instanceof Http\RequestInterface) {
            $phRequest = new Http\Request();
        }

        $uri         = $request->getUri() ?: $phRequest->getURI();
        $pathString  = parse_url($uri, PHP_URL_PATH);
        $queryString = parse_url($uri, PHP_URL_QUERY);

        $_SERVER = $request->getServer();
        $_SERVER['REQUEST_METHOD'] = strtoupper($request->getMethod());
        $_SERVER['REQUEST_URI'] = null === $queryString ? $pathString : $pathString . '?' . $queryString;

        $_COOKIE  = $request->getCookies();
        $_FILES   = $this->remapFiles($request->getFiles());
        $_REQUEST = $this->remapRequestParameters($request->getParameters());
        $_POST    = [];
        $_GET     = [];

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = $_REQUEST;
        } else {
            $_POST = $_REQUEST;
        }

        parse_str((string) $queryString, $output);
        foreach ($output as $k => $v) {
            $_GET[$k] = $v;
        }

        $_GET['_url']            = $pathString;
        $_SERVER['QUERY_STRING'] = http_build_query($_GET);

        Di::reset();
        Di::setDefault($di);

        if ($di->has('request')) {
            $di->remove('request');
        }
        $di['request'] = Stub::construct($phRequest, [], ['getRawBody' => $request->getContent()]);

        $response = $application->handle($pathString);
        if (!$response instanceof Http\ResponseInterface) {
            $response = $application->response;
        }

        $headers = $response->getHeaders();
        $status = (int) $headers->get('Status');

        $headersProperty = new ReflectionProperty($headers, 'headers');
        $headersProperty->setAccessible(true);

        $headers = $headersProperty->getValue($headers);
        if (!is_array($headers)) {
            $headers = [];
        }

        $cookiesProperty = new ReflectionProperty($di['cookies'], 'cookies');

        $cookiesProperty->setAccessible(true);
        $cookies = $cookiesProperty->getValue($di['cookies']);
        if (is_array($cookies)) {
            $restoredProperty = new ReflectionProperty('\Phalcon\Http\Cookie', 'restored');
            $restoredProperty->setAccessible(true);
            $valueProperty = new ReflectionProperty('\Phalcon\Http\Cookie', 'value');
            $valueProperty->setAccessible(true);
            foreach ($cookies as $name => $cookie) {
                if (!$restoredProperty->getValue($cookie)) {
                    $clientCookie = new Cookie(
                        $name,
                        $valueProperty->getValue($cookie),
                        (string)$cookie->getExpiration(),
                        $cookie->getPath(),
                        $cookie->getDomain(),
                        $cookie->getSecure(),
                        $cookie->getHttpOnly()
                    );
                    $headers['Set-Cookie'][] = (string)$clientCookie;
                }
            }
        }

        return new Response(
            $response->getContent() ?: '',
            $status !== 0 ? $status : 200,
            $headers
        );
    }
}

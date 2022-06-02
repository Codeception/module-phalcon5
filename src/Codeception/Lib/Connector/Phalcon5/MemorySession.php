<?php

declare(strict_types=1);

namespace Codeception\Lib\Connector\Phalcon5;

use Phalcon\Session\Adapter\AbstractAdapter;
use SessionHandlerInterface;

class MemorySession extends AbstractAdapter
{
    /**
     * @var string
     */
    protected string $sessionId;

    /**
     * @var string
     */
    protected string $name;

    /**
     * @var bool
     */
    protected bool $started = false;

    /**
     * @var array
     */
    protected array $memory = [];

    /**
     * @var array
     */
    protected array $options = [];

    public function __construct(array $options = null)
    {
        $this->sessionId = $this->generateId();

        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * @inheritdoc
     */
    public function start(): bool
    {
        if ($this->status() !== PHP_SESSION_ACTIVE) {
            $this->memory  = [];
            $this->started = true;

            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     *
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        if (isset($options['uniqueId'])) {
            $this->sessionId = $options['uniqueId'];
        }

        $this->options = $options;
    }

    /**
     * @inheritdoc
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @inheritdoc
     *
     * @param string $index
     * @param mixed  $defaultValue
     * @param bool   $remove
     *
     * @return mixed
     */
    public function get(string $index, $defaultValue = null, bool $remove = false)
    {
        $key = $this->prepareIndex($index);

        if (!isset($this->memory[$key])) {
            return $defaultValue;
        }

        $return = $this->memory[$key];

        if ($remove) {
            unset($this->memory[$key]);
        }

        return $return;
    }

    /**
     * @inheritdoc
     *
     * @param string $index
     * @param mixed  $value
     */
    public function set(string $index, $value): void
    {
        $this->memory[$this->prepareIndex($index)] = $value;
    }

    /**
     * @inheritdoc
     *
     * @param string $index
     *
     * @return bool
     */
    public function has(string $index): bool
    {
        return isset($this->memory[$this->prepareIndex($index)]);
    }

    /**
     * @inheritdoc
     *
     * @param string $index
     */
    public function remove(string $index): void
    {
        unset($this->memory[$this->prepareIndex($index)]);
    }

    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->sessionId;
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Returns the status of the current session
     *
     * ``` php
     * <?php
     * if ($session->status() !== PHP_SESSION_ACTIVE) {
     *     $session->start();
     * }
     * ```
     *
     * @return int
     */
    public function status(): int
    {
        if ($this->isStarted()) {
            return PHP_SESSION_ACTIVE;
        }

        return PHP_SESSION_NONE;
    }

    /**
     * @inheritdoc
     *
     * @param bool $id
     *
     * @return bool
     */
    public function destroy($id): bool
    {
        $this->memory  = [];
        $this->started = false;
        return true;
    }

    /**
     * @inheritdoc
     *
     * @param bool $deleteOldSession
     *
     * @return SessionHandlerInterface
     */
    public function regenerateId(bool $deleteOldSession = true): SessionHandlerInterface
    {
        $this->sessionId = $this->generateId();

        return $this;
    }

    /**
     * @inheritdoc
     *
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Dump all session
     *
     * @return array
     */
    public function toArray(): array
    {
        return (array) $this->memory;
    }

    /**
     * Alias: Gets a session variable from an application context
     *
     * @param string $index
     *
     * @return mixed
     */
    public function __get(string $index)
    {
        return $this->get($index);
    }

    /**
     * Alias: Sets a session variable in an application context
     *
     * @param string $index
     * @param mixed  $value
     */
    public function __set(string $index, $value): void
    {
        $this->set($index, $value);
    }

    /**
     * Alias: Check whether a session variable is set in an application context
     *
     * @param string $index
     *
     * @return bool
     */
    public function __isset(string $index): bool
    {
        return $this->has($index);
    }

    /**
     * Alias: Removes a session variable from an application context
     *
     * @param string $index
     */
    public function __unset(string $index): void
    {
        $this->remove($index);
    }

    private function prepareIndex(string $index): string
    {
        if ($this->sessionId) {
            $key = $this->sessionId . '#' . $index;
        } else {
            $key = $index;
        }

        return $key;
    }

    /**
     * @return string
     */
    private function generateId(): string
    {
        return sha1((string) time());
    }

    /**
     * Dummy - We Don't Actually Read Anything
     *
     * @return string
     */
    public function read($id): string
    {
        return "";
    }

    /**
     * Write - We Don't Actually Write Anything
     *
     * @param $id
     * @param $data
     *
     * @return bool
     */
    public function write($id, $data): bool
    {
        return true;
    }
}

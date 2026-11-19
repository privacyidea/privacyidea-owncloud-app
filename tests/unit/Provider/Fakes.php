<?php

/**
 * Fake collaborators for the provider tests. They keep every call, so that a test can look at it.
 */

namespace Tests\Unit\Provider;

use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;

class FakeConfig implements IConfig
{
    /** @var array */
    public $values;

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function getAppValue($appName, $key, $default = '')
    {
        return $this->values[$key] ?? $default;
    }
}

class FakeSession implements ISession
{
    /** @var array */
    public $data = [];

    public function get($key)
    {
        return $this->data[$key] ?? null;
    }

    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function remove($key)
    {
        unset($this->data[$key]);
    }
}

class FakeLogger implements ILogger
{
    /** @var string[] Every message, with the level in front. */
    public $lines = [];

    public function error($message, array $context = [])
    {
        $this->lines[] = "error: " . $message;
    }

    public function info($message, array $context = [])
    {
        $this->lines[] = "info: " . $message;
    }

    public function debug($message, array $context = [])
    {
        $this->lines[] = "debug: " . $message;
    }

    public function all(): string
    {
        return implode("\n", $this->lines);
    }
}

class FakeL10N implements IL10N
{
    public function t($text, $parameters = [])
    {
        return vsprintf($text, $parameters);
    }
}

class FakeRequest implements IRequest
{
    /** @var array */
    public $params = [];
    /** @var string */
    public $remoteAddress = "203.0.113.9";

    public function getParam($key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function getRemoteAddress()
    {
        return $this->remoteAddress;
    }
}

class FakeGroupManager implements IGroupManager
{
    /** @var string[] Names of the groups that every user is in. */
    public $groups = [];

    public function isInGroup($uid, $gid)
    {
        return in_array($gid, $this->groups, true);
    }
}

class FakeUser implements IUser
{
    /** @var string */
    private $uid;

    public function __construct(string $uid)
    {
        $this->uid = $uid;
    }

    public function getUID()
    {
        return $this->uid;
    }
}

class FakeResponse implements IResponse
{
    /** @var int */
    private $status;
    /** @var string */
    private $body;

    public function __construct(int $status, string $body)
    {
        $this->status = $status;
        $this->body = $body;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getStatusCode()
    {
        return $this->status;
    }
}

/**
 * Answers with the queued responses in order. A queued Throwable is thrown instead.
 * It does the same arithmetic with the timeout as the real HTTP client (Guzzle), because an
 * empty timeout from the settings made that arithmetic fail on PHP 8.
 */
class FakeClient implements IClient, IClientService
{
    /** @var array Every request: method, uri, options. */
    public $requests = [];
    /** @var array */
    private $queue = [];

    /**
     * @param IResponse|\Throwable $item
     */
    public function queue($item): self
    {
        $this->queue[] = $item;
        return $this;
    }

    public function newClient()
    {
        return $this;
    }

    public function get($uri, array $options = [])
    {
        return $this->handle("GET", $uri, $options);
    }

    public function post($uri, array $options = [])
    {
        return $this->handle("POST", $uri, $options);
    }

    private function handle(string $method, string $uri, array $options)
    {
        if (isset($options["timeout"]))
        {
            $options["timeout"] * 1000; // Guzzle: CURLOPT_TIMEOUT_MS
        }
        $this->requests[] = ["method" => $method, "uri" => $uri, "options" => $options];
        if (empty($this->queue))
        {
            throw new \LogicException("The test did not queue a response for " . $method . " " . $uri);
        }
        $item = array_shift($this->queue);
        if ($item instanceof \Throwable)
        {
            throw $item;
        }
        return $item;
    }
}

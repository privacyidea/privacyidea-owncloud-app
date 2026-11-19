<?php

/**
 * Minimal stand-ins for the ownCloud classes that the provider uses.
 *
 * The unit tests run without ownCloud. ownCloud has no Composer package with its API (OCP), so these
 * declarations hold only the methods that the provider and the tests call. They are not a copy of the real API.
 * If a class exists already (for example when the tests run inside ownCloud), the real class wins.
 */

namespace OCP\Authentication\TwoFactorAuth {

    use OCP\IUser;

    if (!interface_exists(IProvider::class, false))
    {
        interface IProvider
        {
            public function getId();

            public function getDisplayName();

            public function getDescription();

            public function getTemplate(IUser $user);

            public function verifyChallenge(IUser $user, $challenge);

            public function isTwoFactorAuthEnabledForUser(IUser $user);
        }
    }

    if (!class_exists(TwoFactorException::class, false))
    {
        class TwoFactorException extends \Exception
        {
        }
    }
}

namespace OCP\Http\Client {

    if (!interface_exists(IClientService::class, false))
    {
        interface IClientService
        {
            public function newClient();
        }
    }

    if (!interface_exists(IClient::class, false))
    {
        interface IClient
        {
            public function get($uri, array $options = []);

            public function post($uri, array $options = []);
        }
    }

    if (!interface_exists(IResponse::class, false))
    {
        interface IResponse
        {
            public function getBody();

            public function getStatusCode();
        }
    }
}

namespace OCP {

    if (!interface_exists(IConfig::class, false))
    {
        interface IConfig
        {
            public function getAppValue($appName, $key, $default = '');
        }
    }

    if (!interface_exists(IGroupManager::class, false))
    {
        interface IGroupManager
        {
            public function isInGroup($uid, $gid);
        }
    }

    if (!interface_exists(IL10N::class, false))
    {
        interface IL10N
        {
            public function t($text, $parameters = []);
        }
    }

    if (!interface_exists(ILogger::class, false))
    {
        interface ILogger
        {
            public function error($message, array $context = []);

            public function info($message, array $context = []);

            public function debug($message, array $context = []);
        }
    }

    if (!interface_exists(IRequest::class, false))
    {
        interface IRequest
        {
            public function getParam($key, $default = null);

            public function getRemoteAddress();
        }
    }

    if (!interface_exists(ISession::class, false))
    {
        interface ISession
        {
            public function get($key);

            public function set($key, $value);

            public function remove($key);
        }
    }

    if (!interface_exists(IUser::class, false))
    {
        interface IUser
        {
            public function getUID();
        }
    }

    if (!class_exists(Template::class, false))
    {
        class Template
        {
            /** @var array The values that the provider assigned. The tests read them. */
            public $vars = [];

            public function __construct($app, $name, $renderAs = '')
            {
            }

            public function assign($key, $value)
            {
                $this->vars[$key] = $value;
                return true;
            }
        }
    }
}

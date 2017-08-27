<?php

namespace AW2MW;

use Mediawiki\Api;

class LoginManager
{
    private $api;
    private $services;
    private $config;

    /**
     * @param Api\MediawikiApi $api
     * @param Config           $config
     */
    public function __construct($api, $config)
    {
        $this->api = $api;
        $this->services = new Api\MediawikiFactory($this->api);
        $this->config = $config;
    }

    public function loginAsAdmin()
    {
        $this->api->login(new Api\ApiUser($this->config->admin['login'], $this->config->admin['password']));
    }

    /**
     * @param string $username
     */
    public function login($username)
    {
        //We need to invalidate tokens if we change user
        $this->api->clearTokens();
        $password = password_hash(
            $username.$this->config->userSecret,
            PASSWORD_BCRYPT,
            ['salt' => $this->config->salt]
        );

        try {
            $this->api->login(new Api\ApiUser($username, $password));
        } catch (Api\UsageException $error) {
            try {
                //No email for now
                $this->services->newUserCreator()->create(
                    $username,
                    $password
                );
                $this->api->login(new Api\ApiUser($username, $password));
            } catch (Api\UsageException $error) {
                $this->login('aw2mw bot');
            }
        }
    }
}

<?php

/**
 * User: PG2000
 */
namespace PG2000\SessionHandler;

use Predis\Client;

class RedisSessionHandler implements \SessionHandlerInterface
{

    protected $redisClient;

    protected $sessionNamePrefix;

    protected $sessionConfiguration;

    public function __construct($redisClient, $sessionNamePrefix = 'PHPREDIS_SESSION:')
    {
        if ($redisClient instanceof Client) {
            $this->redisClient = $redisClient;
        }

        $this->sessionNamePrefix = $sessionNamePrefix;
    }

    public function close()
    {
        $this->redisClient->disconnect();
        unset($this->redisClient);
    }

    public function destroy($session_id)
    {
        $this->redisClient->del($this->getSessionNamePrefix($session_id));
    }

    public function gc($maxlifetime)
    {
    }

    public function open($save_path, $session_id)
    {
    }

    public function read($session_id)
    {
    }

    public function write($session_id, $session_data)
    {
        $this->redisClient->set($this->getSessionNamePrefix($session_id), $session_data);
    }

    private function getSessionNamePrefix($session_id)
    {
        return $this->sessionNamePrefix.$session_id;
    }

    private function getLockKey($session_id)
    {
        return $this->getSessionNamePrefix($session_id).'.lock';
    }
}

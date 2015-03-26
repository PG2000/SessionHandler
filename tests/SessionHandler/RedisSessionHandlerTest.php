<?php
/**
 * Created by PhpStorm.
 * User: geschinski
 * Date: 26.03.2015
 * Time: 21:17
 */

namespace PG2000\SessionHandler\Tests;

use PG2000\SessionHandler\RedisSessionHandler;
use Predis\Client;

class RedisSessionHandlerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var RedisSessionHandler
     */
    private $object;


    public function setUp()
    {
        $redisClient = new Client([
            'scheme' => 'tcp',
            'host'   => '127.0.0.1',
            'port'   => 6379,
        ]);


        $redisClient->connect();
        $this->object = new RedisSessionHandler($redisClient);
    }


    public function testRedisHandlerCanBeInstantiated()
    {
        $sessionId = "xqwertAsertzuqad";
        $expected = '__TEST__';

        $this->object->write($sessionId, $expected);
    }
}

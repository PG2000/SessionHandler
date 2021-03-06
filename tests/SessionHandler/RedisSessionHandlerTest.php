<?php

namespace PG2000\SessionHandler\Tests;

use PG2000\SessionHandler\RedisSessionHandler;

/**
 * RedisSessionHandlerTest
 */
final class RedisSessionHandlerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Redis | \PHPUnit_Framework_MockObject_MockObject
     */
    private $redis;

    /**
     * @var array
     */
    private $defaultPhpIniSettings = [
        'session.save_path' => ''
    ];

    protected function setUp()
    {
        $this->defaultPhpIniSettings['session.save_path'] = ini_get('session.save_path');
        ini_set('session.save_path', 'tcp://127.0.0.1:6379');
        $this->redis = $this->getMock('\Redis', array('get', 'set', 'setex', 'del', 'setnx', 'connect'));
    }

    protected function tearDown()
    {
        $this->resetPhpIniSettings();
        unset($this->redis);
    }

    protected function resetPhpIniSettings()
    {
        foreach ($this->defaultPhpIniSettings as $key => $setting) {
            ini_set($key, $setting);
        }
    }

    public function testSessionReading()
    {
        $this->redis
            ->expects($this->once())
            ->method('get')
            ->with($this->equalTo('PHPREDIS_SESSION:_symfony'));

        $handler = new RedisSessionHandler($this->redis);

        $reflection = new \ReflectionObject($handler);
        $lockMaxWaitProperty = $reflection->getProperty('locking');
        $lockMaxWaitProperty->setAccessible(true);
        $lockMaxWaitProperty->setValue($handler, false);

        $handler->read('_symfony');
    }

    public function testDeletingSessionData()
    {
        $this->redis
            ->expects($this->once())
            ->method('del')
            ->with($this->equalTo('PHPREDIS_SESSION:_symfony'));

        $handler = new RedisSessionHandler($this->redis);
        $handler->destroy('_symfony');
    }

    public function testWritingSessionDataWithExpiration()
    {
        $sessionGcMaxLifetime = ini_get('session.gc_maxlifetime');

        $this->redis
            ->expects($this->exactly(1))
            ->method('setex')
            ->with(
                $this->equalTo('PHPREDIS_SESSION:_symfony'),
                $this->equalTo($sessionGcMaxLifetime),
                $this->equalTo('some data')
            );

        $handler = new RedisSessionHandler($this->redis);
        $handler->write('_symfony', 'some data');
    }

    public function testSessionLocking()
    {
        $lockMaxWait = 2;

        $this->redis
            ->expects($this->exactly($lockMaxWait))
            ->method('setnx')
            ->with($this->equalTo('PHPREDIS_SESSION_symfony.lock'), $this->equalTo('1'));

        $handler = new RedisSessionHandler($this->redis);

        $reflection = new \ReflectionObject($handler);

        $lockMaxWaitProperty = $reflection->getProperty('lockMaxWait');
        $lockMaxWaitProperty->setAccessible(true);
        $lockMaxWaitProperty->setValue($handler, 2);

        $lockMaxWaitProperty = $reflection->getProperty('spinLockWait');
        $lockMaxWaitProperty->setAccessible(true);
        $lockMaxWaitProperty->setValue($handler, 1000000);

        $handler->read('_symfony');
    }

    public function testRedisHandlerWillConnectRedisClientWithParameterFromIni()
    {
        ini_set('session.save_path', 'tcp://127.0.0.1:6379');
        $this->redis = $this->getMock('\Redis');
        $this->redis->expects($this->once())->method('connect')->with(
            $this->equalTo('127.0.0.1'),
            $this->equalTo(6379)
        );
        new RedisSessionHandler($this->redis);
    }

    public function testRedisHandlerWillThrowExceptionWithWrongParameterFromIni()
    {
        $this->setExpectedException('\Exception', 'The connection string is malformed');
        ini_set('session.save_path', '/tmp');
        $this->redis = $this->getMock('\Redis');
        new RedisSessionHandler($this->redis);
    }

    public function testRedisHandlerWithSockConnectionParameterFromIni()
    {
        $connectionString = 'unix:///var/run/redis/redis.sock';
        ini_set('session.save_path', $connectionString);
        $this->redis = $this->getMock('\Redis');
        $this->redis->expects($this->once())->method('connect')->with(str_replace("unix://", "", $connectionString));
        new RedisSessionHandler($this->redis);
    }
}

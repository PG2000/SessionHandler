<?php

namespace PG2000\SessionHandler;

/**
 * Redis based session storage with session locking support
 */
class RedisSessionHandler implements \SessionHandlerInterface
{
    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var int
     */
    protected $ttl;

    /**
     * @var string
     */
    protected $prefix = 'PHPREDIS_SESSION';

    /**
     * @var integer Default PHP max execution time in seconds
     */
    const DEFAULT_MAX_EXECUTION_TIME = 30;

    /**
     * @var boolean Indicates an sessions should be locked
     */
    private $locking = true;

    /**
     * @var boolean Indicates an active session lock
     */
    private $locked = false;

    /**
     * @var string Session lock key
     */
    private $lockKey = null;

    /**
     * @var integer Microseconds to wait between acquire lock tries
     */
    private $spinLockWait = 150000;

    /**
     * @var integer Maximum amount of seconds to wait for the lock
     */
    private $lockMaxWait;


    /**
     * Redis session storage constructor
     *
     * @param \Redis $redis Redis database connection
     */
    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
        $this->connectRedis(ini_get('session.save_path'));

        $this->ttl = $this->getSessionMaxLifetime();

        $this->lockMaxWait = ini_get('max_execution_time');

        if (!$this->lockMaxWait) {
            $this->lockMaxWait = self::DEFAULT_MAX_EXECUTION_TIME;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function close()
    {
        if ($this->locking) {
            if ($this->locked) {
                $this->unlockSession();
            }
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function read($sessionId)
    {
        if ($this->locking) {
            if (!$this->locked) {
                if (!$this->lockSession($sessionId)) {
                    return false;
                }
            }
        }

        return $this->redis->get($this->getRedisKey($sessionId)) ?: '';
    }

    /**
     * {@inheritDoc}
     */
    public function write($sessionId, $data)
    {
        if (0 < $this->ttl) {
            $this->redis->setex($this->getRedisKey($sessionId), $this->ttl, $data);
        } else {
            $this->redis->set($this->getRedisKey($sessionId), $data);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function destroy($sessionId)
    {
        $this->redis->del($this->getRedisKey($sessionId));
        $this->close();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function gc($lifetime)
    {
        return true;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param $sessionSavePath
     * @throws \Exception
     */
    private function connectRedis($sessionSavePath)
    {
        try {
            if (strncmp($sessionSavePath, "unix:", strlen("unix:") - 1) === 0) {
                $sessionSavePath = str_replace('unix:', 'file:', $sessionSavePath);
            }

            $parsedUrl = $this->getParsedUrl($sessionSavePath);

            if ($this->isTcpSessionSavePath($parsedUrl)) {
                $this->redis->connect($parsedUrl['host'], $parsedUrl['port']);
            } elseif ($this->isSockSessionSavePath($parsedUrl)) {
                $this->redis->connect($parsedUrl['path']);
            } else {
                throw new \Exception('The connection string is malformed');
            }
        } catch (\Exception $exc) {
            throw new \Exception($exc->getMessage(), $exc->getCode());
        }
    }

    /**
     * Prepends the session ID with a user-defined prefix (if any).
     * @param string $sessionId session ID
     *
     * @return string prefixed session ID
     */
    private function getRedisKey($sessionId)
    {
        if (empty($this->prefix)) {
            return $sessionId;
        }

        return $this->prefix . ':' . $sessionId;
    }

    private function lockSession($sessionId)
    {
        $attempts = (1000000 / $this->spinLockWait) * $this->lockMaxWait;

        $this->lockKey = $sessionId . '.lock';
        for ($i = 0; $i < $attempts; $i++) {
            $success = $this->redis->setnx($this->prefix . $this->lockKey, '1');
            if ($success) {
                $this->locked = true;
                $this->redis->expire($this->prefix . $this->lockKey, $this->lockMaxWait + 1);
                return true;
            }
            usleep($this->spinLockWait);
        }

        return false;
    }


    private function unlockSession()
    {
        $this->redis->del($this->prefix . $this->lockKey);
        $this->locked = false;
    }

    /**
     * @param $sessionSavePath
     * @return mixed
     * @throws \Exception
     */
    private function getParsedUrl($sessionSavePath)
    {
        return parse_url($sessionSavePath);
    }

    /**
     * @param $parsedUrl
     * @return bool
     */
    private function isTcpSessionSavePath($parsedUrl)
    {
        return isset($parsedUrl['scheme'])
        && $parsedUrl['scheme'] === 'tcp'
        && isset($parsedUrl['host'])
        || isset($parsedUrl['port']);
    }

    /**
     * @param $parsedUrl
     * @return bool
     */
    private function isSockSessionSavePath($parsedUrl)
    {
        return isset($parsedUrl['scheme']) && $parsedUrl['scheme'] === 'file' && isset($parsedUrl['path']);
    }

    /**
     * @return int
     */
    private function getSessionMaxLifetime()
    {
        return (int)ini_get('session.gc_maxlifetime');
    }
}

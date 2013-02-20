<?php
/**
 * This file is part of ninja-mutex.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace NinjaMutex\Lock;

use NinjaMutex\Lock\LockAbstract;

/**
 * Lock implementor using Memcache
 *
 * @author Kamil Dziedzic <arvenil@klecza.pl>
 */
class MemcacheLock extends LockAbstract
{
    /**
     * Memcache connection
     *
     * @var \Memcache
     */
    protected $memcache;
    protected $keys = array();

    /**
     * @param \Memcache $memcache
     */
    public function __construct(\Memcache $memcache)
    {
        parent::__construct();

        $this->memcache = $memcache;
    }

    public function __destruct()
    {
        foreach($this->keys as $name) {
            $this->releaseLock($name);
        }
    }

    public function __clone() {
        $this->keys = array();
    }

    /**
     * Acquire lock
     *
     * @param string $name name of lock
     * @param null|int $timeout 1. null if you want blocking lock
     *                          2. 0 if you want just lock and go
     *                          3. $timeout > 0 if you want to wait for lock some time (in miliseconds)
     * @return bool
     */
    public function acquireLock($name, $timeout = null)
    {
        $start = microtime(true);
        $end = $start + $timeout / 1000;
        $locked = false;
        while (!($locked = $this->getLock($name)) && $timeout > 0 && microtime(true) < $end) {
            usleep(static::USLEEP_TIME);
        }

        return $locked;
    }

    protected function getLock($name)
    {
        return empty($this->keys[$name]) && $this->memcache->add($name, serialize($this->getLockInformation())) && $this->keys[$name] = $name;
    }

    /**
     * Release lock
     *
     * @param string $name name of lock
     * @return bool
     */
    public function releaseLock($name)
    {
        if (isset($this->keys[$name]) && $this->memcache->delete($name)) {
            unset($this->keys[$name]);
            return true;
        }

        return false;
    }

    /**
     * Check if lock is locked
     *
     * @param string $name name of lock
     * @return bool
     */
    public function isLocked($name)
    {
        return false !== $this->memcache->get($name);
    }
}
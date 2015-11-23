<?php
/**
 * 2015 Michael Dekker
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@michaeldekker.com so we can send you a copy immediately.
 *
 * @author    Michael Dekker <prestashop@michaeldekker.com>
 * @copyright 2015 Michael Dekker
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

/**
 * This class require Redis server Installed
 *
 */
class CachePhpRedis extends Cache
{
    /**
     * @var RedisClient
     */
    protected $redis;

    /**
     * @var RedisParams
     */
    protected $_params = array();

    /**
     * @var bool Connection status
     */
    public $is_connected = false;

    public function __construct()
    {
        $this->connect();

        if ($this->is_connected) {
            $this->keys = @$this->redis->get(_COOKIE_IV_);
            if (!is_array($this->keys)) {
                $this->keys = array();
            }
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Connect to redis server
     */
    public function connect()
    {
        $this->is_connected = false;
        $servers = self::getRedisServer();

        if (!$servers) {
            return;
        } else {
            $this->redis = new Redis();

            if ($this->redis->pconnect($servers['PREDIS_SERVER'], $servers['PREDIS_PORT'])) {
                $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
                if ($servers['PREDIS_AUTH'] != '') {
                    if (!($this->redis->auth((string)$servers['PREDIS_AUTH']))) {
                        return;
                    }
                }
                $this->redis->select((int)$servers['PREDIS_DB']);
                $this->is_connected = true;
            }
        }
    }

    /**
     * @see Cache::_set()
     */
    protected function _set($key, $value, $ttl = 0)
    {

        if (!$this->is_connected) {
            return false;
        }

        return $this->redis->set($key, $value);
    }

    /**
     * @see Cache::_get()
     */
    protected function _get($key)
    {
        if (!$this->is_connected) {
            return false;
        }

        return $this->redis->get($key);
    }

    /**
     * @see Cache::_exists()
     */
    protected function _exists($key)
    {
        if (!$this->is_connected) {
            return false;
        }

        return isset($this->keys[$key]);
    }

    /**
     * @see Cache::_delete()
     */
    protected function _delete($key)
    {
        if (!$this->is_connected) {
            return false;
        }

        return $this->redis->del($key);
    }

    /**
     * @see Cache::_writeKeys()
     */
    protected function _writeKeys()
    {
        if (!$this->is_connected) {
            return false;
        }
        $this->redis->set(_COOKIE_IV_, $this->keys);
    }

    /**
     * @see Cache::flush()
     */
    public function flush()
    {
        if (!$this->is_connected) {
            return false;
        }

        return $this->redis->flushDB();
    }

    /**
     * Close connection to redis server
     *
     * @return bool
     */
    protected function close()
    {
        if (!$this->is_connected) {
            return false;
        }

        // Don't close the connection, needs to be persistent across PHP-sessions
        return;
    }

    /**
     * Get list of redis server information
     *
     * @return array
     */
    public static function getRedisServer()
    {
        $server = array();
        // bypass the memory fatal error caused functions nesting on PS 1.5
        $params = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT * FROM '._DB_PREFIX_.'configuration WHERE name = "PREDIS_SERVER" OR name="PREDIS_PORT" OR name="PREDIS_AUTH" OR name="PREDIS_DB"',
            true,
            false
        );
        foreach ($params as $key => $val) {
            $server[$val['name']] = $val['value'];
        }

        return $server;
    }
}
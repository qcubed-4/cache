<?php
/**
 *
 * Part of the QCubed PHP framework.
 *
 * @license MIT
 *
 */

namespace QCubed\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Cache provider that uses a local in memory array.
 * The lifespan of this cache is the request, unless 'KeepInSession' option is used, in which case the lifespan
 * is the session.
 */

class LocalMemoryCache extends CacheBase implements CacheInterface
{
    /** @var array */
    protected $arrLocalCache;

    /**
     * @param array $objOptionsArray configuration options for this cache provider. Currently supported options are
     *   'KeepInSession': if set to true the cache will be kept in session
     */
    public function __construct($objOptionsArray)
    {
        if (array_key_exists('KeepInSession', $objOptionsArray) && $objOptionsArray['KeepInSession'] === true) {
            if (!isset($_SESSION['__LOCAL_MEMORY_CACHE__'])) {
                $_SESSION['__LOCAL_MEMORY_CACHE__'] = array();
            }
            $this->arrLocalCache = &$_SESSION['__LOCAL_MEMORY_CACHE__'];
        } else {
            $this->arrLocalCache = array();
        }
    }

    /**
     * Get the object that has the given key from the cache
     * @param string $strKey the key of the object in the cache
     * @param null|mixed $default
     * @return null|mixed
     */
    public function get($strKey, $default = null)
    {
        if (array_key_exists($strKey, $this->arrLocalCache)) {
            // Note the clone statement - it is important to return a copy,
            // not a pointer to the stored object
            // to prevent it's modification by user code.
            $objToReturn = $this->arrLocalCache[$strKey];
            if ($objToReturn['timeToExpire'] != 0) {
                // Time to expire was set. See if it should be expired
                if ($objToReturn['timeToExpire'] < time()) {
                    $this->delete($strKey);
                    return $default;
                }
            }

            if (isset($objToReturn['value']) && is_object($objToReturn['value'])) {
                $objToReturn['value'] = clone $objToReturn['value'];
            }

            return $objToReturn['value'];
        }
        return $default;
    }

    /**
     * Set the object into the cache with the given key
     *
     * @param string $strKey                    the key to use for the object
     * @param object $objValue                  the object to put in the cache
     * @param int    $intExpirationAfterSeconds Number of seconds after which the key has to expire
     *
     * @return bool true on success
     */
    public function set($strKey, $objValue, $intExpirationAfterSeconds = null)
    {
        // Note the clone statement - it is important to store a copy,
        // not a pointer to the user object
        // to prevent it's modification by user code.
        $objToSet = $objValue;
        if ($objToSet && is_object($objToSet)) {
            $objToSet = clone $objToSet;
        }
        $this->arrLocalCache[$strKey] = array(
            'timeToExpire' => $intExpirationAfterSeconds ? (time() + QType::cast($intExpirationAfterSeconds, QType::Integer)) : 0,
            'value' => $objToSet
        );
        return true;
    }

    /**
     * Delete the object that has the given key from the cache
     * @param string $strKey the key of the object in the cache
     * @return void
     */
    public function delete($strKey)
    {
        if (array_key_exists($strKey, $this->arrLocalCache)) {
            unset($this->arrLocalCache[$strKey]);
        }
    }

    /**
     * Alias for the clear method
     * @return void
     */
    public function deleteAll()
    {
        $this->clear();
    }

    /**
     * Invalidate all objects in the cache
     * @return bool|void
     */
    public function clear(){
        $this->arrLocalCache = array();
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys    A list of keys that can obtained in a single operation.
     * @param mixed    $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null) {
        if (!is_array($keys) && !is_iterable($keys)) {
            throw new Exception\InvalidArgument ('Cannot iterate over keys');
        }

        $ret = [];
        foreach ($keys as $key) {
            $ret[$key] = $this->get($key, $default);
        }

        return $ret;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable               $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null){
        if (!is_array($values) && !is_iterable($values)) {
            throw new Exception\InvalidArgument ('Cannot iterate over values');
        }

        foreach ($values as $key=>$value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys) {
        foreach ($keys as $key) {
            $this->delete ($key);
        }

        return true;
    }


    public function has($key) {
        return array_key_exists($key, $this->arrLocalCache);
    }
}

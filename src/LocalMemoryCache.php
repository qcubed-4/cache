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
use QCubed\Cache\Exception\InvalidArgument;
use QCubed\Exception\Caller;
use QCubed\Exception\InvalidCast;
use DateInterval;
use QCubed\Type;

/**
 * Cache provider that uses a local in a memory array.
 * The lifespan of this cache is the request, unless the 'KeepInSession' option is used, in which case the lifespan
 * is the session.
 */

class LocalMemoryCache extends CacheBase implements CacheInterface
{
    /** @var array */
    protected array $arrLocalCache;

    /**
     * @param array $objOptionsArray configuration options for this cache provider. Currently supported options are
     *   'KeepInSession': if set to true, the cache will be kept in session
     */
    public function __construct(array $objOptionsArray)
    {
        if (array_key_exists('KeepInSession', $objOptionsArray) && $objOptionsArray['KeepInSession'] === true) {
            if (!isset($_SESSION['LOCAL_MEMORY_CACHE'])) {
                $_SESSION['LOCAL_MEMORY_CACHE'] = array();
            }
            $this->arrLocalCache = &$_SESSION['LOCAL_MEMORY_CACHE'];
        } else {
            $this->arrLocalCache = array();
        }
    }

    /**
     * Retrieves a value from the local cache associated with the specified key.
     * If the key does not exist or the cached value has expired, the default value is returned.
     *
     * @param string $strKey The key associated with the cached value.
     * @param mixed|null $default The default value to return if the key does not exist or the value has expired.
     * @return mixed Returns the cached value if it exists and is not expired, otherwise returns the default value.
     */
    public function get(string $strKey, mixed $default = null): mixed
    {
        if (array_key_exists($strKey, $this->arrLocalCache)) {
            // Note the clone statement - it is important to return a copy,
            // not a pointer to the stored object
            // to prevent its modification by user code.
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
     * Stores a value in the local cache with the specified key and optional expiration time.
     *
     * @param string $strKey The key to associate with the value in the cache.
     * @param mixed $objValue The value to be stored in the cache.
     * @param int|DateInterval|null $intExpirationAfterSeconds The number of seconds after which the cached value will expire. Pass null for no expiration.
     * @return bool Returns true if the value was successfully stored in the cache.
     * @throws Caller
     * @throws InvalidCast
     */
    public function set(string $strKey, mixed $objValue, int|DateInterval|null $intExpirationAfterSeconds = null): bool
    {
        // Note the clone statement - it is important to store a copy,
        // not a pointer to the user object
        // to prevent its modification by user code.
        $objToSet = $objValue;
        if ($objToSet && is_object($objToSet)) {
            $objToSet = clone $objToSet;
        }
        $this->arrLocalCache[$strKey] = array(
            'timeToExpire' => $intExpirationAfterSeconds ? (time() + Type::cast($intExpirationAfterSeconds, Type::INTEGER)) : 0,
            'value' => $objToSet
        );

        return true;
    }

    /**
     * Deletes a value from the local cache associated with the specified key.
     * If the key does not exist, no action is taken.
     *
     * @param string $strKey The key associated with the cached value to be deleted.
     * @return bool
     */
    public function delete(string $strKey): bool
    {
        if (array_key_exists($strKey, $this->arrLocalCache)) {
            unset($this->arrLocalCache[$strKey]);
        }

        return true;
    }

    /**
     * Deletes all entries from the local cache by clearing it completely.
     *
     * @return void Does not return any value.
     */
    public function deleteAll(): void
    {
        $this->clear();
    }

    /**
     * Clears all entries from the local cache.
     *
     * @return bool Removes all cached values, resetting the cache to an empty state.
     */
    public function clear(): bool
    {
        $this->arrLocalCache = array();
        return true;
    }

    /**
     * Retrieves multiple values from the local cache for the given keys.
     * If a key does not exist or the associated value has expired, the default value is returned for that key.
     *
     * @param array|iterable $keys The keys to retrieve values for.
     * @param mixed|null $default The default value to return for keys that do not exist or have expired.
     * @return array Returns an associative array where each key corresponds to the provided keys
     *               and each value is the cached value or the default value.
     * @throws Exception\InvalidArgument If the provided keys are not iterable.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
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
     * Sets multiple key-value pairs in the cache with an optional time-to-live.
     *
     * @param array|iterable $values An array or iterable of key-value pairs to set in the cache.
     * @param DateInterval|int|null $ttl Optional. The time-to-live in seconds for the cached items. If null, the default TTL is used.
     * @return bool Returns true on success.
     * @throws Caller
     * @throws InvalidArgument If the provided $values is not an array or iterable.
     * @throws InvalidCast
     */
    public function setMultiple(iterable $values, null|DateInterval|int $ttl = null): bool
    {
        if (!is_array($values) && !is_iterable($values)) {
            throw new Exception\InvalidArgument ('Cannot iterate over values');
        }

        foreach ($values as $key=>$value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * Deletes multiple items from the cache based on the provided keys.
     *
     * @param array $keys An array of keys identifying the items to delete from the cache.
     * @return bool Returns true after all specified keys have been processed for deletion.
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete ($key);
        }

        return true;
    }


    /**
     * Checks if a given key exists in the local cache.
     *
     * @param string $key The key to check for existence in the local cache.
     *
     * @return bool True if the key exists in the local cache, false otherwise.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->arrLocalCache);
    }
}

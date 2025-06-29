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
use Exception;
use DateInterval;
use DateTimeImmutable;
use Traversable;

/**
 * Class ApcCache
 *
 * Cached based on APC or APCu interface, which are not included in PHP but easily added with a PECL installation. Note that
 * the functions to use have changed, and even if you are using APCu, you might be using a version that requires the APC
 * functions. This will try to use the correct one.
 *
 * @package QCubed\Cache
 */
class ApcCache extends CacheBase implements CacheInterface
{
    /** @var int */
    protected int $ttl = 86400; // default ttl, one day between cache drops
    /** @var bool  */
    protected bool $blnUseApcu;

    /**
     * ApcCache constructor.
     * @param array | null $objOptionsArray Configuration options.
     *                      Accepts the one option 'ttl' to set the default ttl value in seconds.
     * @throws Exception
     */
    public function __construct(array $objOptionsArray = null)
    {
        if (function_exists('apcu_fetch')) {
            $this->blnUseApcu = true;
        }
        elseif (function_exists('apc_fetch')) {
            $this->blnUseApcu = false;
        } else {
            throw new Exception('Neither Apc nor Apcu is installed.');
        }

        if (isset($objOptionsArray['ttl'])) {
            $this->ttl = (int)$objOptionsArray['ttl'];
        }
    }

    /**
     * Retrieves a value from the cache using the provided key. If the key does not exist, the default value is returned.
     *
     * @param string $strKey The key used to fetch the value from the cache.
     * @param mixed|null $default The default value to return if the key does not exist in the cache.
     * @return mixed The value from the cache if the key exists, otherwise the default value.
     */
    public function get(string $strKey, mixed $default = null): mixed
    {
        if ($this->blnUseApcu) {
            $value = apcu_fetch($strKey, $success);
        }
        else {
            $value = apc_fetch($strKey, $success);
        }
        if ($success) {
            return $value;
        } else {
            return $default;
        }
    }

    /**
     * Stores a value in the cache with a specified key and optional time-to-live (TTL) duration.
     *
     * @param string $strKey The key used to store the value in the cache. Must not contain invalid characters ('{}()/\@:').
     * @param mixed $objValue The value to be stored in the cache.
     * @param DateInterval|int|null $ttl The time-to-live for the cached value. Can be specified as seconds, a DateInterval, or null to use the default TTL.
     * @return bool True if the value was successfully stored in the cache, false otherwise.
     * @throws InvalidArgument If the key contains invalid characters.
     */
    public function set(string $strKey, mixed $objValue, DateInterval|int $ttl = null): bool
    {
        // PSR-16 is for some reason picky about what characters you can have in the key, thinking it will "some day" use certain characters to mean other things.
        $search = strpbrk($strKey, '{}()/\@:');
        if ($search !== false) {
            throw new InvalidArgument('Invalid character found in the key: ' . $search[0]);
        }

        if ($ttl === null) {
            $ttl = $this->ttl;
        }
        elseif ($ttl instanceof DateInterval) {
            // convert DateInterval to total seconds
            $reference = new DateTimeImmutable;
            $endTime = $reference->add($ttl);
            $ttl = $endTime->getTimestamp() - $reference->getTimestamp();
        }

        if($this->blnUseApcu) {
            $blnSuccess = apcu_store ($strKey, $objValue, $ttl);
        } else {
            $blnSuccess = apc_store($strKey, $objValue, $ttl);
        }

        return $blnSuccess;
    }

    /**
     * Deletes a value from the cache using the provided key.
     *
     * @param string $strKey The key identifying the value to be deleted from the cache.
     * @return bool
     */
    public function delete(string $strKey): bool
    {
        if ($this->blnUseApcu) {
            return apcu_delete($strKey) !== false;
        } else {
            return apc_delete($strKey) !== false;
        }
    }

    /**
     * Deletes all items from the cache.
     *
     * @return void
     */
    public function deleteAll(): void
    {
        $this->clear();
    }

    /**
     * Clears the user cache, removing all stored entries.
     *
     * @return bool
     */
    public function clear(): bool
    {
        if ($this->blnUseApcu) {
            return apcu_clear_cache();
        } else {
            return apc_clear_cache('user');
        }
    }

    /**
     * Retrieves multiple values from the cache using the provided keys. Keys that do not exist in the cache will have the default value assigned.
     *
     * @param array|Traversable $keys A list of keys to fetch values for from the cache.
     * @param mixed|null $default The default value to assign to keys that do not exist in the cache.
     * @return array An associative array of key-value pairs fetched from the cache. Keys not found in the cache will be paired with the default value.
     * @throws InvalidArgument If the provided keys are neither an array nor an instance of Traversable.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        if (!is_array($keys) && !$keys instanceof Traversable) {
            throw new InvalidArgument();
        }

        if ($this->blnUseApcu) {
            $values = apcu_fetch($keys);
        } else {
            $values = apc_fetch($keys);
        }

        if ($values !== false) {
            foreach ($keys as $key) {
                if (!isset($values[$key])) {
                    $values[$key] = $default;
                }
            }
            return $values;
        } else {
            return []; // some way of showing an error
        }
    }

    /**
     * Stores multiple key-value pairs in the cache with an optional time-to-live (TTL) value.
     *
     * @param array $values An associative array of key-value pairs to be stored in the cache.
     * @param DateInterval|int|null $ttl The time-to-live for the cached items. This can be an integer (in seconds), a DateInterval object, or null to use the default TTL.
     * @return bool True if the operation was successful, otherwise False.
     */
    public function setMultiple(iterable $values, null|DateInterval|int $ttl = null): bool
    {
        if ($ttl === null) {
            $ttl = $this->ttl;
        } elseif ($ttl instanceof DateInterval) {
            // convert DateInterval to total seconds
            $reference = new DateTimeImmutable;
            $endTime = $reference->add($ttl);
            $ttl = $endTime->getTimestamp() - $reference->getTimestamp();
        }

        if($this->blnUseApcu) {
            $blnSuccess = apcu_store ($values, null, $ttl);
        } else {
            $blnSuccess = apc_store($values, null, $ttl);
        }

        return $blnSuccess;
    }

    /**
     * Deletes multiple keys from the cache.
     *
     * @param iterable $keys The keys of the items to delete. Must be an iterable, such as an array or Traversable object.
     * @return bool True if the operation is successful for all keys, otherwise false.
     * @throws InvalidArgument
     */
    public function deleteMultiple(iterable $keys): bool {

        if (!is_array($keys) && !$keys instanceof Traversable) {
            throw new InvalidArgument();
        }

        if ($this->blnUseApcu) {
            return apcu_delete($keys) !== false;
        } else {
            return apc_delete($keys) !== false;
        }
    }

    /**
     * Return true if the given key exists. Do not rely on this to query the value after using this in a multi-user environment,
     * because the value might get deleted before asking for the value. However, this could be useful if you are setting keys
     * to boolean values.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool {
        if ($this->blnUseApcu) {
            return apcu_exists($key);
        } else {
            return apc_exists($key);
        }
    }

    /**
     * TODO: Add additional utility functions APC has which are not part of the PSR standard.
     */
}

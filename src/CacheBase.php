<?php
/**
 *
 * Part of the QCubed PHP framework.
 *
 * @license MIT
 *
 */

namespace QCubed\Cache;

abstract class CacheBase
{
    /**
     * Create a key appropriate for this cache provider
     * @return string the key
     */
    public function createKey(/* ... */)
    {
        $objArgsArray = array();
        $arg_list = func_get_args();

        array_walk_recursive($arg_list, function($val, $index) use (&$objArgsArray) {
            $objArgsArray[] = $val;
        });

        return implode("~", $objArgsArray);
    }

    /**
     * Create a key from an array of values
     *
     * @param $a
     * @return string
     */
    public function createKeyArray($a)
    {
        return implode("~", $a);
    }
}

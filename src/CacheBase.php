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
     * Generates a key by concatenating all arguments and their nested values into a single string, separated by a tilde (~).
     *
     * @return string
     */
    public function createKey(/* ... */): string
    {
        $objArgsArray = array();
        $arg_list = func_get_args();

        array_walk_recursive($arg_list, function($val, $index) use (&$objArgsArray) {
            $objArgsArray[] = $val;
        });

        return implode("~", $objArgsArray);
    }

    /**
     * Converts an array into a string by imploding its elements with a delimiter.
     *
     * @param array $a The input array to be converted into a string.
     * @return string The resulting string created by joining the array elements with the delimiter "~".
     */
    public function createKeyArray(array $a): string
    {
        return implode("~", $a);
    }
}

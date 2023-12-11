<?php

namespace LaravelSimpleModule\Helpers;

use Illuminate\Support\Str;

class Change
{

    /**
     * Change the keys and/or the values of an array or object or string to the specified case.
     *
     * @param string|object|array $var
     * @param string $cast
     * @return string|object|array
     */
    public static function case($var, $cast = 'camel', $parameter = 'value')
    {
        if (is_string($var) || is_numeric($var)) {
            return Str::$cast($var);
        }

        $casts = self::getSeparatedValues($cast);

        foreach ($casts as $castType) {
            // If $var is an array or object, return the processed array or object
            if (is_array($var)) {
                $var = self::arrayWalkRecursive($var, $castType, $parameter);
            } elseif (is_object($var)) {
                $var = (object)self::arrayWalkRecursive((array)$var, $castType, $parameter);
            }
        }

        return $var;
    }

    /**
     * Change the keys and/or the values of a multi-dimensional array by the specified case and parameter.
     *
     * @param array $var
     * @param string $cast
     * @return array
     */
    private static function arrayWalkRecursive($var, $cast, $parameter)
    {
        return array_map(
            function ($item) use ($cast, $parameter) {
                return is_array($item) ? self::arrayWalkRecursive($item, $cast, $parameter) : $item;
            },
            self::accessProperty($var, $cast, $parameter)
        );
    }

    /**
     * Change the keys and/or the values of a single-dimensional array by the specified case and parameter.
     *
     * @param array $var
     * @param string $cast
     * @return array
     */
    private static function accessProperty($var, $cast, $parameter)
    {
        $parameter = Str::lower($parameter);
        $result = [];

        foreach ($var as $key => $value) {
            switch ($parameter) {
                case Str::containsAll($parameter, ['key', 'value']):
                    $key = self::case($key, $cast, $parameter);
                    $value = self::case($value, $cast, $parameter);
                    $result[$key] = $value;
                    break;
                case Str::contains($parameter, ['key']):
                    $key = self::case($key, $cast, $parameter);
                    $result[$key] = $value;
                    break;
                case Str::contains($parameter, ['value']):
                    $value = self::case($value, $cast, $parameter);
                    $result[$key] = $value;
                    break;
                default:
                    $key = self::case($key, $cast, $parameter);
                    $result[$key] = $value;
                    break;
            }
        }

        return $result;
    }

    /**
     * Splits a string containing space and/or comma-separated values into an array.
     *
     * @param string $inputString The input string to be split.
     * @return array The array containing separated values.
     */
    private static function getSeparatedValues($inputString)
    {
        // Split the input string using both space and comma as delimiters
        return preg_split('/[\s,]+/', $inputString, -1, PREG_SPLIT_NO_EMPTY);
    }
}

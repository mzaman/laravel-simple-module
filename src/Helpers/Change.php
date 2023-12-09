<?php


namespace LaravelSimpleModule\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class Change
{

    /**
     * Change the keys AND/OR the values of an array or object or string to the specified case
     * @param string|object|array $var
     * @param string $cast
     * @return string|object|array
     */
    public static function case($var, $cast = 'camel', $parameter = 'key value')
    {
        if (is_string($var) || is_numeric($var)) {
            switch ($cast) {
                case $cast == 'pascal': // if cast type is PascalCase
                    $result = Str::studly($var);
                    break;
                
                default:
                    $result = Str::$cast($var);
                    break;
            }
        }


        foreach (self::getSeparatedValues($cast) as $castType) {

            // If $var is an array, then return the processed array
            if (is_array($var)) {
                $result = self::arrayWalkRecursive($var, $castType, $parameter);
            }

            // If $var is an object, then return the processed object
            if (is_object($var)) {
                $result = (object) self::arrayWalkRecursive((array) $var, $castType, $parameter);
            }
        }

        return $result;
    }


    /**
     * Change the keys AND/OR the values of a multi-dimentional array by the specified case and parameter
     * @param string|object|array $var
     * @param string $cast
     * @return array
     */
    private static function arrayWalkRecursive($var, $cast, $parameter)
    {
        return array_map(
            function ($item) use ($cast, $parameter) {
                if (is_array($item)) {
                    $item = self::arrayWalkRecursive($item, $cast, $parameter);
                }

                return $item;
            },
            self::accessProperty($var, $cast, $parameter)
        );
    }

    /**
     * Change the keys AND/OR the values of a single-dimentional array by the specified case and parameter
     * @param string|object|array $var
     * @param string $cast
     * @return array
     */
    private static function accessProperty($var, $cast, $parameter)
    {
        $parameter = Str::lower($parameter);
        $result = [];

        foreach ($var as $key => $value) {
            switch ($parameter) {
                case (Str::containsAll($parameter, ['key', 'value'])): // Convert both the keys and values
                    $key = self::case($key, $cast, $parameter);
                    $value = self::case($value, $cast, $parameter);
                    $result[$key] = $value;
                    break;
                case (Str::contains($parameter, ['key'])): // Convert only the keys
                    $key = self::case($key, $cast, $parameter);
                    $result[$key] = $value;
                    break;
                case (Str::contains($parameter, ['value'])): // Convert only the values
                    $value = self::case($value, $cast, $parameter);
                    $result[$key] = $value;
                    break;
                
                default: // Convert only the keys
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
        $values = preg_split('/[\s,]+/', $inputString, -1, PREG_SPLIT_NO_EMPTY);

        return $values;
    }
}

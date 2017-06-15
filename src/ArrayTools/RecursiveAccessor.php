<?php
/**
 * Created by PhpStorm.
 * User: daniel.jurkovic
 * Date: 15.06.17
 * Time: 14:14
 */

namespace Macroparts\Vortex\ArrayTools;

class RecursiveAccessor
{
    /**
     * @param array $array
     * @param string $path
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public static function readSingleFromPath($array, $path)
    {
        if (self::isInvalidPath($path)) {
            return null;
        }

        $path = explode('.', $path);
        $lastKey = array_pop($path);
        $pointer = &$array;

        foreach ($path as $newPosition) {
            if (!isset($pointer[$newPosition]) || !is_array($pointer[$newPosition])) {
                unset($pointer);
                return null;
            }
            $pointer = &$pointer[$newPosition];
        }

        $value = $pointer[$lastKey];
        unset($pointer);
        return $value;
    }

    private static function isInvalidPath($path)
    {
        return !self::isValidPath($path);
    }

    private static function isValidPath($path)
    {
        return is_string($path) && $path !== '';
    }

    /**
     * @param array $array
     * @param string $path
     */
    public static function unsetInPath(&$array, $path)
    {
        $path = explode('.', $path);
        $lastKey = array_pop($path);
        $pointer = &$array;

        foreach ($path as $newPosition) {
            if (!isset($pointer[$newPosition]) || !is_array($pointer[$newPosition])) {
                unset($pointer);
                return;
            }
            $pointer = &$pointer[$newPosition];
        }

        unset($pointer[$lastKey], $pointer);
    }

    /**
     * @param array $array
     * @param string $path
     * @param string $type
     * @return null
     */
    public static function castInPath(&$array, $path, $type)
    {
        if (self::isInvalidPath($path)) {
            return;
        }

        $path = explode('.', $path);
        $lastKey = array_pop($path);
        $pointer = &$array;

        foreach ($path as $newPosition) {
            if (!isset($pointer[$newPosition]) || !is_array($pointer[$newPosition])) {
                unset($pointer);
                return;
            }
            $pointer = &$pointer[$newPosition];
        }

        settype($pointer[$lastKey], $type);
        unset($pointer);
    }

    /**
     * The writeNestedValue function is simply (over)writing data to arrays, this one tries to merge data when possible
     *
     * @param array $array
     * @param string $path
     * @param mixed $value
     */
    public static function integrateIntoPath(&$array, $path, $value)
    {
        if (self::isInvalidPath($path)) {
            return;
        }

        $path = explode('.', $path);
        $lastKey = array_pop($path);
        $pointer = &$array;

        foreach ($path as $newPosition) {
            if (!isset($pointer[$newPosition]) || !is_array($pointer[$newPosition])) {
                $pointer[$newPosition] = [];
            }
            $pointer = &$pointer[$newPosition];
        }

        //If value and target are both array, concatenate, otherwise overwrite
        if (is_array($value) && is_array($pointer[$lastKey])) {
            $pointer[$lastKey] = $pointer[$lastKey] + $value;
        } else {
            $pointer[$lastKey] = $value;
        }

        unset($pointer);
    }

    /**
     * @param array $array
     * @param string $path
     * @param mixed $value
     */
    public static function writeToPath(&$array, $path, $value)
    {
        $path = explode('.', $path);
        $lastKey = array_pop($path);
        $pointer = &$array;

        foreach ($path as $newPosition) {
            if (!isset($pointer[$newPosition]) || !is_array($pointer[$newPosition])) {
                $pointer[$newPosition] = [];
            }
            $pointer = &$pointer[$newPosition];
        }

        $pointer[$lastKey] = $value;
        unset($pointer);
    }
}

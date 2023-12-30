<?php

if (!function_exists("array_is_list")) {
    function array_is_list(array $array): bool
    {
        if ([] === $array || $array === array_values($array)) {
            return true;
        }

        $nextKey = -1;

        foreach ($array as $k => $v) {
            if ($k !== ++$nextKey) {
                return false;
            }
        }

        return true;
    }
}


if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        if (!is_string($haystack) || !is_string($needle)) {
            return false;
        }
        return strlen($needle) === 0 || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        if (!is_string($haystack) || !is_string($needle)) {
            return false;
        }
        return strlen($needle) === 0 || strpos($haystack, $needle) !== false;
    }
}


// Technically not a polyfill since PHP doesn't have
// this function, but it should! Also, this is where
// PHP gets icky. For values, PHP uses array_values()
// for arrays, but for objects, PHP uses get_object_vars().
// So intuitive! So, should this be called object_keys()
// or get_object_keys()? I don't know. I chose the
// shorter to match array_keys(). ¯\_(ツ)_/¯
if (!function_exists("object_keys")) {
    function object_keys(object|array $object): array
    {
        if (is_array($object)) {
            return array_keys($object);
        }

        if (is_object($object)) {
            $array = get_object_vars($object);
            $properties = array_keys($array);
            return $properties;
        }

        return [];
    }
}

if (!function_exists("object_merge")) {
    function object_merge($object, $merge): object
    {
        if (!is_object($merge) && !is_array($merge)) {
            return $object;
        }

        foreach ($merge as $key => $value) {
            $object->$key = $value;
        }
        return $object;
    }
}

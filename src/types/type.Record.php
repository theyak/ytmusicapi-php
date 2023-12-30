<?php

namespace Ytmusicapi;

class Record
{
    public static function from($object)
    {
        $properties = get_class_vars(static::class);
        $newObject = new static();
        foreach ($properties as $property => $value) {
            if (isset($object->$property)) {
                $newObject->$property = $object->$property;
            }
        }
        return $newObject;
    }
}

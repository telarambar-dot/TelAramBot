<?php

namespace RubikaBot\Models;

class Model
{
    /**
     * @param array|null $data
     * @return static|null
     */
    public static function fromArray($data)
    {
        if (empty($data) || !is_array($data)) {
            return null;
        }

        $instance = new static();
        $reflection = new \ReflectionClass($instance);

        foreach ($data as $key => $value) {
            if (!property_exists($instance, $key) || $value === null) {
                continue;
            }

            try {
                $property = $reflection->getProperty($key);
                $instance->{$key} = self::hydratePropertyValue($property, $value);
            } catch (\ReflectionException $e) {
                $instance->{$key} = $value;
            }
        }

        return $instance;
    }

    /**
     * @param \ReflectionProperty $property
     * @param mixed $value
     * @return mixed
     */
    private static function hydratePropertyValue(\ReflectionProperty $property, $value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $type = self::getPropertyType($property);

        if ($type === 'array') {
            return self::parseArrayProperty($value);
        }

        // support docblocks like "Type[]"
        if ($type && preg_match('/^(.+)\[\]$/', $type, $m)) {
            $itemType = $m[1];
            $parsed = array();
            foreach ($value as $item) {
                if (class_exists($itemType)) {
                    $parsed[] = $itemType::fromArray($item);
                } else {
                    $parsed[] = $item;
                }
            }
            return $parsed;
        }

        if ($type && class_exists($type)) {
            return $type::fromArray($value);
        }

        return $value;
    }

    /**
     * @param \ReflectionProperty $property
     * @return string|null
     */
    private static function getPropertyType(\ReflectionProperty $property)
    {
        $doc = $property->getDocComment();

        if ($doc === false) {
            return null;
        }

        if (preg_match('/@var\s+([^\s]+)/', $doc, $matches)) {
            $raw = trim($matches[1]);

            // If union type like "Type|null", pick first non-null part
            if (strpos($raw, '|') !== false) {
                $parts = preg_split('/\|/', $raw);
                foreach ($parts as $p) {
                    if (strtolower($p) !== 'null') {
                        $raw = $p;
                        break;
                    }
                }
            }

            // Normalize common names
            if (strtolower($raw) === 'array') {
                return 'array';
            }

            // Strip nullable syntax and trailing brackets handled elsewhere
            $raw = trim($raw, "\\\n\r\t ");

            // If it's an array typed like Type[], keep that format
            if (preg_match('/^(.+)\[\]$/', $raw)) {
                return $raw;
            }

            // Try to resolve class name to fully-qualified class
            $tryNames = array($raw);
            if ($raw[0] !== '\\') {
                // try declaring class namespace
                try {
                    $declNs = $property->getDeclaringClass()->getNamespaceName();
                    if ($declNs) {
                        $tryNames[] = $declNs . '\\' . $raw;
                    }
                } catch (\ReflectionException $e) {
                    // ignore
                }
            } else {
                $tryNames[] = ltrim($raw, '\\');
            }

            foreach ($tryNames as $name) {
                if (class_exists($name)) {
                    return $name;
                }
            }

            // fallback to raw
            return $raw;
        }

        return null;
    }

    /**
     * @param array $value
     * @return array
     */
    private static function parseArrayProperty(array $value)
    {
        $parsed = array();
        foreach ($value as $item) {
            $parsed[] = $item;
        }
        return $parsed;
    }

    /**
     * @param array $data
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected static function value(array $data, $key, $default = null)
    {
        return isset($data[$key]) ? $data[$key] : $default;
    }
}

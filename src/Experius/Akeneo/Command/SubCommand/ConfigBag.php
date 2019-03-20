<?php
/**
 * this file is part of ziggy
 *
 * @author Mr. Lewis <https://github.com/lewisvoncken>
 */

namespace Experius\Akeneo\Command\SubCommand;

/**
 * Class ConfigBag
 *
 * @package Experius\Magento\Command\SubCommand
 */
class ConfigBag extends \ArrayObject
{
    /**
     * @param string $key
     * @param bool $value
     *
     * @return $this
     */
    public function setBool($key, $value)
    {
        if (!is_null($value) && !is_bool($value)) {
            throw new \InvalidArgumentException('Type must be of type bool');
        }
        $this->offsetSet($key, (bool) $value);

        return $this;
    }

    /**
     * @param string $key
     * @param bool $value
     *
     * @return $this
     */
    public function setInt($key, $value)
    {
        if (!is_null($value) && !is_int($value)) {
            throw new \InvalidArgumentException('Type must be of type int');
        }
        $this->offsetSet($key, (int) $value);

        return $this;
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return $this
     */
    public function setString($key, $value)
    {
        if (!is_null($value) && !is_string($value)) {
            throw new \InvalidArgumentException('Type must be of type string');
        }
        $this->offsetSet($key, (string) $value);

        return $this;
    }

    /**
     * @param string $key
     * @param float $value
     *
     * @return $this
     */
    public function setFloat($key, $value)
    {
        if (!is_null($value) && !is_float($value)) {
            throw new \InvalidArgumentException('Type must be of type float');
        }
        $this->offsetSet($key, (float) $value);

        return $this;
    }

    /**
     * @param string $key
     * @param array $value
     *
     * @return $this
     */
    public function setArray($key, array $value)
    {
        $this->offsetSet($key, $value);

        return $this;
    }

    /**
     * @param string $key
     * @param object $value
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setObject($key, $value)
    {
        if (!is_null($value) && !is_object($value)) {
            throw new \InvalidArgumentException('Type must be of type object');
        }

        $this->offsetSet($key, $value);

        return $this;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function getBool($key)
    {
        return (bool) $this->offsetGet($key);
    }

    /**
     * @param string $key
     * @return int
     */
    public function getInt($key)
    {
        return (int) $this->offsetGet($key);
    }

    /**
     * @param string $key
     * @return string
     */
    public function getString($key)
    {
        return (string) $this->offsetGet($key);
    }

    /**
     * @param string $key
     * @return float
     */
    public function getFloat($key)
    {
        return (float) $this->offsetGet($key);
    }

    /**
     * @param string $key
     * @return array
     */
    public function getArray($key)
    {
        return (array) $this->offsetGet($key);
    }

    /**
     * @param string $key
     * @return object
     */
    public function getObject($key)
    {
        return $this->offsetGet($key);
    }
}

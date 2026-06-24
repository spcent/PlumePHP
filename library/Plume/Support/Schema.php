<?php

declare(strict_types=1);

class PlumeSchema implements \JsonSerializable
{
    /**
     * Array or ArrayObject that gets filled with
     * data from $json or PlumeParam
     * @var array
     */
    protected $filledFields;

    public function __construct(?PlumeParam $param = null)
    {
        if (null !== $param) {
            $mapper = new PlumeJsonMapper();
            $mapper->bEnforceMapType = false;
    
            return $mapper->map($param->toArray(), $this);
        }
    }

    /**
     * Create schema from the PlumeParam.
     */
    public static function createFromPlumeParam(PlumeParam $param)
    {
        return new static($param);
    }

    /**
     * json serialize.
     */
    public function jsonSerialize(): mixed
    {
        $reflectObj = new \ReflectionClass($this);
        $res = [];
        foreach ($reflectObj->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $name = $this->camelCaseToSnake($property->getName());
                $res[$name] = $property->getValue($this);
            }
        }

        return $res;
    }

    /**
     * Converts the camel case the snake case.
     */
    protected function camelCaseToSnake(string $input): string
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match === strtoupper($match) ? strtolower($match) : lcfirst($match);
        }

        return implode('_', $ret);
    }
}
/**
 * Automatically map JSON structures into objects.
 * Continuously mapping your JSON responses to your own objects becomes
 * tedious and is error prone.
 * Not mentioning the tests that needs to be written for said mapping.
 * JsonMapper has been build with the most common usages in mind.
 * In order to allow for those edge cases which are not supported by default,
 * it can easily be extended as its core has been designed using middleware.
 *
 * @category Netresearch
 * @package  JsonMapper
 * @author   Christian Weiske <cweiske@cweiske.de>
 * @license  OSL-3.0 http://opensource.org/licenses/osl-3.0
 * @link     http://cweiske.de/
 */

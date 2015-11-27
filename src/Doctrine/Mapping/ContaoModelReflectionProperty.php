<?php

namespace Contao\CoreBundle\Doctrine\Mapping;

class ContaoModelReflectionProperty extends \ReflectionProperty
{
    private $className;
    private $propertyName;

    public function __construct($class, $name)
    {
        $this->className = $class;
        $this->propertyName = $name;
    }

    public function getValue($object = null)
    {
        return $object->__get($this->propertyName);
    }

    public function setValue($object, $value = null)
    {
        $object->__set($this->propertyName, $value);
    }

    public function isPublic()
    {
        return true;
    }

    public function isPrivate()
    {
        return false;
    }

    public function isProtected()
    {
        return false;
    }

    public function isStatic()
    {
        return false;
    }

    public function isDefault()
    {
        return false;
    }

    public function getDeclaringClass()
    {
        return $this->className;
    }

    public function getName()
    {
        return $this->propertyName;
    }

    public function getDocComment()
    {
        return '';
    }

    public function setAccessible($accessible)
    {
        // does nothing
    }
}
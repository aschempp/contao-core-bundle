<?php

namespace Contao\CoreBundle\Doctrine\Mapping;

class ContaoModelReflectionProperty extends \ReflectionProperty
{
    private $key;

    public function __construct($class, $name)
    {
        if (0 === strpos($name, 'relation(field=')) {
            $this->key = substr($name, 15, -1);
            parent::__construct($class, 'arrRelated');
        } else {
            $this->key = $name;
            parent::__construct($class, 'arrData');
        }

        $this->setAccessible(true);
    }

    public function getValue($object = null)
    {
        $data = parent::getValue($object);

        return $data[$this->key];
    }

    public function setValue($object, $value = null)
    {
        $data = parent::getValue($object);

        $data[$this->key] = $value;

        parent::setValue($object, $data);
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

    public function getName()
    {
        return $this->key;
    }

    public function getDocComment()
    {
        return '';
    }
}
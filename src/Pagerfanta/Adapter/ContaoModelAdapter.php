<?php

namespace AppBundle\Pagerfanta\Adapter;

use Contao\Model;
use Pagerfanta\Adapter\AdapterInterface;

class ContaoModelAdapter implements AdapterInterface
{
    /**
     * @var Model
     */
    private $modelClass;

    /**
     * @var mixed
     */
    private $column;

    /**
     * @var mixed
     */
    private $value;

    /**
     * @var array
     */
    private $options;

    /**
     * Constructor.
     *
     * @param string $modelClass
     * @param mixed  $column
     * @param mixed  $value
     * @param array  $options
     */
    function __construct($modelClass, $column = null, $value = null, array $options = [])
    {
        $this->modelClass = $modelClass;
        $this->column     = $column;
        $this->value      = $value;
        $this->options    = $options;
    }

    /**
     * Returns the number of results.
     *
     * @return integer The number of results.
     */
    public function getNbResults()
    {
        $class = $this->modelClass;

        return $class::countBy($this->column, $this->value, $this->options);
    }

    /**
     * Returns an slice of the results.
     *
     * @param integer $offset The offset.
     * @param integer $length The length.
     *
     * @return array|\Traversable The slice.
     */
    public function getSlice($offset, $length)
    {
        $class   = $this->modelClass;
        $options = $this->options;

        $options['offset'] = $offset;
        $options['limit']  = $length;

        $collection = $class::findBy($this->column, $this->value, $options);

        if (null === $collection) {
            return [];
        }

        return $collection;
    }
}

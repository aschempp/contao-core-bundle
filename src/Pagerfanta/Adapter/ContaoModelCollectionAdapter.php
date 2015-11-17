<?php

namespace AppBundle\Pagerfanta\Adapter;

use Contao\Model\Collection;
use Pagerfanta\Adapter\AdapterInterface;

class ContaoModelCollectionAdapter implements AdapterInterface
{
    /**
     * @var Collection
     */
    private $collection;

    /**
     * Constructor.
     *
     * @param Collection $collection
     */
    function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * Returns the number of results.
     *
     * @return integer The number of results.
     */
    public function getNbResults()
    {
        return $this->collection->count();
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
        return array_slice($this->collection->getModels(), $offset, $length);
    }
}

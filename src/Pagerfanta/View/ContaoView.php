<?php

namespace AppBundle\Pagerfanta\View;

use Contao\Pagination;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Pagerfanta\View\ViewInterface;

class ContaoView implements ViewInterface
{
    /**
     * @var int
     */
    private $numberOfLinks;
    /**
     * @var string
     */
    private $parameter;
    /**
     * @var \Template
     */
    private $template;
    /**
     * @var bool
     */
    private $forceParam;
    /**
     * @var string
     */
    private $separator;

    /**
     * Constructor.
     *
     * @param int       $numberOfLinks
     * @param string    $parameter
     * @param \Template $template
     * @param bool      $forceParam
     * @param string    $separator
     */
    public function __construct(
        $numberOfLinks = 7,
        $parameter = 'page',
        \Template $template = null,
        $forceParam = false,
        $separator = ' '
    ) {
        $this->numberOfLinks = $numberOfLinks;
        $this->parameter = $parameter;
        $this->template = $template;
        $this->forceParam = $forceParam;
        $this->separator = $separator;
    }

    /**
     * @param PagerfantaInterface|Pagerfanta $pagerfanta     A pagerfanta.
     * @param mixed                          $routeGenerator A callable to generate the routes.
     * @param array                          $options        An array of options (optional).
     */
    public function render(PagerfantaInterface $pagerfanta, $routeGenerator, array $options = array())
    {
        $pagination = new Pagination(
            $pagerfanta->count(),
            $pagerfanta->getMaxPerPage(),
            $this->numberOfLinks,
            $this->parameter,
            $this->template,
            $this->forceParam
        );

        return $pagination->generate($this->separator);
    }

    /**
     * Returns the canonical name.
     *
     * @return string The canonical name.
     */
    public function getName()
    {
        return 'contao';
    }
}

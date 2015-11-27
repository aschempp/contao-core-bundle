<?php

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2015 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\CoreBundle\Cache;


use Contao\CoreBundle\Doctrine\Mapping\ContaoModelRuntimeReflectionService;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class DoctrineMappingCacheWarmer implements CacheWarmerInterface
{
    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;
    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * Constructor.
     *
     * @param ContaoFrameworkInterface $framework
     * @param Registry                 $doctrine
     */
    public function __construct(ContaoFrameworkInterface $framework, Registry $doctrine)
    {
        $this->framework = $framework;
        $this->doctrine  = $doctrine;
    }

    /**
     * Checks whether this warmer is optional or not.
     * Optional warmers can be ignored on certain conditions.
     * A warmer should return true if the cache can be
     * generated incrementally and on-demand.
     *
     * @return bool true if the warmer is optional, false otherwise
     */
    public function isOptional()
    {
        return false;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        $this->doctrine
            ->getManager()
            ->getMetadataFactory()
            ->setReflectionService(
                new ContaoModelRuntimeReflectionService($this->framework)
            )
        ;
    }
}

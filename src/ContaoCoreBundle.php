<?php

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\CoreBundle;

use Contao\CoreBundle\DependencyInjection\Compiler\AddPackagesPass;
use Contao\CoreBundle\DependencyInjection\Compiler\AddResourcesPathsPass;
use Contao\CoreBundle\DependencyInjection\Compiler\AddSessionBagsPass;
use Contao\CoreBundle\DependencyInjection\Compiler\DoctrineMappingDriverPass;
use Contao\CoreBundle\DependencyInjection\ContaoCoreExtension;
use Doctrine\DBAL\Types\Type;
use Contao\CoreBundle\Doctrine\Mapping\ContaoModelRuntimeReflectionService;
use Patchwork\Utf8\Bootup;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Configures the Contao core bundle.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 * @author Andreas Schempp <https://github.com/aschempp>
 */
class ContaoCoreBundle extends Bundle
{
    const SCOPE_BACKEND = 'backend';
    const SCOPE_FRONTEND = 'frontend';

    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        return new ContaoCoreExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        Bootup::initAll();

        if (!Type::hasType('uuid')) {
            Type::addType('uuid', 'Contao\CoreBundle\Doctrine\DBAL\Types\UuidType');
        }

        if (!Type::hasType('uuid_array')) {
            Type::addType('uuid_array', 'Contao\CoreBundle\Doctrine\DBAL\Types\UuidArrayType');
            )
        }

        $this->container
            ->get('doctrine.orm.entity_manager')
            ->getMetadataFactory()
            ->setReflectionService(
                new ContaoModelRuntimeReflectionService($this->container->get('contao.framework'))
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(
            new AddPackagesPass($container->getParameter('kernel.root_dir').'/../vendor/composer/installed.json')
        );

        $container->addCompilerPass(new AddSessionBagsPass());
        $container->addCompilerPass(new AddResourcesPathsPass());
        $container->addCompilerPass(new DoctrineMappingDriverPass());
    }
}

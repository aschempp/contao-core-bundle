<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @package Core
 * @link    https://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace Contao\CoreBundle\Controller;

use Contao\BackendPage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backend controller to show the page selection view.
 *
 * @author Tristan Lins <https://github.com/tristanlins>
 */
class BackendPageController
{
    /**
     * Run the controller
     */
    public function runAction()
    {
        ob_start();

        $controller = new BackendPage();
        $controller->run();

        return new Response(ob_get_clean());
    }
}

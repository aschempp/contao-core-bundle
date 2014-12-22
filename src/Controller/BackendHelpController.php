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

use Contao\BackendHelp;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backend controller to show the help view.
 *
 * @author Tristan Lins <https://github.com/tristanlins>
 */
class BackendHelpController
{
    /**
     * Run the controller
     */
    public function runAction()
    {
        ob_start();

        $controller = new BackendHelp();
        $controller->run();

        return new Response(ob_get_clean());
    }
}
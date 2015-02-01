<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\CoreBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Displays the maintenance mode message if enabled (see #4561 and #6353).
 *
 * @author Andreas Schempp <http://terminal42.ch>
 */
class MaintenanceModeListener
{
    /**
     * Render maintenance mode message if necessary.
     *
     * @param GetResponseEvent $event The event object
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        // No back end user logged in
        if (!$_SESSION['DISABLE_CACHE'] && \Config::get('maintenanceMode')) {
            header('HTTP/1.1 503 Service Unavailable');
            die_nicely('be_unavailable', 'This site is currently down for maintenance. Please come back later.');

            // FIXME: create a response
            // $event->setResponse($response);
        }
    }
}

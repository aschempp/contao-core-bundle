<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\CoreBundle\Templating;

use Symfony\Component\HttpFoundation\RequestStack;


/**
 * Provides information about static urls.
 *
 * @author Leo Feyer <https://contao.org>
 * @author Andreas Schempp <https://www.terminal42.ch>
 */
class StaticUrlProvider
{
    private $requestStack;
    private $debug = false;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;

        // FIXME: can we use Symfony environment for debug check?
        $this->debug = \Config::get('debugMode');
    }

    /**
     * Gets the static files URL.
     *
     * @return string The static files URL
     */
    public function getFilesUrl()
    {
        return $this->getProperty('staticFiles', $this->getPageFromRequest());
    }

    /**
     * Gets the static assets URL.
     *
     * @return string The static assets URL
     */
    public function getAssetsUrl()
    {
        return $this->getProperty('staticPlugins', $this->getPageFromRequest());
    }

    /**
     * Gets a static property from page model or global configuration.
     *
     * @param string     $property The static property to get
     * @param \PageModel $page     The optional page model
     *
     * @return string The static property value
     */
    public function getProperty($property, \PageModel $page = null)
    {
        if (!$this->debug) {
            $url = (null !== $page) ? $page->$property : \Config::get($property);

            if ('' != $url) {
                // FIXME: get path from request
                return '//' . preg_replace('@https?://@', '', $url) . \Environment::get('path') . '/';
            }
        }

        return '';
    }

    /**
     * Adds the static URL to an asset
     *
     * @param string $script The script path
     *
     * @return string The script path with the static URL
     */
    public function addStaticUrlToAsset($script)
    {
        return $this->addStaticUrlTo($this->getAssetsUrl(), $script);
    }

    /**
     * Adds the static URL to a file
     *
     * @param string $file The file path
     *
     * @return string The file path with the static URL
     */
    public function addStaticUrlToFile($file)
    {
        return $this->addStaticUrlTo($this->getFilesUrl(), $file);
    }

    /**
     * Gets the page model from current request.
     *
     * @return \PageModel|null
     */
    private function getPageFromRequest()
    {
        $page    = null;
        $request = $this->requestStack->getCurrentRequest();

        if ($request->attributes->has('contentDocument')) {
            $page = $request->attributes->get('contentDocument');

            if (!($page instanceof \PageModel)) {
                $page = null;
            }
        }

        return $page;
    }

    /**
     * Adds a static URL to a relative path
     *
     * @param string $static The static URL
     * @param string $path   The relative path
     *
     * @return string The relative path with static URL
     */
    private function addStaticUrlTo($static, $path)
    {
        // The feature is not used
        if ('' === $static) {
            return $path;
        }

        // Absolute URLs
        if (preg_match('@^https?://@', $path)) {
            return $path;
        }

        return $static . $path;
    }
}

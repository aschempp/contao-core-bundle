<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\CoreBundle\Templating;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class LayoutProvider implements LayoutProviderInterface
{
    private $page;
    private $layout;

    public function __construct(\PageModel $page, \LayoutModel $layout)
    {
        $this->page = $page;
        $this->layout = $layout;
    }


    public static function createFromMasterRequest(RequestStack $requestStack)
    {
        $request = $requestStack->getMasterRequest();
        $page    = $request->attributes->get('contentDocument');

        if (!($page instanceof \PageModel)) {
            // FIXME: throw PageNotFoundException
        }

        $layoutId = (static::isMobile($request, $page) && $page->mobileLayout) ? $page->mobileLayout : $page->layout;
        $layout   = \LayoutModel::findByPk($layoutId);

        // Die if there is no layout
        if (null === $layout) {
            // FIXME: throw NoLayoutException
            /*header('HTTP/1.1 501 Not Implemented');
            $this->log('Could not find layout ID "' . $intId . '"', __METHOD__, TL_ERROR);
            die_nicely('be_no_layout', 'No layout specified');*/
        }

        // HOOK: modify the page or layout object (see #4736)
        if (isset($GLOBALS['TL_HOOKS']['getPageLayout']) && is_array($GLOBALS['TL_HOOKS']['getPageLayout'])) {
            foreach ($GLOBALS['TL_HOOKS']['getPageLayout'] as $callback) {
                \System::importStatic($callback[0])->$callback[1]($page, $layout); // FIXME: third parameter $this missing
            }
        }

        return new static($page, $layout);
    }

    /**
     * {@inheritdocs}
     */
    public function getPageModel()
    {
        return $this->page;
    }

    /**
     * {@inheritdocs}
     */
    public function getLayoutModel()
    {
        return $this->layout;
    }

    /**
     * {@inheritdocs}
     */
    public function isRTL()
    {
        return 'rtl' === $GLOBALS['TL_LANG']['MSC']['textDirection'];
    }

    /**
     * {@inheritdocs}
     */
    public function getTemplateName()
    {
        return $this->layout->template ?: 'fe_page';
    }

    /**
     * {@inheritdocs}
     */
    public function getTemplateGroup()
    {
        $theme = $this->layout->getRelated('pid');

        return (null !== $theme) ? $theme->templates : '';
    }

    /**
     * {@inheritdocs}
     */
    public function getOutputFormat()
    {
        list($format,) = explode('_', $this->layout->doctype);

        return $format;
    }

    /**
     * {@inheritdocs}
     */
    public function getOutputVariant()
    {
        list(,$variant) = explode('_', $this->layout->doctype);

        return $variant;
    }

    /**
     * {@inheritdocs}
     */
    public function getLanguage()
    {
        return $this->page->language;
    }

    /**
     * {@inheritdocs}
     */
    public function getCharacterSet()
    {
        return \Config::get('characterSet');
    }

    /**
     * {@inheritdocs}
     */
    public function getBasePath()
    {
        return \Environment::get('base');
    }

    /**
     * {@inheritdocs}
     */
    public function getMetaTitle()
    {
        $title  = $this->layout->titleTag;

        // Fall back to the default title tag
        if ('' === $title) {
            $title = '{{page::pageTitle}} - {{page::rootPageTitle}}';
        }

        return strip_insert_tags(\Controller::replaceInsertTags($title)); // see #7097
    }

    /**
     * {@inheritdocs}
     */
    public function getMetaDescription()
    {
        return str_replace(["\n", "\r", '"'], [' ' , '', ''], $this->page->description);
    }

    /**
     * {@inheritdocs}
     */
    public function getViewport()
    {
        return $this->layout->viewport;
    }

    /**
     * {@inheritdocs}
     */
    public function getRobots()
    {
        return $this->page->robots ?: 'index,follow';
    }

    /**
     * {@inheritdocs}
     */
    public function getBodyOnload()
    {
        return trim($this->layout->onload);
    }

    /**
     * {@inheritdocs}
     */
    public function getBodyClass()
    {
        return trim($this->layout->cssClass . ' ' . $this->page->cssClass);
    }

    /**
     * {@inheritdocs}
     */
    public function useContaoFramework()
    {
        return in_array('layout.css', $this->getFrameworkStyleSheets());
    }

    /**
     * {@inheritdocs}
     */
    public function isResponsive()
    {
        $framework = $this->getFrameworkStyleSheets();

        return (in_array('layout.css', $framework) && in_array('responsive.css', $framework));
    }

    /**
     * {@inheritdocs}
     */
    public function hasWrapper()
    {
        return ($this->layout->static && $this->hasSize($this->layout->width));
    }

    /**
     * {@inheritdocs}
     */
    public function hasHeader()
    {
        return ('2rwh' === $this->layout->rows || '3rw' === $this->layout->rows);
    }

    /**
     * {@inheritdocs}
     */
    public function hasFooter()
    {
        return ('2rwf' === $this->layout->rows || '3rw' === $this->layout->rows);
    }

    /**
     * {@inheritdocs}
     */
    public function hasLeftColumn()
    {
        return ('2cll' === $this->layout->cols || '3cl' === $this->layout->cols);
    }

    /**
     * {@inheritdocs}
     */
    public function hasRightColumn()
    {
        return ('2clr' === $this->layout->cols || '3cl' === $this->layout->cols);
    }

    /**
     * {@inheritdocs}
     */
    public function getWrapperWidth()
    {
        return $this->getSize($this->layout->width);
    }

    /**
     * {@inheritdocs}
     */
    public function getWrapperAlignment()
    {
        return $this->layout->align;
    }

    /**
     * {@inheritdocs}
     */
    public function getHeaderHeight()
    {
        return $this->getSize($this->layout->headerHeight);
    }

    /**
     * {@inheritdocs}
     */
    public function getFooterHeight()
    {
        return $this->getSize($this->layout->footerHeight);
    }

    /**
     * {@inheritdocs}
     */
    public function getLeftColumnWidth()
    {
        return $this->getSize($this->layout->widthLeft);
    }

    /**
     * {@inheritdocs}
     */
    public function getRightColumnWidth()
    {
        return $this->getSize($this->layout->widthRight);
    }

    /**
     * {@inheritdocs}
     */
    public function getModuleIdsBySection()
    {
        $sections = [];
        $moduleConfig = deserialize($this->layout->modules);

        if (empty($moduleConfig) || !is_array($moduleConfig)) {
            return [];
        }

        foreach ($moduleConfig as $config) {

            // Disabled module
            if (!$config['enable']) {
                continue;
            }

            $moduleId = $config['mod'];
            $inColumn = $config['col'];

            // Filter active sections (see #3273)
            if (('header' === $inColumn && !$this->hasHeader())
                || ('left' === $inColumn && !$this->hasLeftColumn())
                || ('right' === $inColumn && $this->hasRightColumn())
                || ('footer' === $inColumn && !$this->hasFooter())
            ) {
                continue;
            }

            $sections[$inColumn][] = $moduleId;
        }

        return $sections;
    }

    /**
     * {@inheritdocs}
     */
    public function getCustomSectionPosition()
    {
        return $this->layout->sPosition;
    }

    /**
     * {@inheritdocs}
     */
    public function getFrameworkStyleSheets()
    {
        return $this->getUnserializedArray($this->layout->framework);
    }

    /**
     * {@inheritdocs}
     */
    public function getInternalStyleSheets()
    {
        return $this->getUnserializedArray($this->layout->stylesheet);
    }

    /**
     * {@inheritdocs}
     */
    public function getExternalStyleSheets()
    {
        $ids   = $this->getUnserializedArray($this->layout->external);
        $order = $this->getUnserializedArray($this->layout->orderExt);

        // Consider the sorting order (see #5038)
        if (!empty($order)) {

            // Remove all values
            $order = array_map(function(){}, array_flip($order));

            // Move the matching elements to their position in $arrOrder
            foreach ($ids as $k => $v) {
                if (array_key_exists($v, $order)) {
                    $order[$v] = $v;
                    unset($ids[$k]);
                }
            }

            // Append the left-over style sheets at the end
            if (!empty($ids)) {
                $order = array_merge($order, array_values($ids));
            }

            // Remove empty (unreplaced) entries
            $ids = array_values(array_filter($order));
            unset($order);
        }

        return $ids;
    }

    /**
     * {@inheritdocs}
     */
    public function getStyleSheetLoadingOrder()
    {
        return $this->layout->loadingOrder;
    }

    /**
     * {@inheritdocs}
     */
    public function getGoogleFonts()
    {
        return $this->layout->webfonts;
    }

    /**
     * {@inheritdocs}
     */
    public function getNewsFeeds()
    {
        return $this->getUnserializedArray($this->layout->newsfeeds);
    }

    /**
     * {@inheritdocs}
     */
    public function getCalendarFeeds()
    {
        return $this->getUnserializedArray($this->layout->calendarfeeds);
    }

    /**
     * {@inheritdocs}
     */
    public function getCustomHeadTags()
    {
        return $this->layout->head;
    }

    /**
     * {@inheritdocs}
     */
    public function getCustomScriptTags()
    {
        return $this->layout->script;
    }

    /**
     * {@inheritdocs}
     */
    public function getScriptTemplates()
    {
        return $this->getUnserializedArray($this->layout->scripts);
    }

    /**
     * {@inheritdocs}
     */
    public function getAnalyticsTemplates()
    {
        return $this->getUnserializedArray($this->layout->analytics);
    }

    /**
     * {@inheritdocs}
     */
    public function hasJQuery()
    {
        return (bool) $this->layout->addJQuery;
    }

    /**
     * {@inheritdocs}
     */
    public function getJQueryTemplates()
    {
        return $this->getUnserializedArray($this->layout->jquery);
    }

    /**
     * {@inheritdocs}
     */
    public function hasMooTools()
    {
        return (bool) $this->layout->addMooTools;
    }

    /**
     * {@inheritdocs}
     */
    public function getMooToolsTemplates()
    {
        return $this->getUnserializedArray($this->layout->mootools);
    }




    private function hasSize($size)
    {
        $size = deserialize($size);

        return (isset($size['value']) && $size['value'] != '' && $size['value'] >= 0);
    }

    private function getSize($size)
    {
        $size = deserialize($size);

        if (isset($size['value']) && $size['value'] != '' && $size['value'] >= 0) {
            return $size['value'] . $size['unit'];
        }

        return '';
    }

    /**
     * Gets array from serialized value or empty array
     *
     * @param string $value The serialized value
     *
     * @return array The unserialized or empty array
     */
    private function getUnserializedArray($value)
    {
        if ('' === $value) {
            return [];
        }

        $value = deserialize($value);

        return is_array($value) ? $value : [];
    }

    /**
     * @param Request    $request
     * @param \PageModel $page
     *
     * @return bool
     */
    private static function isMobile(Request $request, \PageModel $page)
    {
        $isMobile = ($page->mobileLayout && \Environment::get('agent')->mobile);

        // Override the autodetected value
        // FIXME: this part should be in environment class or similar
        if ('mobile' === $request->cookies->get('TL_VIEW')) {
            $isMobile = true;
        } elseif ('desktop' === $request->cookies->get('TL_VIEW')) {
            $isMobile = false;
        }

        return $isMobile;
    }
}

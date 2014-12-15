<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\CoreBundle\Templating;

interface LayoutProviderInterface
{
    /**
     * @return \PageModel
     */
    public function getPageModel();

    /**
     * @return \LayoutModel
     */
    public function getLayoutModel();

    /**
     * @return bool
     */
    public function isRTL();

    /**
     * @return string
     */
    public function getTemplateName();

    /**
     * @return string
     */
    public function getTemplateGroup();

    public function getOutputFormat();

    public function getOutputVariant();

    public function getLanguage();

    public function getCharacterSet();

    public function getBasePath();

    /**
     * @return string
     */
    public function getMetaTitle();

    public function getMetaDescription();

    public function getViewport();

    public function getRobots();

    public function getBodyOnload();

    public function getBodyClass();


    public function useContaoFramework();
    public function isResponsive();

    public function hasWrapper();
    public function hasHeader();
    public function hasFooter();
    public function hasLeftColumn();
    public function hasRightColumn();

    public function getWrapperWidth();
    public function getWrapperAlignment();
    public function getHeaderHeight();
    public function getFooterHeight();
    public function getLeftColumnWidth();
    public function getRightColumnWidth();

    public function getModuleIdsBySection();
    public function getCustomSectionPosition();

    public function getFrameworkStyleSheets();
    public function getInternalStyleSheets();
    public function getExternalStyleSheets();
    public function getStyleSheetLoadingOrder();

    public function getGoogleFonts();
    public function getNewsFeeds();
    public function getCalendarFeeds();

    public function getCustomHeadTags();
    public function getCustomScriptTags();

    public function getScriptTemplates();
    public function getAnalyticsTemplates();


    /**
     * @return bool
     */
    public function hasJQuery();

    public function getJQueryTemplates();

    /**
     * @return bool
     */
    public function hasMooTools();

    public function getMooToolsTemplates();
}

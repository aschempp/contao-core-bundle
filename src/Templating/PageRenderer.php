<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\CoreBundle\Templating;

use Contao\CalendarFeedModel;
use Contao\Config;
use Contao\Controller;
use Contao\Environment;
use Contao\FilesModel;
use Contao\Frontend;
use Contao\FrontendTemplate;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\NewsFeedModel;
use Contao\StyleSheetModel;
use Contao\System;
use Contao\Template;

class PageRenderer
{
    /**
     * @var LayoutProviderInterface
     */
    private $layout;

    /**
     * @var StaticUrlProvider
     */
    private $staticUrls;

    public function __construct(StaticUrlProvider $staticUrls)
    {
        $this->staticUrls = $staticUrls;
    }


    public function generate(LayoutProviderInterface $layout)
    {
        $this->layout = $layout;

        $this->initGlobals();
        $this->initPageConfig();

        $template = $this->createTemplate($this->layout->getTemplateName());
        $sections = $this->generateSections();

        $this->addSectionsToTemplate($sections, $template);

        $this->addMetaDataToTemplate($template);

        $template->mootools = $this->generateFooterScripts();
        $template->stylesheets = $this->generateStyleSheets();
        $template->head = $this->generateHeaderScripts();

        return $template->parse();
    }


    private function initGlobals()
    {
        $GLOBALS['TL_KEYWORDS'] = '';
        $GLOBALS['TL_LANGUAGE'] = $this->layout->getLanguage();

        System::loadLanguageFile('default');

        // Static URLs
        Controller::setStaticUrls($this->layout->getPageModel());

        // Make sure TL_USER_CSS is set
        if (!is_array($GLOBALS['TL_USER_CSS'])) {
            $GLOBALS['TL_USER_CSS'] = array();
        }
    }


    private function initPageConfig()
    {
        $page = $this->layout->getPageModel();

        // Set the layout template and template group
        $page->template = $this->layout->getTemplateName();
        $page->templateGroup = $this->layout->getTemplateGroup();

        // Store the output format
        $page->outputFormat = $this->layout->getOutputFormat();
        $page->outputVariant = $this->layout->getOutputVariant();
    }


    /**
     * @param string $templateName
     *
     * @return FrontendTemplate
     */
    private function createTemplate($templateName)
    {
        /** @var FrontendTemplate|object $template */
        $template = new FrontendTemplate($templateName);

        $template->viewport   = '';
        $template->framework  = '';
        $template->mooScripts = '';

        // Initialize the sections
        $template->header = '';
        $template->left   = '';
        $template->main   = '';
        $template->right  = '';
        $template->footer = '';

        // Initialize the custom layout sections
        $template->sections  = array();
        $template->sPosition = $this->layout->getCustomSectionPosition();

        // Default settings
        $template->layout      = $this->layout->getLayoutModel();
        $template->language    = $this->layout->getLanguage();
        $template->charset     = $this->layout->getCharacterSet();
        $template->base        = $this->layout->getBasePath();
        $template->disableCron = Config::get('disableCron'); // FIXME: can we always disable cron with the new terminate event?
        $template->cronTimeout = Frontend::getCronTimeout();
        $template->isRTL       = $this->layout->isRTL();

        // Generate the CSS framework
        if ($this->layout->useContaoFramework()) {

            if ($this->layout->isResponsive()) {
                $template->viewport = '<meta name="viewport" content="width=device-width,initial-scale=1.0">' . "\n";
            }

            $template->framework = $this->generateFrameworkStyles();
        }

        // Overwrite the viewport tag (see #6251)
        if (($viewport = $this->layout->getViewport()) != '') {
            $template->viewport = '<meta name="viewport" content="' . $viewport . '">' . "\n";
        }

        return $template;
    }


    private function generateFrameworkStyles()
    {
        $framework = '';

        // Wrapper
        if ($this->layout->hasWrapper()) {
            $arrMargin = array(
                'left'   => '0 auto 0 0',
                'center' => '0 auto',
                'right'  => '0 0 0 auto'
            );

            $framework .= sprintf(
                '#wrapper{width:%s;margin:%s}',
                $this->layout->getWrapperWidth(),
                $arrMargin[$this->layout->getWrapperAlignment()]
            );
        }

        // Header
        if ($this->layout->hasHeader()) {
            $framework .= sprintf('#header{height:%s}', $this->layout->getHeaderHeight());
        }

        $container = '';

        // Left column
        if ($this->layout->hasLeftColumn()) {
            $leftWidth     = $this->layout->getLeftColumnWidth();
            $framework .= sprintf('#left{width:%s;right:%s}', $leftWidth, $leftWidth);
            $container .= sprintf('padding-left:%s;', $leftWidth);
        }

        // Right column
        if ($this->layout->hasRightColumn()) {
            $rightWidth    = $this->layout->getRightColumnWidth();
            $framework .= sprintf('#right{width:%s}', $rightWidth);
            $container .= sprintf('padding-right:%s;', $rightWidth);
        }

        // Main column
        if ('' !== $container) {
            $framework .= sprintf('#container{%s}', substr($container, 0, -1));
        }

        // Footer
        if ($this->layout->hasFooter()) {
            $framework .= sprintf('#footer{height:%s}', $this->layout->getFooterHeight());
        }

        // Add the layout specific CSS
        if ('' !== $framework) {
            return Template::generateInlineStyle($framework) . "\n";
        }

        return '';
    }


    private function generateSections()
    {
        $sections = [];
        $modules  = $this->layout->getModuleIdsBySection();

        if (empty($modules)) {
            return [];
        }

        // Create a mapper array in case a module is included more than once (see #4849)
        $modelsById = $this->getModuleModelsById(call_user_func_array('array_merge', $modules));

        foreach ($modules as $inColumn => $moduleIds) {
            foreach ($moduleIds as $id) {

                // Module does no longer exist in database, simply skip it
                // TODO: maybe we should throw an exception or at least log this?
                if ($id > 0 && !isset($modelsById[$id])) {
                    continue;
                }

                $sections[$inColumn] .= Controller::getFrontendModule(
                    ($modelsById[$id] ?: $id),
                    $inColumn
                );
            }
        }

        return $sections;
    }

    private function addSectionsToTemplate(array $sections, FrontendTemplate $template)
    {
        foreach (['header', 'left', 'right', 'main', 'footer'] as $name) {
            if (isset($sections[$name])) {
                $template->$name = $sections[$name];
                unset($sections[$name]);
            }
        }

        $template->sections = $sections;
    }

    private function addMetaDataToTemplate(FrontendTemplate $template)
    {
        // Set the page title and description AFTER the modules have been generated
        // FIXME: this is only for compatibility with pre-3.3 fe_page templates
        $page = $this->layout->getPageModel();
        $template->mainTitle = $page->rootPageTitle;
        $template->pageTitle = $page->pageTitle ?: $page->title;

        // Meta robots tag
        $template->robots = $this->layout->getRobots();

        // Remove shy-entities (see #2709)
        // FIXME: this would no longer work with the new inserttag meta title?
        $template->mainTitle = str_replace('[-]', '', $template->mainTitle);
        $template->pageTitle = str_replace('[-]', '', $template->pageTitle);

        // Assign the title and description
        $template->title = $this->layout->getMetaTitle();
        $template->description = $this->layout->getMetaDescription();

        // Body onload and body classes
        $template->onload = $this->layout->getBodyOnload();
        $template->class = $this->layout->getBodyClass();
    }


    private function generateStyleSheets()
    {
        $headerTags = '';

        $this->addGlobalFrameworkCss($this->layout->getFrameworkStyleSheets());

        $headerTags .= $this->generateGoogleFontsTag($this->layout->getGoogleFonts());

        $this->addGlobalExternalUserCss(
            $this->layout->getExternalStyleSheets(),
            $this->layout->getStyleSheetLoadingOrder()
        );

        // Add a placeholder for dynamic style sheets (see #4203)
        $headerTags .= '[[TL_CSS]]';

        // Add the debug style sheet
        // FIXME: use constructor injection for debug mode
        if (Config::get('debugMode')) {
            $headerTags .= Template::generateStyleTag($this->staticUrls->addStaticUrlToAsset('assets/contao/css/debug.min.css'), 'all') . "\n";
        }

        // Always add conditional style sheets at the end
        $headerTags .= $this->getInternalUserCss($this->layout->getInternalStyleSheets());

        $headerTags .= $this->generateNewsFeeds($this->layout->getNewsFeeds());
        $headerTags .= $this->generateCalendarFeeds($this->layout->getCalendarFeeds());

        return $headerTags;
    }


    private function generateHeaderScripts()
    {
        // Add a placeholder for dynamic <head> tags (see #4203)
        $headTags = '[[TL_HEAD]]';

        // Add the user <head> tags
        // FIXME: that's a strange comparison
        if (($head = trim($this->layout->getCustomHeadTags())) != false) {
            $headTags .= $head . "\n";
        }

        return $headTags;
    }


    private function generateFooterScripts()
    {
        $scripts = '';

        // jQuery
        if ($this->layout->hasJQuery())
        {
            $scripts .= $this->generateScriptTemplates($this->layout->getJQueryTemplates());

            // Add a placeholder for dynamic scripts (see #4203)
            $scripts .= '[[TL_JQUERY]]';
        }

        // MooTools
        if ($this->layout->hasMooTools())
        {
            $scripts .= $this->generateScriptTemplates($this->layout->getMooToolsTemplates());

            // Add a placeholder for dynamic scripts (see #4203)
            $scripts .= '[[TL_MOOTOOLS]]';
        }

        // Add the framework agnostic JavaScripts
        $scripts .= $this->generateScriptTemplates($this->layout->getScriptTemplates());

        // Add a placeholder for dynamic scripts (see #4203, #5583)
        $scripts .= '[[TL_BODY]]';

        // Add the custom JavaScript
        if ($this->layout->getCustomScriptTags() != '') {
            $scripts .= "\n" . trim($this->layout->getCustomScriptTags()) . "\n";
        }

        // Add the analytics scripts
        $scripts .= $this->generateScriptTemplates($this->layout->getAnalyticsTemplates());

        return $scripts;
    }


    /**
     * @param array $moduleIds
     *
     * @return array
     */
    private function getModuleModelsById(array $moduleIds)
    {
        $modulesById  = array();
        $moduleModels = ModuleModel::findMultipleByIds($moduleIds);

        if (null !== $moduleModels) {
            foreach ($moduleModels as $model) {
                $modulesById[$model->id] = $model;
            }
        }

        return $modulesById;
    }

    private function generateScriptTemplates(array $scripts)
    {
        $buffer = '';

        foreach ($scripts as $templateName) {
            if ($templateName != '') {
                $template = new FrontendTemplate($templateName);
                $buffer  .= $template->parse();
            }
        }

        return $buffer;
    }


    private function addGlobalFrameworkCss(array $frameworkFiles)
    {
        foreach ($frameworkFiles as $file) {

            if ('tinymce.css' === $file) {
                // Add the TinyMCE style sheet
                // FIXME: do not use constants
                if (file_exists(TL_ROOT . '/' . Config::get('uploadPath') . '/tinymce.css')) {
                    $GLOBALS['TL_FRAMEWORK_CSS'][] = Config::get('uploadPath') . '/tinymce.css';
                }
            } else {
                // Add the Contao CSS framework style sheets
                $GLOBALS['TL_FRAMEWORK_CSS'][] = 'assets/contao/css/' . basename($file, '.css') . '.min.css';
            }
        }
    }

    /**
     * @param string $webfonts
     *
     * @return string
     */
    private function generateGoogleFontsTag($webfonts)
    {
        if ('' === $webfonts) {
            return '';
        }

        return Template::generateStyleTag('//fonts.googleapis.com/css?family=' . str_replace('|', '%7C', $webfonts), 'all') . "\n";
    }


    /**
     * @param array $ids
     *
     * @return string
     */
    private function getInternalUserCss(array $ids)
    {
        if (empty($ids)) {
            return '';
        }

        $styleTags = '';
        $ids       = array_map('intval', $ids);
        $models    = StyleSheetModel::findByIds($ids);

        if (null === $models) {
            return '';
        }

        /** @var StyleSheetModel $model */
        foreach ($models as $model) {
            try {
                $path  = $this->getStyleSheetPath($model);
                $media = $model->mediaQuery ?: implode(',', deserialize($model->media, true));

                // Style sheets with a CC or a combination of font-face and media-type != all cannot be aggregated (see #5216)
                if ($model->cc || ($model->hasFontFace && 'all' !== $media)) {
                    $styleSheet = Template::generateStyleTag($this->staticUrls->addStaticUrlToAsset($path), $media);

                    if ($model->cc) {
                        $styleSheet = '<!--[' . $model->cc . ']>' . $styleSheet . '<![endif]-->';
                    }

                    $styleTags .= $styleSheet . "\n";
                } else {
                    // FIXME: Contao 3 is using model timestamp but I can't see any advantage of this?
                    // max($objStylesheets->tstamp, $objStylesheets->tstamp2, $objStylesheets->tstamp3)
                    $GLOBALS['TL_USER_CSS'][] = $path . '|' . $media . '|static|' . filemtime(TL_ROOT . '/' . $path);
                }

            } catch (\RuntimeException $e) {
                // FIXME: do we want to ignore this?
            }
        }

        return $styleTags;
    }

    /**
     * @param StyleSheetModel $model
     *
     * @return string
     * @throws \RuntimeException
     */
    private function getStyleSheetPath(StyleSheetModel $model)
    {
        // External style sheet
        if ('external' === $model->type) {
            $file = FilesModel::findByPk($model->singleSRC);

            if (null === $file) {
                throw new \RuntimeException('No file for external style sheet ID ' . $model->id);
            }

            return $file->path;
        } else {
            return 'assets/css/' . $model->name . '.css';
        }
    }


    private function addGlobalExternalUserCss(array $ids, $loadingOrder = 'external_first')
    {
        if (empty($ids)) {
            return;
        }

        // Get the file entries from the database
        $fileModels = FilesModel::findMultipleByUuids($ids);

        if (null !== $fileModels) {
            $files = array();

            foreach ($fileModels as $model) {
                if (file_exists(TL_ROOT . '/' . $model->path)) {
                    $files[] = $model->path . '|static';
                }
            }

            // Inject the external style sheets before or after the internal ones (see #6937)
            // FIXME: can't this simply be solved by array_merge?
            if ('external_first' === $loadingOrder) {
                array_splice($GLOBALS['TL_USER_CSS'], 0, 0, $files);
            } else {
                array_splice($GLOBALS['TL_USER_CSS'], count($GLOBALS['TL_USER_CSS']), 0, $files);
            }
        }
    }


    private function generateNewsFeeds(array $feeds)
    {
        // Add newsfeeds
        if (!empty($feeds)) {
            $feeds = NewsFeedModel::findByIds($feeds);

            if (null !== $feeds) {
                return $this->generateFeeds($feeds);
            }
        }

        return '';
    }


    private function generateCalendarFeeds(array $feeds)
    {
        // Add calendarfeeds
        if (!empty($feeds)) {
            $feeds = CalendarFeedModel::findByIds($feeds);

            if (null !== $feeds) {
                return $this->generateFeeds($feeds);
            }
        }

        return '';
    }


    private function generateFeeds(Collection $feeds)
    {
        $buffer = '';

        foreach ($feeds as $feed) {
            $buffer .= Template::generateFeedTag(($feed->feedBase ?: Environment::get('base')) . 'share/' . $feed->alias . '.xml', $feed->format, $feed->title) . "\n";
        }

        return $buffer;
    }
}

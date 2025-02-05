<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.skipto
 *
 * @copyright   (C) 2020 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\Skipto\Extension;

use Joomla\CMS\Event\Application\AfterDispatchEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Skipto plugin to add accessible keyboard navigation to the site and administrator templates.
 *
 * @since  4.0.0
 */
final class Skipto extends CMSPlugin implements SubscriberInterface
{
    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return array
     *
     * @since   __DEPLOY_VERSION__
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterDispatch' => 'onAfterDispatch',
        ];
    }

    /**
     * Add the skipto navigation menu.
     *
     * @param   AfterDispatchEvent  $event  The event instance.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function onAfterDispatch(AfterDispatchEvent $event): void
    {
        $section = $this->params->get('section', 'administrator');
        $app     = $event->getApplication();

        if ($section !== 'both' && $app->isClient($section) !== true) {
            return;
        }

        // Get the document object.
        $document = $app->getDocument();

        if ($document->getType() !== 'html') {
            return;
        }

        // Are we in a modal?
        if ($app->getInput()->get('tmpl', '', 'cmd') === 'component') {
            return;
        }

        // Load language file.
        $this->loadLanguage();

        // Add plugin settings and strings for translations in JavaScript.
        $document->addScriptOptions(
            'skipto-settings',
            [
                'settings' => [
                    'skipTo' => [
                        // Feature switches
                        'enableActions'               => false,
                        'enableHeadingLevelShortcuts' => false,

                        // Customization of button and menu
                        'accesskey'     => '9',
                        'displayOption' => 'popup',

                        // Button labels and messages
                        'buttonLabel'            => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_TITLE'),
                        'buttonTooltipAccesskey' => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_ACCESS_KEY'),

                        // Menu labels and messages
                        'landmarkGroupLabel'  => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_LANDMARK'),
                        'headingGroupLabel'   => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_HEADING'),
                        'mofnGroupLabel'      => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_HEADING_MOFN'),
                        'headingLevelLabel'   => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_HEADING_LEVEL'),
                        'mainLabel'           => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_LANDMARK_MAIN'),
                        'searchLabel'         => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_LANDMARK_SEARCH'),
                        'navLabel'            => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_LANDMARK_NAV'),
                        'regionLabel'         => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_LANDMARK_REGION'),
                        'asideLabel'          => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_LANDMARK_ASIDE'),
                        'footerLabel'         => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_LANDMARK_FOOTER'),
                        'headerLabel'         => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_LANDMARK_HEADER'),
                        'formLabel'           => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_LANDMARK_FORM'),
                        'msgNoLandmarksFound' => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_LANDMARK_NONE'),
                        'msgNoHeadingsFound'  => $app->getLanguage()->_('PLG_SYSTEM_SKIPTO_HEADING_NONE'),

                        // Selectors for landmark and headings sections
                        'headings'  => 'h1, h2, h3',
                        'landmarks' => 'main, nav, search, aside, header, footer, form',
                    ],
                ],
            ]
        );

        /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = $document->getWebAssetManager();
        $wa->useScript('skipto');
    }
}

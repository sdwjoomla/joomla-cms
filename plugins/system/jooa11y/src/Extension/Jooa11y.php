<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.jooa11y
 *
 * @copyright   (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\Jooa11y\Extension;

use Joomla\CMS\Event\Application\AfterRouteEvent;
use Joomla\CMS\Event\Application\BeforeCompileHeadEvent;
use Joomla\CMS\Event\PageCache\SetCachingEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Priority;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Jooa11y plugin to add an accessibility checker
 *
 * @since  4.1.0
 */
final class Jooa11y extends CMSPlugin implements SubscriberInterface
{
    /**
     * Subscribe to certain events
     *
     * @return string[]  An array of event mappings
     *
     * @since 4.1.0
     *
     * @throws \Exception
     */
    public static function getSubscribedEvents(): array
    {
        if (Factory::getApplication()->isClient('site')) {
            return ['onAfterRoute' => ['initJooa11y', Priority::HIGH]];
        }

        return [];
    }

    /**
     * Method to check if the current user is allowed to see the debug information or not.
     *
     * @return  boolean  True if access is allowed.
     *
     * @since   4.1.0
     */
    private function isAuthorisedDisplayChecker(): bool
    {
        static $result;

        if ($result !== null) {
            return $result;
        }

        $result = true;

        // If the user is not allowed to view the output then end here.
        $filterGroups = (array) $this->params->get('filter_groups', []);

        if (!empty($filterGroups)) {
            $userGroups = $this->getApplication()
                ->getIdentity()
                ->get('groups');

            if (!array_intersect($filterGroups, $userGroups)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Init the checker.
     *
     * @param  AfterRouteEvent $event  The event object
     *
     * @return  void
     *
     * @since   4.1.0
     */
    public function initJooa11y(AfterRouteEvent $event)
    {
        if (!$event->getApplication()->isClient('site')) {
            return;
        }

        // Check if we are in a preview modal or the plugin has enforced loading
        $showJooa11y = $event->getApplication()
            ->getInput()
            ->get('jooa11y', $this->params->get('showAlways', 0));

        // Load the checker if authorised
        if (!$showJooa11y || !$this->isAuthorisedDisplayChecker()) {
            return;
        }

        // Disable page cache
        $this->getDispatcher()->addListener(
            'onPageCacheSetCaching',
            static function (SetCachingEvent $event) {
                $event->addResult(false);
            }
        );

        // Register own event to add the checker later, once a document is created
        $this->getDispatcher()->addListener('onBeforeCompileHead', [$this, 'addJooa11y']);
    }

    /**
     * Add the checker.
     *
     * @param BeforeCompileHeadEvent $event The event object
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    public function addJooa11y(BeforeCompileHeadEvent $event)
    {
        // Load translations
        $this->loadLanguage();

        // Detect the current active language
        $getLang = $event->getApplication()
            ->getLanguage()
            ->getTag();

        // Get the right locale
        $splitLang = explode('-', $getLang);
        $lang      = $splitLang[0];
        $country   = $splitLang[1] ?? '';

        // Sa11y is available in the following languages
        $supportedLang = [
            'bg',
            'cs',
            'da',
            'de',
            'el',
            'en',
            'es',
            'et',
            'fi',
            'fr',
            'hu',
            'id',
            'it',
            'ja',
            'ko',
            'lt',
            'lv',
            'nb',
            'nl',
            'pl',
            'pt',
            'ro',
            'sl',
            'sk',
            'sv',
            'tr',
            'uk',
            'ua',
            'zh',
        ];

        // Check if Sa11y supports language
        if (!\in_array($lang, $supportedLang)) {
            $lang = 'en';
        } elseif ($lang === 'pt') {
            $lang = $country === 'BR' ? 'ptBR' : 'ptPT';
        } elseif ($lang === 'uk') {
            $lang = 'ua';
        } elseif ($lang === 'en') {
            $lang = $country === 'US' ? 'enUS' : 'en';
        }

        // Get the document object
        /** @var \Joomla\CMS\Document\HtmlDocument $document */
        $document = $event->getDocument();

        // Get plugin options from xml
        $getOptions = [
            'checkRoot'       => $this->params->get('checkRoot', 'main'),
            'readabilityRoot' => $this->params->get('readabilityRoot', 'main'),
            'containerIgnore' => $this->params->get('containerIgnore'),
        ];
        $getExtraProps = $this->params->get('extraProps', []);
        $getChecks     = $this->params->get('checks', []);

        // Process Sa11y's props
        function processProps($props)
        {
            $result = [];
            foreach ($props as $prop) {
                $decodedValue = json_decode($prop->value);
                if (is_numeric($decodedValue) || \is_bool($decodedValue)) {
                    $result[$prop->key] = $decodedValue;
                } else {
                    $result[$prop->key] = "{$prop->value}";
                }
            }
            return $result;
        }
        $extraProps = processProps($getExtraProps);
        $checks     = processProps($getChecks);
        $allChecks  = ['checks' => $checks];

        // Merge all options together and add to page
        $allOptions = array_merge($getOptions, $extraProps, $allChecks);
        $document->addScriptOptions('jooa11yOptions', $allOptions);

        /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa*/
        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('plg_system_jooa11y');

        // Load scripts and instantiate
        $wa->useStyle('sa11y')
            ->useScript('sa11y')
            ->registerAndUseScript(
                'sa11y-lang',
                'vendor/sa11y/' . $lang . '.js',
                ['importmap' => true]
            )
            ->useScript('plg_system_jooa11y.jooa11y');
    }
}

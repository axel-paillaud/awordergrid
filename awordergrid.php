<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of Axelweb
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

class AwOrderGrid extends Module
{
    public $tabs = [
        [
            'name' => [
                'en' => 'Module Base',
                'fr' => 'Module Base',
            ],
            'class_name' => 'AwOrderGrid',
            'parent_class_name' => 'AdminParentModulesSf',
            'wording' => 'Module Base',
            'wording_domain' => 'Modules.Awordergrid.Admin',
        ],
    ];

    public function __construct()
    {
        $this->name = 'awordergrid';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Axelweb';
        $this->need_instance = 1;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Module Base', [], 'Modules.Awordergrid.Admin');
        $this->description = $this->trans('Base template module for PrestaShop development', [], 'Modules.Awordergrid.Admin');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall this module?', [], 'Modules.Awordergrid.Admin');

        $this->ps_versions_compliancy = [
            'min' => '8.0',
            'max' => _PS_VERSION_,
        ];
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function install(): bool
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        $installed = parent::install()
            && $this->installDb()
            && $this->registerHook('actionFrontControllerSetMedia')
            && Configuration::updateValue('AWORDERGRID_SAMPLE_CONFIG', '');

        // Prevent 'Unable to generate a URL for the named route [...]' error,
        // clear Symfony cache
        if ($installed) {
            Tools::clearSf2Cache();
        }

        return $installed;
    }

    public function uninstall(): bool
    {
        return parent::uninstall()
            && Configuration::deleteByName('AWORDERGRID_SAMPLE_CONFIG')
            && $this->uninstallDb();
    }

    protected function installDb(): bool
    {
        $file = __DIR__ . '/sql/install.php';

        return is_file($file) ? (bool) require $file : false;
    }

    protected function uninstallDb(): bool
    {
        $file = __DIR__ . '/sql/uninstall.php';

        return is_file($file) ? (bool) require $file : false;
    }

    /**
     * Redirect to the module symfony configuration page
     *
     * @return void
     */
    public function getContent(): void
    {
        $route = $this->get('router')->generate('awordergrid_form_configuration');
        Tools::redirectAdmin($route);
    }

    /**
     * Hook to register CSS and JS on front-office pages
     */
    public function hookActionFrontControllerSetMedia()
    {
        $this->context->controller->registerStylesheet(
            'module-awordergrid-style',
            'modules/' . $this->name . '/views/css/awordergrid.css',
            [
                'media' => 'all',
                'priority' => 200,
            ]
        );

        $this->context->controller->registerJavascript(
            'module-awordergrid-script',
            'modules/' . $this->name . '/views/js/awordergrid.js',
            [
                'position' => 'bottom',
                'priority' => 200,
            ]
        );
    }
}

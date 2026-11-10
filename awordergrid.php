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

use Axelweb\AwOrderGrid\Grid\OrderGridDefinitionModifier;
use Axelweb\AwOrderGrid\Grid\OrderGridQueryModifier;

class AwOrderGrid extends Module
{
    public function __construct()
    {
        $this->name = 'awordergrid';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Axelweb';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Order grid columns', [], 'Modules.Awordergrid.Admin');
        $this->description = $this->trans('Adds carrier, tracking number and customer email columns to the back-office order list.', [], 'Modules.Awordergrid.Admin');

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
        return parent::install()
            && $this->registerHook('actionOrderGridDefinitionModifier')
            && $this->registerHook('actionOrderGridQueryBuilderModifier');
    }

    public function uninstall(): bool
    {
        return parent::uninstall();
    }

    /**
     * Add the columns and filters to the order grid
     */
    public function hookActionOrderGridDefinitionModifier(array $params): void
    {
        (new OrderGridDefinitionModifier(Db::getInstance(), $this->getTranslator()))
            ->modify($params['definition']);
    }

    /**
     * Select, filter and sort the added columns
     */
    public function hookActionOrderGridQueryBuilderModifier(array $params): void
    {
        (new OrderGridQueryModifier(_DB_PREFIX_))->modify(
            $params['search_query_builder'],
            $params['count_query_builder'],
            $params['search_criteria']
        );
    }
}

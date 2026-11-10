<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of Axelweb
 */

namespace Axelweb\AwOrderGrid\Grid;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DateTimeColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShopBundle\Form\Admin\Type\DateRangeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Order grid columns and filters:
 * - adds carrier, tracking number and customer email (core translation domains)
 * - adds marketplace order id and shipping deadline from the amazon module table
 *   (translated in translations/fr-FR), only when that table exists
 * - removes "New client" and "Delivery" (country), not used on this shop
 */
class OrderGridDefinitionModifier
{
    public function __construct(
        private readonly \Db $db,
        private readonly TranslatorInterface $translator,
        private readonly bool $withMarketplace,
        private readonly string $dateFormat,
    ) {
    }

    public function modify(GridDefinitionInterface $definition): void
    {
        $trans = $this->translator->trans(...);

        foreach (self::REMOVED_COLUMNS as $id) {
            $definition->getColumns()->remove($id);
            $definition->getFilters()->remove($id);
        }

        $definition->getColumns()
            ->addAfter(
                'customer',
                (new DataColumn(OrderGridQueryModifier::COLUMN_EMAIL))
                    ->setName($trans('Email', [], 'Admin.Global'))
                    ->setOptions(['field' => OrderGridQueryModifier::COLUMN_EMAIL])
            )
            ->addAfter(
                'payment',
                (new DataColumn(OrderGridQueryModifier::COLUMN_CARRIER))
                    ->setName($trans('Carrier', [], 'Admin.Global'))
                    ->setOptions(['field' => OrderGridQueryModifier::COLUMN_CARRIER])
            )
            ->addAfter(
                OrderGridQueryModifier::COLUMN_CARRIER,
                (new DataColumn(OrderGridQueryModifier::COLUMN_TRACKING))
                    ->setName($trans('Tracking number', [], 'Admin.Global'))
                    ->setOptions(['field' => OrderGridQueryModifier::COLUMN_TRACKING])
            );

        $definition->getFilters()
            ->add(
                (new Filter(OrderGridQueryModifier::COLUMN_EMAIL, TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => ['placeholder' => $trans('Search email', [], 'Admin.Actions')],
                    ])
                    ->setAssociatedColumn(OrderGridQueryModifier::COLUMN_EMAIL)
            )
            ->add(
                (new Filter(OrderGridQueryModifier::COLUMN_CARRIER, ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => $this->getCarrierChoices(),
                        'translation_domain' => false,
                    ])
                    ->setAssociatedColumn(OrderGridQueryModifier::COLUMN_CARRIER)
            )
            ->add(
                (new Filter(OrderGridQueryModifier::COLUMN_TRACKING, TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn(OrderGridQueryModifier::COLUMN_TRACKING)
            );

        if ($this->withMarketplace) {
            $this->addMarketplaceColumns($definition);
        }
    }

    private function addMarketplaceColumns(GridDefinitionInterface $definition): void
    {
        $trans = $this->translator->trans(...);

        $definition->getColumns()
            ->addAfter(
                'reference',
                (new DataColumn(OrderGridQueryModifier::COLUMN_MP_ORDER_ID))
                    ->setName($trans('Marketplace order ID', [], 'Modules.Awordergrid.Admin'))
                    ->setOptions(['field' => OrderGridQueryModifier::COLUMN_MP_ORDER_ID])
            )
            ->addAfter(
                'date_add',
                (new DateTimeColumn(OrderGridQueryModifier::COLUMN_SHIP_DEADLINE))
                    ->setName($trans('Shipping deadline', [], 'Modules.Awordergrid.Admin'))
                    ->setOptions([
                        'field' => OrderGridQueryModifier::COLUMN_SHIP_DEADLINE,
                        // Amazon deadlines are always at 23:59:59, the date is enough
                        'format' => $this->dateFormat,
                        'empty_data' => '',
                    ])
            );

        $definition->getFilters()
            ->add(
                (new Filter(OrderGridQueryModifier::COLUMN_MP_ORDER_ID, TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn(OrderGridQueryModifier::COLUMN_MP_ORDER_ID)
            )
            ->add(
                (new Filter(OrderGridQueryModifier::COLUMN_SHIP_DEADLINE, DateRangeType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn(OrderGridQueryModifier::COLUMN_SHIP_DEADLINE)
            );
    }

    /** Core columns (and their filters) removed from the grid */
    private const REMOVED_COLUMNS = ['new', 'country_name'];

    /**
     * Distinct carrier names, as label => value.
     *
     * Filtering is done on the name, not on id_carrier / id_reference: editing a
     * carrier in PrestaShop creates a new id_carrier, and the same carrier has
     * been recreated several times over the years (several id_reference for the
     * same name). Keeps active carriers and every carrier used by an order.
     *
     * @return array<string, string>
     */
    private function getCarrierChoices(): array
    {
        $rows = $this->db->executeS(
            'SELECT DISTINCT c.`name`
            FROM `' . _DB_PREFIX_ . 'carrier` c
            WHERE (c.`deleted` = 0 AND c.`active` = 1)
                OR EXISTS (
                    SELECT 1 FROM `' . _DB_PREFIX_ . 'orders` o
                    WHERE o.`id_carrier` = c.`id_carrier`
                )
            ORDER BY c.`name` ASC'
        ) ?: [];

        $choices = [];
        foreach ($rows as $row) {
            $choices[$row['name']] = $row['name'];
        }

        return $choices;
    }
}

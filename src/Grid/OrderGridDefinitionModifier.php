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
use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds the carrier, tracking number and customer email columns (and their
 * filters) to the back-office order grid.
 *
 * Labels use core translation domains, already translated in every language.
 */
class OrderGridDefinitionModifier
{
    public function __construct(
        private readonly \Db $db,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function modify(GridDefinitionInterface $definition): void
    {
        $trans = $this->translator->trans(...);

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
    }

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

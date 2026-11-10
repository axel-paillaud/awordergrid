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

use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

/**
 * Selects, filters and sorts the extra columns of the order grid.
 *
 * WHY the paginated sub-query is rebuilt: since PrestaShop 9.2 (#41916),
 * OrderQueryBuilder resolves the page of order ids first, in a sub-query
 * already rendered as SQL in the FROM clause (alias `paginated`), then hydrates
 * the display columns for those ids only. A WHERE / ORDER BY added by this
 * hook to the search query would therefore only apply to the current page.
 * When one of our filters or our sort is active, the id sub-query is rebuilt
 * from the count query (which holds every core filter) plus our conditions.
 * Otherwise the core query is left untouched, only display joins are added.
 */
class OrderGridQueryModifier
{
    public const COLUMN_CARRIER = 'aw_carrier';
    public const COLUMN_TRACKING = 'aw_tracking_number';
    public const COLUMN_EMAIL = 'aw_customer_email';
    public const COLUMN_MP_ORDER_ID = 'aw_mp_order_id';
    public const COLUMN_SHIP_DEADLINE = 'aw_latest_ship_date';

    /** Text filters, matched with LIKE or = */
    private const TEXT_FILTERS = [self::COLUMN_CARRIER, self::COLUMN_TRACKING, self::COLUMN_EMAIL, self::COLUMN_MP_ORDER_ID];

    /** Date range filters (DateRangeType: ['from' => 'Y-m-d', 'to' => 'Y-m-d']) */
    private const DATE_FILTERS = [self::COLUMN_SHIP_DEADLINE];

    /**
     * Mirror of OrderQueryBuilder::applySorting(), used when the id sub-query
     * is rebuilt while a core column is sorted. Keep in sync with the core.
     */
    private const CORE_SORT = [
        'id_order' => 'o.id_order',
        'country_name' => 'c.id_country',
        'total_paid_tax_incl' => 'o.total_paid_tax_incl',
        'reference' => 'o.`reference`',
        'company' => 'cu.`company`',
        'payment' => 'o.`payment`',
        'customer' => 'CONCAT(LEFT(cu.`firstname`, 1), \'. \', cu.`lastname`)',
        'osname' => 'osl.name',
        'date_add' => 'o.`date_add`',
    ];

    public function __construct(
        private readonly string $dbPrefix,
        private readonly bool $withMarketplace,
    ) {
    }

    public function modify(QueryBuilder $searchQb, QueryBuilder $countQb, SearchCriteriaInterface $criteria): void
    {
        $filters = $this->getOwnFilters($criteria->getFilters());
        $sort = $this->getOwnSort()[$criteria->getOrderBy()] ?? null;
        if (!$this->withMarketplace) {
            unset($filters[self::COLUMN_MP_ORDER_ID], $filters[self::COLUMN_SHIP_DEADLINE]);
            if (in_array($criteria->getOrderBy(), [self::COLUMN_MP_ORDER_ID, self::COLUMN_SHIP_DEADLINE], true)) {
                $sort = null;
            }
        }

        // Display: the search query only holds the current page, these joins are cheap
        $searchQb
            ->addSelect('ca.`name` AS ' . self::COLUMN_CARRIER)
            ->addSelect('cu.`email` AS ' . self::COLUMN_EMAIL)
            ->addSelect('(' . $this->getTrackingListSql() . ') AS ' . self::COLUMN_TRACKING);
        $this->joinCarrier($searchQb);

        if ($this->withMarketplace) {
            $searchQb
                ->addSelect('awmpo.`mp_order_id` AS ' . self::COLUMN_MP_ORDER_ID)
                ->addSelect('awmpo.`latest_ship_date` AS ' . self::COLUMN_SHIP_DEADLINE);
            $this->joinMarketplaceOrder($searchQb);
        }

        if (!$filters && null === $sort) {
            return;
        }

        // Filters on the count query, which is the base query of the id sub-query
        $used = array_merge(array_keys($filters), [(string) $criteria->getOrderBy()]);
        if (in_array(self::COLUMN_CARRIER, $used, true)) {
            $this->joinCarrier($countQb);
        }
        if (array_intersect([self::COLUMN_MP_ORDER_ID, self::COLUMN_SHIP_DEADLINE], $used)) {
            $this->joinMarketplaceOrder($countQb);
        }
        $this->applyFilters($countQb, $filters);

        // Rebuild the paginated id sub-query
        $orderWay = $criteria->getOrderWay();
        $idsQb = (clone $countQb)->select('o.id_order');

        $sortExpr = $sort ?? self::CORE_SORT[$criteria->getOrderBy()] ?? null;
        if (null !== $sortExpr) {
            $idsQb->orderBy($sortExpr, $orderWay);
        }
        if ('id_order' !== $criteria->getOrderBy()) {
            $idsQb->addOrderBy('o.`id_order`', $orderWay);
        }
        if (null !== $criteria->getLimit()) {
            $idsQb->setMaxResults($criteria->getLimit());
        }
        if (null !== $criteria->getOffset()) {
            $idsQb->setFirstResult($criteria->getOffset());
        }

        // No DBAL 3 replacement for resetting the FROM part, joins stay attached
        // to the `paginated` alias
        $searchQb
            ->resetQueryPart('from')
            ->from('(' . $idsQb->getSQL() . ')', 'paginated')
            ->setParameters($idsQb->getParameters(), $idsQb->getParameterTypes());

        // Core sorting ignores our columns: sort the displayed page too
        if (null !== $sort) {
            $searchQb
                ->orderBy($sort, $orderWay)
                ->addOrderBy('o.`id_order`', $orderWay);
        }
    }

    private function joinCarrier(QueryBuilder $qb): void
    {
        $qb->leftJoin('o', $this->dbPrefix . 'carrier', 'ca', 'ca.`id_carrier` = o.`id_carrier`');
    }

    /**
     * Table of the amazon module (one row per marketplace order, id_order is the primary key)
     */
    private function joinMarketplaceOrder(QueryBuilder $qb): void
    {
        $qb->leftJoin('o', $this->dbPrefix . 'marketplace_orders', 'awmpo', 'awmpo.`id_order` = o.`id_order`');
    }

    /**
     * @return array<string, string|array{from?: string, to?: string}> active filters, validated
     */
    private function getOwnFilters(array $filters): array
    {
        $own = [];
        foreach (self::TEXT_FILTERS as $name) {
            if (isset($filters[$name]) && is_scalar($filters[$name]) && '' !== trim((string) $filters[$name])) {
                $own[$name] = trim((string) $filters[$name]);
            }
        }

        foreach (self::DATE_FILTERS as $name) {
            if (!isset($filters[$name]) || !is_array($filters[$name])) {
                continue;
            }
            $range = [];
            foreach (['from', 'to'] as $bound) {
                $date = $filters[$name][$bound] ?? null;
                if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $range[$bound] = $date;
                }
            }
            if ($range) {
                $own[$name] = $range;
            }
        }

        return $own;
    }

    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        if (isset($filters[self::COLUMN_CARRIER])) {
            $qb->andWhere('ca.`name` = :aw_carrier')
                ->setParameter('aw_carrier', $filters[self::COLUMN_CARRIER]);
        }

        if (isset($filters[self::COLUMN_EMAIL])) {
            $qb->andWhere('cu.`email` LIKE :aw_customer_email')
                ->setParameter('aw_customer_email', '%' . $filters[self::COLUMN_EMAIL] . '%');
        }

        if (isset($filters[self::COLUMN_TRACKING])) {
            $qb->andWhere(
                'EXISTS (SELECT 1 FROM `' . $this->dbPrefix . 'order_carrier` awoc
                WHERE awoc.`id_order` = o.`id_order` AND awoc.`tracking_number` LIKE :aw_tracking_number)'
            )->setParameter('aw_tracking_number', '%' . $filters[self::COLUMN_TRACKING] . '%');
        }

        if (isset($filters[self::COLUMN_MP_ORDER_ID])) {
            $qb->andWhere('awmpo.`mp_order_id` LIKE :aw_mp_order_id')
                ->setParameter('aw_mp_order_id', '%' . $filters[self::COLUMN_MP_ORDER_ID] . '%');
        }

        if (isset($filters[self::COLUMN_SHIP_DEADLINE]['from'])) {
            $qb->andWhere('awmpo.`latest_ship_date` >= :aw_latest_ship_date_from')
                ->setParameter('aw_latest_ship_date_from', $filters[self::COLUMN_SHIP_DEADLINE]['from'] . ' 00:00:00');
        }
        if (isset($filters[self::COLUMN_SHIP_DEADLINE]['to'])) {
            $qb->andWhere('awmpo.`latest_ship_date` <= :aw_latest_ship_date_to')
                ->setParameter('aw_latest_ship_date_to', $filters[self::COLUMN_SHIP_DEADLINE]['to'] . ' 23:59:59');
        }
    }

    /**
     * Sort expressions, valid in both the id sub-query and the display query.
     *
     * @return array<string, string>
     */
    private function getOwnSort(): array
    {
        return [
            self::COLUMN_CARRIER => 'ca.`name`',
            self::COLUMN_EMAIL => 'cu.`email`',
            self::COLUMN_MP_ORDER_ID => 'awmpo.`mp_order_id`',
            self::COLUMN_SHIP_DEADLINE => 'awmpo.`latest_ship_date`',
            self::COLUMN_TRACKING => '(SELECT MAX(awoc.`tracking_number`) FROM `' . $this->dbPrefix . 'order_carrier` awoc
                WHERE awoc.`id_order` = o.`id_order`)',
        ];
    }

    /**
     * An order can have several order_carrier rows (none on this shop so far).
     */
    private function getTrackingListSql(): string
    {
        return 'SELECT GROUP_CONCAT(DISTINCT awoc.`tracking_number` SEPARATOR \', \')
            FROM `' . $this->dbPrefix . 'order_carrier` awoc
            WHERE awoc.`id_order` = o.`id_order` AND awoc.`tracking_number` <> \'\'';
    }
}

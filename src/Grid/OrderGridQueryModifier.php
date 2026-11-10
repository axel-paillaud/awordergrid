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

    public function __construct(private readonly string $dbPrefix)
    {
    }

    public function modify(QueryBuilder $searchQb, QueryBuilder $countQb, SearchCriteriaInterface $criteria): void
    {
        $filters = $this->getOwnFilters($criteria->getFilters());
        $sort = $this->getOwnSort()[$criteria->getOrderBy()] ?? null;

        // Display: the search query only holds the current page, these joins are cheap
        $searchQb
            ->addSelect('ca.`name` AS ' . self::COLUMN_CARRIER)
            ->addSelect('cu.`email` AS ' . self::COLUMN_EMAIL)
            ->addSelect('(' . $this->getTrackingListSql() . ') AS ' . self::COLUMN_TRACKING)
            ->leftJoin('o', $this->dbPrefix . 'carrier', 'ca', 'ca.`id_carrier` = o.`id_carrier`');

        if (!$filters && null === $sort) {
            return;
        }

        // Filters on the count query, which is the base query of the id sub-query
        if (isset($filters[self::COLUMN_CARRIER]) || self::COLUMN_CARRIER === $criteria->getOrderBy()) {
            $countQb->leftJoin('o', $this->dbPrefix . 'carrier', 'ca', 'ca.`id_carrier` = o.`id_carrier`');
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

    /**
     * @return array<string, string> filter name => trimmed value
     */
    private function getOwnFilters(array $filters): array
    {
        $own = [];
        foreach ([self::COLUMN_CARRIER, self::COLUMN_TRACKING, self::COLUMN_EMAIL] as $name) {
            if (isset($filters[$name]) && is_scalar($filters[$name]) && '' !== trim((string) $filters[$name])) {
                $own[$name] = trim((string) $filters[$name]);
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

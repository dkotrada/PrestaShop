<?php
/**
 * 2007-2018 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2018 PrestaShop SA
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\PrestaShop\Core\Grid\Query\Builder;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

/**
 * Class CustomerQueryBuilder builds queries to fetch data for customers grid.
 */
final class CustomerQueryBuilder extends AbstractDoctrineQueryBuilder
{
    /**
     * @var int
     */
    private $contextShopId;

    /**
     * @var int
     */
    private $contextLangId;

    /**
     * @var int[]
     */
    private $contextShopIds;

    /**
     * @param Connection $connection
     * @param string $dbPrefix
     * @param int $contextShopId
     * @param int $contextLangId
     * @param int[] $contextShopIds
     */
    public function __construct(
        Connection $connection,
        $dbPrefix,
        $contextShopId,
        $contextLangId,
        $contextShopIds
    ) {
        parent::__construct($connection, $dbPrefix);

        $this->contextShopId = $contextShopId;
        $this->contextLangId = $contextLangId;
        $this->contextShopIds = $contextShopIds;
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        $searchQueryBuilder = $this->getCustomerQueryBuilder($searchCriteria)
            ->select('c.id_customer, c.firstname, c.lastname, c.email, c.active, c.newsletter, c.optin')
            ->addSelect('c.date_add, gl.name as social_title, s.name as shop_name, c.company')
        ;

        $this->appendTotalSpentQuery($searchQueryBuilder);
        $this->appendLastVisitQuery($searchQueryBuilder);

        return $searchQueryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        $countQueryBuilder = $this->getCustomerQueryBuilder($searchCriteria)
            ->select('COUNT(*)')
        ;

        return $countQueryBuilder;
    }

    private function getCustomerQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->from($this->dbPrefix . 'customer', 'c')
            ->leftJoin(
                'c',
                $this->dbPrefix . 'gender_lang',
                'gl',
                'c.id_gender = gl.id_gender AND gl.id_lang = :context_lang_id'
            )
            ->leftJoin(
                'c',
                $this->dbPrefix . 'shop',
                's',
                'c.id_shop = s.id_shop'
            )
            ->where('c.deleted = 0')
            ->andWhere('c.id_shop IN (:context_shop_ids)')
            ->setParameter('context_shop_ids', $this->contextShopIds, Connection::PARAM_INT_ARRAY)
            ->setParameter('context_lang_id', $this->contextLangId)
        ;

        return $queryBuilder;
    }

    private function appendTotalSpentQuery(QueryBuilder $queryBuilder)
    {
        $totalSpentQueryBuilder = $this->connection->createQueryBuilder()
            ->select('SUM(total_paid_real / conversion_rate)')
            ->from($this->dbPrefix . 'orders', 'o')
            ->where('o.id_customer = c.id_customer')
            ->andWhere('o.id_shop IN (:context_shop_ids)')
            ->andWhere('o.valid = 1')
            ->setParameter('context_shop_ids', $this->contextShopIds, Connection::PARAM_INT_ARRAY)
        ;

        $queryBuilder->addSelect('(' . $totalSpentQueryBuilder->getSQL() . ') as total_spent');
    }

    private function appendLastVisitQuery(QueryBuilder $queryBuilder)
    {
        $lastVisitQueryBuilder = $this->connection->createQueryBuilder()
            ->select('c.date_add')
            ->from($this->dbPrefix . 'guest', 'g')
            ->leftJoin('g', $this->dbPrefix . 'connections', 'con', 'con.id_guest = g.id_guest')
            ->where('g.id_customer = c.id_customer')
            ->orderBy('c.date_add', 'DESC')
            ->setMaxResults(1)
        ;

        $queryBuilder->addSelect('(' . $lastVisitQueryBuilder->getSQL() . ') as connect');
    }
}

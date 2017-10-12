<?php
/**
 * @copyright 2017 innosabi GmbH
 * @author Daniel Jurkovic <daniel.jurkovic@innosabi.com>
 */

namespace Macroparts\Vortex\ExpressionBuilder;

use Macroparts\Vortex\Exception\InvalidFilterModifierUsed;
use Macroparts\Vortex\Helper\UniqueNumberGenerator;

/**
 * Has methods for building DQL expressions out of parsed RichParam modifiers. You know that you have to implement
 * filter methods for every filter that you add in your Vortex implementation. Soon you will find out that
 * implementing every filter from scratch is nasty. It's slow and introduces inconsistencies. This class provides
 * implementations for many common filters. You should avoid implementing custom filters If you want your API
 * to be consistent.
 *
 * Example:
 * If your RichParam is: "firstname:contains(querystring1|querystring2)" you would usually add a filterFirstname method
 * to your Vortex implementation and do something like parseParamLogic...loop...$queryBuilder->andWhere(...)...return.
 *
 * Now you can just do:
 * function filterFirstame(){ return ExpressionBuilder::forStringColumn(...); }
 * And every string column is filtered the same way.
 */
class ExpressionBuilder
{
    use IntegerColumnExpressionBuilder;



    /**
     * @param $subquery
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param mixed $currentUser
     * @param array $modifiers
     * @param string $filtername
     * @return \Doctrine\ORM\Query\Expr\Andx
     * @internal param string $alias
     */
    protected function forIntegerCollectionSubquery($subquery, $query, $currentUser, $modifiers, $filtername)
    {
        return $this->createExpression(
            ['anyis'],
            __FUNCTION__,
            $subquery,
            $query,
            $currentUser,
            $modifiers,
            $filtername
        );
    }

    /**
     * @param callable $subquery
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param mixed $currentUser
     * @param array $modifiers
     * @param string $filtername
     * @return \Doctrine\ORM\Query\Expr\Andx
     * @uses subqueryAnyisExpression
     */
    protected function forStringCollectionSubquery($subquery, $query, $currentUser, $modifiers, $filtername)
    {
        return $this->createExpression(
            ['anyis'],
            __FUNCTION__,
            $subquery,
            $query,
            $currentUser,
            $modifiers,
            $filtername
        );
    }

    /**
     * @param callable $subquery
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param mixed $currentUser
     * @param array $modifiers
     * @param string $filtername
     * @return \Doctrine\ORM\Query\Expr\Andx
     * @uses subqueryAnyisExpression
     */
    protected function forDatetimeSubquery($subquery, $query, $currentUser, $modifiers, $filtername)
    {
        return $this->createExpression(
            ['true', 'false'],
            __FUNCTION__,
            $subquery,
            $query,
            $currentUser,
            $modifiers,
            $filtername
        );
    }



    /**
     * Todo: Whitelisting allowed subqueries for the any filter makes having this extra function unnecessary
     *
     * This one allows some filter directives that result to function calls on protected methods. Don't ever redirect
     * user content here.
     *
     * Translates params into where conditions. Null values are handled as you would expect it.
     *
     * @param $col
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @param array $methods
     * @return \Doctrine\ORM\Query\Expr\Andx
     * @uses integerIsExpression
     * @uses integerNotExpression
     * @uses integerGtExpression
     * @uses integerGteExpression
     * @uses integerLtExpression
     * @uses integerLteExpression
     * @uses integerFalseExpression
     * @uses integerTrueExpression
     * @uses integerAnyExpression
     */
    protected function createConditionsForIntegerColumnInternal($col, $query, $alias, $currentUser, $methods)
    {
        if (\UnserAllerLib_Tool_Array::hasMoreKeysThan(
            $methods,
            ['is', 'not', 'gt', 'gte', 'lt', 'lte', 'false', 'true', 'any']
        )
        ) {
            throw new \InvalidArgumentException('Invalid expression methods used');
        }

        return $this->createExpression('integer', $col, $query, $alias, $currentUser, $methods);
    }


}
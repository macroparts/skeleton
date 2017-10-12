<?php

namespace Macroparts\Vortex\ExpressionBuilder;

trait IntegerSubqueryExpressionBuilder
{
    use AbstractExpressionBuilder;

    /**
     * @param $subquery
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param mixed $currentUser
     * @param array $modifiers
     * @param string $filtername
     * @return \Doctrine\ORM\Query\Expr\Andx
     * @uses createFalseExpressionForIntegerSubquery
     * @uses createTrueExpressionForIntegerSubquery
     * @uses createGtExpressionForIntegerSubquery
     * @uses createGteExpressionForIntegerSubquery
     * @uses createLtExpressionForIntegerSubquery
     * @uses createLteExpressionForIntegerSubquery
     * @uses createEqExpressionForIntegerSubquery
     * @uses createNullExpressionForIntegerSubquery
     */
    public function forIntegerSubquery($subquery, $query, $currentUser, $modifiers, $filtername)
    {
        return $this->createExpression(
            ['false', 'true', 'gt', 'gte', 'lt', 'lte', 'eq', 'null'],
            __FUNCTION__,
            $subquery,
            $query,
            $currentUser,
            $modifiers,
            $filtername
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param array $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function createFalseExpressionForIntegerSubquery($query, $field, $params, $alias)
    {
        return $query->expr()->orX(
            $query->expr()->not($query->expr()->exists($this->consumeSubquery($field))),
            $query->expr()->eq('(' . $this->consumeSubquery($field) . ')', 0)
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param array $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function createNullExpressionForIntegerSubquery($query, $field, $params, $alias)
    {
        return $query->expr()->not($query->expr()->exists($this->consumeSubquery($field)));
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param array $subquery
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function createTrueExpressionForIntegerSubquery($query, $subquery, $params, $alias)
    {
        return $query->expr()->andX(
            $query->expr()->exists($this->consumeSubquery($subquery)),
            $query->expr()->neq('(' . $this->consumeSubquery($subquery) . ')', 0)
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param array $subquery
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function createGtExpressionForIntegerSubquery($query, $subquery, $params, $alias)
    {
        return $query->expr()->andX(
            $query->expr()->exists($this->consumeSubquery($subquery)),
            $query->expr()->gt('(' . $this->consumeSubquery($subquery) . ')', $params[0])
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param array $subquery
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function createGteExpressionForIntegerSubquery($query, $subquery, $params, $alias)
    {
        return $query->expr()->andX(
            $query->expr()->exists($this->consumeSubquery($subquery)),
            $query->expr()->gte('(' . $this->consumeSubquery($subquery) . ')', $params[0])
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param array $subquery
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function createLteExpressionForIntegerSubquery($query, $subquery, $params, $alias)
    {
        return $query->expr()->andX(
            $query->expr()->exists($this->consumeSubquery($subquery)),
            $query->expr()->lte('(' . $this->consumeSubquery($subquery) . ')', $params[0])
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param array $subquery
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function createLtExpressionForIntegerSubquery($query, $subquery, $params, $alias)
    {
        return $query->expr()->andX(
            $query->expr()->exists($this->consumeSubquery($subquery)),
            $query->expr()->lt('(' . $this->consumeSubquery($subquery) . ')', $params[0])
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param array $subquery
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function createEqExpressionForIntegerSubquery($query, $subquery, $params, $alias)
    {
        return $query->expr()->andX(
            $query->expr()->exists($this->consumeSubquery($subquery)),
            $query->expr()->eq('(' . $this->consumeSubquery($subquery) . ')', $params[0])
        );
    }
}
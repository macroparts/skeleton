<?php

namespace Macroparts\Vortex\ExpressionBuilder;

trait IntegerColumnExpressionBuilder
{
    use AbstractExpressionBuilder;

    /**
     * @param string $col
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param mixed $currentUser
     * @param array $modifiers
     * @param string $filtername
     * @return \Doctrine\ORM\Query\Expr\Andx
     * @uses createIsExpressionForIntegerColumn
     * @uses createNotExpressionForIntegerColumn
     * @uses createGtExpressionForIntegerColumn
     * @uses createGteExpressionForIntegerColumn
     * @uses createLtExpressionForIntegerColumn
     * @uses createLteExpressionForIntegerColumn
     * @uses createFalseExpressionForIntegerColumn
     * @uses createTrueExpressionForIntegerColumn
     */
    protected function forIntegerColumn($col, $query, $currentUser, $modifiers, $filtername)
    {
        return $this->createExpression(
            ['is', 'not', 'gt', 'gte', 'lt', 'lte', 'false', 'true'],
            __FUNCTION__,
            $col,
            $query,
            $currentUser,
            $modifiers,
            $filtername
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $col
     * @param array $arguments
     * @return mixed
     */
    private function createIsExpressionForIntegerColumn($query, $col, $arguments)
    {
        return $query->expr()->in($col, $arguments);
    }


    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $col
     * @param array $arguments
     * @return \Doctrine\ORM\Query\Expr\Func
     */
    private function createNotExpressionForIntegerColumn($query, $col, $arguments)
    {
        return $query->expr()->notIn($col, $arguments);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $col
     * @return \Doctrine\ORM\Query\Expr\Comparison
     */
    private function createFalseExpressionForIntegerColumn($query, $col)
    {
        return $query->expr()->eq('COALESCE(' . $col . ',0)', 0);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $col
     * @return \Doctrine\ORM\Query\Expr\Comparison
     */
    private function createTrueExpressionForIntegerColumn($query, $col)
    {
        return $query->expr()->neq('COALESCE(' . $col . ',0)', 0);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $col
     * @param array $arguments
     * @return mixed
     */
    private function createLtExpressionForIntegerColumn($query, $col, $arguments)
    {
        $uniqid = $this->uniqidGenerator->next();
        $lt = $query->expr()->orX();
        $index = 0;

        foreach ($arguments as $argument) {
            $lt->add($query->expr()->lt($col, ":lt_{$uniqid}_{$index}"));
            $query->setParameter("lt_{$uniqid}_{$index}", $argument);
            $index++;
        }

        return $lt;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $col
     * @param array $arguments
     * @return mixed
     */
    private function createLteExpressionForIntegerColumn($query, $col, $arguments)
    {
        $uniqid = $this->uniqidGenerator->next();
        $lte = $query->expr()->orX();
        $index = 0;

        foreach ($arguments as $argument) {
            $lte->add($query->expr()->lte($col, ":lte_{$uniqid}_{$index}"));
            $query->setParameter("lte_{$uniqid}_{$index}", $argument);
            $index++;
        }

        return $lte;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $col
     * @param array $arguments
     * @return mixed
     */
    private function createGtExpressionForIntegerColumn($query, $col, $arguments)
    {
        $uniqid = $this->uniqidGenerator->next();
        $gt = $query->expr()->orX();
        $index = 0;
        foreach ($arguments as $argument) {
            $gt->add($query->expr()->gt($col, ":gt_{$uniqid}_{$index}"));
            $query->setParameter("gt_{$uniqid}_{$index}", $argument);
            $index++;
        }

        return $gt;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $col
     * @param array $arguments
     * @return mixed
     */
    private function createGteExpressionForIntegerColumn($query, $col, $arguments)
    {
        $uniqid = $this->uniqidGenerator->next();
        $gte = $query->expr()->orX();
        $index = 0;
        foreach ($arguments as $argument) {
            $gte->add($query->expr()->gte($col, ":gte_{$uniqid}_{$index}"));
            $query->setParameter("gte_{$uniqid}_{$index}", $argument);
            $index++;
        }

        return $gte;
    }
}
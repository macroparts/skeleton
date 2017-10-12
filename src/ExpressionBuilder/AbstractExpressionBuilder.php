<?php
/**
 * @copyright 2017 innosabi GmbH
 * @author Daniel Jurkovic <daniel.jurkovic@innosabi.com>
 */

namespace Macroparts\Vortex\ExpressionBuilder;

use Macroparts\Vortex\Exception\InvalidFilterModifierUsed;
use Macroparts\Vortex\Helper\UniqueNumberGenerator;

trait AbstractExpressionBuilder
{
    /**
     * @var UniqueNumberGenerator
     */
    private $uniqidGenerator;

    /**
     * ExpressionBuilder constructor.
     * @param UniqueNumberGenerator $uniqidGenerator
     */
    public function __construct(UniqueNumberGenerator $uniqidGenerator)
    {
        $this->uniqidGenerator = $uniqidGenerator;
    }

    protected function failIfInvalidFilterModifiersUsed($modifiers, $allowedModifiers, $filtername)
    {
        if (!empty(array_diff(array_keys($modifiers), $allowedModifiers))) {
            throw new InvalidFilterModifierUsed($filtername, $allowedModifiers);
        }
    }

    /**
     * @param string[] $allowedModifiers
     * @param string $suffix
     * @param mixed $source
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param mixed $currentUser
     * @param array $modifiers
     * @param string $filtername
     * @return \Doctrine\ORM\Query\Expr\Andx
     */
    protected function createExpression(
        $allowedModifiers,
        $suffix,
        $source,
        $query,
        $currentUser,
        $modifiers,
        $filtername
    ) {
        $this->failIfInvalidFilterModifiersUsed($modifiers, $allowedModifiers, $filtername);

        $expression = $query->expr()->andX();
        foreach ($modifiers as $modifier => $arguments) {
            $expression->add(call_user_func_array(
                [$this,  'create'. ucfirst($modifier) . 'Expression' . ucfirst($suffix)],
                [$query, $source, $arguments, $currentUser]
            ));
        }

        return $expression;
    }
}
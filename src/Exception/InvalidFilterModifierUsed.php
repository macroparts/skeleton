<?php
/**
 * @copyright 2017 innosabi GmbH
 * @author Daniel Jurkovic <daniel.jurkovic@innosabi.com>
 */

namespace Macroparts\Vortex\Exception;

class InvalidFilterModifierUsed extends \Exception
{

    /**
     * InvalidFilterModifierUsed constructor.
     * @param string $field
     * @param string[] $allowedModifiers
     */
    public function __construct($field, $allowedModifiers)
    {
        parent::__construct(
            implode(' ', [
                "You have used invalid filter modifiers for filtering by $field.",
                "Allowed modifiers are:",
                implode(', ', $allowedModifiers)
            ])
        );
    }
}
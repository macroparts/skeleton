<?php

namespace Macroparts\Vortex\RichParam;

/**
 * Created by PhpStorm.
 * User: daniel.jurkovic
 * Date: 14.06.17
 * Time: 18:02
 */
class Parser
{
    public function parseRichParam($param)
    {
        $interpretation = [];
        foreach ($this->parseElements($param) as $element) {
            list($fieldname, $modifiersStr) = array_pad(explode(':', $element, 2), 2, null);

            if ($modifiersStr === null) {
                if (!isset($interpretation[$fieldname])) {
                    $interpretation[$fieldname] = [];
                }
                continue;
            }

            $modifierArr = $this->parseModifiers($modifiersStr);

            if (isset($interpretation[$fieldname])) {
                $interpretation[$fieldname] = $interpretation[$fieldname] + $modifierArr;
            } else {
                $interpretation[$fieldname] = $modifierArr;
            }
        }

        return $interpretation;
    }

    /**
     * creates an array out of string in this format:
     *
     * modifierName1(modifierParam1|modifierParam2):modifierName2(modifierParam3))
     *
     * Can also handle modifier params that contain other modifier with params
     *
     * @param string $modifiersStr
     * @return array
     */
    private function parseModifiers($modifiersStr)
    {
        $interpretation = [];
        $modifierName = '';
        $modifierParamStr = '';

        $depth = 0;
        for ($i = 0; $i < strlen($modifiersStr); $i++) {
            switch ($modifiersStr[$i]) {
                case '(':
                    if ($depth) {
                        $modifierParamStr .= $modifiersStr[$i];
                    }
                    $depth++;
                    break;

                case ')':
                    $depth--;
                    if ($depth) {
                        $modifierParamStr .= $modifiersStr[$i];
                    }
                    break;
                case ':':
                    if ($depth) {
                        $modifierParamStr .= $modifiersStr[$i];
                    } else {
                        $interpretation[$modifierName] = $this->parseArguments($modifierParamStr);
                        $modifierName = '';
                        $modifierParamStr = '';
                    }
                    break;
                default:
                    if ($depth) {
                        $modifierParamStr .= $modifiersStr[$i];
                    } else {
                        $modifierName .= $modifiersStr[$i];
                    }
            }
        }

        if ($modifierName) {
            $interpretation[$modifierName] = $this->parseArguments($modifierParamStr);
        }

        return $interpretation;
    }

    /**
     * Can make an array out of parameter string that looks like this:
     *
     * param1|param2|param3
     *
     * Can also handle params that contain other modifiers with params like this:
     * param1|modifier(innerParam1|innerParam2)|param3
     *
     * @param string $argumentsStr
     * @return array
     */
    private function parseArguments($argumentsStr)
    {
        $paramArr = [];
        $tmpStr = '';

        $depth = 0;
        for ($i = 0; $i < strlen($argumentsStr); $i++) {
            switch ($argumentsStr[$i]) {
                case '(':
                    $tmpStr .= $argumentsStr[$i];
                    $depth++;
                    break;

                case ')':
                    $tmpStr .= $argumentsStr[$i];
                    $depth--;
                    break;

                case '|':
                    if ($depth) {
                        $tmpStr .= $argumentsStr[$i];
                    } else {
                        $paramArr[] = $tmpStr;
                        $tmpStr = '';
                    }
                    break;

                default:
                    $tmpStr .= $argumentsStr[$i];
            }
        }

        if (strlen($tmpStr)) {
            $paramArr[] = $tmpStr;
        }

        return $paramArr;
    }

    private function parseElements($richParam)
    {
        if ($richParam === '' || !is_string($richParam)) {
            return [];
        }

        $fields = explode(',', $richParam);

        return is_array($fields) ? $fields : [];
    }
}

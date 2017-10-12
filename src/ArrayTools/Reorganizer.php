<?php
/**
 * Created by PhpStorm.
 * User: daniel.jurkovic
 * Date: 15.06.17
 * Time: 13:23
 */

namespace Macroparts\Vortex\ArrayTools;

/**
 * Class Patcher
 * @package Macroparts\Vortex\ArrayPatcher
 */
class Reorganizer
{
    const CAST = 'executeCast';
    const READ = 'executeRead';
    const WRITE = 'executeWrite';
    const DELETE = 'scheduleUnset';
    const CUSTOM_TRANSFORM = 'executeCustom';

    private $scheduledUnsets = [];

    /**
     * Execute $tasks on $array and return result
     * @param array $array
     * @param array[] $tasks
     * @return array (patched)
     */
    public function run(&$array, $tasks)
    {
        $temporaryValue = null;

        while ($tasks) {
            $taskName = array_shift($tasks);
            $taskParams = array_shift($tasks);
            $this->runTask($array, $taskName, $taskParams, $temporaryValue);
        }

        $this->executeUnsets($array);

        return $array;
    }

    /**
     * @param array $array
     */
    private function executeUnsets(&$array)
    {
        foreach ($this->scheduledUnsets as $path => $nobodyCares) {
            RecursiveAccessor::unsetInPath($array, $path);
        }
    }

    /**
     * @uses executeCast
     * @uses executeRead
     * @uses executeWrite
     * @uses scheduleUnset
     * @uses executeCustom
     *
     * @param array $array
     * @param string $taskName
     * @param mixed $taskParams
     * @param mixed $temporaryValue
     */
    private function runTask(&$array, $taskName, $taskParams, &$temporaryValue)
    {
        $this->$taskName($array, $temporaryValue, $taskParams);
    }

    /**
     * @param array $array
     * @param mixed $temporaryValue
     * @param string $path
     */
    private function executeRead(&$array, &$temporaryValue, $path)
    {
        $temporaryValue = RecursiveAccessor::readSingleFromPath($array, $path);
    }

    /**
     * @param array $array
     * @param mixed $temporaryValue
     * @param string $path
     */
    private function executeWrite(&$array, $temporaryValue, $path)
    {
        RecursiveAccessor::integrateIntoPath($array, $path, $temporaryValue);
    }

    /**
     * @param array $array
     * @param mixed $temporaryValue
     * @param string $path
     */
    private function scheduleUnset(&$array, &$temporaryValue, $path)
    {
        $this->scheduledUnsets[$path] = 1;
    }

    /**
     * @param array $array
     * @param mixed $temporaryValue
     * @param string $type
     */
    private function executeCast(&$array, &$temporaryValue, $type)
    {
        settype($temporaryValue, $type);
    }

    /**
     * @param array $array
     * @param mixed $temporaryValue
     * @param array $customFunction
     */
    private function executeCustom(&$array, &$temporaryValue, $customFunction)
    {
        list($callable, $parameters) = $customFunction;
        call_user_func_array($callable, array_merge([&$array, &$temporaryValue], $parameters));
    }
}

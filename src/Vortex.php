<?php

namespace Macroparts\Vortex;

abstract class Vortex
{
    use \UnserAllerLib_Api_V4_AdapterProvider;

    const DIRECTIVE_FIELDS = 'fields';

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    /**
     * Defines data that can be included with an api call. You'd try to return only the most frequently used data
     * in default payload. Other available raw and aggregated data you'd make includable. Every availableInclude
     * has it's own include method and has to be mentioned in this configuration.
     *
     * Go to the Project Repository for an example configuration
     *
     * @var array
     */
    protected static $includeWhitelist = [];
    protected static $filterWhitelist = [];
    protected static $orderWhitelist = [];

    private $abstractFilterWhitelist = [
        'id' => '',
        'any' => '',
        'all' => ''
    ];

    /**
     * Used in the include configuration to tell if a property is a recursive inclusion or not
     */
    const INCLUDE_DIRECT = 0;
    const INCLUDE_RECURSIVE = 1;

    /**
     * Collects operations that have to be run on the doctrine result after query execution
     *
     * @var array
     */
    private $resultArrayFixSchedule = [];

    /**
     * Repositories can define a set of properties that are included by default
     *
     * @var array
     */
    protected static $defaultIncludes = [];

    /**
     * Repositories can define a default order which is taken by default
     *
     * @var array
     */
    protected static $defaultOrder = [];

    /**
     * If an ID is specified in the URL, it will be saved here for usage in child adapters.
     *
     * @var int|null
     */
    protected $id = null;

    /**
     * All parameters unsorted
     *
     * @var array
     */
    protected $unsortedParams = [];

    private $supportedLanguages;


    private $finalIncludeWhitelist;
    private $finalFilterWhitelist;
    private $finalOrderWhitelist;

    public function __construct($entityManager, $supportedLanguages = [])
    {
        $this->entityManager = $entityManager;
        $this->supportedLanguages = $supportedLanguages;

        //Cache customized whitelists merged with core whitelists
        $this->finalFilterWhitelist = $this->getStaticPropertyOfClassMergedWithParents(
            static::class,
            'filterWhitelist'
        );
        $this->finalOrderWhitelist = $this->getStaticPropertyOfClassMergedWithParents(static::class, 'orderWhitelist');
        $this->finalIncludeWhitelist = $this->getStaticPropertyOfClassMergedWithParents(
            static::class,
            'includeWhitelist'
        );
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @param \Doctrine\ORM\EntityManager $entityManager
     * @return $this
     */
    public function setEntityManager($entityManager)
    {
        $this->entityManager = $entityManager;
        return $this;
    }

    private function isNotIncludableProperty($property)
    {
        return !isset($this->finalIncludeWhitelist[$property]);
    }

    private function isNotFilterableProperty($property)
    {
        return !isset($this->finalFilterWhitelist[$property]) && !isset($this->abstractFilterWhitelist[$property]);
    }

    private function isNotOrderableProperty($property)
    {
        return !isset($this->finalOrderWhitelist[$property]);
    }

    public function getTranslatableIncludeNames()
    {
        return array_keys(array_filter($this->finalIncludeWhitelist, function ($inc) {
            return isset($inc['translatable']) && $inc['translatable'];
        }));
    }

    /**
     * @return array
     */
    private function getPlatformOptions()
    {
        return \Zend_Registry::get('platformOptions');
    }

    /**
     * @return bool
     */
    protected function arePlatformUsersPublic()
    {
        return (bool)$this->getPlatformOptions()['user']['public'];
    }

    /**
     * @return bool
     */
    protected function arePlatformUsersPrivate()
    {
        return !$this->arePlatformUsersPublic();
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @param array $additionalParams
     * @param $language
     * @param $filtername
     * @return \Doctrine\ORM\Query\Expr\Orx
     * @throws \UnserAllerLib_Api_V4_Exception_MissingFilterDirective
     * @throws \UnserAllerLib_Api_V4_Exception_SafeForPrinting
     */
    private function filterAny($query, $alias, $currentUser, $additionalParams, $language, $filtername)
    {
        return $this->abstractFilterMultipleFields(
            $query,
            'orX',
            $currentUser,
            $additionalParams,
            $language,
            $filtername
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @param array $additionalParams
     * @param $language
     * @param $filtername
     * @return \Doctrine\ORM\Query\Expr\Orx
     * @throws \UnserAllerLib_Api_V4_Exception_MissingFilterDirective
     * @throws \UnserAllerLib_Api_V4_Exception_SafeForPrinting
     */
    private function filterAll($query, $alias, $currentUser, $additionalParams, $language, $filtername)
    {
        return $this->abstractFilterMultipleFields(
            $query,
            'andX',
            $currentUser,
            $additionalParams,
            $language,
            $filtername
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $expressionType
     * @param \UnserAller_Model_User $currentUser
     * @param array $additionalParams
     * @param $language
     * @param $filtername
     * @return \Doctrine\ORM\Query\Expr\Orx
     * @throws \UnserAllerLib_Api_V4_Exception_MissingFilterDirective
     * @throws \UnserAllerLib_Api_V4_Exception_SafeForPrinting
     */
    private function abstractFilterMultipleFields(
        $query,
        $expressionType,
        $currentUser,
        $additionalParams,
        $language,
        $filtername
    ) {
        if (!isset($additionalParams[self::DIRECTIVE_FIELDS])) {
            throw new \UnserAllerLib_Api_V4_Exception_MissingFilterDirective(
                $filtername,
                self::DIRECTIVE_FIELDS,
                ['fieldname1', 'fieldname2'],
                ':someFilterDirective(params):maySomeMoreDirectives...'
            );
        }

        $fields = $additionalParams[self::DIRECTIVE_FIELDS];
        if (count(array_intersect_key($this->finalFilterWhitelist, array_flip($fields))) !== count($fields)) {
            throw new \UnserAllerLib_Api_V4_Exception_SafeForPrinting(
                'Wrong use of "' . $filtername . '" filter. '.
                'One of your specified fields is not filterable. '.
                'Try using fields that are filterable.'
            );
        }

        unset($additionalParams[self::DIRECTIVE_FIELDS]);

        $expression = call_user_func([$query->expr(), $expressionType]);
        foreach ($fields as $field) {
            $filterMethod = $this->decodeMethodFromRequestedFilter($field);
            $expression->add($this->$filterMethod(
                $query, $filterMethod, $currentUser, $additionalParams, $language, $field,
                $this->finalFilterWhitelist[$field]
            ));
        }

        return $expression;
    }

    /**
     * Executes include methods driven by a include string. See API docs to know how this string looks like
     *
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param \UnserAller_Model_User $currentUser
     * @param $language
     * @param string $includeString
     * @param array $meta
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function addIncludeStatements($query, $currentUser, $language, $includeString, &$meta = [])
    {
        $requestedIncludes = $this->parseIncludeString($includeString, $this->finalIncludeWhitelist);

        $requestedIncludes = $requestedIncludes + static::$defaultIncludes;
        foreach ($requestedIncludes as $requestedInclude => $additionalParams) {
            if ($this->isNotIncludableProperty($requestedInclude)) {
                continue;
            }

            $includeMethod = $this->decodeMethodFromRequestedInclude($requestedInclude);
            $postProcessDirections = $this->$includeMethod($query, $includeMethod, $currentUser, $additionalParams,
                $language);

            if ($postProcessDirections) {
                $this->schedulePostProcessingDirections($postProcessDirections);
            }

            $this->updateMetaOnInclude($meta, $requestedInclude);
        }
        return $query;
    }

    /**
     * Collecting whitelist not just for current class but merged with all whitelists from parent classes.
     * So when we overwrite whitelists locally they are still including all the elements from core adapters.
     *
     * @param null|string $class
     * @param $propertyname
     * @return array
     */
    private function getStaticPropertyOfClassMergedWithParents($class, $propertyname)
    {
        $class = $class ? $class : static::class;
        $parent = get_parent_class($class);
        return $parent ? $this->getStaticPropertyOfClassOrArray(
            $class,
            $propertyname
        ) + $this->getStaticPropertyOfClassMergedWithParents(
            $parent,
            $propertyname
        ) : $this->getStaticPropertyOfClassOrArray($class, $propertyname);
    }

    private function getStaticPropertyOfClassOrArray($class, $propertyname)
    {
        return isset($class::$$propertyname) ? $class::$$propertyname : [];
    }

    private function updateMetaOnInclude(&$meta, $includeName)
    {
        $include = $this->finalIncludeWhitelist[$includeName];
        if (isset($include['model'])) {
            echo $includeName;
            $meta['modelnameIndex']["{$include['model']}"][] = $includeName;
        }
    }

    /**
     * Calls methods that add where conditions to a query driven by a string (see api docs for string format)
     *
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param \UnserAller_Model_User $currentUser
     * @param string $filterString
     * @param $language
     * @param string $joinFiltersWith
     * @return \Doctrine\ORM\QueryBuilder
     * @throws \UnserAllerLib_Api_V4_Exception_InvalidFilter
     * @uses filterAny
     * @uses filterAll
     */
    protected function addFilterStatements($query, $currentUser, $filterString, $language, $joinFiltersWith = 'AND')
    {
        $requestedFilters = array_filter($this->parseRichParamString($filterString));
        if (!$requestedFilters) {
            return $query;
        }

        $expression = mb_strtoupper($joinFiltersWith) === 'OR' ? $query->expr()->orX() : $query->expr()->andX();
        foreach ($requestedFilters as $requestedFilter => $additionalParams) {
            if ($this->isNotFilterableProperty($requestedFilter)) {
                throw new \UnserAllerLib_Api_V4_Exception_InvalidFilter($requestedFilter);
            }

            $filterMethod = $this->decodeMethodFromRequestedFilter($requestedFilter);
            $expression->add($this->$filterMethod(
                $query, $filterMethod, $currentUser, $additionalParams, $language, $requestedFilter,
                $this->finalFilterWhitelist[$requestedFilter]
            ));
        }

        return $query->andWhere($expression);
    }


    /**
     * Transforms additionalParams for included collections into params which are used
     * during post processing to call a findXForApi method
     *
     * @param array $additionalParams
     * @return array
     */
    protected function parseAdditionalIncludeParams($additionalParams)
    {
        $filter = \UnserAllerLib_Tool_Array::spliceElemOrNull($additionalParams, 'filter');
        $filter = is_array($filter) ? implode(',', $filter) : '';

        $include = \UnserAllerLib_Tool_Array::spliceElemOrNull($additionalParams, 'include');
        $include = is_array($include) ? implode(',', $include) : '';

        $order = \UnserAllerLib_Tool_Array::spliceElemOrNull($additionalParams, 'order');
        $order = is_array($order) ? implode(',', $order) : '';

        $limit = \UnserAllerLib_Tool_Array::spliceElemOrNull($additionalParams, 'limit');
        $limit = is_array($limit) ? (int)array_shift($limit) : 0;

        $page = \UnserAllerLib_Tool_Array::spliceElemOrNull($additionalParams, 'page');
        $page = is_array($page) ? (int)array_shift($page) : 1;

        $filterMode = \UnserAllerLib_Tool_Array::spliceElemOrNull($additionalParams, 'filterMode');
        $filterMode = is_array($filterMode) ? array_shift($filterMode) : 'AND';

        return [$filter, $include, $order, $limit, $page, $filterMode];
    }

    /**
     * Calls methods that add orderBy statements to a query driven by a string (see api docs for the string format)
     *
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param \UnserAller_Model_User $currentUser
     * @param string $orderString
     * @return \Doctrine\ORM\QueryBuilder
     * @throws \UnserAllerLib_Api_V4_Exception_InvalidOrder
     */
    private function addOrderStatements($query, $currentUser, $orderString)
    {
        $requestedOrders = array_filter($this->parseRichParamString($orderString));

        if (!$requestedOrders) {
            $requestedOrders = static::$defaultOrder;
        }

        foreach ($requestedOrders as $field => $order) {
            if ($this->isNotOrderableProperty($field)) {
                throw new \UnserAllerLib_Api_V4_Exception_InvalidOrder($field);
            }

            $orderMethod = $this->decodeMethodFromRequestedOrder($field);
            $postProcessTasks = $this->$orderMethod($query, $orderMethod, $currentUser,
                isset($order['desc']) ? 'DESC' : 'ASC', $order);
            if ($postProcessTasks) {
                $this->schedulePostProcessingDirections($postProcessTasks);
            }
        }

        return $query;
    }

    /**
     * Knows how to append post processing directions to the post process schedule
     *
     * @param array $tasks
     */
    private function schedulePostProcessingDirections($tasks)
    {
        if (!$tasks) {
            return;
        }

        if (!is_array($tasks[0])) {
            $tasks = [$tasks];
        }

        foreach ($tasks as $task) {
            $this->resultArrayFixSchedule[array_shift($task)] = $task;
        }
    }

    /**
     * Returns the name of the appropriate include method for $requestedInclude. The rule is simple:
     * uppercase first letter and every letter that comes after a dot, remove dots and prepend 'include'. Examples:
     *
     * returns includeProject when $requestedInclude = project
     * returns includePhaseProjectNumberOfLikes when $requestedInclude = phase.project.numerOfLikes
     *
     * @param string $requestedInclude
     * @return string
     */
    private function decodeMethodFromRequestedInclude($requestedInclude)
    {
        return 'include' . implode('', array_map('ucfirst', explode('.', str_replace('[]', '_cp', $requestedInclude))));
    }

    /**
     * Returns the name of the appropriate include method for $requestedInclude. The rule is simple:
     * uppercase first letter and every letter that comes after a dot, remove dots, prepend 'include'. Examples:
     *
     * returns includeProject when $requestedInclude = project
     * returns includePhaseProjectNumberOfLikes when $requestedInclude = phase.project.numerOfLikes
     *
     * @param string $requestedInclude
     * @return string
     */
    private function decodeMethodFromRequestedFilter($requestedInclude)
    {
        return 'filter' . implode('', array_map('ucfirst', explode('.', $requestedInclude)));
    }

    /**
     * Returns the name of the appropriate include method for $requestedInclude. The rule is simple:
     * uppercase first letter and every letter that comes after a dot, remove dots, prepend 'include'. Examples:
     *
     * returns includeProject when $requestedInclude = project
     * returns includePhaseProjectNumberOfLikes when $requestedInclude = phase.project.numerOfLikes
     *
     * @param string $field
     * @return string
     */
    private function decodeMethodFromRequestedOrder($field)
    {
        return 'orderBy' . implode('', array_map('ucfirst', explode('.', $field)));
    }

    /**
     * Calculates total pages for an $incompleteStatement. Incomplete statements are doctrine query builder instances
     * with all required conditions but no select statement and no additional includes.
     *
     * @param \Doctrine\ORM\QueryBuilder $incompleteStatement
     * @param int $limit
     * @return float|int
     */
    private function calculateTotalPages($incompleteStatement, $limit)
    {
        $incompleteStatement = clone $incompleteStatement;

        if ($limit) {
            return (int)ceil($this->executeRowCountStatement($incompleteStatement) / $limit);
        }
        return 1;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $incompleteStatement
     * @return int
     */
    private function executeRowCountStatement($incompleteStatement)
    {
        $rootAlias = $this->getRootAlias($incompleteStatement);
        $primaryIndexCol = $rootAlias . '.' . $this->getPrimaryIndexCol();

        if($incompleteStatement->getDQLPart('having')){
            return (int)$incompleteStatement->getEntityManager()->createQueryBuilder()
                ->select('COUNT(x)')
                ->from(array_shift($incompleteStatement->getRootEntities()),'x')
                ->where(
                    $incompleteStatement->expr()->in(
                        'x.'.$this->getPrimaryIndexCol(),
                        $incompleteStatement->select($primaryIndexCol)->getDQL()
                    )
                )->setParameters($incompleteStatement->getParameters())->getQuery()->getSingleScalarResult();
        }

        return (int)$incompleteStatement
            ->select("COUNT(DISTINCT $primaryIndexCol)")
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Doctrine will throw errors if a table has a multi column primary index
     * http://stackoverflow.com/questions/18968963/select-countdistinct-error-on-multiple-columns
     * @return string
     */
    protected function getPrimaryIndexCol()
    {
        return 'id';
    }

    /**
     * Todo: Include collections by additional params and not by includes and adjust docs
     * Takes the include string and decodes it to an array with include names as keys and an array with additionalParams
     * as the value. Includes that are nested inside included collections are grouped and added as additional params
     * to the included collection.
     *
     * @param $string
     * @param $availableIncludes
     * @return array
     */
    private function parseIncludeString($string, $availableIncludes)
    {
        if ($string === '') {
            return [];
        }

        if (is_string($string)) {
            $string = explode(',', $string);
        }

        if (!is_array($string)) {
            return [];
        }

        $requestedIncludes = [];
        $implicitIncludes = [];
        foreach ($string as $include) {
            list($includeName, $allModifiersStr) = array_pad(explode(':', $include, 2), 2, null);

            $pathToFirstRecursiveInclusion = $this->pathForNestedInclude($includeName, $availableIncludes);
            if ($pathToFirstRecursiveInclusion) {
                $requestedIncludes[$pathToFirstRecursiveInclusion]['include'][] = substr(
                    $include,
                    strlen($pathToFirstRecursiveInclusion) + 1
                );
                continue;
            }

            $implicitIncludes = array_merge($implicitIncludes, $this->getImplicitIncludes($includeName));

            if ($allModifiersStr === null) {
                if (!isset($requestedIncludes[$includeName])) {
                    $requestedIncludes[$includeName] = [];
                }
                continue;
            }

            if (preg_match('~filter\(~u', $allModifiersStr)) {
                $modifierArr = $this->parseModifierArraySlowButAccurate($allModifiersStr);
            } else {
                $modifierArr = $this->parseModifierStringQuickButInaccurate($allModifiersStr);
            }

            if (isset($requestedIncludes[$includeName])) {
                $requestedIncludes[$includeName] = $requestedIncludes[$includeName] + $modifierArr;
            } else {
                $requestedIncludes[$includeName] = $modifierArr;
            }
        }

        return $this->mergeWithImplicitIncludes($requestedIncludes, $implicitIncludes);
    }

    /**
     * creates an array out of string in this format:
     *
     * modifierName1(modifierParam1|modifierParam2):modifierName2(modifierParam3)
     *
     * Result:
     * [
     *  'modifierName1' => ['modifierParam1','modifierParam2'],
     *  'modifierName2' => ['modifierParam3']
     * ]
     *
     * But doesn't work when modifier params contain other modifiers with params themselves
     *
     * @param string $allModifiersStr
     * @return array
     */
    private function parseModifierStringQuickButInaccurate($allModifiersStr)
    {
        // Matches multiple instances of 'something(foo|bar|baz)' in the string
        // I guess it ignores : so you could use anything, but probably don't do that
        preg_match_all('/([\w]+)(\(([^\)]+)\))?/', $allModifiersStr, $allModifiersArr);
        // [0] is full matched strings...
        $modifierCount = count($allModifiersArr[0]);
        $modifierArr = [];
        for ($modifierIt = 0; $modifierIt < $modifierCount; $modifierIt++) {
            // [1] is the modifier
            $modifierName = $allModifiersArr[1][$modifierIt];
            // and [3] is delimited params
            $modifierParamStr = $allModifiersArr[3][$modifierIt];
            // Make modifier array key with an array of params as the value
            $modifierArr[$modifierName] = explode('|', $modifierParamStr);
        }

        return $modifierArr;
    }

    /**
     * creates an array out of string in this format:
     *
     * modifierName1(modifierParam1|modifierParam2):modifierName2(modifierParam3))
     *
     * Can also handle modifier params that contain other modifier with params
     *
     * @param string $s
     * @return array
     */
    private function parseModifierArraySlowButAccurate($s)
    {
        $modifierArr = [];
        $modifierName = '';
        $modifierParamStr = '';

        $depth = 0;
        for ($i = 0; $i <= strlen($s); $i++) {
            switch ($s[$i]) {
                case '(':
                    if ($depth) {
                        $modifierParamStr .= $s[$i];
                    }
                    $depth++;
                    break;

                case ')':
                    $depth--;
                    if ($depth) {
                        $modifierParamStr .= $s[$i];
                    }
                    break;
                case ':':
                    if ($depth) {
                        $modifierParamStr .= $s[$i];
                    } else {
                        $modifierArr[$modifierName] = $this->parseModifierParamStringSlowButAccurate($modifierParamStr);
                        $modifierName = '';
                        $modifierParamStr = '';
                    }
                    break;
                default:
                    if ($depth) {
                        $modifierParamStr .= $s[$i];
                    } else {
                        $modifierName .= $s[$i];
                    }
            }
        }

        if ($modifierName) {
            $modifierArr[$modifierName] = $this->parseModifierParamStringSlowButAccurate($modifierParamStr);
        }

        return $modifierArr;
    }

    /**
     * Can make an array out of parameter string that looks like this:
     *
     * param1|param2|param3
     *
     * Can also handle params that contain other modifiers with params like this:
     * param1|modifier(innerParam1|innerParam2)|param3
     *
     * @param string $s
     * @return array
     */
    private function parseModifierParamStringSlowButAccurate($s)
    {
        $paramArr = [];
        $tmpStr = '';

        $depth = 0;
        for ($i = 0; $i <= strlen($s); $i++) {
            switch ($s[$i]) {
                case '(':
                    $tmpStr .= $s[$i];
                    $depth++;
                    break;

                case ')':
                    $tmpStr .= $s[$i];
                    $depth--;
                    break;

                case '|':
                    if ($depth) {
                        $tmpStr .= $s[$i];
                    } else {
                        $paramArr[] = $tmpStr;
                        $tmpStr = '';
                    }
                    break;

                default:
                    $tmpStr .= $s[$i];
            }
        }

        if (strlen($tmpStr)) {
            $paramArr[] = $tmpStr;
        }

        return $paramArr;
    }

    /**
     * Checks if includeName is an include nested inside a recursive inclusion.
     * If yes, return the path to that item - false otherwise.
     *
     * Example:
     * For projects there can be an include for phases. Phases are included recursively in its own adapter. So you'd
     * want when you include phases.steps that the steps inclusion is executed in the phase adapter and not in the
     * project adapter. That's why we need to separate includes that need to be passed further here.
     *
     * "recursiveinclude" results to false
     * "normalprop1" results to false
     * "recursiveinclude.normalprop1.normalprop2" results to "recursiveinclude"
     * "normalprop1.recursiveinclude.normalprop2" results to "normalprop1.recursiveinclude"
     *
     * @param $includeName
     * @param $availableIncludes
     * @return bool|string
     */
    private function pathForNestedInclude($includeName, $availableIncludes)
    {
        $pathArray = explode('.', $includeName);
        if (!isset($pathArray[1])) {
            return false;
        }

        $pathArrayLength = count($pathArray);
        for ($i = 1; $i < $pathArrayLength; $i++) {
            $implicitPath = implode('.', array_slice($pathArray, 0, $i));
            if ($this->extractStrategyForInclude($availableIncludes[$implicitPath]) === self::INCLUDE_RECURSIVE) {
                return $implicitPath;
            }
        }

        return false;
    }

    /**
     * Include configuration can either have just the strategy or a configuration array with the strategy inside.
     *
     * @param mixed $include
     * @return integer
     */
    private function extractStrategyForInclude($include)
    {
        return is_array($include) ? $include['strategy'] : $include;
    }

    /**
     * Validates the include string and returns an array with requiredIncludes
     *
     * @param string $string
     * @return array
     */
    private function parseRichParamString($string)
    {
        if ($string === '') {
            return [];
        }

        if (is_string($string)) {
            $string = explode(',', $string);
        }

        if (!is_array($string)) {
            return [];
        }

        $requestedIncludes = [];
        $implicitIncludes = [];
        foreach ($string as $include) {
            list($includeName, $allModifiersStr) = array_pad(explode(':', $include, 2), 2, null);
            $implicitIncludes = array_merge($implicitIncludes, $this->getImplicitIncludes($includeName));

            if ($allModifiersStr === null) {
                $requestedIncludes[$includeName] = [];
                continue;
            }

            // Matches multiple instances of 'something(foo|bar|baz)' in the string
            // I guess it ignores : so you could use anything, but probably don't do that
            preg_match_all('/([\w]+)(\(([^\)]+)\))?/', $allModifiersStr, $allModifiersArr);
            // [0] is full matched strings...
            $modifierCount = count($allModifiersArr[0]);
            $modifierArr = [];
            for ($modifierIt = 0; $modifierIt < $modifierCount; $modifierIt++) {
                // [1] is the modifier
                $modifierName = $allModifiersArr[1][$modifierIt];
                // and [3] is delimited params
                $modifierParamStr = $allModifiersArr[3][$modifierIt];
                // Make modifier array key with an array of params as the value
                $modifierArr[$modifierName] = explode('|', $modifierParamStr);
            }
            $requestedIncludes[$includeName] = $modifierArr;
        }

        return $this->mergeWithImplicitIncludes($requestedIncludes, $implicitIncludes);
    }

    private function mergeWithImplicitIncludes($includes, $implicitIncludes)
    {
        foreach ($implicitIncludes as $implicitInclude) {
            if (isset($includes[$implicitInclude])) {
                continue;
            }

            $includes[$implicitInclude] = [];
        }

        return $includes;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @param array $additionalParams
     * @return \Doctrine\ORM\Query\Expr\Andx
     */
    protected function filterId($query, $alias, $currentUser, $additionalParams)
    {
        $rootAlias = array_shift($query->getRootAliases());
        return $this->createConditionsForEntityColumn("$rootAlias.id", $query, $alias, $currentUser, $additionalParams);
    }

    private function getImplicitIncludes($includeName)
    {
        $parts = explode('.', $includeName);
        $numberOfParts = count($parts);

        if ($numberOfParts < 2) {
            return [];
        }

        $implicitIncludes = [];
        for ($i = 1; $i < $numberOfParts; $i++) {
            $implicitIncludes[] = implode('.', array_slice($parts, 0, $i));
        }

        return $implicitIncludes;
    }

    /**
     * Creates fixed, paginated results from an $incompleteStatement and a requiredIncludes string. An incomplete
     * statement is a query builder instance with only froms, joins and where conditions (groupbys, havings not tested).
     *
     * @param \UnserAller_Model_User $currentUser
     * @param $language
     * @param \Doctrine\ORM\QueryBuilder $incompleteStatement
     * @param string $filterString
     * @param string $includeString
     * @param int $limit
     * @param int $page
     * @return array
     */
    protected function createPaginatedResults(
        $currentUser,
        $language,
        $incompleteStatement,
        $filterString,
        $includeString,
        $limit,
        $page
    ) {
        $this->addFilterStatements($incompleteStatement, $currentUser, $filterString, $language);
        $completeStatement = $this->completeStatement(
            $currentUser,
            $language,
            $incompleteStatement,
            $includeString,
            ''
        );

        if ($limit > 0) {
            $completeStatement
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);
        }

        return [
            'totalPages' => $this->calculateTotalPages($incompleteStatement, $limit),
            'page' => $page,
            'filter' => $filterString,
            'include' => $includeString,
            'pageSize' => $limit,
            'data' => $this->applyScheduledFixes($this->getRawResult($completeStatement), $currentUser, $language)
        ];
    }

    /**
     * @param \UnserAller_Model_User $currentUser
     * @return \Doctrine\ORM\QueryBuilder
     */
    abstract protected function initIncompleteStatement($currentUser);

    private function createIncompleteStatement(
        $currentUser,
        $filterString,
        $language,
        $joinFiltersWith = 'AND',
        &$meta = []
    ) {
        return $this->addFilterStatements(
            $this->initIncompleteStatement($currentUser),
            $currentUser,
            $filterString,
            $language,
            $joinFiltersWith
        );
    }

    /**
     * @param \UnserAller_Model_User $currentUser
     * @param string $filterString
     * @param $language
     * @param string $joinFiltersWith
     * @return int
     */
    public function findTotalNumberOfRows($currentUser, $filterString = '', $language = '', $joinFiltersWith = 'AND')
    {
        return $this->executeRowCountStatement(
            $this->createIncompleteStatement($currentUser, $filterString, $language, $joinFiltersWith)
        );
    }

    /**
     * @param \UnserAller_Model_User $currentUser
     * @param $language
     * @param string $filterString
     * @param string $includeString
     * @param string $orderString
     * @param int $limit
     * @param int $page
     * @param string $joinFiltersWith
     * @return array
     */
    public function findMultipleForApi(
        $currentUser,
        $language = '',
        $filterString = '',
        $includeString = '',
        $orderString = '',
        $limit = 0,
        $page = 1,
        $joinFiltersWith = 'AND'
    ) {
        if ($page <= 0) {
            $page = 1;
        }

        $meta = $this->initMetaArray('', $language);

        $incompleteStatement = $this->createIncompleteStatement(
            $currentUser,
            $filterString,
            $language,
            $joinFiltersWith,
            $meta
        );

        $completeStatement = $this->completeStatement(
            $currentUser,
            $language,
            $incompleteStatement,
            $includeString,
            $orderString,
            $meta
        );
        if ($limit > 0) {
            $completeStatement
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);
        }

        return [
            'totalPages' => $this->calculateTotalPages($incompleteStatement, $limit),
            'filter' => $filterString,
            'include' => $includeString,
            'page' => $page,
            'pageSize' => $limit,
            'data' => $this->applyScheduledFixes(
                $this->getRawResult($completeStatement),
                $currentUser,
                $language,
                $meta
            ),
            'meta' => $meta
        ];
    }

    private function initMetaArray($modelPathOffset = '', $language = '')
    {
        return [
            'modelnameIndex' => [
                $this->getModelForMeta() => [$modelPathOffset]
            ],
            'language' => $language
        ];
    }

    /**
     * @param string $language
     * @param string $filterString
     * @param string $includeString
     * @param string $orderString
     * @param int $limit
     * @param int $page
     * @param string $filterMode
     * @return array
     */
    public function findMultiple(
        $language = '',
        $filterString = '',
        $includeString = '',
        $orderString = '',
        $limit = 0,
        $page = 1,
        $filterMode = 'AND'
    ) {
        return json_decode(json_encode($this->findMultipleForApi(
            $this->getCurrentlyAuthenticatedUser(),
            $language,
            $filterString,
            $includeString,
            $orderString,
            $limit,
            $page,
            $filterMode
        )), true);
    }

    /**
     * @param \UnserAller_Model_User $currentUser
     * @param string $language
     * @param string $filterString
     * @param string $includeString
     * @param string $orderString
     * @param int $limit
     * @param string $filterMode
     * @return \Generator
     */
    public function batchFindMultiple(
        $currentUser,
        $language = '',
        $filterString = '',
        $includeString = '',
        $orderString = '',
        $limit = 500,
        $filterMode = 'AND'
    ) {
        $page = 1;

        $result = $this->findMultipleForApi(
            $currentUser,
            $language,
            $filterString,
            $includeString,
            $orderString,
            $limit,
            $page,
            $filterMode
        );

        yield $result;

        $totalPages = $result['totalPages'];
        unset($result);
        $page++;
        while ($page <= $totalPages) {
            $result = $this->findMultipleForApi(
                $currentUser,
                $language,
                $filterString,
                $includeString,
                $orderString,
                $limit,
                $page,
                $filterMode
            );
            yield $result;
            $page++;
            unset($result);
        }
    }

    /**
     * @param \UnserAller_Model_User $currentUser
     * @param string $language
     * @param string $filterString
     * @param string $includeString
     * @param string $orderString
     * @param int $limit
     * @param int $page
     * @param string $filterMode
     * @return array
     */
    public function getNativeSqlIngredientsForFindMultiple(
        $currentUser,
        $language = '',
        $filterString = '',
        $includeString = '',
        $orderString = '',
        $limit = 0,
        $page = 1,
        $filterMode = 'AND'
    ) {
        if ($page <= 0) {
            $page = 1;
        }

        $incompleteStatement = $this->createIncompleteStatement($currentUser, $filterString, $language, $filterMode);

        $completeStatement = $this->completeStatement(
            $currentUser,
            $language,
            $incompleteStatement,
            $includeString,
            $orderString
        );
        if ($limit > 0) {
            $completeStatement
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);
        }

        return $this->getNativeSqlIngredients($completeStatement->getQuery());
    }

    /**
     * @param \Doctrine\ORM\Query $query
     * @return array
     */
    private function getNativeSqlIngredients($query)
    {
        $sql = $query->getSQL();
        $c = new \ReflectionClass('Doctrine\ORM\Query');
        $parser = $c->getProperty('_parserResult');
        $parser->setAccessible(true);
        /** @var \ReflectionProperty $parser */
        $parser = $parser->getValue($query);
        /** @var \Doctrine\ORM\Query\ParserResult $parser */
        $resultSet = $parser->getResultSetMapping();

        // Change the aliases back to what was originally specified in the QueryBuilder.
        $sql = preg_replace_callback('/AS\s([a-zA-Z0-9_]+)/', function ($matches) use ($resultSet) {
            $ret = 'AS ';
            if ($resultSet->isScalarResult($matches[1])) {
                $ret .= $resultSet->getScalarAlias($matches[1]);
            } else {
                $ret .= $matches[1];
            }
            return $ret;
        }, $sql);
        $m = $c->getMethod('processParameterMappings');
        $m->setAccessible(true);
        list($params, $types) = $m->invoke($query, $parser->getParameterMappings());
        return [$sql, $params, $types];
    }

    /**
     * @param \UnserAller_Model_User $currentUser
     * @param string $language
     * @param string $filterString
     * @param string $includeString
     * @param string $orderString
     * @return array|null
     */
    public function findOneForApi(
        $currentUser,
        $language = '',
        $filterString = '',
        $includeString = '',
        $orderString = ''
    ) {
        return $this->createSingleResult($currentUser, $language, $filterString, $includeString, $orderString);
    }

    /**
     * @param string $language
     * @param string $filterString
     * @param string $includeString
     * @param string $orderString
     * @return array|null
     */
    public function findOne($language = '', $filterString = '', $includeString = '', $orderString = '')
    {
        return json_decode(json_encode($this->findOneForApi(
            $this->getCurrentlyAuthenticatedUser(),
            $language,
            $filterString,
            $includeString,
            $orderString
        )), true);
    }

    /**
     * @param \UnserAller_Model_User $currentUser
     * @param int $id
     * @param string $language
     * @param string $include
     * @return array|null
     */
    public function findForApi($currentUser, $id, $language = '', $include = '')
    {
        $this->id = (int)$id;
        return $this->findOneForApi($currentUser, $language, "id:is($id)", $include, '');
    }

    /**
     * @param int $id
     * @param string $language
     * @param string $include
     * @return array|null
     */
    public function find($id, $language = '', $include = '')
    {
        return json_decode(json_encode($this->findForApi(
            $this->getCurrentlyAuthenticatedUser(),
            $id,
            $language,
            $include
        )), true);
    }

    /**
     * @return null|\UnserAller_Model_User|object
     */
    private function getCurrentlyAuthenticatedUser()
    {
        return $this->getEntityManager()->find(
            \UnserAller_Model_User::class,
            (int)\Zend_Auth::getInstance()->getIdentity()
        );
    }

    /**
     * @param \UnserAller_Model_User $currentUser
     * @param string $language
     * @param string $filterString
     * @param string $includeString
     * @param string $orderString
     * @return array|null
     */
    protected function createSingleResult($currentUser, $language, $filterString, $includeString, $orderString)
    {
        $meta = $this->initMetaArray('', $language);

        $result = $this->getRawResult(
            $this->completeStatement(
                $currentUser,
                $language,
                $this->createIncompleteStatement($currentUser, $filterString, $language, 'AND', $meta),
                $includeString,
                $orderString,
                $meta
            )->setFirstResult(0)->setMaxResults(1)
        );

        if (!isset($result[0])) {
            return null;
        }

        return $this->applyScheduledFixes($result, $currentUser, $language, $meta)[0] + ['meta' => $meta];
    }

    protected function getDefaultPostProcessDirections()
    {
        return [];
    }

    /**
     * Adds the default select statement, all includes and order statements to the incomplete statement
     * and returns the qurey builder instance
     *
     * @param \UnserAller_Model_User $currentUser
     * @param $language
     * @param \Doctrine\ORM\QueryBuilder $incompleteStatement
     * @param string $includeString
     * @param string $orderString
     * @param array $meta
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function completeStatement(
        $currentUser,
        $language,
        $incompleteStatement,
        $includeString,
        $orderString,
        &$meta = []
    ) {
        $statement = clone $incompleteStatement;

        $this->schedulePostProcessingDirections($this->getDefaultPostProcessDirections());

        return $this->addOrderStatements(
            $this->addIncludeStatements(
                $statement->select($this->getDefaultSelectStatement($statement)),
                $currentUser,
                $language,
                $includeString,
                $meta
            ),
            $currentUser,
            $orderString
        );
    }

    /**
     * Returns the default select statement. In this case it just returns the first root entity which means the
     * entire root entity will be selected
     *
     * @param \Doctrine\ORM\QueryBuilder $query
     * @return string
     */
    protected function getDefaultSelectStatement($query)
    {
        return 'DISTINCT ' . $this->getRootAlias($query);
    }

    /**
     * Returns first root alias from query builder
     *
     * @param \Doctrine\ORM\QueryBuilder $query
     * @return string
     */
    protected function getRootAlias($query)
    {
        return array_shift($query->getRootAliases());
    }

    /**
     * Returns true if result item has an additional layer in the hierarchy because of custom subselects
     *
     * @param array $item
     * @return bool
     */
    private function mustFlattenResultItem($item)
    {
        return isset($item[0]);
    }

    /**
     * Returns doctrine array results with all fixes applied
     *
     * @param \Doctrine\ORM\QueryBuilder $statement
     * @return array
     */
    private function getRawResult($statement)
    {
        //Output raw sql here if you like to debug hard
        //echo $statement->getQuery()->getSQL(); die;
        return $statement->getQuery()->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
    }

    /**
     * Doctrine will create an additional result layer when values are selected that do not belong
     * to the doctrine object model. This function removes this additional layer and merges custom values with
     * the doctrine object model for a single result item - not the whole result array.
     *
     * @param array $item
     * @return array
     */
    private function flattenResultItem($item)
    {
        if (!$this->mustFlattenResultItem($item)) {
            return $item;
        }

        return array_merge(array_shift($item), $item);
    }

    /**
     * Parses $string for orderBy statements and returns an array where order statements are values.
     *
     * When string is "latestFirst, longestFirst" result would be: ['latestFirst', 'longestFirst']
     *
     * @param string $string
     * @return array
     */
    protected function parseOrderString($string)
    {
        return array_filter(array_map('trim', explode(',', $string)));
    }

    /**
     * Executes all operations that were scheduled for post processing
     *
     * @param array $result
     * @param \UnserAller_Model_User $currentUser
     * @param $language
     * @param array $meta
     * @return array
     */
    private function applyScheduledFixes($result, $currentUser, $language, &$meta = [])
    {
        $scheduledFixes = $this->flushResultArrayFixSchedule();

        if (!$result) {
            return $result;
        }

        $numberOfResults = count($result);

        $this->applyFixesToItem(
            $result[0],
            $scheduledFixes,
            $currentUser,
            $meta,
            'retrieveNestedCollectionAndMergeMeta',
            $language
        );
        for ($i = 1; $i < $numberOfResults; $i++) {
            $this->applyFixesToItem(
                $result[$i],
                $scheduledFixes,
                $currentUser,
                $meta,
                'retrieveNestedCollection',
                $language
            );
        }

        return $result;
    }

    private function retrieveNestedCollectionResult($value, $nestingOptions, $language = '')
    {
        list($model, $filterFunction, $currentUser, $additionalParams) = $nestingOptions;
        list(
            $filterString,
            $includeString,
            $orderString,
            $limit,
            $page,
            $filterMode
        ) = $this->parseAdditionalIncludeParams($additionalParams);

        if ($filterString) {
            $filterString = $filterString . ',';
        }

        if (is_array($value)) {
            $filterFunctionString = vsprintf($filterFunction, array_merge($value));
        } else {
            $filterFunctionString = sprintf($filterFunction, $value);
        }

        return $this->getAdapter($model)->findMultipleForApi(
            $currentUser,
            $language,
            $filterString . $filterFunctionString,
            $includeString,
            $orderString,
            $limit,
            $page,
            $filterMode
        );
    }

    private function retrieveNestedCollection($value, $nestingOptions, $language, $finalPath, $meta)
    {
        return $this->retrieveNestedCollectionResult($value, $nestingOptions, $language)['data'];
    }

    private function retrieveNestedCollectionAndMergeMeta($value, $nestingOptions, $language, $finalPath, &$meta)
    {
        $result = $this->retrieveNestedCollectionResult($value, $nestingOptions, $language);
        $this->mergeNestedMeta($meta, $result['meta'], $finalPath);
        return $result['data'];
    }

    private function retrieveNestedSingleResult($value, $nestingOptions, $language = '')
    {
        list($model, $filterFunction, $currentUser, $additionalParams) = $nestingOptions;
        list($filterString, $includeString, $orderString, , ,) = $this->parseAdditionalIncludeParams($additionalParams);

        if ($filterString) {
            $filterString = $filterString . ',';
        }

        return $this->getAdapter($model)->findOneForApi(
            $currentUser,
            $language,
            $filterString . sprintf($filterFunction, $value),
            $includeString,
            $orderString
        );
    }

    private function retrieveNestedSingleAndMergeMeta($value, $nestingOptions, $language, $finalPath, &$meta)
    {
        $result = $this->retrieveNestedSingleResult($value, $nestingOptions, $language);
        $this->mergeNestedMeta($meta, $result['meta'], $finalPath);
        unset($result['meta']);
        return $result;
    }

    private function mergeNestedMeta(&$meta, $nestedMeta, $includeName)
    {
        foreach ($nestedMeta['modelnameIndex'] as $model => $paths) {
            foreach ($paths as $path) {
                $fullPath = $includeName . '.' . $path;
                if ($path && !in_array($fullPath, $meta['modelnameIndex'][$model])) {
                    $meta['modelnameIndex'][$model][] = $fullPath;
                }
            }
        }
    }

    /**
     * @param $item
     * @param $scheduledFixes
     * @param $currentUser
     * @param $meta
     * @param $collectionNestingMethod
     * @param $language
     * @uses retrieveNestedCollection
     * @uses retrieveNestedCollectionAndMergeMeta
     */
    private function applyFixesToItem(
        &$item,
        $scheduledFixes,
        $currentUser,
        &$meta,
        $collectionNestingMethod,
        $language
    ) {
        $item = $this->flattenResultItem($item);

        //If deleteion is not scheduled and done at the end, multiple tasks on same field can fail
        $scheduledDeletions = [];

        foreach ($scheduledFixes as $path => $fix) {
            if (isset($fix['delete'])) {
                $scheduledDeletions[$path] = $fix;
                continue;
            }

            if (isset($fix['cast'])) {
                \UnserAllerLib_Tool_Array::castNestedValue($item, $path, $fix['cast']);
            }

            $value = \UnserAllerLib_Tool_Array::readNestedValue($item, $path);

            if (isset($fix['additionalFilterValues'])) {
                $value = [$value];

                foreach ($fix['additionalFilterValues'] as $additionalFilterValue) {
                    $value[] = \UnserAllerLib_Tool_Array::readNestedValue($item, $additionalFilterValue);
                }
            }

            if (isset($fix['nestCollection'])) {
                $value = $this->$collectionNestingMethod($value, $fix['nestCollection'], $language,
                    $fix['move'] ? $fix['move'] : $path, $meta);
            }

            if (isset($fix['nestSingle'])) {
                $value = $this->retrieveNestedSingleAndMergeMeta(
                    $value,
                    $fix['nestSingle'],
                    $language,
                    $fix['move'] ? $fix['move'] : $path,
                    $meta
                );
            }

            if (isset($fix['filter'])) {
                $value = $this->filterValue($fix['filter'], $value, $currentUser);
            }

            if (isset($fix['cFilter'])) {
                $value = $this->filterValue($fix['cFilter'], $value, $currentUser);
            }

            if (isset($fix['mFilter'])) {
                $value = $this->filterValue($fix['mFilter'], $item, $currentUser);
            }

            if (isset($fix['move'])) {
                \UnserAllerLib_Tool_Array::integrateNestedValue($item, $fix['move'], $value);
                if ($path != $fix['move']) {
                    $scheduledDeletions[$path] = ['delete' => 1];
                }
            }
        }

        foreach ($scheduledDeletions as $path => $fix) {
            \UnserAllerLib_Tool_Array::unsetNestedValue($item, $path);
        }
    }

    /**
     * Applies filter methods for $filterName to $value
     * @uses filterJsonAfterwards
     * @uses filterJsonIfNullSetEmptyObjectAfterwards
     * @uses filterJsonOrNullAfterwards
     * @uses filterDatetimeAfterwards
     * @uses filterDatetimeOrNullAfterwards
     * @uses filterIntOrNullAfterwards
     * @uses filterNl2BrAfterwards
     * @param string $filterName
     * @param mixed $value
     * @param $currentUser
     * @return mixed
     */
    private function filterValue($filterName, $value, $currentUser)
    {
        if (!is_callable([$this, $filterName])) {
            throw new \InvalidArgumentException('Post Processing Filter method not found: ' . $filterName);
        }

        return call_user_func_array([$this, $filterName], [$value, $currentUser]);
    }

    /**
     * @param $field
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @param array $methods
     * @return \Doctrine\ORM\Query\Expr\Andx
     * @uses stringContainExpression
     * @uses stringContainsExpression
     * @uses stringIsExpression
     * @uses stringNotExpression
     * @uses stringFalseExpression
     * @uses stringTrueExpression
     */
    protected function createConditionsForStringColumn($field, $query, $alias, $currentUser, $methods)
    {
        if (\UnserAllerLib_Tool_Array::hasMoreKeysThan(
            $methods,
            ['contain', 'contains', 'is', 'not', 'false', 'true']
        )
        ) {
            throw new \InvalidArgumentException('Invalid expression methods used');
        }

        return $this->createExpression('string', $field, $query, $alias, $currentUser, $methods);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param $fallbackField
     * @param $translationName
     * @param $language
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @param $additionalParams
     * @return \Doctrine\ORM\Query\Expr\Composite
     * @throws \UnserAllerLib_Api_V4_Exception_InvalidFilter
     */
    protected function createConditionsForMultilanguageStringColumn(
        $query,
        $fallbackField,
        $translationName,
        $language,
        $alias,
        $currentUser,
        $additionalParams
    ) {
        if (isset($additionalParams['overAllTranslations'])) {
            if (!$this->supportedLanguages) {
                throw new \UnserAllerLib_Api_V4_Exception_InvalidFilter('Supported languages are not set');
            }

            unset($additionalParams['overAllTranslations']);

            $expr = $query->expr()->orX();
            foreach ($this->supportedLanguages as $supportedLanguage) {
                $expr->add($this->createConditionsForStringColumn(
                    "COALESCE(" . $this->joinTranslationOnce(
                        $query,
                        $translationName,
                        $supportedLanguage
                    ) . ".translation, $fallbackField)",
                    $query,
                    $alias,
                    $currentUser,
                    $additionalParams
                ));
            }

            return $expr;
        }

        return $this->createConditionsForStringColumn(
            "COALESCE(" . $this->joinTranslationOnce(
                $query,
                $translationName,
                $language
            ) . ".translation, $fallbackField)",
            $query,
            $alias,
            $currentUser,
            $additionalParams
        );
    }


    /**
     * @param $field
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @param array $methods
     * @return \Doctrine\ORM\Query\Expr\Andx
     * @uses dateGtExpression
     * @uses dateGteExpression
     * @uses dateLtExpression
     * @uses dateLteExpression
     * @uses dateFalseExpression
     * @uses dateTrueExpression
     * @uses dateIsExpression
     * @uses dateNotExpression
     */
    protected function createConditionsForDatetimeColumn($field, $query, $alias, $currentUser, $methods)
    {
        if (\UnserAllerLib_Tool_Array::hasMoreKeysThan(
            $methods,
            ['is', 'not', 'gt', 'gte', 'lt', 'lte', 'false', 'true']
        )
        ) {
            throw new \InvalidArgumentException('Invalid expression methods used');
        }

        return $this->createExpression('date', $field, $query, $alias, $currentUser, $methods);
    }

    /**
     * @param $field
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @param array $methods
     * @return \Doctrine\ORM\Query\Expr\Andx
     * @uses integerFalseExpression
     * @uses integerTrueExpression
     * @uses integerIsExpression
     * @uses integerNotExpression
     * @uses integerMeExpression
     * @uses integerNotmeExpression
     */
    protected function createConditionsForEntityColumn($field, $query, $alias, $currentUser, $methods)
    {
        if (\UnserAllerLib_Tool_Array::hasMoreKeysThan($methods, ['false', 'true', 'is', 'not', 'me', 'notme'])) {
            throw new \InvalidArgumentException('Invalid expression methods used');
        }

        return $this->createExpression('integer', $field, $query, $alias, $currentUser, $methods);
    }

    /**
     * Translates params into where conditions. The subquery must really return an integer for it to work!
     * Returning null will cause wrong bahvior!!! In DQL it seems to be impossible to do an IS NULL comparison
     * on a subquery. And it seems to be impossible to not return null values either
     * Todo: Needs research, for time being only true comparison is working as expected
     *
     *
     * @param $subquery
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @param array $methods
     * @return \Doctrine\ORM\Query\Expr\Andx
     * @uses subqueryFalseExpression
     * @uses subqueryTrueExpression
     * @uses subqueryGtExpression
     * @uses subqueryGteExpression
     * @uses subqueryLtExpression
     * @uses subqueryLteExpression
     * @uses subqueryEqExpression
     * @uses subqueryAnyExpression
     * @uses subqueryNullExpression
     */
    protected function createConditionsForIntegerSubquery($subquery, $query, $alias, $currentUser, $methods)
    {
        if (\UnserAllerLib_Tool_Array::hasMoreKeysThan(
            $methods,
            ['false', 'true', 'gt', 'gte', 'lt', 'lte', 'eq', 'any', 'null']
        )
        ) {
            throw new \InvalidArgumentException('Invalid expression methods used');
        }

        return $this->createExpression('subquery', $subquery, $query, $alias, $currentUser, $methods);
    }

    /**
     * @param $subquery
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @param array $methods
     * @return \Doctrine\ORM\Query\Expr\Andx
     */
    protected function createConditionsForIntegerCollectionSubquery($subquery, $query, $alias, $currentUser, $methods)
    {
        if (\UnserAllerLib_Tool_Array::hasMoreKeysThan($methods, ['anyis'])) {
            throw new \InvalidArgumentException('Invalid expression methods used');
        }

        return $this->createExpression('subquery', $subquery, $query, $alias, $currentUser, $methods);
    }

    /**
     * Translates params into where conditions. The subquery must really return an integer for it to work!
     * Returning null will cause wrong bahvior!!! In DQL it seems to be impossible to do an IS NULL comparison
     * on a subquery. And it seems to be impossible to not return null values either
     * Todo: Needs research, for time being only true comparison is working as expected
     *
     *
     * @param $subquery
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @param array $methods
     * @return \Doctrine\ORM\Query\Expr\Andx
     * @uses subqueryAnyisExpression
     */
    protected function createConditionsForStringCollectionSubquery($subquery, $query, $alias, $currentUser, $methods)
    {
        if (\UnserAllerLib_Tool_Array::hasMoreKeysThan($methods, ['anyis'])) {
            throw new \InvalidArgumentException('Invalid expression methods used');
        }

        return $this->createExpression('subquery', $subquery, $query, $alias, $currentUser, $methods);
    }

    /**
     * Translates params into where conditions. The subquery must really return an integer for it to work!
     * Returning null will cause wrong bahvior!!! In DQL it seems to be impossible to do an IS NULL comparison
     * on a subquery. And it seems to be impossible to not return null values either
     * Todo: Needs research, for time being only true comparison is working as expected
     *
     *
     * @param $subquery
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @param array $methods
     * @return \Doctrine\ORM\Query\Expr\Andx
     * @uses subqueryTrueExpression
     * @uses subqueryFalseExpression
     */
    protected function createConditionsForDatetimeSubquery($subquery, $query, $alias, $currentUser, $methods)
    {
        if (\UnserAllerLib_Tool_Array::hasMoreKeysThan($methods, ['false', 'true'])) {
            throw new \InvalidArgumentException('Invalid expression methods used');
        }

        return $this->createExpression('subquery', $subquery, $query, $alias, $currentUser, $methods);
    }

    /**
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
     */
    protected function createConditionsForIntegerColumn($col, $query, $alias, $currentUser, $methods)
    {
        if (\UnserAllerLib_Tool_Array::hasMoreKeysThan(
            $methods,
            ['is', 'not', 'gt', 'gte', 'lt', 'lte', 'false', 'true']
        )
        ) {
            throw new \InvalidArgumentException('Invalid expression methods used');
        }

        return $this->createExpression('integer', $col, $query, $alias, $currentUser, $methods);
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

    /**
     * Knows how to create a callable from a subquery definition
     *
     * @param string $name of subquery
     * @param mixed[] $params for subquerymethod
     * @return callable
     */
    protected function locateCallableSubquery($name, $params)
    {
        return [$this, $name];
    }

    /**
     * @param array $subqueryDefinition
     * @return string DQL
     */
    private function consumeSubquery($subqueryDefinition)
    {
        list($name, $params) = $subqueryDefinition;
        return call_user_func_array(
            $this->locateCallableSubquery($name, $params),
            $params
        );
    }

    /**
     * @param $prefix
     * @param string $field
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @param array $methods
     * @return \Doctrine\ORM\Query\Expr\Andx
     */
    private function createExpression($prefix, $field, $query, $alias, $currentUser, $methods)
    {
        $expression = $query->expr()->andX();
        foreach ($methods as $method => $params) {
            $expression->add(call_user_func_array(
                [$this, $prefix . ucfirst($method) . 'Expression'],
                [$query, $field, $params, $alias, $currentUser]
            ));
        }

        return $expression;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param array $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function subqueryFalseExpression($query, $field, $params, $alias)
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
    private function subqueryNullExpression($query, $field, $params, $alias)
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
    private function subqueryTrueExpression($query, $subquery, $params, $alias)
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
    private function subqueryAnyisExpression($query, $subquery, $params, $alias)
    {
        $expression = $query->expr()->orX();
        foreach ($params as $param) {
            $alias = uniqid();
            $query->setParameter("param$alias", $param);
            $expression->add(
                $query->expr()->eq(":param$alias", $query->expr()->any($this->consumeSubquery($subquery)))
            );
        }
        return $expression;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param array $subquery
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function subqueryGtExpression($query, $subquery, $params, $alias)
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
    private function subqueryGteExpression($query, $subquery, $params, $alias)
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
    private function subqueryLteExpression($query, $subquery, $params, $alias)
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
    private function subqueryLtExpression($query, $subquery, $params, $alias)
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
    private function subqueryEqExpression($query, $subquery, $params, $alias)
    {
        return $query->expr()->andX(
            $query->expr()->exists($this->consumeSubquery($subquery)),
            $query->expr()->eq('(' . $this->consumeSubquery($subquery) . ')', $params[0])
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function dateIsExpression($query, $field, $params, $alias)
    {
        return $query->expr()->in($field, $params);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function integerIsExpression($query, $field, $params, $alias)
    {
        return $query->expr()->in($field, $params);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function stringIsExpression($query, $field, $params, $alias)
    {
        return $query->expr()->in($field, $params);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @return mixed
     * @throws \UnserAllerLib_Api_V4_Exception_UserRequiredButNotAuthenticated
     */
    private function integerMeExpression($query, $field, $params, $alias, $currentUser)
    {
        if (!$currentUser) {
            throw new \UnserAllerLib_Api_V4_Exception_UserRequiredButNotAuthenticated();
        }
        return $query->expr()->eq($field, $currentUser->getId());
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return \Doctrine\ORM\Query\Expr\Comparison
     * @throws \UnserAllerLib_Api_V4_Exception_UserRequiredButNotAuthenticated
     */
    private function integerAnyExpression($query, $field, $params, $alias)
    {
        return $query->expr()->eq($field, $query->expr()->any($this->consumeSubquery($params)));
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @param \UnserAller_Model_User $currentUser
     * @return \Doctrine\ORM\Query\Expr\Comparison
     * @throws \UnserAllerLib_Api_V4_Exception_UserRequiredButNotAuthenticated
     */
    private function integerNotmeExpression($query, $field, $params, $alias, $currentUser)
    {
        if (!$currentUser) {
            throw new \UnserAllerLib_Api_V4_Exception_UserRequiredButNotAuthenticated();
        }
        return $query->expr()->neq($field, $currentUser->getId());
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return \Doctrine\ORM\Query\Expr\Func
     */
    private function integerNotExpression($query, $field, $params, $alias)
    {
        return $query->expr()->notIn($field, $params);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return \Doctrine\ORM\Query\Expr\Func
     */
    private function dateNotExpression($query, $field, $params, $alias)
    {
        return $query->expr()->notIn($field, $params);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function stringNotExpression($query, $field, $params, $alias)
    {
        return $query->expr()->notIn($field, $params);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return \Doctrine\ORM\Query\Expr\Comparison
     */
    private function integerFalseExpression($query, $field, $params, $alias)
    {
        return $query->expr()->eq('COALESCE(' . $field . ',0)', 0);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return \Doctrine\ORM\Query\Expr\Comparison
     */
    private function dateFalseExpression($query, $field, $params, $alias)
    {
        return $query->expr()->eq('COALESCE(' . $field . ',0)', 0);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return \Doctrine\ORM\Query\Expr\Base
     */
    private function stringFalseExpression($query, $field, $params, $alias)
    {
        return $query->expr()->orX(
            $query->expr()->isNull($field),
            $query->expr()->eq($field, "''")
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return \Doctrine\ORM\Query\Expr\Comparison
     */
    private function integerTrueExpression($query, $field, $params, $alias)
    {
        return $query->expr()->neq('COALESCE(' . $field . ',0)', 0);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return \Doctrine\ORM\Query\Expr\Comparison
     */
    private function dateTrueExpression($query, $field, $params, $alias)
    {
        return $query->expr()->neq('COALESCE(' . $field . ',0)', 0);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return \Doctrine\ORM\Query\Expr\Base
     */
    private function stringTrueExpression($query, $field, $params, $alias)
    {
        return $query->expr()->andX(
            $query->expr()->isNotNull($field),
            $query->expr()->neq($field, "''")
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function stringContainsExpression($query, $field, $params, $alias)
    {
        $contains = $query->expr()->orX();

        $index = 0;
        foreach ($params as $string) {
            $contains->add($query->expr()->like($field, ":contains_{$alias}_{$index}"));
            $query->setParameter("contains_{$alias}_{$index}", '%' . $string . '%');
            $index++;
        }

        return $contains;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function stringContainExpression($query, $field, $params, $alias)
    {
        return $this->stringContainsExpression($query, $field, $params, $alias);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function dateLtExpression($query, $field, $params, $alias)
    {
        $lt = $query->expr()->orX();
        $index = 0;
        foreach ($params as $datetime) {
            $lt->add($query->expr()->lt($field, ":lt_{$alias}_{$index}"));
            $query->setParameter("lt_{$alias}_{$index}", $datetime);
            $index++;
        }

        return $lt;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function integerLtExpression($query, $field, $params, $alias)
    {
        $lt = $query->expr()->orX();
        $index = 0;
        foreach ($params as $datetime) {
            $lt->add($query->expr()->lt($field, ":lt_{$alias}_{$index}"));
            $query->setParameter("lt_{$alias}_{$index}", $datetime);
            $index++;
        }

        return $lt;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function integerLteExpression($query, $field, $params, $alias)
    {
        $lte = $query->expr()->orX();
        $index = 0;
        foreach ($params as $datetime) {
            $lte->add($query->expr()->lte($field, ":lte_{$alias}_{$index}"));
            $query->setParameter("lte_{$alias}_{$index}", $datetime);
            $index++;
        }

        return $lte;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function dateLteExpression($query, $field, $params, $alias)
    {
        $lte = $query->expr()->orX();
        $index = 0;
        foreach ($params as $datetime) {
            $lte->add($query->expr()->lte($field, ":lte_{$alias}_{$index}"));
            $query->setParameter("lte_{$alias}_{$index}", $datetime);
            $index++;
        }

        return $lte;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function dateGtExpression($query, $field, $params, $alias)
    {
        $gt = $query->expr()->orX();
        $index = 0;
        foreach ($params as $datetime) {
            $gt->add($query->expr()->gt($field, ":gt_{$alias}_{$index}"));
            $query->setParameter("gt_{$alias}_{$index}", $datetime);
            $index++;
        }

        return $gt;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function integerGtExpression($query, $field, $params, $alias)
    {
        $gt = $query->expr()->orX();
        $index = 0;
        foreach ($params as $datetime) {
            $gt->add($query->expr()->gt($field, ":gt_{$alias}_{$index}"));
            $query->setParameter("gt_{$alias}_{$index}", $datetime);
            $index++;
        }

        return $gt;
    }

    /**
     * @return string
     */
    protected function getModelForMeta()
    {
        return uniqid('UnknownClass');
    }

    /**
     * @return string
     */
    public function getClassnameForRepresentedModel()
    {
        return $this->getModelForMeta();
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function integerGteExpression($query, $field, $params, $alias)
    {
        $gte = $query->expr()->orX();
        $index = 0;
        foreach ($params as $datetime) {
            $gte->add($query->expr()->gte($field, ":gte_{$alias}_{$index}"));
            $query->setParameter("gte_{$alias}_{$index}", $datetime);
            $index++;
        }

        return $gte;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $field
     * @param array $params
     * @param string $alias
     * @return mixed
     */
    private function dateGteExpression($query, $field, $params, $alias)
    {
        $gte = $query->expr()->orX();
        $index = 0;
        foreach ($params as $datetime) {
            $gte->add($query->expr()->gte($field, ":gte_{$alias}_{$index}"));
            $query->setParameter("gte_{$alias}_{$index}", $datetime);
            $index++;
        }

        return $gte;
    }

    /**
     * Does some crazy things
     *
     * @param string $value
     * @return array
     */
    private function filterJsonAfterwards($value)
    {
        return json_decode($value, true);
    }

    /**
     * Does some crazy things
     *
     * @param string $value
     * @return mixed
     */
    private function filterJsonIfNullSetEmptyObjectAfterwards($value)
    {
        return $value === null ? new \stdClass() : json_decode($value, true);
    }

    /**
     * Does some crazy things
     *
     * @param string $value
     * @return string
     */
    private function filterNl2BrAfterwards($value)
    {
        return nl2br($value, false);
    }

    /**
     * Does some crazy things
     *
     * @param string $value
     * @return array
     */
    private function filterJsonOrNullAfterwards($value)
    {
        return $value === null ? null : json_decode($value, true);
    }

    /**
     * Too complex to explain
     *
     * @param string $value
     * @return \DateTime
     */
    private function filterDatetimeAfterwards($value)
    {
        return new \DateTime($value);
    }

    /**
     * Too complex to explain
     *
     * @param string $value
     * @return \DateTime
     */
    private function filterDatetimeOrNullAfterwards($value)
    {
        return $value === null ? null : new \DateTime($value);
    }

    /**
     * Too complex to explain
     *
     * @param string|null $value
     * @return int|null
     */
    private function filterIntOrNullAfterwards($value)
    {
        return $value === null ? null : (int)$value;
    }

    /**
     * Returns the current resultArrayFixSchedule. Afterwards the schedule will be empty again.
     *
     * @return array
     */
    private function flushResultArrayFixSchedule()
    {
        $scheduledFixes = $this->resultArrayFixSchedule;
        $this->resultArrayFixSchedule = [];
        return $scheduledFixes;
    }

    /**
     * Returns true if $alias was used in $query already - false otherwise
     *
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @return bool
     */
    protected function wasAliasUsed($query, $alias)
    {
        return in_array($alias, $query->getAllAliases());
    }

    /**
     * Returns true if $alias was used in $query already - false otherwise
     *
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @return bool
     */
    protected function wasntAliasUsed($query, $alias)
    {
        return !$this->wasAliasUsed($query, $alias);
    }

    /**
     * @return array
     */
    public function getUnsortedParams()
    {
        return $this->unsortedParams;
    }

    /**
     * @param array $unsortedParams
     * @return $this
     */
    public function setUnsortedParams($unsortedParams)
    {
        $this->unsortedParams = $unsortedParams;

        return $this;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $translationName
     * @param string $language
     * @return string alias of joined translation table
     */
    protected function joinTranslationOnce($query, $translationName, $language)
    {
        $alias = 'translation' . $translationName . $language;

        if ($this->wasAliasUsed($query, $alias)) {
            return $alias;
        }

        $rootAlias = $this->getRootAlias($query);

        $query->setParameter("name$alias", $translationName);
        $query->setParameter("target$alias", $language);

        $query->leftJoin(
            'UnserAller_Model_Translation',
            $alias,
            'WITH',
            "$alias.name = CONCAT(:name$alias,$rootAlias.id) AND $alias.target = :target$alias"
        );

        return $alias;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $alias
     * @param string $col
     * @param string $name
     * @param string $translationName
     * @param string $language
     * @return array|void
     */
    protected function abstractIncludeMultilanguageStringColumn(
        $query,
        $alias,
        $col,
        $name,
        $translationName,
        $language
    ) {
        if (!$language) {
            $query->addSelect("($col) $alias");
        } else {
            $query->addSelect("(COALESCE(" . $this->joinTranslationOnce(
                $query,
                $translationName,
                $language
            ) . ".translation,$col)) $alias");
        }

        return [
            $alias,
            'move' => $name
        ];
    }

    protected function getAdditionalUserParamOrFail(&$additionalParams)
    {
        if (!isset($additionalParams['user'][0])) {
            throw new \InvalidArgumentException('User identifier required but not given');
        }

        $param = $additionalParams['user'];
        unset($additionalParams['user']);
        return \UnserAllerLib_Validate_Helper::integerOrFail($param[0], 1);
    }
}

<?php

namespace League\Vortex\RichParam;

use Macroparts\Vortex\RichParam\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Created by PhpStorm.
 * User: daniel.jurkovic
 * Date: 14.06.17
 * Time: 18:02
 */
class ParserTest extends TestCase
{
    /**
     * @var Parser
     */
    private $parser;

    public function setUp()
    {
        $this->parser = new Parser();
    }

    /**
     * @param mixed $arg
     * @dataProvider invalidArgumentProvider
     */
    public function testInvalidArgument($arg)
    {
        $this->assertEquals($this->parser->parseRichParam($arg), []);
    }

    public function invalidArgumentProvider()
    {
        return [
            [''],
            [0],
            [new \stdClass()],
            [true],
            [false],
            [[]],
        ];
    }

    /**
     * @param mixed $arg
     * @dataProvider validExampleProvider
     */
    public function testValidArgumentsAreCorrectlyParsed($arg, $expected)
    {
        $this->assertEquals(serialize($this->parser->parseRichParam($arg)), serialize($expected));
    }

    public function validExampleProvider()
    {
        return [
            [
                'field',
                ['field' => []]
            ],
            [
                'field:mod',
                ['field' => ['mod' => []]]
            ],
            [
                'field:mod(param1)',
                ['field' => ['mod' => ['param1']]]
            ],
            [
                'field:mod(field2:mod2(param))',
                ['field' => ['mod' => ['field2:mod2(param)']]]
            ],
            [
                'field:mod(field2:mod2(innerParam1|innerParam2)|outerParam2)',
                ['field' => ['mod' => ['field2:mod2(innerParam1|innerParam2)', 'outerParam2']]]
            ],
            [
                'field:mod(innerField1:innerMod1(innerParam1|innerParam2)|param2):mod2(param3)',
                [
                    'field' => [
                        'mod' => ['innerField1:innerMod1(innerParam1|innerParam2)', 'param2'],
                        'mod2' => ['param3']
                    ]
                ]
            ],
            [
                'field:mod(param1),field:mod2(param2)',
                [
                    'field' => [
                        'mod' => ['param1'],
                        'mod2' => ['param2']
                    ]
                ]
            ]
        ];
    }
}
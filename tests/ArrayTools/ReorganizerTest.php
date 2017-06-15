<?php
/**
 * Created by PhpStorm.
 * User: daniel.jurkovic
 * Date: 15.06.17
 * Time: 17:02
 */

namespace ArrayTools;

use Macroparts\Vortex\ArrayTools\Reorganizer;
use PHPUnit\Framework\TestCase;

class ReorganizerTest extends TestCase
{
    /**
     * @var Reorganizer
     */
    protected static $reorganizer;

    public static function setUpBeforeClass()
    {
        self::$reorganizer = new Reorganizer();
    }

    private function assertPatchArrayIsProducingExpectedResult($array, $tasks, $expected)
    {
        self::$reorganizer->patchArray($array, $tasks);
        $this->assertEquals(serialize($expected), serialize($array));
    }

    /**
     * @param $array
     * @param $tasks
     * @param $expected
     * @dataProvider validReadWriteExampleProvider
     */
    public function testReadWrite($array, $tasks, $expected)
    {
        $this->assertPatchArrayIsProducingExpectedResult($array, $tasks, $expected);
    }

    /**
     * @param $array
     * @param $tasks
     * @param $expected
     * @dataProvider readWriteCollisionExampleProvider
     */
    public function testWriteIsHandlingCollisionsWell($array, $tasks, $expected)
    {
        $this->assertPatchArrayIsProducingExpectedResult($array, $tasks, $expected);
    }

    /**
     * @param $array
     * @param $tasks
     * @param $expected
     * @dataProvider customTransformExampleProvider
     */
    public function testCustomTransform($array, $tasks, $expected)
    {
        $this->assertPatchArrayIsProducingExpectedResult($array, $tasks, $expected);
    }

    /**
     * @param $array
     * @param $tasks
     * @param $expected
     * @dataProvider castExampleProvider
     */
    public function testCast($array, $tasks, $expected)
    {
        $this->assertPatchArrayIsProducingExpectedResult($array, $tasks, $expected);
    }

    /**
     * @param $array
     * @param $tasks
     * @param $expected
     * @dataProvider deleteExampleProvider
     */
    public function testDelete($array, $tasks, $expected)
    {
        $this->assertPatchArrayIsProducingExpectedResult($array, $tasks, $expected);
    }

    public function testDeletionsAreAlwaysDoneLast()
    {
        $this->assertPatchArrayIsProducingExpectedResult(
            ['key1' => 1],
            [
                Reorganizer::DELETE, 'key1',
                Reorganizer::READ, 'key1',
                Reorganizer::WRITE, 'key2'
            ],
            ['key2' => 1]
        );
    }

    public function validReadWriteExampleProvider()
    {
        return [
            [
                ['key1' => [1,2]],
                [
                    Reorganizer::READ, 'key1.1',
                    Reorganizer::WRITE, 'key2.0',
                    Reorganizer::READ, 'key1.0',
                ],
                ['key1' => [1,2], 'key2' => [2]]
            ]
        ];
    }

    public function readWriteCollisionExampleProvider()
    {
        return [
            [
                ['key1' => [0 => 1], 'key2' => [0 => 2]],
                [
                    Reorganizer::READ, 'key1',
                    Reorganizer::WRITE, 'key2'
                ],
                ['key1' => [0 => 1], 'key2' => [0 => 2]]
            ]
        ];
    }

    public function customTransformExampleProvider()
    {
        return [
            [
                ['key1' => 1],
                [
                    Reorganizer::READ, 'key1',
                    Reorganizer::CUSTOM_TRANSFORM, [
                        function (&$array, &$temporaryValue, $newValue) {
                            $temporaryValue = $newValue;
                        },
                        ['customValue']
                    ],
                    Reorganizer::WRITE, 'key2'
                ],
                ['key1' => 1, 'key2' => 'customValue']
            ]
        ];
    }

    public function castExampleProvider()
    {
        return [
            [
                ['key1' => 1],
                [
                    Reorganizer::READ, 'key1',
                    Reorganizer::CAST, 'string',
                    Reorganizer::WRITE, 'key2'
                ],
                ['key1' => 1, 'key2' => '1']
            ]
        ];
    }

    public function deleteExampleProvider()
    {
        return [
            [
                ['key1' => 1],
                [
                    Reorganizer::DELETE, 'key1',
                ],
                []
            ]
        ];
    }
}

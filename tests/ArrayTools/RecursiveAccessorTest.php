<?php
/**
 * Created by PhpStorm.
 * User: daniel.jurkovic
 * Date: 15.06.17
 * Time: 14:45
 */

namespace ArrayTools;

use Macroparts\Vortex\ArrayTools\RecursiveAccessor;
use PHPUnit\Framework\TestCase;

class RecursiveAccessorTest extends TestCase
{
    private $readTestArray;
    private $emptyTestArray;

    public function setUp()
    {
        $this->emptyTestArray = [];
        $this->readTestArray = [
            0 => 'numericKey',
            'nestedArray1' => [
                'nestedArray2' => [
                    'integer' => 3,
                ],
            ],
            'boolean' => true
        ];
    }

    public function validWriteExampleProvider()
    {
        return [
            ['0', 'numericKey'],
            ['boolean', true],
            ['nestedArray1.nestedArray2.integer', 3],
            ['nestedArray1.nestedArray2', [
                'integer' => 3,
            ]],
        ];
    }

    /**
     * @dataProvider notExistentPathProvider
     */
    public function testReadSingleFromPathReturnsNullOnNonExistentPath($invalidPath)
    {
        $this->assertNull(RecursiveAccessor::readSingleFromPath($this->readTestArray, $invalidPath));
    }

    public function invalidPathProvider()
    {
        return [
            [''],
            [0]
        ];
    }

    public function notExistentPathProvider()
    {
        return array_merge(
            [['not.existent']],
            $this->invalidPathProvider()
        );
    }

    /**
     * @dataProvider validWriteExampleProvider
     */
    public function testReadSingleFromPathReturnsCorrectValues($path, $expectedValue)
    {
        $this->assertEquals(
            serialize(RecursiveAccessor::readSingleFromPath($this->readTestArray, $path)),
            serialize($expectedValue)
        );
    }

    public function testWriteToPathWritesCorrectly()
    {
        $someArray = ['nested2' => []];
        RecursiveAccessor::writeToPath($this->emptyTestArray, '0', 1);
        $this->assertEquals(serialize($this->emptyTestArray), serialize([
            0 => 1
        ]));

        RecursiveAccessor::writeToPath($this->emptyTestArray, '1.nested', $someArray);
        $this->assertEquals(serialize($this->emptyTestArray), serialize([
            0 => 1,
            1 => ['nested' => $someArray]
        ]));

        RecursiveAccessor::writeToPath($this->emptyTestArray, '0.nested', $someArray);
        $this->assertEquals(serialize($this->emptyTestArray), serialize([
            0 => ['nested' => $someArray],
            1 => ['nested' => $someArray]
        ]));
    }

    public function testWriteToPathOverwritesCorrectly()
    {
        RecursiveAccessor::writeToPath($this->emptyTestArray, '0', 'willBeOverwritten');
        RecursiveAccessor::writeToPath($this->emptyTestArray, '0.nested', ['nested2' => []]);
        $this->assertEquals(serialize($this->emptyTestArray), serialize([
            0 => ['nested' => ['nested2' => []]]
        ]));
    }

    public function testIntergrateIntoPathOverwritesWhenPointingToNonArray()
    {
        RecursiveAccessor::integrateIntoPath($this->emptyTestArray, '0.key', 'willBeOverwritten');
        $this->assertEquals(serialize($this->emptyTestArray), serialize([
            0 => ['key' => 'willBeOverwritten']
        ]));

        RecursiveAccessor::integrateIntoPath($this->emptyTestArray, '0.key', 'newValue');
        $this->assertEquals(serialize($this->emptyTestArray), serialize([
            0 => ['key' => 'newValue']
        ]));
    }

    public function testIntergrateIntoPathIntegratesWhenPointingToArray()
    {
        $this->emptyTestArray = [0 => ['key1' => 1]];
        RecursiveAccessor::integrateIntoPath($this->emptyTestArray, '0', ['key2' => 2]);
        $this->assertEquals(serialize($this->emptyTestArray), serialize([
            0 => ['key1' => 1, 'key2' => 2]
        ]));
    }

    public function testIntergrateIntoPathDoesntOverwriteOnIntegration()
    {
        $this->emptyTestArray = [0 => ['key' => 'newValue']];
        RecursiveAccessor::integrateIntoPath($this->emptyTestArray, '0', ['key' => 'shouldNotOverwrite']);
        $this->assertEquals(serialize($this->emptyTestArray), serialize([
            0 => ['key' => 'newValue']
        ]));
    }

    /**
     * @param $invalidPath
     * @dataProvider invalidPathProvider
     */
    public function testIntergrateIntoPathDoesNothingWhenPathIsInvalid($invalidPath)
    {
        $this->emptyTestArray;
        RecursiveAccessor::integrateIntoPath($this->emptyTestArray, $invalidPath, 'someValue');
        $this->assertEquals(serialize($this->emptyTestArray), serialize($this->emptyTestArray));
    }

    public function testUnsetInPathUnsetsCorrectly()
    {
        RecursiveAccessor::unsetInPath($this->readTestArray, 'nestedArray1.nestedArray2.integer');
        $this->assertEquals(serialize($this->readTestArray), serialize([
            0 => 'numericKey',
            'nestedArray1' => [
                'nestedArray2' => [],
            ],
            'boolean' => true
        ]));

        RecursiveAccessor::unsetInPath($this->readTestArray, 'nestedArray1');
        $this->assertEquals(serialize($this->readTestArray), serialize([
            0 => 'numericKey',
            'boolean' => true
        ]));

        RecursiveAccessor::unsetInPath($this->readTestArray, 'doesnt.exist');
        $this->assertEquals(serialize($this->readTestArray), serialize([
            0 => 'numericKey',
            'boolean' => true
        ]));

        RecursiveAccessor::unsetInPath($this->readTestArray, '0');
        RecursiveAccessor::unsetInPath($this->readTestArray, 'boolean');
        $this->assertEquals(serialize($this->readTestArray), serialize([]));
    }

    /**
     * @param $initialArray
     * @param $path
     * @param $type
     * @param $expected
     * @dataProvider provideValidCastInPathExamples
     */
    public function testCastInPathCastsCorrectly($initialArray, $path, $type, $expected)
    {
        RecursiveAccessor::castInPath($initialArray, $path, $type);
        $this->assertEquals(serialize($initialArray), serialize($expected));
    }

    public function provideValidCastInPathExamples()
    {
        return [
            [
                [0 => [0 => '1s']],
                '0.0',
                'int',
                [0 => [0 => 1]]
            ],
            [
                [0 => [0 => 0]],
                '0.0',
                'string',
                [0 => [0 => '0']]
            ],
            [
                [0 => 1],
                '',
                'string',
                [0 => 1]
            ],
            [
                [0 => 1],
                'invalid.path',
                'string',
                [0 => 1]
            ]

        ];
    }
}

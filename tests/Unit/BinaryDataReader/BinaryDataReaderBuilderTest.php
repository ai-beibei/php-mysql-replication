<?php


namespace MySQLReplication\Unit\BinaryDataReader;

use MySQLReplication\BinaryDataReader\BinaryDataReaderBuilder;
use MySQLReplication\Unit\BaseTest;
use MySQLReplication\BinaryDataReader\BinaryDataReader;

/**
 * Class BinaryDataReaderBuilderTest
 * @package Unit\BinaryDataReader
 * @covers \MySQLReplication\BinaryDataReader\BinaryDataReaderBuilder
 */
class BinaryDataReaderBuilderTest extends BaseTest
{
    /**
     * @test
     */
    public function shouldBuild()
    {
        $expected = 'foo';

        $builder = new BinaryDataReaderBuilder();
        $builder->withBinaryData($expected);
        $class = $builder->build();

        self::assertAttributeEquals($expected, 'data', $builder);
        self::assertInstanceOf(BinaryDataReader::class, $class);
    }
}
<?php


namespace MySQLReplication\Unit\Event\RowEvent;

use MySQLReplication\BinaryDataReader\BinaryDataReader;
use MySQLReplication\Config\Config;
use MySQLReplication\Event\EventInfo;
use MySQLReplication\Event\RowEvent\RowEvent;
use MySQLReplication\JsonBinaryDecoder\JsonBinaryDecoderFactory;
use MySQLReplication\Repository\RepositoryInterface;
use MySQLReplication\Unit\BaseTest;
use Psr\SimpleCache\CacheInterface;

/**
 * Class RowEventTest
 * @package Unit\Event\RowEvent
 */
class RowEventTest extends BaseTest
{
    /**
     * @var RowEvent
     */
    private $rowEvent;
    /**
     * @var EventInfo|\PHPUnit_Framework_MockObject_MockObject
     */
    private $eventInfo;
    /**
     * @var BinaryDataReader|\PHPUnit_Framework_MockObject_MockObject
     */
    private $binaryDataReader;
    /**
     * @var RepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $repository;
    /**
     * @var Config|\PHPUnit_Framework_MockObject_MockObject
     */
    private $config;
    /**
     * @var JsonBinaryDecoderFactory
     */
    private $jsonBinaryDecoderFactory;
    /**
     * @var CacheInterface
     */
    private $cache;

    public function setUp()
    {
        parent::setUp();

        $this->config = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $this->repository = $this->getMockBuilder(RepositoryInterface::class)->disableOriginalConstructor()->getMock();
        $this->binaryDataReader = $this->getMockBuilder(BinaryDataReader::class)->disableOriginalConstructor()->getMock(
        );
        $this->eventInfo = $this->getMockBuilder(EventInfo::class)->disableOriginalConstructor()->getMock();
        $this->jsonBinaryDecoderFactory = $this->getMockBuilder(
            JsonBinaryDecoderFactory::class
        )->disableOriginalConstructor()->getMock();
        $this->cache = $this->getMockBuilder(CacheInterface::class)->disableOriginalConstructor()->getMock();

        $this->rowEvent = new RowEvent(
            $this->config,
            $this->repository,
            $this->binaryDataReader,
            $this->eventInfo,
            $this->jsonBinaryDecoderFactory,
            $this->cache
        );
    }

    /**
     * @test
     */
    public function shouldMakeUpdateRowsDTO()
    {
    }
}
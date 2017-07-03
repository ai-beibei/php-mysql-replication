<?php

namespace MySQLReplication;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use MySQLReplication\BinaryDataReader\BinaryDataReaderException;
use MySQLReplication\BinaryDataReader\BinaryDataReaderFactory;
use MySQLReplication\BinLog\BinLogException;
use MySQLReplication\BinLog\BinLogSocketConnect;
use MySQLReplication\Cache\ArrayCache;
use MySQLReplication\Config\Config;
use MySQLReplication\Config\ConfigException;
use MySQLReplication\Event\Event;
use MySQLReplication\Event\EventException;
use MySQLReplication\Event\EventSubscribers;
use MySQLReplication\Event\RowEvent\RowEventFactory;
use MySQLReplication\Exception\MySQLReplicationException;
use MySQLReplication\JsonBinaryDecoder\JsonBinaryDecoderException;
use MySQLReplication\JsonBinaryDecoder\JsonBinaryDecoderFactory;
use MySQLReplication\Repository\MySQLRepository;
use MySQLReplication\Socket\Socket;
use MySQLReplication\Socket\SocketException;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class MySQLReplicationFactory
 * @package MySQLReplication
 */
class MySQLReplicationFactory
{
    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;
    /**
     * @var Event
     */
    private $event;

    /**
     * @param Config $config
     * @throws MySQLReplicationException
     * @throws DBALException
     * @throws ConfigException
     * @throws BinLogException
     * @throws \MySQLReplication\Gtid\GtidException
     * @throws \MySQLReplication\Socket\SocketException
     */
    public function __construct(Config $config)
    {
        $config->validate();

        $this->connection = DriverManager::getConnection(
            [
                'user' => $config->getUser(),
                'password' => $config->getPassword(),
                'host' => $config->getHost(),
                'port' => $config->getPort(),
                'driver' => 'pdo_mysql',
                'charset' => $config->getCharset()
            ]
        );
        $repository = new MySQLRepository($this->connection);

        $rowEventService = new RowEventFactory(
            $config,
            $repository,
            new JsonBinaryDecoderFactory(),
            new ArrayCache($config)
        );
        $this->eventDispatcher = new EventDispatcher();

        $this->event = new Event(
            $config,
            new BinLogSocketConnect($config, $repository, new Socket()),
            new BinaryDataReaderFactory(),
            $rowEventService,
            $this->eventDispatcher
        );
    }

    /**
     * @param EventSubscribers $eventSubscribers
     */
    public function registerSubscriber(EventSubscribers $eventSubscribers)
    {
        $this->eventDispatcher->addSubscriber($eventSubscribers);
    }

    /**
     * @return Connection
     */
    public function getDbConnection()
    {
        return $this->connection;
    }

    /**
     * @throws MySQLReplicationException
     * @throws InvalidArgumentException
     * @throws BinLogException
     * @throws BinaryDataReaderException
     * @throws ConfigException
     * @throws EventException
     * @throws JsonBinaryDecoderException
     * @throws SocketException
     */
    public function binLogEvent()
    {
        $this->event->consume();
    }
}

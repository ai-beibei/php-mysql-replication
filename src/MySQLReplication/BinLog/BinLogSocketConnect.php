<?php

namespace MySQLReplication\BinLog;

use MySQLReplication\BinaryDataReader\BinaryDataReader;
use MySQLReplication\Config\Config;
use MySQLReplication\Definitions\ConstCapabilityFlags;
use MySQLReplication\Definitions\ConstCommand;
use MySQLReplication\Gtid\GtidException;
use MySQLReplication\Gtid\GtidFactory;
use MySQLReplication\Repository\RepositoryInterface;
use MySQLReplication\Socket\SocketInterface;

/**
 * Class BinLogSocketConnect
 * @package MySQLReplication\BinLog
 */
class BinLogSocketConnect
{
    /**
     * @var bool
     */
    private $checkSum = false;
    /**
     * @var RepositoryInterface
     */
    private $repository;
    /**
     * @var Config
     */
    private $config;
    /**
     * http://dev.mysql.com/doc/internals/en/auth-phase-fast-path.html 00 FE
     * @var array
     */
    private $packageOkHeader = [0, 254];
    /**
     * @var SocketInterface
     */
    private $socket;
    /**
     * 2^24 - 1 16m
     * @var int
     */
    private $binaryDataMaxLength = 16777215;

    /**
     * @param Config $config
     * @param RepositoryInterface $repository
     * @param SocketInterface $socket
     * @throws BinLogException
     * @throws \MySQLReplication\Gtid\GtidException
     * @throws \MySQLReplication\Socket\SocketException
     */
    public function __construct(
        Config $config,
        RepositoryInterface $repository,
        SocketInterface $socket
    ) {
        $this->repository = $repository;
        $this->config = $config;
        $this->socket = $socket;


        $this->socket->connectToStream($this->config->getHost(), $this->config->getPort());
        BinLogServerInfo::parsePackage($this->getResponse(false), $this->repository->getVersion());
        $this->authenticate();
        $this->getBinlogStream();
    }

    /**
     * @return bool
     */
    public function getCheckSum()
    {
        return $this->checkSum;
    }

    /**
     * @param bool $checkResponse
     * @return string
     * @throws \MySQLReplication\BinLog\BinLogException
     * @throws \MySQLReplication\Socket\SocketException
     */
    public function getResponse($checkResponse = true)
    {
        $header = $this->socket->readFromSocket(4);
        if ('' === $header) {
            return '';
        }
        $dataLength = unpack('L', $header[0] . $header[1] . $header[2] . chr(0))[1];

        $result = $this->socket->readFromSocket($dataLength);
        if (true === $checkResponse) {
            $this->isWriteSuccessful($result);
        }

        return $result;
    }

    /**
     * @param string $data
     * @throws BinLogException
     */
    private function isWriteSuccessful($data)
    {
        $head = ord($data[0]);
        if (!in_array($head, $this->packageOkHeader, true)) {
            $errorCode = unpack('v', $data[1] . $data[2])[1];
            $errorMessage = '';
            $packetLength = strlen($data);
            for ($i = 9; $i < $packetLength; ++$i) {
                $errorMessage .= $data[$i];
            }

            throw new BinLogException($errorMessage, $errorCode);
        }
    }

    /**
     * @throws BinLogException
     * @throws \MySQLReplication\Socket\SocketException
     */
    private function authenticate()
    {
        // http://dev.mysql.com/doc/internals/en/secure-password-authentication.html#packet-Authentication::Native41
        $data = pack('L', ConstCapabilityFlags::getCapabilities());
        $data .= pack('L', $this->binaryDataMaxLength);
        $data .= chr(33);
        for ($i = 0; $i < 23; $i++) {
            $data .= chr(0);
        }
        $result = sha1($this->config->getPassword(), true) ^ sha1(BinLogServerInfo::getSalt() . sha1(sha1($this->config->getPassword(), true), true), true);

        $data = $data . $this->config->getUser() . chr(0) . chr(strlen($result)) . $result;
        $str = pack('L', strlen($data));
        $s = $str[0] . $str[1] . $str[2];
        $data = $s . chr(1) . $data;

        $this->socket->writeToSocket($data);
        $this->getResponse();
    }

    /**
     * @throws BinLogException
     * @throws GtidException
     * @throws \MySQLReplication\Socket\SocketException
     */
    private function getBinlogStream()
    {
        $this->checkSum = $this->repository->isCheckSum();
        if ($this->checkSum) {
            $this->execute('SET @master_binlog_checksum=@@global.binlog_checksum');
        }

        $this->registerSlave();

        if ('' !== $this->config->getGtid()) {
            $this->setBinLogDumpGtid();
        } else {
            $this->setBinLogDump();
        }
    }

    /**
     * @param string $sql
     * @throws BinLogException
     * @throws \MySQLReplication\Socket\SocketException
     */
    private function execute($sql)
    {
        $this->socket->writeToSocket(pack('LC', strlen($sql) + 1, 0x03) . $sql);
        $this->getResponse();
    }

    /**
     * @see https://dev.mysql.com/doc/internals/en/com-register-slave.html
     * @throws BinLogException
     * @throws \MySQLReplication\Socket\SocketException
     */
    private function registerSlave()
    {
        $host = gethostname();
        $hostLength = strlen($host);
        $userLength = strlen($this->config->getUser());
        $passLength = strlen($this->config->getPassword());

        $data = pack('l', 18 + $hostLength + $userLength + $passLength);
        $data .= chr(ConstCommand::COM_REGISTER_SLAVE);
        $data .= pack('V', $this->config->getSlaveId());
        $data .= pack('C', $hostLength);
        $data .= $host;
        $data .= pack('C', $userLength);
        $data .= $this->config->getUser();
        $data .= pack('C', $passLength);
        $data .= $this->config->getPassword();
        $data .= pack('v', $this->config->getPort());
        $data .= pack('V', 0);
        $data .= pack('V', 0);

        $this->socket->writeToSocket($data);
        $this->getResponse();
    }

    /**
     * @see https://dev.mysql.com/doc/internals/en/com-binlog-dump-gtid.html
     * @throws BinLogException
     * @throws GtidException
     * @throws \MySQLReplication\Socket\SocketException
     */
    private function setBinLogDumpGtid()
    {
        $collection = GtidFactory::makeCollectionFromString($this->config->getGtid());

        $data = pack('l', 26 + $collection->getEncodedLength()) . chr(ConstCommand::COM_BINLOG_DUMP_GTID);
        $data .= pack('S', 0);
        $data .= pack('I', $this->config->getSlaveId());
        $data .= pack('I', 3);
        $data .= chr(0);
        $data .= chr(0);
        $data .= chr(0);
        $data .= BinaryDataReader::pack64bit(4);
        $data .= pack('I', $collection->getEncodedLength());
        $data .= $collection->getEncoded();

        $this->socket->writeToSocket($data);
        $this->getResponse();
    }

    /**
     * @see https://dev.mysql.com/doc/internals/en/com-binlog-dump.html
     * @throws BinLogException
     * @throws \MySQLReplication\Socket\SocketException
     */
    private function setBinLogDump()
    {
        if ('' !== $this->config->getMariaDbGtid()) {
            $this->execute('SET @mariadb_slave_capability = 4');
            $this->execute('SET @slave_connect_state = \'' . $this->config->getMariaDbGtid() . '\'');
            $this->execute('SET @slave_gtid_strict_mode = 0');
            $this->execute('SET @slave_gtid_ignore_duplicates = 0');
        }

        $binFilePos = $this->config->getBinLogPosition();
        $binFileName = $this->config->getBinLogFileName();
        if (0 === $binFilePos || '' === $binFileName) {
            $master = $this->repository->getMasterStatus();
            $binFilePos = $master['Position'];
            $binFileName = $master['File'];
        }

        $data = pack('i', strlen($binFileName) + 11) . chr(ConstCommand::COM_BINLOG_DUMP);
        $data .= pack('I', $binFilePos);
        $data .= pack('v', 0);
        $data .= pack('I', $this->config->getSlaveId());
        $data .= $binFileName;

        $this->socket->writeToSocket($data);
        $this->getResponse();
    }
}

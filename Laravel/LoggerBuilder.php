<?php

namespace Builders;

use Contracts\Builders\LoggerBuilderInterface;
use Contracts\Loggers\LoggerInterface;
use Contracts\Loggers\LoggerFactoryInterface;

class LoggerBuilder implements LoggerBuilderInterface
{
    /**
     * @var LoggerFactoryInterface
     */
    protected $loggerFactory;

    /**
     * Worker type
     *
     * @var string
     */
    protected $workerType = 'common';

    /**
     * Worker name
     *
     * @var string
     */
    protected $workerName;

    /**
     * Logging provider type ('text', 'html', etc)
     *
     * @var string
     */
    protected $logType = 'text';


    /**
     * LoggerBuilder constructor.
     *
     * @param LoggerFactoryInterface $loggerFactory
     */
    public function __construct(LoggerFactoryInterface $loggerFactory)
    {
        $this->loggerFactory = $loggerFactory;
    }

    /**
     * @return LoggerInterface
     */
    public function build(): LoggerInterface
    {
        $providerBuilder = $this->loggerFactory->provide($this->logType);

        $provider = $providerBuilder->setWorkerType($this->workerType)
                                    ->setWorkerName($this->workerName)
                                    ->build();

        return $provider;
    }

    /**
     * @param string $workerType
     *
     * @return LoggerBuilderInterface
     */
    public function setWorkerType(string $workerType) : LoggerBuilderInterface
    {
        $this->workerType = strtolower($workerType);
        return $this;
    }

    /**
     * @param string $workerName
     *
     * @return LoggerBuilderInterface
     */
    public function setWorkerName(string $workerName) : LoggerBuilderInterface
    {
        $this->workerName = strtolower($workerName);
        return $this;
    }

    /**
     * @param string $logType
     *
     * @return LoggerBuilderInterface
     */
    public function setLogType(string $logType): LoggerBuilderInterface
    {
        $this->logType = $logType;
        return $this;
    }
}

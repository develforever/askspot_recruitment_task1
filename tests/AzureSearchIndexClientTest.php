<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use AzureSearchIndexClient;
use HttpClientInterface;
use Psr\Log\LoggerInterface;
use SleeperInterface;

class AzureSearchIndexClientTest extends TestCase
{
    public function testRetryLogicUsingHttpMock(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $httpMock = $this->createMock(HttpClientInterface::class);
        $sleeper = $this->createMock(SleeperInterface::class);

        $httpMock->expects($this->exactly(4))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                ['{"error": "Gateway timeout"}', 504],
                ['{"error": "retry after 5 seconds"}', 429],
                ['{"error": "Rate limit exceeded"}', 429],
                ['{"status": "success"}', 200]
            );

        $client = new AzureSearchIndexClient(
            $logger,
            $httpMock,
            'fake-key',
            'fake-service',
            1000,
            null,
            null,
            $sleeper
        );

        $result = $client->createIndex([
            'name' => 'products',
            'fields' => [
                [
                    'name' => 'id',
                    'type' => 'Edm.String',
                    'key' => true,
                ],
            ],
        ]);

        $this->assertStringContainsString('products', $result);
    }

    public function testRetryWaitTimeAndWarningLogging(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $httpMock = $this->createMock(HttpClientInterface::class);
        $sleeper = $this->createMock(SleeperInterface::class);

        $httpMock->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                ['{ "error": "retry after 6 seconds" }', 429],
                ['{ "status": "success" }', 200]
            );

        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Wait time 6 seconds is too long, using default 0 seconds'));
        $logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Retrying [action: PUT https://fake-service.search.windows.net/indexes/products?api-version=2023-11-01] after 0 seconds'));

        $sleeper->expects($this->once())
            ->method('sleep')
            ->with(0);

        $client = new AzureSearchIndexClient($logger, $httpMock, 'fake-key', 'fake-service', 10, 0, 0, $sleeper);

        $client->createIndex([
            'name' => 'products',
            'fields' => [['name' => 'id', 'type' => 'Edm.String', 'key' => true]]
        ]);
    }
}

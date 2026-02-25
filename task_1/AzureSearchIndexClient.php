<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;

final readonly class AzureSearchIndexClient extends AbstractAzureClient implements SearchIndexClientInterface
{
    private const string INDEX_URL = 'https://%s.search.windows.net/indexes/%s?api-version=2023-11-01';
    private const string INDEX_DOC_URL = 'https://%s.search.windows.net/indexes/%s/docs/index?api-version=2023-11-01';
    private const string SEARCH_URL = 'https://%s.search.windows.net/indexes/%s/docs/search?api-version=2023-11-01';

    // 32000 but for safety we use 5000
    private const int MAX_BATCH_SIZE = 5000;

    public function __construct(
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
        string $azureSearchApiKey,
        protected string $azureSearchService,
        protected int $batchSize = 1000,
        ?int $retryDelay = null,
        ?int $retryDelayMax = null,
        ?SleeperInterface $sleeper = null,
    ) {
        parent::__construct(
            $logger,
            $httpClient,
            $azureSearchApiKey,
            $retryDelay === null ?  self::RETRY_DELAY : $retryDelay,
            $retryDelayMax === null ? self::RETRY_DELAY_MAX : $retryDelayMax,
            $sleeper
        );
    }

    /**
     * @throws JsonException,InvalidArgumentException
     */
    public function createIndex(array $indexDefinition): string
    {

        if (empty($indexDefinition['name'])) {
            throw new InvalidArgumentException('Index name is required');
        }

        if (empty($indexDefinition['fields'])) {
            throw new InvalidArgumentException('Index fields are required');
        }

        $fields = $indexDefinition['fields'];

        $keyField = array_filter(
            $fields,
            fn($field) => isset($field['key']) && $field['key'] === true
        );

        if (empty($keyField)) {
            throw new InvalidArgumentException('Index "key" is required in fields');
        }

        $url = sprintf(self::INDEX_URL, $this->azureSearchService, $indexDefinition['name']);
        $body = json_encode($indexDefinition, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->executeRequest(
            'PUT',
            $url,
            $body
        );

        return sprintf(self::SEARCH_URL, $this->azureSearchService, $indexDefinition['name']);
    }

    /**
     * @throws JsonException,InvalidArgumentException
     */
    public function load(string $indexName, array $products): void
    {

        if (empty($indexName)) {
            throw new InvalidArgumentException('Index name is required');
        }

        $url = sprintf(self::INDEX_DOC_URL, $this->azureSearchService, $indexName);
        $documents = array_map(
            fn(Product $product) => ['@search.action' => 'mergeOrUpload', ...$product->getIndexDocument()],
            array_values($products)
        );
        $body = json_encode(['value' => $documents], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->executeRequest(
            'POST',
            $url,
            $body,
            60
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function deleteIndex(string $indexName): void
    {

        if (empty($indexName)) {
            throw new InvalidArgumentException('Index name is required');
        }

        $url = sprintf(self::INDEX_URL, $this->azureSearchService, $indexName);
        $this->executeRequest(
            'DELETE',
            $url,
            null
        );
    }

    /**
     * @throws JsonException,InvalidArgumentException
     */
    public function deleteDocuments(string $indexName, array $documentIds): void
    {

        if (empty($indexName)) {
            throw new InvalidArgumentException('Index name is required');
        }

        $batchSize = $this->batchSize;

        if ($this->batchSize > self::MAX_BATCH_SIZE) {
            $batchSize = self::MAX_BATCH_SIZE;
            $this->logger->warning(
                sprintf(
                    'Batch size %d is too large, using default max %d',
                    $this->batchSize,
                    self::MAX_BATCH_SIZE
                )
            );
        }

        if (empty($documentIds)) {
            $this->logger->warning('No document IDs provided');
            return;
        }

        $url = sprintf(self::INDEX_DOC_URL, $this->azureSearchService, $indexName);

        $chunks = array_chunk($documentIds, $batchSize);

        foreach ($chunks as $chunk) {
            $documents = array_map(
                fn(string $documentId) => ['@search.action' => 'delete', Product::ID_FIELD_NAME => $documentId],
                array_values($chunk)
            );

            $body = json_encode(['value' => $documents], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->executeRequest(
                'POST',
                $url,
                $body
            );
        }
    }
}

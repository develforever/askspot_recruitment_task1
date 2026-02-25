<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;

abstract readonly class AbstractAzureClient
{
    private const int MAX_RETRIES = 9;
    public const int RETRY_DELAY = 2;
    public const int RETRY_DELAY_MAX = 5;
    public const int DEFAULT_TIMEOUT = 120;

    protected SleeperInterface $sleeper;

    public function __construct(
        protected LoggerInterface $logger,
        protected HttpClientInterface $httpClient,
        protected string $azureSearchApiKey,
        protected ?int $retryDelay = null,
        protected ?int $retryDelayMax = null,
        ?SleeperInterface $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? new SystemSleeper();
    }

    /**
     * @param string $method
     * @param string $url
     * @param string|null $body
     * @param int|null $timeoutSeconds
     * @return array{0: string, 1: int}
     */
    protected function executeRequest(
        string $method,
        string $url,
        ?string $body = null,
        ?int $timeoutSeconds = null
    ): array {

        $headers = [];

        $headers[] = 'api-key: ' . $this->azureSearchApiKey;

        $options = [
            CURLOPT_RETURNTRANSFER => true,
        ];

        $options[CURLOPT_TIMEOUT] = $timeoutSeconds ?? self::DEFAULT_TIMEOUT;


        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        if (null !== $body) {
            if (!json_validate($body)) {
                throw new InvalidArgumentException('Invalid JSON body');
            }

            $options[CURLOPT_POSTFIELDS] = $body;
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($body);
        }

        $options[CURLOPT_HTTPHEADER] = $headers;

        $this->httpClient->init($method, $url, $options);

        $responseBody = false;
        $responseCode = 0;
        $action = sprintf('[action: %s %s]', $method, $url);

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            $allowRetry = false;
            $waitSeconds = $this->getRetryDelay();

            [$responseBody, $responseCode] = $this->httpClient->request();

            if (false === $responseBody) {
                $this->logger->error(
                    sprintf(
                        '[try: %d] cURL error when %s: %s',
                        $attempt + 1,
                        $action,
                        $this->httpClient->getError()
                    )
                );
                $allowRetry = true;
            }

            if ($responseCode >= 400) {
                if ($responseCode === 429) {
                    if (preg_match('/\d+/', $responseBody, $matches)) {
                        $seconds = (int)$matches[0];
                        $waitSeconds = $seconds;
                    }
                }

                $this->logger->error(sprintf(
                    '[try: %d] HTTP error when %s: HTTP %d - %s',
                    $attempt + 1,
                    $action,
                    $responseCode,
                    $responseBody
                ));

                $allowRetry = true;
            }

            if ($allowRetry && $attempt < self::MAX_RETRIES) {
                $this->httpClient->reset($options);

                if ($waitSeconds > $this->getRetryDelayMax()) {
                    $this->logger->warning(
                        sprintf(
                            '[try: %d] Wait time %d seconds is too long, using default %d seconds',
                            $attempt + 1,
                            $waitSeconds,
                            $this->getRetryDelayMax()
                        )
                    );
                    $waitSeconds = $this->getRetryDelayMax();
                }

                $this->logger->info(
                    sprintf(
                        '[try: %d] Retrying %s after %d seconds',
                        $attempt + 1,
                        $action,
                        $waitSeconds
                    )
                );
                $this->sleeper->sleep($waitSeconds);

                continue;
            }

            break;
        }

        if (false === $responseBody || $responseCode >= 400) {
            throw new RuntimeException(
                sprintf(
                    '[try: %d] Unable to %s after %d attempts.',
                    $attempt + 1,
                    $action,
                    self::MAX_RETRIES + 1
                )
            );
        }


        return [$responseBody, $responseCode];
    }

    protected function getRetryDelay(): int
    {
        return $this->retryDelay !== null ? $this->retryDelay : self::RETRY_DELAY;
    }

    protected function getRetryDelayMax(): int
    {
        return $this->retryDelayMax !== null ? $this->retryDelayMax : self::RETRY_DELAY_MAX;
    }

}
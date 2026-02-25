<?php

declare(strict_types=1);

interface HttpClientInterface
{
    public function init(string $method, string $url, array $options = []): void;

    /**
     * @return array{0: string, 1: int} [body, status_code]
     */
    public function request(): array;

    public function getError(): string;

    public function reset(array $options = []): void;
}

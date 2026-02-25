<?php

declare(strict_types=1);

class CurlHttpClient implements HttpClientInterface
{
    private $curl;
    private array $options;


    public function init(string $method, string $url, array $options = []): void
    {
        $this->options = $options;
        $this->curl = curl_init($url);
        curl_setopt_array($this->curl, $this->options);
    }


    public function request(): array
    {

        $body = curl_exec($this->curl);
        $code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

        return [$body, $code];
    }

    public function getError(): string
    {
        return curl_error($this->curl);
    }

    public function reset(array $options = []): void
    {
        curl_reset($this->curl);
        curl_setopt_array($this->curl, $options ?? $this->options);
    }
}

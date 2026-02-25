<?php

declare(strict_types=1);

class SystemSleeper implements SleeperInterface
{
    public function sleep(int $seconds): void
    {
        sleep($seconds);
    }
}
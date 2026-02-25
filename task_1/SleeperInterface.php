<?php

declare(strict_types=1);

interface SleeperInterface {
    public function sleep(int $seconds): void;
}
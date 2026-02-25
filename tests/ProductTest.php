<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Product;

class ProductTest extends TestCase
{
    public function testNormalizeIdSuccess(): void
    {
        $id = "Przycisk-123!";
        $normalized = Product::normalizeId($id);

        $this->assertEquals("Przycisk-123", $normalized);
    }

    public function testNormalizeIdThrowsExceptionOnEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Product::normalizeId("!!!");
    }
}

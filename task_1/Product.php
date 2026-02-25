<?php

declare(strict_types=1);

final class Product
{
    public const string PRODUCT_HASH_FIELD_NAME = 'productHash';
    public const string VECTOR_HASH_FIELD_NAME = 'vectorHash';
    public const string VECTOR_FIELD_NAME = 'vector';
    public const string ID_FIELD_NAME = 'id';
    public const int MAX_FIELD_VALUE_LENGTH = 1500;
    public const int MAX_ID_LENGTH = 1024;

    private function __construct(public string $searchEngineId, public string $productId, public array $productData)
    {
    }

    /**
     * @param string $searchEngineId
     * @param string $productId
     * @param array $productData
     * @param string[] $vectorableFields
     * @throws InvalidArgumentException
     */
    public static function create(
        string $searchEngineId,
        string $productId,
        array $productData,
        array $vectorableFields,
    ): self {
        $normalized = self::normalizeId($productId);
        $productData[self::ID_FIELD_NAME] = $normalized;

        ksort($productData);
        $vectorString = [];
        $fieldsString = [];

        foreach ($productData as $key => $value) {
            if (in_array($key, $vectorableFields, true)) {
                $vectorString[] = $value;
            }

            $fieldsString[] = $value;
        }

        $productData[self::PRODUCT_HASH_FIELD_NAME] = md5(implode('|', $fieldsString));
        $productData[self::VECTOR_HASH_FIELD_NAME] = md5(implode('|', $vectorString));

        return new self($searchEngineId, $normalized, $productData);
    }

    public function getIndexDocument(): array
    {
        $data = $this->productData;
        unset($data[self::PRODUCT_HASH_FIELD_NAME]);
        unset($data[self::VECTOR_HASH_FIELD_NAME]);

        return $data;
    }

    public static function normalizeId(string $id): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9_\-=]/', '', $id);

        if ($normalized === '') {
            // phpcs:disable Generic.Files.LineLength.TooLong
            throw new InvalidArgumentException('Product ID must contain only letters (a-z, A-Z), numbers (0-9), dashes (-) and underscores (_), equals (=). Not start with (_).');
        }

        if (strlen($normalized) > self::MAX_ID_LENGTH) {
            // phpcs:disable Generic.Files.LineLength.TooLong
            throw new InvalidArgumentException('Product ID must be less than or equal to ' . self::MAX_ID_LENGTH . ' characters.');
        }

        if (strpos($normalized, '_') === 0) {
            throw new InvalidArgumentException('Product ID must not start with an underscore (_).');
        }

        return $normalized;
    }
}

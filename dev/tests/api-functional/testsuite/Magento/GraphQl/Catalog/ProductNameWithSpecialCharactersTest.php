<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Catalog;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

class ProductNameWithSpecialCharactersTest extends GraphQlAbstract
{
    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedQuoteId;

    /**
     * @var DataFixtureStorage
     */
    private $fixtures;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->quoteIdToMaskedQuoteId = Bootstrap::getObjectManager()->get(QuoteIdToMaskedQuoteIdInterface::class);
        $this->fixtures = Bootstrap::getObjectManager()->get(DataFixtureStorageManager::class)->getStorage();
    }

    /**
     * Test product name with special characters
     *
     * @param string $sku
     * @param string $expectedName
     * @throws NoSuchEntityException
     * @dataProvider productNameProvider
     */
    #[
        DataFixture(ProductFixture::class, [
            'sku' => 'test-product-1',
            'name' => 'Test Product© 1'
        ]),
        DataFixture(ProductFixture::class, [
            'sku' => 'test-product-2',
            'name' => 'Test Product™ 2'
        ]),
        DataFixture(ProductFixture::class, [
            'sku' => 'test-product-3',
            'name' => 'Sample Product&copy; 3'
        ]),
        DataFixture(ProductFixture::class, [
            'sku' => 'test-product-4',
            'name' => 'Sample Product&trade; 4'
        ]),
        DataFixture(ProductFixture::class, [
            'sku' => 'test-product-5',
            'name' => 'Test Product 5'
        ]),
        DataFixture(GuestCartFixture::class, as: 'cart')
    ]
    public function testProductName(string $sku, string $expectedName): void
    {
        $cart = $this->fixtures->get('cart');
        $maskedQuoteId = $this->quoteIdToMaskedQuoteId->execute((int) $cart->getId());

        $query = $this->getAddToCartMutation($maskedQuoteId, $sku);
        $response = $this->graphQlMutation($query);
        $result = $response['addProductsToCart'];

        self::assertCount(1, $result['cart']['items']);
        self::assertEquals(1, $result['cart']['items'][0]['quantity']);
        self::assertEquals($expectedName, $result['cart']['items'][0]['product']['name']);
    }

    /**
     * Data provider for product name test cases
     *
     * @return array[]
     */
    public static function productNameProvider(): array
    {
        return [
            ['test-product-1', 'Test Product© 1'],
            ['test-product-2', 'Test Product™ 2'],
            ['test-product-3', 'Sample Product© 3'],
            ['test-product-4', 'Sample Product™ 4'],
            ['test-product-5', 'Test Product 5']
        ];
    }

    /**
     * Returns GraphQl mutation
     *
     * @param string $maskedQuoteId
     * @param string $sku
     * @return string
     */
    private function getAddToCartMutation(string $maskedQuoteId, string $sku): string
    {
        return <<<MUTATION
mutation {
  addProductsToCart(
        cartId: "{$maskedQuoteId}",
        cartItems: [
            {
                sku: "{$sku}"
                quantity: 1
            }
        ]
    ) {
    cart {
      id
      items {
        uid
        quantity
        product {
          sku
          name
        }
      }
    }
  }
}
MUTATION;
    }
}

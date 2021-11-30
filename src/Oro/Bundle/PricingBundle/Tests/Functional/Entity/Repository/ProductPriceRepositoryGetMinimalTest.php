<?php

namespace Oro\Bundle\PricingBundle\Tests\Functional\Entity\Repository;

use Oro\Bundle\PricingBundle\Entity\ProductPrice;
use Oro\Bundle\PricingBundle\Entity\Repository\ProductPriceRepository;
use Oro\Bundle\PricingBundle\Tests\Functional\DataFixtures\LoadProductPricesForMinimalStrategy;
use Oro\Bundle\PricingBundle\Tests\Functional\ProductPriceReference;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

/**
 * @dbIsolationPerTest
 */
class ProductPriceRepositoryGetMinimalTest extends WebTestCase
{
    use ProductPriceReference;

    /**
     * @var ProductPriceRepository
     */
    protected $repository;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->initClient();

        $this->loadFixtures(
            [
                LoadProductPricesForMinimalStrategy::class,
            ]
        );

        $this->repository = $this->getContainer()->get('doctrine')
            ->getRepository(ProductPrice::class);
    }

    /**
     * !!!IMPORTANT!!! If this test is unstable - this means that repository constructs incorrect query.
     * Because repository fetches MIN ids and ID is UUID, we can't create test that will fail all the time
     * with incorrect query as UUIDs may be generated by DB differently.
     *
     * @dataProvider minimalPricesProvider
     * @param array $priceLists
     * @param array $expectedPrices
     */
    public function testGetMinimalPriceIdsQueryBuilder(array $priceLists, array $expectedPrices)
    {
        $priceLists = array_map(function (string $priceListReference) {
            return $this->getReference($priceListReference)->getId();
        }, $priceLists);
        $expectedPrices = array_map(function (string $priceReference) {
            return $this->getReference($priceReference)->getId();
        }, $expectedPrices);

        $qb = $this->repository->getMinimalPriceIdsQueryBuilder($priceLists);
        $this->assertEqualsCanonicalizing($expectedPrices, array_column($qb->getQuery()->getArrayResult(), 'id'));
    }

    public function minimalPricesProvider(): \Generator
    {
        yield [
            ['price_list_3'],
            [
                'min.product_price.p1_1l_USD_pl3',
                'min.product_price.p1_10l_USD_pl3'
            ]
        ];

        yield [
            [
                'price_list_1',
                'price_list_3',
            ],
            [
                'min.product_price.p1_1l_USD_pl3',
                'min.product_price.p1_10l_USD_pl1'
            ]
        ];
    }
}
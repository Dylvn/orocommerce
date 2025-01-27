<?php

namespace Oro\Bundle\UPSBundle\Tests\Unit\Method;

use Oro\Bundle\AddressBundle\Entity\Country;
use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\ShippingBundle\Context\ShippingContextInterface;
use Oro\Bundle\UPSBundle\Cache\ShippingPriceCache;
use Oro\Bundle\UPSBundle\Cache\ShippingPriceCacheKey;
use Oro\Bundle\UPSBundle\Entity\ShippingService;
use Oro\Bundle\UPSBundle\Entity\UPSTransport;
use Oro\Bundle\UPSBundle\Factory\PriceRequestFactory;
use Oro\Bundle\UPSBundle\Form\Type\UPSShippingMethodOptionsType;
use Oro\Bundle\UPSBundle\Method\UPSShippingMethodType;
use Oro\Bundle\UPSBundle\Model\Package;
use Oro\Bundle\UPSBundle\Model\PriceRequest;
use Oro\Bundle\UPSBundle\Model\PriceResponse;
use Oro\Bundle\UPSBundle\Provider\UPSTransport as UPSTransportProvider;
use Oro\Component\Testing\Unit\EntityTrait;

class UPSShippingMethodTypeTest extends \PHPUnit\Framework\TestCase
{
    use EntityTrait;

    private const IDENTIFIER = '02';
    private const LABEL = 'service_code_label';

    /** @var string */
    private $methodId = 'shipping_method';

    /** @var UPSTransport|\PHPUnit\Framework\MockObject\MockObject */
    private $transport;

    /** @var UPSTransportProvider|\PHPUnit\Framework\MockObject\MockObject */
    private $transportProvider;

    /** @var ShippingService|\PHPUnit\Framework\MockObject\MockObject */
    private $shippingService;

    /** @var PriceRequestFactory|\PHPUnit\Framework\MockObject\MockObject */
    private $priceRequestFactory;

    /** @var ShippingPriceCache|\PHPUnit\Framework\MockObject\MockObject */
    private $cache;

    /** @var UPSShippingMethodType */
    private $upsShippingMethodType;

    protected function setUp(): void
    {
        $this->transport = $this->getEntity(
            UPSTransport::class,
            [
                'upsApiUser' => 'some user',
                'upsApiPassword' => 'some password',
                'upsApiKey' => 'some key',
                'upsShippingAccountNumber' => 'some number',
                'upsShippingAccountName' => 'some name',
                'upsPickupType' => '01',
                'upsUnitOfWeight' => 'LPS',
                'upsCountry' => new Country('US'),
                'applicableShippingServices' => [new ShippingService()]
            ]
        );

        $this->transportProvider = $this->createMock(UPSTransportProvider::class);
        $this->shippingService = $this->createMock(ShippingService::class);
        $this->priceRequestFactory = $this->createMock(PriceRequestFactory::class);
        $this->cache = $this->createMock(ShippingPriceCache::class);

        $this->upsShippingMethodType = new UPSShippingMethodType(
            self::IDENTIFIER,
            self::LABEL,
            $this->methodId,
            $this->shippingService,
            $this->transport,
            $this->transportProvider,
            $this->priceRequestFactory,
            $this->cache
        );
    }

    public function testGetOptionsConfigurationFormType()
    {
        self::assertEquals(
            UPSShippingMethodOptionsType::class,
            $this->upsShippingMethodType->getOptionsConfigurationFormType()
        );
    }

    public function testGetSortOrder()
    {
        self::assertEquals(0, $this->upsShippingMethodType->getSortOrder());
    }

    /**
     * @dataProvider calculatePriceDataProvider
     */
    public function testCalculatePrice(int $methodSurcharge, int $typeSurcharge, int $expectedPrice)
    {
        $context = $this->createMock(ShippingContextInterface::class);

        $methodOptions = ['surcharge' => $methodSurcharge];
        $this->shippingService->expects(self::any())
            ->method('getCode')
            ->willReturn(self::IDENTIFIER);
        $typeOptions = ['surcharge' => $typeSurcharge];

        $priceRequest = $this->createMock(PriceRequest::class);
        $priceRequest->expects(self::once())
            ->method('getPackages')
            ->willReturn([new Package(), new Package()]);

        $this->priceRequestFactory->expects(self::once())
            ->method('create')
            ->willReturn($priceRequest);

        $responsePrice = Price::create(50, 'USD');

        $priceResponse = $this->createMock(PriceResponse::class);
        $priceResponse->expects(self::once())
            ->method('getPriceByService')
            ->willReturn($responsePrice);

        $this->transportProvider->expects(self::once())
            ->method('getPriceResponse')
            ->willReturn($priceResponse);

        $cacheKey = (new ShippingPriceCacheKey())->setTransport($this->transport)->setPriceRequest($priceRequest)
            ->setMethodId($this->methodId)->setTypeId($this->shippingService->getCode());

        $this->cache->expects(self::once())
            ->method('createKey')
            ->with($this->transport, $priceRequest, $this->methodId, $this->shippingService->getCode())
            ->willReturn($cacheKey);

        $this->cache->expects(self::once())
            ->method('containsPrice')
            ->with($cacheKey)
            ->willReturn(false);

        $this->cache->expects(self::once())
            ->method('savePrice')
            ->with($cacheKey, $responsePrice);

        $price = $this->upsShippingMethodType->calculatePrice($context, $methodOptions, $typeOptions);

        self::assertEquals(Price::create($expectedPrice, 'USD'), $price);
    }

    public function calculatePriceDataProvider(): array
    {
        return [
            'TypeSurcharge' => [
                'methodSurcharge' => 0,
                'typeSurcharge' => 5,
                'expectedPrice' => 55
            ],
            'MethodSurcharge' => [
                'methodSurcharge' => 3,
                'typeSurcharge' => 0,
                'expectedPrice' => 53
            ],
            'Method&TypeSurcharge' => [
                'methodSurcharge' => 3,
                'typeSurcharge' => 5,
                'expectedPrice' => 58
            ],
            'NoSurcharge' => [
                'methodSurcharge' => 0,
                'typeSurcharge' => 0,
                'expectedPrice' => 50
            ]
        ];
    }

    public function testCalculatePriceCache()
    {
        $context = $this->createMock(ShippingContextInterface::class);

        $methodOptions = ['surcharge' => 10];
        $this->shippingService->expects(self::any())
            ->method('getCode')
            ->willReturn('02');
        $typeOptions = ['surcharge' => 15];

        $priceRequest = $this->createMock(PriceRequest::class);
        $priceRequest->expects(self::once())
            ->method('getPackages')
            ->willReturn([new Package(), new Package()]);

        $this->priceRequestFactory->expects(self::once())
            ->method('create')
            ->willReturn($priceRequest);

        $this->transportProvider->expects(self::never())
            ->method('getPriceResponse');

        $cacheKey = (new ShippingPriceCacheKey())->setTransport($this->transport)->setPriceRequest($priceRequest)
            ->setMethodId($this->methodId)->setTypeId($this->shippingService->getCode());

        $this->cache->expects(self::once())
            ->method('createKey')
            ->with($this->transport, $priceRequest, $this->methodId, $this->shippingService->getCode())
            ->willReturn($cacheKey);

        $this->cache->expects(self::once())
            ->method('containsPrice')
            ->with($cacheKey)
            ->willReturn(true);

        $this->cache->expects(self::once())
            ->method('fetchPrice')
            ->with($cacheKey)
            ->willReturn(Price::create(5, 'USD'));

        $price = $this->upsShippingMethodType->calculatePrice($context, $methodOptions, $typeOptions);

        self::assertEquals(Price::create(30, 'USD'), $price);
    }
}

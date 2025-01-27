<?php

namespace Oro\Bundle\ShippingBundle\Tests\Unit\Provider;

use Oro\Bundle\ShippingBundle\Context\ShippingContext;
use Oro\Bundle\ShippingBundle\Method\ShippingMethodProviderInterface;
use Oro\Bundle\ShippingBundle\Method\ShippingMethodViewCollection;
use Oro\Bundle\ShippingBundle\Provider\EnabledMethodsShippingPriceProviderDecorator;
use Oro\Bundle\ShippingBundle\Provider\Price\ShippingPriceProviderInterface;
use Oro\Bundle\ShippingBundle\Tests\Unit\Provider\Stub\PriceAwareShippingMethodStub;
use Oro\Bundle\ShippingBundle\Tests\Unit\Provider\Stub\ShippingMethodStub;
use Oro\Component\Testing\Unit\EntityTrait;

class EnabledMethodsShippingPriceProviderDecoratorTest extends \PHPUnit\Framework\TestCase
{
    use EntityTrait;

    /** @var ShippingPriceProviderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $shippingPriceProvider;

    /** @var ShippingMethodProviderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $shippingMethodProvider;

    /** @var EnabledMethodsShippingPriceProviderDecorator */
    private $decorator;

    protected function setUp(): void
    {
        $this->shippingPriceProvider = $this->createMock(ShippingPriceProviderInterface::class);
        $this->shippingMethodProvider = $this->createMock(ShippingMethodProviderInterface::class);

        $this->decorator = new EnabledMethodsShippingPriceProviderDecorator(
            $this->shippingPriceProvider,
            $this->shippingMethodProvider
        );
    }

    /**
     * @dataProvider getApplicableMethodsViewsProvider
     */
    public function testGetApplicableMethodsViews(array $methods, array $methodViews, array $expectedMethodViews)
    {
        $context = $this->createMock(ShippingContext::class);

        $methodViewCollection = new ShippingMethodViewCollection();
        foreach ($methodViews as $id => $view) {
            $methodViewCollection ->addMethodView($id, $view);
        }

        $expectedCollection = new ShippingMethodViewCollection();
        foreach ($expectedMethodViews as $id => $view) {
            $expectedCollection ->addMethodView($id, $view);
        }

        $this->shippingPriceProvider->expects($this->any())
            ->method('getApplicableMethodsViews')
            ->with($context)
            ->willReturn($methodViewCollection);

        $this->shippingMethodProvider->expects($this->any())
            ->method('getShippingMethod')
            ->willReturnCallback(function ($methodId) use ($methods) {
                return $methods[$methodId] ?? null;
            });

        $this->assertEquals($expectedCollection, $this->decorator->getApplicableMethodsViews($context));
    }

    public function getApplicableMethodsViewsProvider(): array
    {
        return [
            'all_methods_enabled' => [
                'methods' => [
                    'flat_rate' => $this->getEntity(ShippingMethodStub::class, [
                        'identifier' => 'flat_rate',
                        'sortOrder' => 1,
                        'isEnabled' => true,
                        'types' => []
                    ]),
                    'ups' => $this->getEntity(PriceAwareShippingMethodStub::class, [
                        'identifier' => 'ups',
                        'sortOrder' => 2,
                        'isEnabled' => true,
                        'types' => []
                    ])
                ],
                'method_views' => [
                    'flat_rate' => ['flat_rate', false, 'flat_rate', 1],
                    'ups' => ['ups', false, 'ups', 2],
                ],
                'expected_method_views' => [
                    'flat_rate' => ['flat_rate', false, 'flat_rate', 1],
                    'ups' => ['ups', false, 'ups', 2],
                ]
            ],
            'all_methods_disabled' => [
                'methods' => [
                    'flat_rate' => $this->getEntity(ShippingMethodStub::class, [
                        'identifier' => 'flat_rate',
                        'sortOrder' => 1,
                        'isEnabled' => false,
                        'types' => []
                    ]),
                    'ups' => $this->getEntity(PriceAwareShippingMethodStub::class, [
                        'identifier' => 'ups',
                        'sortOrder' => 2,
                        'isEnabled' => false,
                        'types' => []
                    ])
                ],
                'method_views' => [
                    'flat_rate' => ['flat_rate', false, 'flat_rate', 1],
                    'ups' => ['ups', false, 'ups', 2],
                ],
                'expected_method_views' => []
            ],
            'some_methods_enabled' => [
                'methods' => [
                    'flat_rate' => $this->getEntity(ShippingMethodStub::class, [
                        'identifier' => 'flat_rate',
                        'sortOrder' => 1,
                        'isEnabled' => true,
                        'types' => []
                    ]),
                    'ups' => $this->getEntity(PriceAwareShippingMethodStub::class, [
                        'identifier' => 'ups',
                        'sortOrder' => 2,
                        'isEnabled' => false,
                        'types' => []
                    ])
                ],
                'method_views' => [
                    'flat_rate' => ['flat_rate', false, 'flat_rate', 1],
                    'ups' => ['ups', false, 'ups', 2],
                ],
                'expected_method_views' => [
                    'flat_rate' => ['flat_rate', false, 'flat_rate', 1],
                ]
            ],
            'no_methods' => [
                'methods' => [],
                'method_views' => [],
                'expected_method_views' => []
            ]
        ];
    }
}

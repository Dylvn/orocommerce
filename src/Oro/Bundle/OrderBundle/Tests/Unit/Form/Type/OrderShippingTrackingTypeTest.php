<?php

namespace Oro\Bundle\OrderBundle\Tests\Unit\Form\Type;

use Oro\Bundle\OrderBundle\Entity\OrderShippingTracking;
use Oro\Bundle\OrderBundle\Form\Type\OrderShippingTrackingType;
use Oro\Bundle\OrderBundle\Form\Type\SelectSwitchInputType;
use Oro\Component\Testing\Unit\EntityTrait;
use Oro\Component\Testing\Unit\FormIntegrationTestCase;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Validator\Validation;

class OrderShippingTrackingTypeTest extends FormIntegrationTestCase
{
    use EntityTrait;

    /**
     * @dataProvider submitDataProvider
     */
    public function testSubmit(array $submitted, OrderShippingTracking $expected)
    {
        $form = $this->factory->create(OrderShippingTrackingType::class);
        $form->submit($submitted);

        self::assertTrue($form->isValid());
        self::assertEquals($expected, $form->getData());
    }

    public function submitDataProvider(): array
    {
        return [
            'test' => [
                'submitted' => ['method' => 'UPS', 'number' => '1Z111'],
                'expected' => (new OrderShippingTracking())->setMethod('UPS')->setNumber('1Z111'),
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension(
                [
                    TextType::class => new TextType(),
                    SelectSwitchInputType::class => new SelectSwitchInputType()
                ],
                []
            ),
            new ValidatorExtension(Validation::createValidator())
        ];
    }

    public function testGetBlockPrefix()
    {
        $type = new OrderShippingTrackingType();
        self::assertSame(OrderShippingTrackingType::NAME, $type->getBlockPrefix());
    }
}

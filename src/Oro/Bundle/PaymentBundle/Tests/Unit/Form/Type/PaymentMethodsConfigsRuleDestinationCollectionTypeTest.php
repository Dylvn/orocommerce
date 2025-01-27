<?php

namespace Oro\Bundle\PaymentBundle\Tests\Unit\Form\Type;

use Oro\Bundle\AddressBundle\Entity\Country;
use Oro\Bundle\AddressBundle\Entity\Region;
use Oro\Bundle\FormBundle\Form\Type\CollectionType;
use Oro\Bundle\PaymentBundle\Entity\PaymentMethodsConfigsRuleDestination;
use Oro\Bundle\PaymentBundle\Entity\PaymentMethodsConfigsRuleDestinationPostalCode;
use Oro\Bundle\PaymentBundle\Form\Type\PaymentMethodsConfigsRuleDestinationCollectionType;
use Oro\Bundle\PaymentBundle\Form\Type\PaymentMethodsConfigsRuleDestinationType;
use Oro\Component\Testing\Unit\AddressFormExtensionTestCase;
use Oro\Component\Testing\Unit\EntityTrait;
use Oro\Component\Testing\Unit\Form\EventListener\Stub\AddressCountryAndRegionSubscriberStub;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Validator\Validation;

class PaymentMethodsConfigsRuleDestinationCollectionTypeTest extends AddressFormExtensionTestCase
{
    use EntityTrait;

    /**
     * @dataProvider submitDataProvider
     */
    public function testSubmit(array $existing, array $submitted, array $expected = null)
    {
        $options = [
            'entry_options' => [
                'data_class' => PaymentMethodsConfigsRuleDestination::class
            ]
        ];

        $form = $this->factory->create(PaymentMethodsConfigsRuleDestinationCollectionType::class, $existing, $options);
        $form->submit($submitted);

        self::assertTrue($form->isValid());
        self::assertTrue($form->isSynchronized());
        self::assertEquals($expected, $form->getData());
    }

    public function submitDataProvider(): array
    {
        return [
            'test' => [
                'existing' => [
                    new PaymentMethodsConfigsRuleDestination(),
                    new PaymentMethodsConfigsRuleDestination(),
                ],
                'submitted' => [
                    [
                        'country' => self::COUNTRY_WITH_REGION,
                        'region' => self::REGION_WITH_COUNTRY,
                        'postalCodes' => 'code1, code2',
                    ],
                    [
                        'country' => self::COUNTRY_WITHOUT_REGION,
                    ]
                ],
                'expected' => [
                    // first code not stripped, because form used model transformer that split string by comma
                    // our extension applied on pre_submit, so all string stripped
                    $this->getDestination(
                        self::COUNTRY_WITH_REGION,
                        self::REGION_WITH_COUNTRY,
                        ['code1', 'code2_stripped']
                    ),
                    (new PaymentMethodsConfigsRuleDestination())
                        ->setCountry(new Country(self::COUNTRY_WITHOUT_REGION)),
                ]
            ]
        ];
    }

    private function getDestination(
        string $countryCode,
        string $regionCode,
        array $postalCodes
    ): PaymentMethodsConfigsRuleDestination {
        $country = new Country($countryCode);

        $region = new Region($regionCode);
        $region->setCountry($country);
        $country->addRegion($region);

        $destination = new PaymentMethodsConfigsRuleDestination();
        $destination->setCountry($country)
            ->setRegion($region);

        foreach ($postalCodes as $code) {
            $postalCode = new PaymentMethodsConfigsRuleDestinationPostalCode();
            $postalCode->setName($code);

            $destination->addPostalCode($postalCode);
        }

        return $destination;
    }

    /**
     * {@inheritDoc}
     */
    protected function getExtensions(): array
    {
        return array_merge(
            parent::getExtensions(),
            [
                new PreloadedExtension(
                    [
                        PaymentMethodsConfigsRuleDestinationType::class => new PaymentMethodsConfigsRuleDestinationType(
                            new AddressCountryAndRegionSubscriberStub()
                        )
                    ],
                    []
                ),
                new ValidatorExtension(Validation::createValidator())
            ]
        );
    }

    public function testGetParent()
    {
        $type = new PaymentMethodsConfigsRuleDestinationCollectionType();
        self::assertSame(CollectionType::class, $type->getParent());
    }

    public function testGetBlockPrefix()
    {
        $type = new PaymentMethodsConfigsRuleDestinationCollectionType();
        self::assertSame(PaymentMethodsConfigsRuleDestinationCollectionType::NAME, $type->getBlockPrefix());
    }
}

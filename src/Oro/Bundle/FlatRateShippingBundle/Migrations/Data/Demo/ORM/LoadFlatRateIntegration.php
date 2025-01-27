<?php

namespace Oro\Bundle\FlatRateShippingBundle\Migrations\Data\Demo\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\CurrencyBundle\DependencyInjection\Configuration as CurrencyConfig;
use Oro\Bundle\FlatRateShippingBundle\Entity\FlatRateSettings;
use Oro\Bundle\FlatRateShippingBundle\Integration\FlatRateChannelType;
use Oro\Bundle\FlatRateShippingBundle\Method\FlatRateMethodType;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\MigrationBundle\Fixture\RenamedFixtureInterface;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\OrganizationBundle\Migrations\Data\ORM\LoadOrganizationAndBusinessUnitData;
use Oro\Bundle\RuleBundle\Entity\Rule;
use Oro\Bundle\ShippingBundle\Entity\ShippingMethodConfig;
use Oro\Bundle\ShippingBundle\Entity\ShippingMethodsConfigsRule;
use Oro\Bundle\ShippingBundle\Entity\ShippingMethodTypeConfig;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Migrations\Data\ORM\LoadAdminUserData;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures an integration instance and adds a shipping rule to enable flat rate shipping ($10 per order).
 */
class LoadFlatRateIntegration extends AbstractFixture implements
    DependentFixtureInterface,
    ContainerAwareInterface,
    RenamedFixtureInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [
            LoadOrganizationAndBusinessUnitData::class,
            LoadAdminUserData::class,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getPreviousClassNames(): array
    {
        return [
            'Oro\\Bundle\\FlatRateBundle\\Migrations\\Data\\ORM\\LoadFlatRateIntegration',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager)
    {
        if (!$this->container) {
            return;
        }

        $channel = $this->loadIntegration($manager);

        $this->loadShippingRule($manager, $channel);
    }

    /**
     * @param ObjectManager $manager
     *
     * @return Channel
     */
    private function loadIntegration(ObjectManager $manager)
    {
        $label = (new LocalizedFallbackValue())->setString('Flat Rate');

        $transport = new FlatRateSettings();
        $transport->addLabel($label);

        $channel = new Channel();
        $channel->setType(FlatRateChannelType::TYPE)
            ->setName('Flat Rate')
            ->setEnabled(true)
            ->setOrganization($this->getOrganization($manager))
            ->setDefaultUserOwner($this->getMainUser($manager))
            ->setTransport($transport);

        $manager->persist($channel);
        $manager->flush();

        return $channel;
    }

    private function loadShippingRule(ObjectManager $manager, Channel $channel)
    {
        $typeConfig = new ShippingMethodTypeConfig();
        $typeConfig->setEnabled(true);
        $typeConfig->setType(FlatRateMethodType::IDENTIFIER)
            ->setOptions([
                FlatRateMethodType::PRICE_OPTION => 10,
                FlatRateMethodType::TYPE_OPTION => FlatRateMethodType::PER_ORDER_TYPE,
            ]);

        $methodConfig = new ShippingMethodConfig();
        $methodConfig->setMethod($this->getFlatRateIdentifier($channel))
            ->addTypeConfig($typeConfig);

        $rule = new Rule();
        $rule->setName('Default')
            ->setExpression('lineItems.all(lineItem.product.id > 21)')
            ->setEnabled(true)
            ->setSortOrder(1);

        $shippingRule = new ShippingMethodsConfigsRule();

        $shippingRule->setRule($rule)
            ->setOrganization($this->getOrganization($manager))
            ->setCurrency($this->getDefaultCurrency())
            ->addMethodConfig($methodConfig);

        $manager->persist($shippingRule);
        $manager->flush();
    }

    /**
     * @param ObjectManager $manager
     *
     * @return Organization|object
     */
    private function getOrganization(ObjectManager $manager)
    {
        if ($this->hasReference(LoadOrganizationAndBusinessUnitData::REFERENCE_DEFAULT_ORGANIZATION)) {
            return $this->getReference(LoadOrganizationAndBusinessUnitData::REFERENCE_DEFAULT_ORGANIZATION);
        }

        return $manager->getRepository('OroOrganizationBundle:Organization')->getFirst();
    }

    /**
     * @param ObjectManager $manager
     *
     * @return User
     *
     * @throws EntityNotFoundException
     */
    public function getMainUser(ObjectManager $manager)
    {
        /** @var User $entity */
        $entity = $manager->getRepository(User::class)->findOneBy([], ['id' => 'ASC']);
        if (!$entity) {
            throw new EntityNotFoundException('Main user does not exist.');
        }

        return $entity;
    }

    /**
     * @param Channel $channel
     *
     * @return int|string
     */
    private function getFlatRateIdentifier(Channel $channel)
    {
        return $this->container
            ->get('oro_flat_rate_shipping.method.identifier_generator.method')
            ->generateIdentifier($channel);
    }

    /**
     * @return string
     */
    private function getDefaultCurrency()
    {
        /** @var ConfigManager $configManager * */
        $configManager = $this->container->get('oro_config.global');

        $currencyConfigKey = CurrencyConfig::getConfigKeyByName(CurrencyConfig::KEY_DEFAULT_CURRENCY);

        return $configManager->get($currencyConfigKey) ?: CurrencyConfig::DEFAULT_CURRENCY;
    }
}

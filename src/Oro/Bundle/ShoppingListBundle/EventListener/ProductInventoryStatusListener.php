<?php

namespace Oro\Bundle\ShoppingListBundle\EventListener;

use Doctrine\ORM\Event\PreUpdateEventArgs;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ShoppingListBundle\Async\MessageFactory;
use Oro\Bundle\ShoppingListBundle\Async\Topics;
use Oro\Bundle\WebsiteBundle\Provider\WebsiteProviderInterface;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;

/**
 * Listen product inventory_status changes. Schedule product removal from shopping lists on inventory_status change
 * to not allowed value.
 */
class ProductInventoryStatusListener
{
    /**
     * @var ConfigManager
     */
    private $configManager;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var MessageProducerInterface
     */
    private $producer;

    /**
     * @var WebsiteProviderInterface
     */
    private $websiteProvider;

    public function __construct(
        ConfigManager $configManager,
        MessageFactory $messageFactory,
        MessageProducerInterface $producer,
        WebsiteProviderInterface $websiteProvider
    ) {
        $this->configManager = $configManager;
        $this->messageFactory = $messageFactory;
        $this->producer = $producer;
        $this->websiteProvider = $websiteProvider;
    }

    public function preUpdate(Product $product, PreUpdateEventArgs $args)
    {
        if ($args->hasChangedField('inventory_status')) {
            $websites = $this->websiteProvider->getWebsites();
            $allowedStatusesPerWebsite = $this->configManager->getValues(
                'oro_product.general_frontend_product_visibility',
                $websites
            );

            foreach ($allowedStatusesPerWebsite as $websiteId => $allowedStatuses) {
                if (!\in_array($product->getInventoryStatus()->getId(), $allowedStatuses, true)) {
                    $context = $websites[$websiteId] ?? null;
                    $this->producer->send(
                        Topics::INVALIDATE_TOTALS_BY_INVENTORY_STATUS_PER_PRODUCT,
                        $this->messageFactory->createShoppingTotalsInvalidateMessage($context, [$product->getId()])
                    );
                }
            }
        }
    }
}

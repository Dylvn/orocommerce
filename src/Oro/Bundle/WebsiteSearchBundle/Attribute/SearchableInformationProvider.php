<?php

namespace Oro\Bundle\WebsiteSearchBundle\Attribute;

use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;

/**
 * Provides searchable info for attribute.
 */
class SearchableInformationProvider
{
    /** @var ConfigManager */
    private $configManager;

    /**
     * @param ConfigManager $configManager
     */
    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    /**
     * @param FieldConfigModel $attribute
     *
     * @return float|null
     */
    public function getAttributeSearchBoost(FieldConfigModel $attribute): ?float
    {
        $className = $attribute->getEntity()->getClassName();
        $fieldName = $attribute->getFieldName();

        return $this->configManager->getProvider('attribute')
            ->getConfig($className, $fieldName)
            ->get('search_boost');
    }
}

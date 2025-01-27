<?php

namespace Oro\Bundle\ConsentBundle\Tests\Unit\DependencyInjection;

use Oro\Bundle\ConsentBundle\DependencyInjection\OroConsentExtension;
use Oro\Bundle\TestFrameworkBundle\Test\DependencyInjection\ExtensionTestCase;

class OroConsentExtensionTest extends ExtensionTestCase
{
    public function testLoad()
    {
        $this->loadExtension(new OroConsentExtension());

        $expectedDefinitions = [
            'oro_consent.form.autocomplete.consent.search_handler',
            'oro_consent.form.consent_collection_data_transformer',
            'oro_consent.validator.unique_consent',
        ];
        $this->assertDefinitionsLoaded($expectedDefinitions);

        $expectedExtensionConfigs = [
            'oro_consent'
        ];
        $this->assertExtensionConfigsLoaded($expectedExtensionConfigs);
    }
}

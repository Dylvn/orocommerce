<?php

namespace Oro\Bundle\CMSBundle\Model;

use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;

/**
 * Model class which allows to extend the Page entity.
 *
 * @method LocalizedFallbackValue getTitle(Localization $localization = null)
 * @method LocalizedFallbackValue getDefaultTitle()
 * @method LocalizedFallbackValue getSlug(Localization $localization = null)
 * @method LocalizedFallbackValue getDefaultSlug()
 * @method setDefaultTitle($title)
 * @method setDefaultSlug($slug)
 * @method $this cloneLocalizedFallbackValueAssociations()
 */
class ExtendPage
{
    /**
     * Constructor
     *
     * The real implementation of this method is auto generated.
     *
     * IMPORTANT: If the derived class has own constructor it must call parent constructor.
     */
    public function __construct()
    {
    }
}

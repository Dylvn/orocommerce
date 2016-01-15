<?php

namespace OroB2B\Bundle\MenuBundle\Menu;

use Knp\Menu\ItemInterface;

interface BuilderInterface
{
    const IS_ALLOWED_OPTION_KEY = 'isAllowed';

    /**
     * Modify menu by adding, removing or editing items.
     *
     * @param string $alias
     * @param array                   $options
     * @param string|null             $alias
     * @return ItemInterface
     */
    public function build($alias, array $options = []);

    /**
     * @param $alias
     * @return bool
     */
    public function isSupported($alias);
}

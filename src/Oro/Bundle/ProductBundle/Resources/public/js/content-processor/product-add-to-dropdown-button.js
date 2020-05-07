define([
    'oroui/js/content-processor/pinned-dropdown-button',
    'underscore',
    'oroui/js/mediator',
    'oroui/js/tools',
    'oroui/js/app/components/base/component-container-mixin'
], function($, _, mediator, tools, componentContainerMixin) {
    'use strict';

    $.widget(
        'oroui.productAddToDropdownButtonProcessor',
        $.oroui.pinnedDropdownButtonProcessor,
        _.extend(componentContainerMixin, {
            options: {
                moreButtonAttrs: {
                    'data-inherit-parent-width': 'strictly'
                }
            },

            keyPreffix: 'product-add-to-dropdown-button-processor-',

            modules: [],

            getLayoutElement: function() {
                return this.element;
            },

            _create: function(...args) {
                const modules = this.modules = [];
                $(this.element).trigger('deferredInitialize', {
                    dropdownWidget: this,
                    productModel: this.options.productModel,
                    callback: function(module) {
                        modules.push(module);
                    }
                });
                $(this.element).attr('data-product-dropdown-root', '');
                this._super(...args);
            },

            _destroy: function() {
                delete this.modules;
                this.disposePageComponents();
                this._super();
            },

            _renderButtons: function(...args) {
                this._super(...args);
                _.each(this.modules, function(module) {
                    if (_.isFunction(module._afterRenderButtons)) {
                        module._afterRenderButtons();
                    } else if (module.view && _.isFunction(module.view._afterRenderButtons)) {
                        module.view._afterRenderButtons();
                    }
                });
            },

            _moreButton: function() {
                const $button = this._super();

                if (this.options.appendToBody === true) {
                    $button.data('container', 'body');
                }

                return $button;
            },

            _prepareMainButton: function($button) {
                const $mainButton = this._super($button);
                $mainButton.data('clone', $button);
                return $mainButton;
            },

            validateForm: function() {
                const $form = $(this.element).closest('form');

                return $form.data('validator') ? $form.valid() : true;
            }
        })
    );

    return $;
});

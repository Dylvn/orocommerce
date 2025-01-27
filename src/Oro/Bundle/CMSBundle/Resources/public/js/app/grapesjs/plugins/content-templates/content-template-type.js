import __ from 'orotranslation/js/translator';
import BaseTypeBuilder from 'orocms/js/app/grapesjs/type-builders/base-type-builder';
import LoadingMaskView from 'oroui/js/app/views/loading-mask-view';

const ContentTemplateType = BaseTypeBuilder.extend({
    constructor: function ContentTemplateType(...args) {
        ContentTemplateType.__super__.constructor.apply(this, args);
    },

    viewMixin: {
        async onActive() {
            const loaderMask = new LoadingMaskView({
                container: this.$el,
                className: 'gjs-loader-mask'
            });
            loaderMask.show();
            this.$el.addClass('gjs-view-loading');

            const hideLoading = () => {
                loaderMask.dispose();
                this.$el.removeClass('gjs-view-loading');
            };

            try {
                const {content, contentStyle} = await this.editor.templateContentApiAccessor.send({
                    id: this.model.get('template').id
                }, null, {}, {
                    errorHandlerMessage: __('oro.cms.wysiwyg.content_template_plugin.content_template.not_found', {
                        name: this.model.get('template').name
                    })
                });

                const newModel = this.model.replaceWith(`${content}<style>${contentStyle}</style>`);
                this.editor.select(newModel);
                this.editor.trigger('change:canvasOffset');
            } catch (e) {
                hideLoading();

                this.editor.selectRemove(this.model);
                this.model.remove();
            } finally {
                hideLoading();
            }
        }
    },

    isComponent() {
        return false;
    }
});

export default ContentTemplateType;

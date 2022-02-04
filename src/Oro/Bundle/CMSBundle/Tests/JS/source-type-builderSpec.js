import 'jasmine-jquery';
import grapesJS from 'grapesjs';
import SourceTypeBuilder from 'orocms/js/app/grapesjs/type-builders/source-type-builder';
import ComponentRestriction from 'orocms/js/app/grapesjs/plugins/components/component-restriction';
import html from 'text-loader!./fixtures/grapesjs-editor-view-fixture.html';

describe('orocms/js/app/grapesjs/type-builders/source-type-builder', () => {
    let sourceTypeBuilder;
    let editor;

    beforeEach(() => {
        window.setFixtures(html);
        editor = grapesJS.init({
            container: document.querySelector('.page-content-editor')
        });

        editor.ComponentRestriction = new ComponentRestriction(editor, {});
    });

    afterEach(() => {
        editor.destroy();
    });

    describe('component "SourceTypeBuilder"', () => {
        beforeEach(() => {
            sourceTypeBuilder = new SourceTypeBuilder({
                editor,
                componentType: 'source'
            });

            sourceTypeBuilder.execute();
        });

        afterEach(() => {
            sourceTypeBuilder.dispose();
        });

        it('check to be defined', () => {
            expect(sourceTypeBuilder).toBeDefined();
            expect(sourceTypeBuilder.componentType).toEqual('source');
        });

        it('check is component type defined', () => {
            const type = sourceTypeBuilder.editor.DomComponents.getType('source');
            expect(type).toBeDefined();
            expect(type.id).toEqual('source');
        });

        it('check base model extend', () => {
            const mockElement = document.createElement('SOURCE');
            expect(sourceTypeBuilder.Model.isComponent).toBeDefined();
            expect(sourceTypeBuilder.Model.isComponent(mockElement)).toEqual({
                type: sourceTypeBuilder.componentType
            });

            expect(sourceTypeBuilder.Model.componentType).toEqual(sourceTypeBuilder.componentType);
            expect(sourceTypeBuilder.Model.prototype.defaults.tagName).toEqual('source');
            expect(sourceTypeBuilder.Model.prototype.defaults.attributes).toEqual({
                srcset: '',
                type: '',
                media: '',
                sizes: ''
            });

            expect(sourceTypeBuilder.Model.prototype.editor).toEqual(editor);
        });
    });
});

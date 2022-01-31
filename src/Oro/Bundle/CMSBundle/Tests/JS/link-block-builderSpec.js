import 'jasmine-jquery';
import grapesJS from 'grapesjs';
import LinkBlockBuilder from 'orocms/js/app/grapesjs/type-builders/link-block-builder';
import ComponentRestriction from 'orocms/js/app/grapesjs/plugins/components/component-restriction';
import html from 'text-loader!./fixtures/grapesjs-editor-view-fixture.html';

describe('orocms/js/app/grapesjs/type-builders/link-block-builder', () => {
    let linkBlockBuilder;
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

    describe('component "LinkTypeBuilder"', () => {
        beforeEach(() => {
            linkBlockBuilder = new LinkBlockBuilder({
                editor,
                componentType: 'link-block'
            });

            linkBlockBuilder.execute();
        });

        afterEach(() => {
            linkBlockBuilder.dispose();
        });

        it('check to be defined', () => {
            expect(linkBlockBuilder).toBeDefined();
            expect(linkBlockBuilder.componentType).toEqual('link-block');
        });

        it('check is component type defined', () => {
            const type = linkBlockBuilder.editor.DomComponents.getType('link-block');
            expect(type).toBeDefined();
            expect(type.id).toEqual('link-block');
        });

        it('check is component type button', () => {
            const button = linkBlockBuilder.editor.BlockManager.get(linkBlockBuilder.componentType);
            expect(button).toBeDefined();
            expect(button.get('category').get('label')).toEqual('Basic');
        });

        it('check component parent type', () => {
            expect(linkBlockBuilder.parentType).toEqual('link');
        });

        it('check base model extend', () => {
            const mockElement = document.createElement('A');
            mockElement.classList.add('link-block');

            expect(linkBlockBuilder.Model.isComponent).toBeDefined();
            expect(linkBlockBuilder.Model.isComponent(mockElement)).toEqual({
                type: linkBlockBuilder.componentType
            });
            expect(linkBlockBuilder.Model.componentType).toEqual(linkBlockBuilder.componentType);

            expect(linkBlockBuilder.Model.prototype.defaults.tagName).toEqual('a');
            expect(linkBlockBuilder.Model.prototype.defaults.classes).toEqual(
                ['link-block']
            );
            expect(linkBlockBuilder.Model.prototype.defaults.style).toEqual({
                'display': 'inline-block',
                'padding': '5px',
                'min-height': '50px',
                'min-width': '50px'
            });
            expect(linkBlockBuilder.Model.prototype.defaults.traits).toEqual(['href', 'title', 'target']);
            expect(linkBlockBuilder.Model.prototype.defaults.components).toEqual([]);
            expect(linkBlockBuilder.Model.prototype.defaults.editable).toBeFalsy();
            expect(linkBlockBuilder.Model.prototype.defaults.droppable).toBeTruthy();

            expect(linkBlockBuilder.Model.prototype.editor).toBeDefined();
            expect(linkBlockBuilder.Model.prototype.editor).toEqual(editor);
        });
    });
});

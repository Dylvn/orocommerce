import 'jasmine-jquery';
import grapesJS from 'grapesjs';
import html from 'text-loader!../fixtures/grapesjs-editor-view-fixture.html';
import GrapesjsRteEditor from 'orocms/js/app/grapesjs/plugins/oro-rte-editor';

describe('orocms/js/app/grapesjs/plugins/oro-rte-editor', () => {
    let editor;
    let rteEditor;

    beforeEach(() => {
        window.setFixtures(html);
        editor = grapesJS.init({
            container: document.querySelector('.page-content-editor'),
            plugins: [GrapesjsRteEditor]
        });

        rteEditor = editor.RteEditor;
    });

    afterEach(() => {
        rteEditor.onDestroy();
        editor.destroy();
    });

    describe('feature "GrapesjsRteEditor"', () => {
        it('initialize', () => {
            expect(editor.RteEditor.collection.length).toEqual(10);
        });

        it('check "addAction"', () => {
            editor.RteEditor.addAction({
                name: 'test',
                order: 20,
                group: 'test-group',
                result() {}
            });
            const added = editor.RteEditor.collection.find(model => model.get('name') === 'test');

            expect(editor.RteEditor.collection.length).toEqual(11);
            expect(added.get('event')).toEqual('click');
        });

        it('check "removeAction"', () => {
            editor.RteEditor.removeAction('formatBlock');
            const added = editor.RteEditor.collection.find(model => model.get('name') === 'formatBlock');

            expect(editor.RteEditor.collection.length).toEqual(9);
            expect(added).toBeFalsy();
        });
    });
});
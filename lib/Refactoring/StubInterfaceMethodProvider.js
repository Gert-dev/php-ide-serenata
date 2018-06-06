/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let StubInterfaceMethodProvider;
const AbstractProvider = require('./AbstractProvider');

module.exports =

//#*
// Provides the ability to stub interface methods.
//#
(StubInterfaceMethodProvider = (function() {
    StubInterfaceMethodProvider = class StubInterfaceMethodProvider extends AbstractProvider {
        static initClass() {
            /**
             * The view that allows the user to select the properties to generate for.
            */
            this.prototype.selectionView = null;

            /**
             * @type {Object}
            */
            this.prototype.docblockBuilder = null;

            /**
             * @type {Object}
            */
            this.prototype.functionBuilder = null;
        }

        /**
         * @param {Object} docblockBuilder
         * @param {Object} functionBuilder
        */
        constructor(docblockBuilder, functionBuilder) {
            super();

            this.docblockBuilder = docblockBuilder;
            this.functionBuilder = functionBuilder;
        }

        /**
         * @inheritdoc
        */
        deactivate() {
            super.deactivate();

            if (this.selectionView) {
                this.selectionView.destroy();
                return this.selectionView = null;
            }
        }

        /**
         * @inheritdoc
        */
        getIntentionProviders() {
            return [{
                grammarScopes: ['source.php'],
                getIntentions: ({textEditor, bufferPosition}) => {
                    if ((this.getCurrentProjectPhpVersion() == null)) { return []; }

                    return this.getStubInterfaceMethodIntentions(textEditor, bufferPosition);
                }
            }];
        }

        /**
         * @param {TextEditor} editor
         * @param {Point}      triggerPosition
        */
        getStubInterfaceMethodIntentions(editor, triggerPosition) {
            const failureHandler = () => [];

            const successHandler = currentClassName => {
                if (!currentClassName) { return []; }

                const nestedSuccessHandler = classInfo => {
                    if (!classInfo) { return []; }

                    const items = [];

                    for (let name in classInfo.methods) {
                        const method = classInfo.methods[name];
                        const data = {
                            name,
                            method
                        };

                        if ((method.declaringStructure.type === 'interface') && ((method.implementations != null ? method.implementations.length : undefined) === 0)) {
                            items.push(data);
                        }
                    }

                    if (items.length === 0) { return []; }

                    this.getSelectionView().setItems(items);

                    return [
                        {
                            priority : 100,
                            icon     : 'link',
                            title    : 'Stub Unimplemented Interface Method(s)',

                            selected : () => {
                                return this.executeStubInterfaceMethods(editor);
                            }
                        }
                    ];
                };

                return this.service.getClassInfo(currentClassName).then(nestedSuccessHandler, failureHandler);
            };

            return this.service.determineCurrentClassName(editor, triggerPosition).then(successHandler, failureHandler);
        }

        /**
         * @param {TextEditor} editor
         * @param {Point}      triggerPosition
        */
        executeStubInterfaceMethods(editor) {
            this.getSelectionView().setMetadata({editor});
            this.getSelectionView().storeFocusedElement();
            return this.getSelectionView().present();
        }

        /**
         * Called when the selection of properties is cancelled.
        */
        onCancel(metadata) {}

        /**
         * Called when the selection of properties is confirmed.
         *
         * @param {array}       selectedItems
         * @param {Object|null} metadata
        */
        onConfirm(selectedItems, metadata) {
            const itemOutputs = [];

            const tabText = metadata.editor.getTabText();
            const indentationLevel = metadata.editor.indentationForBufferRow(metadata.editor.getCursorBufferPosition().row);
            const maxLineLength = atom.config.get('editor.preferredLineLength', metadata.editor.getLastCursor().getScopeDescriptor());

            for (const item of selectedItems) {
                itemOutputs.push(this.generateStubForInterfaceMethod(item.method, tabText, indentationLevel, maxLineLength));
            }

            const output = itemOutputs.join('\n').trim();

            return metadata.editor.insertText(output);
        }

        /**
         * Generates a stub for the specified selected data.
         *
         * @param {Object} data
         * @param {String} tabText
         * @param {Number} indentationLevel
         * @param {Number} maxLineLength
         *
         * @return {string}
        */
        generateStubForInterfaceMethod(data, tabText, indentationLevel, maxLineLength) {
            const statements = [
                'throw new \\LogicException(\'Not implemented\'); // TODO'
            ];

            const functionText = this.functionBuilder
                .setFromRawMethodData(data)
                .setStatements(statements)
                .setTabText(tabText)
                .setIndentationLevel(indentationLevel)
                .setMaxLineLength(maxLineLength)
                .build();

            const docblockText = this.docblockBuilder.buildByLines(['@inheritDoc'], tabText.repeat(indentationLevel));

            return docblockText + functionText;
        }

        /**
         * @return {Builder}
        */
        getSelectionView() {
            if ((this.selectionView == null)) {
                const View = require('./StubInterfaceMethodProvider/View');

                this.selectionView = new View(this.onConfirm.bind(this), this.onCancel.bind(this));
                this.selectionView.setLoading('Loading class information...');
                this.selectionView.setEmptyMessage('No unimplemented interface methods found.');
            }

            return this.selectionView;
        }
    };
    StubInterfaceMethodProvider.initClass();
    return StubInterfaceMethodProvider;
})());

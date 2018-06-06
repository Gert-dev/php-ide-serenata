/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let OverrideMethodProvider;
const AbstractProvider = require('./AbstractProvider');

module.exports =

//#*
// Provides the ability to implement interface methods.
//#
(OverrideMethodProvider = (function() {
    OverrideMethodProvider = class OverrideMethodProvider extends AbstractProvider {
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

                        // Interface methods can already be stubbed via StubInterfaceMethodProvider.
                        if (method.declaringStructure.type === 'interface') { continue; }

                        // Abstract methods can already be stubbed via StubAbstractMethodProvider.
                        if (method.isAbstract) { continue; }

                        if (method.declaringStructure.name !== classInfo.name) {
                            items.push(data);
                        }
                    }

                    if (items.length === 0) { return []; }

                    this.getSelectionView().setItems(items);

                    return [
                        {
                            priority : 100,
                            icon     : 'link',
                            title    : 'Override Method(s)',

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
                const stub = this.generateStubForInterfaceMethod(item.method, tabText, indentationLevel, maxLineLength);

                itemOutputs.push(stub);
            }

            const output = itemOutputs.join('\n').trim();

            return metadata.editor.insertText(output);
        }

        /**
         * Generates an override for the specified selected data.
         *
         * @param {Object} data
         * @param {String} tabText
         * @param {Number} indentationLevel
         * @param {Number} maxLineLength
         *
         * @return {string}
        */
        generateStubForInterfaceMethod(data, tabText, indentationLevel, maxLineLength) {
            const parameterNames = data.parameters.map(item => `$${item.name}`);

            const hasReturnValue = this.hasReturnValue(data);

            let parentCallStatement = '';

            if (hasReturnValue) {
                parentCallStatement += '$value = ';
            }

            parentCallStatement += `parent::${data.name}(`;
            parentCallStatement += parameterNames.join(', ');
            parentCallStatement += ');';

            const statements = [
                parentCallStatement,
                '',
                '// TODO'
            ];

            if (hasReturnValue) {
                statements.push('');
                statements.push('return $value;');
            }

            const functionText = this.functionBuilder
                .setFromRawMethodData(data)
                .setIsAbstract(false)
                .setStatements(statements)
                .setTabText(tabText)
                .setIndentationLevel(indentationLevel)
                .setMaxLineLength(maxLineLength)
                .build();

            const docblockText = this.docblockBuilder.buildByLines(['@inheritDoc'], tabText.repeat(indentationLevel));

            return docblockText + functionText;
        }

        /**
         * @param {Object} data
         *
         * @return {Boolean}
        */
        hasReturnValue(data) {
            if (data.name === '__construct') { return false; }
            if (data.returnTypes.length === 0) { return false; }
            if ((data.returnTypes.length === 1) && (data.returnTypes[0].type === 'void')) { return false; }

            return true;
        }

        /**
         * @return {Builder}
        */
        getSelectionView() {
            if ((this.selectionView == null)) {
                const View = require('./OverrideMethodProvider/View');

                this.selectionView = new View(this.onConfirm.bind(this), this.onCancel.bind(this));
                this.selectionView.setLoading('Loading class information...');
                this.selectionView.setEmptyMessage('No overridable methods found.');
            }

            return this.selectionView;
        }
    };
    OverrideMethodProvider.initClass();
    return OverrideMethodProvider;
})());

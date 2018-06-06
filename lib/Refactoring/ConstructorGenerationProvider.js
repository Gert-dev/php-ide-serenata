/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let DocblockProvider;
const {Point} = require('atom');

const AbstractProvider = require('./AbstractProvider');

module.exports =

//#*
// Provides docblock generation and maintenance capabilities.
//#
(DocblockProvider = (function() {
    DocblockProvider = class DocblockProvider extends AbstractProvider {
        static initClass() {
            /**
             * The view that allows the user to select the properties to add to the constructor as parameters.
            */
            this.prototype.selectionView = null;

            /**
             * Aids in building functions.
            */
            this.prototype.functionBuilder = null;

            /**
             * The docblock builder.
            */
            this.prototype.docblockBuilder = null;

            /**
             * The type helper.
            */
            this.prototype.typeHelper = null;
        }

        /**
         * @param {Object} typeHelper
         * @param {Object} functionBuilder
         * @param {Object} docblockBuilder
        */
        constructor(typeHelper, functionBuilder, docblockBuilder) {
            super();

            this.typeHelper = typeHelper;
            this.functionBuilder = functionBuilder;
            this.docblockBuilder = docblockBuilder;
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

                    return this.getIntentions(textEditor, bufferPosition);
                }
            }];
        }

        /**
         * @param {TextEditor} editor
         * @param {Point}      triggerPosition
        */
        getIntentions(editor, triggerPosition) {
            const successHandler = currentClassName => {
                if ((currentClassName == null)) { return []; }

                const nestedSuccessHandler = classInfo => {
                    if ((classInfo == null)) { return []; }

                    return [{
                        priority : 100,
                        icon     : 'gear',
                        title    : 'Generate Constructor',

                        selected : () => {
                            const items = [];
                            const promises = [];

                            const localTypesResolvedHandler = results => {
                                let resultIndex = 0;

                                for (const item of items) {
                                    for (const type of item.types) {
                                        type.type = results[resultIndex++];
                                    }
                                }

                                const zeroBasedStartLine = classInfo.startLine - 1;

                                const tabText = editor.getTabText();
                                const indentationLevel = editor.indentationForBufferRow(triggerPosition.row);

                                return this.generateConstructor(
                                    editor,
                                    triggerPosition,
                                    items,
                                    tabText,
                                    indentationLevel,
                                    atom.config.get('editor.preferredLineLength', editor.getLastCursor().getScopeDescriptor())
                                );
                            };

                            if (classInfo.properties.length === 0) {
                                return localTypesResolvedHandler([]);

                            } else {
                                // Ensure all types are localized to the use statements of this file, the original types will be
                                // relative to the original file (which may not be the same). The FQCN works but is long and
                                // there may be a local use statement that can be used to shorten it.
                                for (let name in classInfo.properties) {
                                    const property = classInfo.properties[name];
                                    items.push({
                                        name,
                                        types : property.types
                                    });

                                    for (const type of property.types) {
                                        if (this.typeHelper.isClassType(type.fqcn)) {
                                            promises.push(this.service.localizeType(
                                                editor.getPath(),
                                                triggerPosition.row + 1,
                                                type.fqcn
                                            )
                                            );

                                        } else {
                                            promises.push(Promise.resolve(type.fqcn));
                                        }
                                    }
                                }

                                return Promise.all(promises).then(localTypesResolvedHandler, failureHandler);
                            }
                        }
                    }];
                };

                return this.service.getClassInfo(currentClassName).then(nestedSuccessHandler, failureHandler);
            };

            var failureHandler = () => [];

            return this.service.determineCurrentClassName(editor, triggerPosition).then(successHandler, failureHandler);
        }

        /**
         * @param {TextEditor} editor
         * @param {Point}      triggerPosition
         * @param {Array}      items
         * @param {String}     tabText
         * @param {Number}     indentationLevel
         * @param {Number}     maxLineLength
        */
        generateConstructor(editor, triggerPosition, items, tabText, indentationLevel, maxLineLength) {
            const metadata = {
                editor,
                position         : triggerPosition,
                tabText,
                indentationLevel,
                maxLineLength
            };

            if (items.length > 0) {
                this.getSelectionView().setItems(items);
                this.getSelectionView().setMetadata(metadata);
                this.getSelectionView().storeFocusedElement();
                return this.getSelectionView().present();

            } else {
                return this.onConfirm([], metadata);
            }
        }

        /**
         * Called when the selection of properties is cancelled.
         *
         * @param {Object|null} metadata
        */
        onCancel(metadata) {}

        /**
         * Called when the selection of properties is confirmed.
         *
         * @param {Array}       selectedItems
         * @param {Object|null} metadata
        */
        onConfirm(selectedItems, metadata) {
            const statements = [];
            const parameters = [];
            const docblockParameters = [];

            for (const item of selectedItems) {
                const typeSpecification = this.typeHelper.buildTypeSpecificationFromTypeArray(item.types);
                const parameterTypeHint = this.typeHelper.getTypeHintForTypeSpecification(typeSpecification);

                parameters.push({
                    name         : `$${item.name}`,
                    typeHint     : parameterTypeHint.typeHint,
                    defaultValue : parameterTypeHint.shouldSetDefaultValueToNull ? 'null' : null
                });

                docblockParameters.push({
                    name : `$${item.name}`,
                    type : item.types.length > 0 ? typeSpecification : 'mixed'
                });

                statements.push(`$this->${item.name} = $${item.name};`);
            }

            if (statements.length === 0) {
                statements.push('');
            }

            const functionText = this.functionBuilder
                .makePublic()
                .setIsStatic(false)
                .setIsAbstract(false)
                .setName('__construct')
                .setReturnType(null)
                .setParameters(parameters)
                .setStatements(statements)
                .setTabText(metadata.tabText)
                .setIndentationLevel(metadata.indentationLevel)
                .setMaxLineLength(metadata.maxLineLength)
                .build();

            const docblockText = this.docblockBuilder.buildForMethod(
                docblockParameters,
                null,
                false,
                metadata.tabText.repeat(metadata.indentationLevel)
            );

            const text = docblockText.trimLeft() + functionText;

            return metadata.editor.getBuffer().insert(metadata.position, text);
        }

        /**
         * @return {Builder}
        */
        getSelectionView() {
            if ((this.selectionView == null)) {
                const View = require('./ConstructorGenerationProvider/View');

                this.selectionView = new View(this.onConfirm.bind(this), this.onCancel.bind(this));
                this.selectionView.setLoading('Loading class information...');
                this.selectionView.setEmptyMessage('No properties found.');
            }

            return this.selectionView;
        }
    };
    DocblockProvider.initClass();
    return DocblockProvider;
})());

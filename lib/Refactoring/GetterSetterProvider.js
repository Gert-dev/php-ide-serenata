/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let GetterSetterProvider;
const AbstractProvider = require('./AbstractProvider');

module.exports =

//#*
// Provides getter and setter (accessor and mutator) generation capabilities.
//#
(GetterSetterProvider = (function() {
    GetterSetterProvider = class GetterSetterProvider extends AbstractProvider {
        static initClass() {
            /**
             * The view that allows the user to select the properties to generate for.
            */
            this.prototype.selectionView = null;

            /**
             * Aids in building methods.
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
        activate(service) {
            super.activate(service);

            atom.commands.add('atom-workspace', { 'php-ide-serenata-refactoring:generate-getter': () => {
                return this.executeCommand(true, false);
            }
            }
            );

            atom.commands.add('atom-workspace', { 'php-ide-serenata-refactoring:generate-setter': () => {
                return this.executeCommand(false, true);
            }
            }
            );

            return atom.commands.add('atom-workspace', { 'php-ide-serenata-refactoring:generate-getter-setter-pair': () => {
                return this.executeCommand(true, true);
            }
            }
            );
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
                    const successHandler = currentClassName => {
                        if (!currentClassName) { return []; }

                        return [
                            {
                                priority : 100,
                                icon     : 'gear',
                                title    : 'Generate Getter And Setter Pair(s)',

                                selected : () => {
                                    return this.executeCommand(true, true);
                                }
                            },

                            {
                                priority : 100,
                                icon     : 'gear',
                                title    : 'Generate Getter(s)',

                                selected : () => {
                                    return this.executeCommand(true, false);
                                }
                            },

                            {
                                priority : 100,
                                icon     : 'gear',
                                title    : 'Generate Setter(s)',

                                selected : () => {
                                    return this.executeCommand(false, true);
                                }
                            }
                        ];
                    };

                    const failureHandler = () => [];

                    const activeTextEditor = atom.workspace.getActiveTextEditor();

                    if (!activeTextEditor) { return []; }
                    if ((this.getCurrentProjectPhpVersion() == null)) { return []; }

                    return this.service.determineCurrentClassName(activeTextEditor, activeTextEditor.getCursorBufferPosition()).then(successHandler, failureHandler);
                }
            }];
        }

        /**
         * Executes the generation.
         *
         * @param {boolean} enableGetterGeneration
         * @param {boolean} enableSetterGeneration
        */
        executeCommand(enableGetterGeneration, enableSetterGeneration) {
            const activeTextEditor = atom.workspace.getActiveTextEditor();

            if (!activeTextEditor) { return; }

            this.getSelectionView().setMetadata({editor: activeTextEditor});
            this.getSelectionView().storeFocusedElement();
            this.getSelectionView().present();

            const successHandler = currentClassName => {
                if (!currentClassName) { return; }

                const nestedSuccessHandler = classInfo => {
                    const enabledItems = [];
                    const disabledItems = [];

                    const indentationLevel = activeTextEditor.indentationForBufferRow(activeTextEditor.getCursorBufferPosition().row);

                    for (let name in classInfo.properties) {
                        const property = classInfo.properties[name];
                        const getterName = `get${name.substr(0, 1).toUpperCase()}${name.substr(1)}`;
                        const setterName = `set${name.substr(0, 1).toUpperCase()}${name.substr(1)}`;

                        const getterExists = getterName in classInfo.methods ? true : false;
                        const setterExists = setterName in classInfo.methods ? true : false;

                        const data = {
                            name,
                            types            : property.types,
                            needsGetter      : enableGetterGeneration,
                            needsSetter      : enableSetterGeneration,
                            getterName,
                            setterName,
                            tabText          : activeTextEditor.getTabText(),
                            indentationLevel,
                            maxLineLength    : atom.config.get('editor.preferredLineLength', activeTextEditor.getLastCursor().getScopeDescriptor())
                        };

                        if ((enableGetterGeneration && enableSetterGeneration && getterExists && setterExists) ||
                           (enableGetterGeneration && getterExists) ||
                           (enableSetterGeneration && setterExists)) {
                            data.className = 'php-ide-serenata-refactoring-strikethrough';
                            disabledItems.push(data);

                        } else {
                            data.className = '';
                            enabledItems.push(data);
                        }
                    }

                    return this.getSelectionView().setItems(enabledItems.concat(disabledItems));
                };

                const nestedFailureHandler = () => {
                    return this.getSelectionView().setItems([]);
                };

                return this.service.getClassInfo(currentClassName).then(nestedSuccessHandler, nestedFailureHandler);
            };

            const failureHandler = () => {
                return this.getSelectionView().setItems([]);
            };

            return this.service.determineCurrentClassName(activeTextEditor, activeTextEditor.getCursorBufferPosition()).then(successHandler, failureHandler);
        }

        /**
         * Indicates if the specified type is a class type or not.
         *
         * @return {bool}
        */
        isClassType(type) {
            if (type.substr(0, 1).toUpperCase() === type.substr(0, 1)) { return true; } else { return false; }
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
         * @param {array}       selectedItems
         * @param {Object|null} metadata
        */
        onConfirm(selectedItems, metadata) {
            const itemOutputs = [];

            for (const item of selectedItems) {
                if (item.needsGetter) {
                    itemOutputs.push(this.generateGetterForItem(item));
                }

                if (item.needsSetter) {
                    itemOutputs.push(this.generateSetterForItem(item));
                }
            }

            const output = itemOutputs.join('\n').trim();

            return metadata.editor.getBuffer().insert(metadata.editor.getCursorBufferPosition(), output);
        }

        /**
         * Generates a getter for the specified selected item.
         *
         * @param {Object} item
         *
         * @return {string}
        */
        generateGetterForItem(item) {
            const typeSpecification = this.typeHelper.buildTypeSpecificationFromTypeArray(item.types);

            const statements = [
                `return $this->${item.name};`
            ];

            const functionText = this.functionBuilder
                .makePublic()
                .setIsStatic(false)
                .setIsAbstract(false)
                .setName(item.getterName)
                .setReturnType(this.typeHelper.getReturnTypeHintForTypeSpecification(typeSpecification))
                .setParameters([])
                .setStatements(statements)
                .setTabText(item.tabText)
                .setIndentationLevel(item.indentationLevel)
                .setMaxLineLength(item.maxLineLength)
                .build();

            const docblockText = this.docblockBuilder.buildForMethod(
                [],
                typeSpecification,
                false,
                item.tabText.repeat(item.indentationLevel)
            );

            return docblockText + functionText;
        }

        /**
         * Generates a setter for the specified selected item.
         *
         * @param {Object} item
         *
         * @return {string}
        */
        generateSetterForItem(item) {
            const typeSpecification = this.typeHelper.buildTypeSpecificationFromTypeArray(item.types);
            const parameterTypeHint = this.typeHelper.getTypeHintForTypeSpecification(typeSpecification);

            const statements = [
                `$this->${item.name} = $${item.name};`,
                'return $this;'
            ];

            const parameters = [
                {
                    name         : `$${item.name}`,
                    typeHint     : parameterTypeHint.typeHint,
                    defaultValue : parameterTypeHint.shouldSetDefaultValueToNull ? 'null' : null
                }
            ];

            const functionText = this.functionBuilder
                .makePublic()
                .setIsStatic(false)
                .setIsAbstract(false)
                .setName(item.setterName)
                .setReturnType(null)
                .setParameters(parameters)
                .setStatements(statements)
                .setTabText(item.tabText)
                .setIndentationLevel(item.indentationLevel)
                .setMaxLineLength(item.maxLineLength)
                .build();

            const docblockText = this.docblockBuilder.buildForMethod(
                [{name : `$${item.name}`, type : typeSpecification}],
                'static',
                false,
                item.tabText.repeat(item.indentationLevel)
            );

            return docblockText + functionText;
        }

        /**
         * @return {Builder}
        */
        getSelectionView() {
            if ((this.selectionView == null)) {
                const View = require('./GetterSetterProvider/View');

                this.selectionView = new View(this.onConfirm.bind(this), this.onCancel.bind(this));
                this.selectionView.setLoading('Loading class information...');
                this.selectionView.setEmptyMessage('No properties found.');
            }

            return this.selectionView;
        }
    };
    GetterSetterProvider.initClass();
    return GetterSetterProvider;
})());

/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
const AbstractProvider = require('./AbstractProvider');

module.exports =

//#*
// Provides getter and setter (accessor and mutator) generation capabilities.
//#
class GetterSetterProvider extends AbstractProvider {
    /**
     * @param {Object} typeHelper
     * @param {Object} functionBuilder
     * @param {Object} docblockBuilder
    */
    constructor(typeHelper, functionBuilder, docblockBuilder) {
        super();

        /**
         * The view that allows the user to select the properties to generate for.
        */
        this.selectionView = null;

        /**
         * Aids in building methods.
        */
        this.functionBuilder = functionBuilder;

        /**
         * The docblock builder.
        */
        this.docblockBuilder = docblockBuilder;

        /**
         * The type helper.
        */
        this.typeHelper = typeHelper;
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

        return atom.commands.add(
            'atom-workspace',
            { 'php-ide-serenata-refactoring:generate-getter-setter-pair': () => {
                return this.executeCommand(true, true);
            } }
        );
    }

    /**
     * @inheritdoc
    */
    deactivate() {
        super.deactivate();

        if (this.selectionView) {
            this.selectionView.destroy();
            this.selectionView = null;
        }
    }

    /**
     * @inheritdoc
    */
    getIntentionProviders() {
        return [{
            grammarScopes: ['source.php'],
            getIntentions: () => {
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

                return this.service.determineCurrentClassName(
                    activeTextEditor,
                    activeTextEditor.getCursorBufferPosition()
                ).then(successHandler, failureHandler);
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

                const indentationLevel = activeTextEditor.indentationForBufferRow(
                    activeTextEditor.getCursorBufferPosition().row
                );

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
                        maxLineLength    : atom.config.get(
                            'editor.preferredLineLength',
                            activeTextEditor.getLastCursor().getScopeDescriptor()
                        )
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

        return this.service.determineCurrentClassName(activeTextEditor, activeTextEditor.getCursorBufferPosition())
            .then(successHandler, failureHandler);
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
    onCancel() {}

    /**
     * Called when the selection of properties is confirmed.
     *
     * @param {array}       selectedItems
     * @param {Object|null} metadata
    */
    async onConfirm(selectedItems, metadata) {
        const itemOutputs = [];
        const editorUri = 'file://' + metadata.editor.getPath();
        const bufferPosition = metadata.editor.getCursorBufferPosition();

        for (const item of selectedItems) {
            if (item.needsGetter) {
                itemOutputs.push(await this.generateGetterForItem(item, editorUri, bufferPosition));
            }

            if (item.needsSetter) {
                itemOutputs.push(await this.generateSetterForItem(item, editorUri, bufferPosition));
            }
        }

        const output = itemOutputs.join('\n').trim();

        return metadata.editor.getBuffer().insert(bufferPosition, output);
    }

    /**
     * Generates a getter for the specified selected item.
     *
     * @param {Object} item
     * @param {String} editorUri
     * @param {Point}  bufferPosition
     *
     * @return {string}
    */
    async generateGetterForItem(item, editorUri, bufferPosition) {
        await this.localizeFunctionParameterTypes(item, editorUri, bufferPosition);

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

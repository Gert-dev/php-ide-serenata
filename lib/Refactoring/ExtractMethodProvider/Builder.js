/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let Builder;
const {Range} = require('atom');

module.exports =

(Builder = (function() {
    Builder = class Builder {
        static initClass() {
            /**
             * The body of the new method that will be shown in the preview area.
             *
             * @type {String}
            */
            this.prototype.methodBody = '';

            /**
             * The tab string that is used by the current editor.
             *
             * @type {String}
            */
            this.prototype.tabText = '';

            /**
             * @type {Number}
            */
            this.prototype.indentationLevel = null;

            /**
             * @type {Number}
            */
            this.prototype.maxLineLength = null;

            /**
             * The php-ide-serenata service.
             *
             * @type {Service}
            */
            this.prototype.service = null;

            /**
             * A range of the selected/highlighted area of code to analyse.
             *
             * @type {Range}
            */
            this.prototype.selectedBufferRange = null;

            /**
             * The text editor to be analysing.
             *
             * @type {TextEditor}
            */
            this.prototype.editor = null;

            /**
             * The parameter parser that will work out the parameters the
             * selectedBufferRange will need.
             *
             * @type {Object}
            */
            this.prototype.parameterParser = null;

            /**
             * All the variables to return
             *
             * @type {Array}
            */
            this.prototype.returnVariables = null;

            /**
             * @type {Object}
            */
            this.prototype.docblockBuilder = null;

            /**
             * @type {Object}
            */
            this.prototype.functionBuilder = null;

            /**
             * @type {Object}
            */
            this.prototype.typeHelper = null;
        }

        /**
         * Constructor.
         *
         * @param  {Object} parameterParser
         * @param  {Object} docblockBuilder
         * @param  {Object} functionBuilder
         * @param  {Object} typeHelper
        */
        constructor(parameterParser, docblockBuilder, functionBuilder, typeHelper) {
            this.setEditor = this.setEditor.bind(this);
            this.buildMethod = this.buildMethod.bind(this);
            this.buildMethodCall = this.buildMethodCall.bind(this);
            this.parameterParser = parameterParser;
            this.docblockBuilder = docblockBuilder;
            this.functionBuilder = functionBuilder;
            this.typeHelper = typeHelper;
        }

        /**
         * Sets the method body to use in the preview.
         *
         * @param {String} text
        */
        setMethodBody(text) {
            return this.methodBody = text;
        }

        /**
         * The tab string to use when generating the new method.
         *
         * @param {String} tab
        */
        setTabText(tab) {
            return this.tabText = tab;
        }

        /**
         * @param {Number} indentationLevel
        */
        setIndentationLevel(indentationLevel) {
            return this.indentationLevel = indentationLevel;
        }

        /**
         * @param {Number} maxLineLength
        */
        setMaxLineLength(maxLineLength) {
            return this.maxLineLength = maxLineLength;
        }

        /**
         * Set the php-ide-serenata service to be used.
         *
         * @param {Service} service
        */
        setService(service) {
            this.service = service;
            return this.parameterParser.setService(service);
        }

        /**
         * Set the selectedBufferRange to analyse.
         *
         * @param {Range} range [description]
        */
        setSelectedBufferRange(range) {
            return this.selectedBufferRange = range;
        }

        /**
         * Set the TextEditor to be used when analysing the selectedBufferRange
         *
         * @param {TextEditor} editor [description]
        */
        setEditor(editor) {
            this.editor = editor;
            this.setTabText(editor.getTabText());
            this.setIndentationLevel(1);
            this.setMaxLineLength(atom.config.get('editor.preferredLineLength', editor.getLastCursor().getScopeDescriptor()));
            return this.setSelectedBufferRange(editor.getSelectedBufferRange());
        }

        /**
         * Builds the new method from the selectedBufferRange and settings given.
         *
         * The settings parameter should be an object with these properties:
         *   - methodName (string)
         *   - visibility (string) ['private', 'protected', 'public']
         *   - tabs (boolean)
         *   - generateDocs (boolean)
         *   - arraySyntax (string) ['word', 'brackets']
         *   - generateDocPlaceholders (boolean)
         *
         * @param  {Object} settings
         *
         * @return {Promise}
        */
        buildMethod(settings) {
            const successHandler = parameters => {
                if (this.returnVariables === null) {
                    this.returnVariables = this.workOutReturnVariables(this.parameterParser.getVariableDeclarations());
                }

                const tabText = settings.tabs ? this.tabText : '';
                const totalIndentation = tabText.repeat(this.indentationLevel);

                const statements = [];

                for (const statement of this.methodBody.split('\n')) {
                    const newStatement = statement.substr(totalIndentation.length);

                    statements.push(newStatement);
                }

                let returnTypeHintSpecification = 'void';
                let returnStatement = this.buildReturnStatement(this.returnVariables, settings.arraySyntax);

                if (returnStatement != null) {
                    if (this.returnVariables.length === 1) {
                        returnTypeHintSpecification = this.returnVariables[0].types.join('|');

                    } else {
                        returnTypeHintSpecification = 'array';
                    }

                    returnStatement = returnStatement.substr(totalIndentation.length);

                    statements.push('');
                    statements.push(returnStatement);
                }

                const functionParameters = parameters.map(parameter => {
                    const typeHintInfo = this.typeHelper.getTypeHintForDocblockTypes(parameter.types);

                    return {
                        name         : parameter.name,
                        typeHint     : (typeHintInfo != null) && settings.typeHinting    ? typeHintInfo.typeHint : null,
                        defaultValue : (typeHintInfo != null) && typeHintInfo.isNullable ? 'null' : null
                    };
                });

                const docblockParameters = parameters.map(parameter => {
                    const typeSpecification = this.typeHelper.buildTypeSpecificationFromTypes(parameter.types);

                    return {
                        name : parameter.name,
                        type : typeSpecification.length > 0 ? typeSpecification : '[type]'
                    };
                });

                this.functionBuilder
                    .setIsStatic(false)
                    .setIsAbstract(false)
                    .setName(settings.methodName)
                    .setReturnType(this.typeHelper.getReturnTypeHintForTypeSpecification(returnTypeHintSpecification))
                    .setParameters(functionParameters)
                    .setStatements(statements)
                    .setIndentationLevel(this.indentationLevel)
                    .setTabText(tabText)
                    .setMaxLineLength(this.maxLineLength);

                if (settings.visibility === 'public') {
                    this.functionBuilder.makePublic();

                } else if (settings.visibility === 'protected') {
                    this.functionBuilder.makeProtected();

                } else if (settings.visibility === 'private') {
                    this.functionBuilder.makePrivate();

                } else {
                    this.functionBuilder.makeGlobal();
                }

                let docblockText = '';

                if (settings.generateDocs) {
                    let returnType = 'void';

                    if ((this.returnVariables !== null) && (this.returnVariables.length > 0)) {
                        returnType = '[type]';

                        if (this.returnVariables.length > 1) {
                            returnType = 'array';

                        } else if ((this.returnVariables.length === 1) && (this.returnVariables[0].types.length > 0)) {
                            returnType = this.typeHelper.buildTypeSpecificationFromTypes(this.returnVariables[0].types);
                        }
                    }

                    docblockText = this.docblockBuilder.buildForMethod(
                        docblockParameters,
                        returnType,
                        settings.generateDescPlaceholders,
                        totalIndentation
                    );
                }

                return docblockText + this.functionBuilder.build();
            };

            const failureHandler = () => null;

            return this.parameterParser.findParameters(this.editor, this.selectedBufferRange).then(successHandler, failureHandler);
        }

        /**
         * Build the line that calls the new method and the variable the method
         * to be assigned to.
         *
         * @param  {String} methodName
         * @param  {String} variable   [Optional]
         *
         * @return {Promise}
        */
        buildMethodCall(methodName, variable) {
            const successHandler = parameters => {
                const parameterNames = parameters.map(item => item.name);

                let methodCall = `$this->${methodName}(${parameterNames.join(', ')});`;

                if (variable !== undefined) {
                    methodCall = `$${variable} = ${methodCall}`;
                } else {
                    if (this.returnVariables !== null) {
                        if (this.returnVariables.length === 1) {
                            methodCall = `${this.returnVariables[0].name} = ${methodCall}`;
                        } else if (this.returnVariables.length > 1) {
                            const variables = this.returnVariables.reduce(function(previous, current) {
                                if (typeof previous !== 'string') {
                                    previous = previous.name;
                                }

                                return previous + ', ' + current.name;
                            });

                            methodCall = `list(${variables}) = ${methodCall}`;
                        }
                    }
                }

                return methodCall;
            };

            const failureHandler = () => null;

            return this.parameterParser.findParameters(this.editor, this.selectedBufferRange).then(successHandler, failureHandler);
        }

        /**
         * Performs any clean up needed with the builder.
        */
        cleanUp() {
            this.returnVariables = null;
            return this.parameterParser.cleanUp();
        }

        /**
         * Works out which variables need to be returned from the new method.
         *
         * @param  {Array} variableDeclarations
         *
         * @return {Array}
        */
        workOutReturnVariables(variableDeclarations) {
            const startPoint = this.selectedBufferRange.end;
            const scopeRange = this.parameterParser.getRangeForCurrentScope(this.editor, startPoint);

            const lookupRange = new Range(startPoint, scopeRange.end);

            const textAfterExtraction = this.editor.getTextInBufferRange(lookupRange);
            const allVariablesAfterExtraction = textAfterExtraction.match(/\$[a-zA-Z0-9]+/g);

            if (allVariablesAfterExtraction === null) { return null; }

            variableDeclarations = variableDeclarations.filter(variable => {
                if (allVariablesAfterExtraction.includes(variable.name)) { return true; }
                return false;
            });

            return variableDeclarations;
        }

        /**
         * Builds the return statement for the new method.
         *
         * @param {Array}  variableDeclarations
         * @param {String} arrayType ['word', 'brackets']
         *
         * @return {String|null}
        */
        buildReturnStatement(variableDeclarations, arrayType) {
            if (arrayType == null) { arrayType = 'word'; }
            if (variableDeclarations != null) {
                if (variableDeclarations.length === 1) {
                    return `${this.tabText}return ${variableDeclarations[0].name};`;

                } else if (variableDeclarations.length > 1) {
                    let variables = variableDeclarations.reduce(function(previous, current) {
                        if (typeof previous !== 'string') {
                            previous = previous.name;
                        }

                        return previous + ', ' + current.name;
                    });

                    if (arrayType === 'brackets') {
                        variables = `[${variables}]`;

                    } else {
                        variables = `array(${variables})`;
                    }

                    return `${this.tabText}return ${variables};`;
                }
            }

            return null;
        }

        /**
         * Checks if the new method will be returning any values.
         *
         * @return {Boolean}
        */
        hasReturnValues() {
            return (this.returnVariables !== null) && (this.returnVariables.length > 0);
        }

        /**
         * Returns if there are multiple return values.
         *
         * @return {Boolean}
        */
        hasMultipleReturnValues() {
            return (this.returnVariables !== null) && (this.returnVariables.length > 1);
        }
    };
    Builder.initClass();
    return Builder;
})());

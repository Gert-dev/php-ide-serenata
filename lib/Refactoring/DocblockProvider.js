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
         * @param {Object} docblockBuilder
        */
        constructor(typeHelper, docblockBuilder) {
            super();

            this.typeHelper = typeHelper;
            this.docblockBuilder = docblockBuilder;
        }

        /**
         * @inheritdoc
        */
        getIntentionProviders() {
            return [{
                grammarScopes: ['entity.name.type.class.php', 'entity.name.type.interface.php', 'entity.name.type.trait.php'],
                getIntentions: ({textEditor, bufferPosition}) => {
                    const nameRange = textEditor.bufferRangeForScopeAtCursor('entity.name.type');

                    if ((nameRange == null)) { return; }
                    if ((this.getCurrentProjectPhpVersion() == null)) { return []; }

                    const name = textEditor.getTextInBufferRange(nameRange);

                    return this.getClasslikeIntentions(textEditor, bufferPosition, name);
                }
            }, {
                grammarScopes: ['entity.name.function.php', 'support.function.magic.php'],
                getIntentions: ({textEditor, bufferPosition}) => {
                    let nameRange = textEditor.bufferRangeForScopeAtCursor('entity.name.function.php');

                    if ((nameRange == null)) {
                        nameRange = textEditor.bufferRangeForScopeAtCursor('support.function.magic.php');
                    }

                    if ((nameRange == null)) { return; }
                    if ((this.getCurrentProjectPhpVersion() == null)) { return []; }

                    const name = textEditor.getTextInBufferRange(nameRange);

                    return this.getFunctionlikeIntentions(textEditor, bufferPosition, name);
                }
            }, {
                grammarScopes: ['variable.other.php'],
                getIntentions: ({textEditor, bufferPosition}) => {
                    const nameRange = textEditor.bufferRangeForScopeAtCursor('variable.other.php');

                    if ((nameRange == null)) { return; }
                    if ((this.getCurrentProjectPhpVersion() == null)) { return []; }

                    const name = textEditor.getTextInBufferRange(nameRange);

                    return this.getPropertyIntentions(textEditor, bufferPosition, name);
                }
            }, {
                grammarScopes: ['constant.other.php'],
                getIntentions: ({textEditor, bufferPosition}) => {
                    const nameRange = textEditor.bufferRangeForScopeAtCursor('constant.other.php');

                    if ((nameRange == null)) { return; }
                    if ((this.getCurrentProjectPhpVersion() == null)) { return []; }

                    const name = textEditor.getTextInBufferRange(nameRange);

                    return this.getConstantIntentions(textEditor, bufferPosition, name);
                }
            }];
        }

        /**
         * @inheritdoc
        */
        deactivate() {
            super.deactivate();

            if (this.docblockBuilder) {
                //@docblockBuilder.destroy()
                return this.docblockBuilder = null;
            }
        }

        /**
         * @param {TextEditor} editor
         * @param {Point}      triggerPosition
         * @param {String}     name
        */
        getClasslikeIntentions(editor, triggerPosition, name) {
            const failureHandler = () => [];

            const successHandler = resolvedType => {
                const nestedSuccessHandler = classInfo => {
                    const intentions = [];

                    if ((classInfo == null)) { return intentions; }

                    if (!classInfo.hasDocblock) {
                        if (classInfo.hasDocumentation) {
                            intentions.push({
                                priority : 100,
                                icon     : 'gear',
                                title    : 'Generate Docblock (inheritDoc)',

                                selected : () => {
                                    return this.generateDocblockInheritance(editor, triggerPosition);
                                }
                            });
                        }

                        intentions.push({
                            priority : 100,
                            icon     : 'gear',
                            title    : 'Generate Docblock',

                            selected : () => {
                                return this.generateClasslikeDocblockFor(editor, classInfo);
                            }
                        });
                    }

                    return intentions;
                };

                return this.service.getClassInfo(resolvedType).then(nestedSuccessHandler, failureHandler);
            };

            return this.service.resolveType(editor.getPath(), triggerPosition.row + 1, name, 'classlike').then(successHandler, failureHandler);
        }

        /**
         * @param {TextEditor} editor
         * @param {Object}     classData
        */
        generateClasslikeDocblockFor(editor, classData) {
            const zeroBasedStartLine = classData.startLine - 1;

            const indentationLevel = editor.indentationForBufferRow(zeroBasedStartLine);

            const docblock = this.docblockBuilder.buildByLines(
                [],
                editor.getTabText().repeat(indentationLevel)
            );

            return editor.getBuffer().insert(new Point(zeroBasedStartLine, -1), docblock);
        }

        /**
         * @param {TextEditor} editor
         * @param {Point}      triggerPosition
         * @param {String}     name
        */
        getFunctionlikeIntentions(editor, triggerPosition, name) {
            const failureHandler = () => {
                return [];
            };

            const successHandler = currentClassName => {
                let nestedSuccessHandler;
                const helperFunction = functionlikeData => {
                    const intentions = [];

                    if (!functionlikeData) { return intentions; }

                    if (!functionlikeData.hasDocblock) {
                        if (functionlikeData.hasDocumentation) {
                            intentions.push({
                                priority : 100,
                                icon     : 'gear',
                                title    : 'Generate Docblock (inheritDoc)',

                                selected : () => {
                                    return this.generateDocblockInheritance(editor, triggerPosition);
                                }
                            });
                        }

                        intentions.push({
                            priority : 100,
                            icon     : 'gear',
                            title    : 'Generate Docblock',

                            selected : () => {
                                return this.generateFunctionlikeDocblockFor(editor, functionlikeData);
                            }
                        });
                    }

                    return intentions;
                };

                if (currentClassName) {
                    nestedSuccessHandler = classInfo => {
                        if (!(name in classInfo.methods)) { return []; }
                        return helperFunction(classInfo.methods[name]);
                    };

                    return this.service.getClassInfo(currentClassName).then(nestedSuccessHandler, failureHandler);

                } else {
                    nestedSuccessHandler = globalFunctions => {
                        if (!(name in globalFunctions)) { return []; }
                        return helperFunction(globalFunctions[name]);
                    };

                    return this.service.getGlobalFunctions().then(nestedSuccessHandler, failureHandler);
                }
            };

            return this.service.determineCurrentClassName(editor, triggerPosition).then(successHandler, failureHandler);
        }

        /**
         * @param {TextEditor} editor
         * @param {Object}     data
        */
        generateFunctionlikeDocblockFor(editor, data) {
            const zeroBasedStartLine = data.startLine - 1;

            const parameters = data.parameters.map(parameter => {
                let type = 'mixed';

                if (parameter.types.length > 0) {
                    type = this.typeHelper.buildTypeSpecificationFromTypeArray(parameter.types);
                }

                let name = '';

                if (parameter.isReference) {
                    name += '&';
                }

                name += `$${parameter.name}`;

                if (parameter.isVariadic) {
                    name = `...${name}`;
                    type += '[]';
                }

                return {
                    name,
                    type
                };
            });

            const indentationLevel = editor.indentationForBufferRow(zeroBasedStartLine);

            let returnType = null;

            if ((data.returnTypes.length > 0) && (data.name !== '__construct')) {
                returnType = this.typeHelper.buildTypeSpecificationFromTypeArray(data.returnTypes);
            }

            const docblock = this.docblockBuilder.buildForMethod(
                parameters,
                returnType,
                false,
                editor.getTabText().repeat(indentationLevel)
            );

            return editor.getBuffer().insert(new Point(zeroBasedStartLine, -1), docblock);
        }

        /**
         * @param {TextEditor} editor
         * @param {Point}      triggerPosition
         * @param {String}     name
        */
        getPropertyIntentions(editor, triggerPosition, name) {
            const failureHandler = () => {
                return [];
            };

            const successHandler = currentClassName => {
                if ((currentClassName == null)) { return []; }

                const nestedSuccessHandler = classInfo => {
                    name = name.substr(1);

                    if (!(name in classInfo.properties)) { return []; }

                    const propertyData = classInfo.properties[name];

                    if ((propertyData == null)) { return; }

                    const intentions = [];

                    if (!propertyData) { return intentions; }

                    if (!propertyData.hasDocblock) {
                        if (propertyData.hasDocumentation) {
                            intentions.push({
                                priority : 100,
                                icon     : 'gear',
                                title    : 'Generate Docblock (inheritDoc)',

                                selected : () => {
                                    return this.generateDocblockInheritance(editor, triggerPosition);
                                }
                            });
                        }

                        intentions.push({
                            priority : 100,
                            icon     : 'gear',
                            title    : 'Generate Docblock',

                            selected : () => {
                                return this.generatePropertyDocblockFor(editor, propertyData);
                            }
                        });
                    }

                    return intentions;
                };

                return this.service.getClassInfo(currentClassName).then(nestedSuccessHandler, failureHandler);
            };

            return this.service.determineCurrentClassName(editor, triggerPosition).then(successHandler, failureHandler);
        }

        /**
         * @param {TextEditor} editor
         * @param {Object}     data
        */
        generatePropertyDocblockFor(editor, data) {
            const zeroBasedStartLine = data.startLine - 1;

            const indentationLevel = editor.indentationForBufferRow(zeroBasedStartLine);

            let type = 'mixed';

            if (data.types.length > 0) {
                type = this.typeHelper.buildTypeSpecificationFromTypeArray(data.types);
            }

            const docblock = this.docblockBuilder.buildForProperty(
                type,
                false,
                editor.getTabText().repeat(indentationLevel)
            );

            return editor.getBuffer().insert(new Point(zeroBasedStartLine, -1), docblock);
        }

        /**
         * @param {TextEditor} editor
         * @param {Point}      triggerPosition
         * @param {String}     name
        */
        getConstantIntentions(editor, triggerPosition, name) {
            const failureHandler = () => {
                return [];
            };

            const successHandler = currentClassName => {
                let nestedSuccessHandler;
                const helperFunction = constantData => {
                    const intentions = [];

                    if (!constantData) { return intentions; }

                    if (!constantData.hasDocblock) {
                        intentions.push({
                            priority : 100,
                            icon     : 'gear',
                            title    : 'Generate Docblock',

                            selected : () => {
                                return this.generateConstantDocblockFor(editor, constantData);
                            }
                        });
                    }

                    return intentions;
                };

                if (currentClassName) {
                    nestedSuccessHandler = classInfo => {
                        if (!(name in classInfo.constants)) { return []; }
                        return helperFunction(classInfo.constants[name]);
                    };

                    return this.service.getClassInfo(currentClassName).then(nestedSuccessHandler, failureHandler);

                } else {
                    nestedSuccessHandler = globalConstants => {
                        if (!(name in globalConstants)) { return []; }
                        return helperFunction(globalConstants[name]);
                    };

                    return this.service.getGlobalConstants().then(nestedSuccessHandler, failureHandler);
                }
            };

            return this.service.determineCurrentClassName(editor, triggerPosition).then(successHandler, failureHandler);
        }

        /**
         * @param {TextEditor} editor
         * @param {Object}     data
        */
        generateConstantDocblockFor(editor, data) {
            const zeroBasedStartLine = data.startLine - 1;

            const indentationLevel = editor.indentationForBufferRow(zeroBasedStartLine);

            let type = 'mixed';

            if (data.types.length > 0) {
                type = this.typeHelper.buildTypeSpecificationFromTypeArray(data.types);
            }

            const docblock = this.docblockBuilder.buildForProperty(
                type,
                false,
                editor.getTabText().repeat(indentationLevel)
            );

            return editor.getBuffer().insert(new Point(zeroBasedStartLine, -1), docblock);
        }

        /**
         * @param {TextEditor} editor
         * @param {Point}      triggerPosition
        */
        generateDocblockInheritance(editor, triggerPosition) {
            const indentationLevel = editor.indentationForBufferRow(triggerPosition.row);

            const docblock = this.docblockBuilder.buildByLines(
                ['@inheritDoc'],
                editor.getTabText().repeat(indentationLevel)
            );

            return editor.getBuffer().insert(new Point(triggerPosition.row, -1), docblock);
        }
    };
    DocblockProvider.initClass();
    return DocblockProvider;
})());

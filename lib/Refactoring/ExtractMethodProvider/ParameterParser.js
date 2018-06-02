/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS202: Simplify dynamic range loops
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let ParameterParser;
const {Point, Range} = require('atom');

module.exports =

(ParameterParser = (function() {
    ParameterParser = class ParameterParser {
        static initClass() {
            /**
             * Service object from the php-ide-serenata service
             *
             * @type {Service}
            */
            this.prototype.service = null;

            /**
             * @type {Object}
            */
            this.prototype.typeHelper = null;

            /**
             * List of all the variable declarations that have been process
             *
             * @type {Array}
            */
            this.prototype.variableDeclarations = [];

            /**
             * The selected range that we are scanning for parameters in.
             *
             * @type {Range}
            */
            this.prototype.selectedBufferRange = null;
        }

        /**
         * Constructor
         *
         * @param {Object} typeHelper
        */
        constructor(typeHelper) {
            this.typeHelper = typeHelper;
        }

        /**
         * @param {Object} service
        */
        setService(service) {
            this.service = service;
        }

        /**
         * Takes the editor and the range and loops through finding all the
         * parameters that will be needed if this code was to be moved into
         * its own function
         *
         * @param  {TextEditor} editor
         * @param  {Range}      selectedBufferRange
         *
         * @return {Promise}
        */
        findParameters(editor, selectedBufferRange) {
            this.selectedBufferRange = selectedBufferRange;

            let parameters = [];

            editor.scanInBufferRange(/\$[a-zA-Z0-9_]+/g, selectedBufferRange, element => {
                // Making sure we matched a variable and not a variable within a string
                const descriptions = editor.scopeDescriptorForBufferPosition(element.range.start);
                const indexOfDescriptor = descriptions.scopes.indexOf('variable.other.php');
                if (indexOfDescriptor > -1) {
                    return parameters.push({
                        name: element.matchText,
                        range: element.range
                    });
                }
            });

            const regexFilters = [
                {
                    name: 'Foreach loops',
                    regex: /as\s(\$[a-zA-Z0-9_]+)(?:\s=>\s(\$[a-zA-Z0-9_]+))?/g
                },
                {
                    name: 'For loops',
                    regex: /for\s*\(\s*(\$[a-zA-Z0-9_]+)\s*=/g
                },
                {
                    name: 'Try catch',
                    regex: /catch(?:\(|\s)+.*?(\$[a-zA-Z0-9_]+)/g
                },
                {
                    name: 'Closure',
                    regex: /function(?:\s)*?\((?:\$).*?\)/g
                },
                {
                    name: 'Variable declarations',
                    regex: /(\$[a-zA-Z0-9]+)\s*?=(?!>|=)/g
                }
            ];

            const getTypePromises = [];
            const variableDeclarations = [];

            for (const filter of regexFilters) {
                editor.backwardsScanInBufferRange(filter.regex, selectedBufferRange, element => {
                    const variables = element.matchText.match(/\$[a-zA-Z0-9]+/g);
                    const startPoint = new Point(element.range.end.row, 0);
                    const scopeRange = this.getRangeForCurrentScope(editor, startPoint);

                    if (filter.name === 'Variable declarations') {
                        let chosenParameter = null;
                        for (const parameter of parameters) {
                            if (element.range.containsRange(parameter.range)) {
                                chosenParameter = parameter;
                                break;
                            }
                        }

                        if (chosenParameter !== null) {
                            getTypePromises.push((this.getTypesForParameter(editor, chosenParameter)));
                            variableDeclarations.push(chosenParameter);
                        }
                    }

                    return variables.map((variable) => {
                        (parameters = parameters.filter(parameter => {
                            if (parameter.name !== variable) {
                                return true;
                            }
                            if (scopeRange.containsRange(parameter.range)) {
                                // If variable declaration is after parameter then it's
                                // still needed in parameters.
                                if (element.range.start.row > parameter.range.start.row) {
                                    return true;
                                }
                                if ((element.range.start.row === parameter.range.start.row) &&
                                (element.range.start.column > parameter.range.start.column)) {
                                    return true;
                                }

                                return false;
                            }

                            return true;
                        }));
                    });
                });
            }

            this.variableDeclarations = this.makeUnique(variableDeclarations);

            parameters = this.makeUnique(parameters);

            // Grab the variable types of the parameters.
            const promises = [];

            parameters.forEach(parameter => {
                // Removing $this from parameters as this doesn't need to be passed in.
                if (parameter.name === '$this') { return; }

                return promises.push(this.getTypesForParameter(editor, parameter));
            });

            const returnFirstResultHandler = resultArray => resultArray[0];

            return Promise.all([Promise.all(promises), Promise.all(getTypePromises)]).then(returnFirstResultHandler);
        }

        /**
         * Takes the current buffer position and returns a range of the current
         * scope that the buffer position is in.
         *
         * For example this could be the code within an if statement or closure.
         *
         * @param  {TextEditor} editor
         * @param  {Point}      bufferPosition
         *
         * @return {Range}
        */
        getRangeForCurrentScope(editor, bufferPosition) {
            let descriptions, i, indexOfDescriptor, line, row;
            let asc;
            let asc2, end;
            let startScopePoint = null;
            let endScopePoint = null;

            // Tracks any extra scopes that might exist inside the scope we are
            // looking for.
            let childScopes = 0;

            // First walk back until we find the start of the current scope.
            for (({ row } = bufferPosition), asc = bufferPosition.row <= 0; asc ? row <= 0 : row >= 0; asc ? row++ : row--) {
                var asc1;
                line = editor.lineTextForBufferRow(row);

                if (!line) { continue; }

                const lastIndex = line.length - 1;

                for (i = lastIndex, asc1 = lastIndex <= 0; asc1 ? i <= 0 : i >= 0; asc1 ? i++ : i--) {
                    descriptions = editor.scopeDescriptorForBufferPosition(
                        [row, i]
                    );

                    indexOfDescriptor = descriptions.scopes.indexOf('punctuation.section.scope.end.php');
                    if (indexOfDescriptor > -1) {
                        childScopes++;
                    }

                    indexOfDescriptor = descriptions.scopes.indexOf('punctuation.section.scope.begin.php');
                    if (indexOfDescriptor > -1) {
                        childScopes--;

                        if (childScopes === -1) {
                            startScopePoint = new Point(row, 0);
                            break;
                        }
                    }
                }

                if (startScopePoint != null) { break; }
            }

            if (startScopePoint === null) {
                startScopePoint = new Point(0, 0);
            }

            childScopes = 0;

            // Walk forward until we find the end of the current scope
            for (({ row } = startScopePoint), end = editor.getLineCount(), asc2 = startScopePoint.row <= end; asc2 ? row <= end : row >= end; asc2 ? row++ : row--) {
                var asc3, end1;
                line = editor.lineTextForBufferRow(row);

                if (!line) { continue; }

                let startIndex = 0;

                if (startScopePoint.row === row) {
                    startIndex = line.length - 1;
                }

                for (i = startIndex, end1 = line.length - 1, asc3 = startIndex <= end1; asc3 ? i <= end1 : i >= end1; asc3 ? i++ : i--) {
                    descriptions = editor.scopeDescriptorForBufferPosition(
                        [row, i]
                    );

                    indexOfDescriptor = descriptions.scopes.indexOf('punctuation.section.scope.begin.php');
                    if (indexOfDescriptor > -1) {
                        childScopes++;
                    }

                    indexOfDescriptor = descriptions.scopes.indexOf('punctuation.section.scope.end.php');
                    if (indexOfDescriptor > -1) {
                        if (childScopes > 0) {
                            childScopes--;
                        }

                        if (childScopes === 0) {
                            endScopePoint = new Point(row, i + 1);
                            break;
                        }
                    }
                }

                if (endScopePoint != null) { break; }
            }

            return new Range(startScopePoint, endScopePoint);
        }

        /**
         * Takes an array of parameters and removes any parameters that appear more
         * that once with the same name.
         *
         * @param  {Array} array
         *
         * @return {Array}
        */
        makeUnique(array) {
            return array.filter(function(filterItem, pos, self) {
                for (let i = 0, end = self.length - 1, asc = 0 <= end; asc ? i <= end : i >= end; asc ? i++ : i--) {
                    if (self[i].name !== filterItem.name) {
                        continue;
                    }

                    return pos === i;
                }
                return true;
            });
        }
        /**
         * Generates the key used to store the parameters in the cache.
         *
         * @param  {TextEditor} editor
         * @param  {Range}      selectedBufferRange
         *
         * @return {String}
        */
        buildKey(editor, selectedBufferRange) {
            return editor.getPath() + JSON.stringify(selectedBufferRange);
        }

        /**
         * Gets the type for the parameter given.
         *
         * @param  {TextEditor} editor
         * @param  {Object}     parameter
         *
         * @return {Promise}
        */
        getTypesForParameter(editor, parameter) {
            const successHandler = types => {
                parameter.types = types;

                const typeResolutionPromises = [];
                const path = editor.getPath();

                const localizeTypeSuccessHandler = localizedType => {
                    return localizedType;
                };

                const localizeTypeFailureHandler = () => null;

                for (const fqcn of parameter.types) {
                    if (this.typeHelper.isClassType(fqcn)) {
                        const typeResolutionPromise = this.service.localizeType(
                            path,
                            this.selectedBufferRange.end.row + 1,
                            fqcn
                        );

                        typeResolutionPromises.push(typeResolutionPromise.then(
                            localizeTypeSuccessHandler,
                            localizeTypeFailureHandler
                        )
                        );

                    } else {
                        typeResolutionPromises.push(Promise.resolve(fqcn));
                    }
                }

                const combineResolvedTypesHandler = function(processedTypeArray) {
                    parameter.types = processedTypeArray;

                    return parameter;
                };

                return Promise.all(typeResolutionPromises).then(
                    combineResolvedTypesHandler,
                    combineResolvedTypesHandler
                );
            };

            const failureHandler = () => {
                return null;
            };

            return this.service.deduceTypesAt(parameter.name, editor, this.selectedBufferRange.end).then(
                successHandler,
                failureHandler
            );
        }

        /**
         * Returns all the variable declarations that have been parsed.
         *
         * @return {Array}
        */
        getVariableDeclarations() {
            return this.variableDeclarations;
        }

        /**
         * Clean up any data from previous usage
        */
        cleanUp() {
            return this.variableDeclarations = [];
        }
    };
    ParameterParser.initClass();
    return ParameterParser;
})());

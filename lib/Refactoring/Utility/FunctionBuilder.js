/*
 * decaffeinate suggestions:
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let FunctionBuilder;
module.exports =

(FunctionBuilder = (function() {
    FunctionBuilder = class FunctionBuilder {
        static initClass() {
            /**
             * The access modifier (null if none).
            */
            this.prototype.accessModifier = null;

            /**
             * Whether the method is static or not.
            */
            this.prototype.isStatic = false;

            /**
             * Whether the method is abstract or not.
            */
            this.prototype.isAbstract = null;

            /**
             * The name of the function.
            */
            this.prototype.name = null;

            /**
             * The return type of the function. This could be set when generating PHP >= 7 methods.
            */
            this.prototype.returnType = null;

            /**
             * The parameters of the function (a list of objects).
            */
            this.prototype.parameters = null;

            /**
             * A list of statements to place in the body of the function.
            */
            this.prototype.statements = null;

            /**
             * The tab text to insert on each line.
            */
            this.prototype.tabText = '';

            /**
             * The indentation level.
            */
            this.prototype.indentationLevel = null;

            /**
             * The indentation level.
             *
             * @var {Number|null}
            */
            this.prototype.maxLineLength = null;
        }

        /**
         * Constructor.
        */
        constructor() {
            this.build = this.build.bind(this);
            this.parameters = [];
            this.statements = [];
        }

        /**
         * Makes the method public.
         *
         * @return {FunctionBuilder}
        */
        makePublic() {
            this.accessModifier = 'public';
            return this;
        }

        /**
         * Makes the method private.
         *
         * @return {FunctionBuilder}
        */
        makePrivate() {
            this.accessModifier = 'private';
            return this;
        }

        /**
         * Makes the method protected.
         *
         * @return {FunctionBuilder}
        */
        makeProtected() {
            this.accessModifier = 'protected';
            return this;
        }

        /**
         * Makes the method global (i.e. no access modifier is added).
         *
         * @return {FunctionBuilder}
        */
        makeGlobal() {
            this.accessModifier = null;
            return this;
        }

        /**
         * Sets whether the method is static or not.
         *
         * @param {bool} isStatic
         *
         * @return {FunctionBuilder}
        */
        setIsStatic(isStatic) {
            this.isStatic = isStatic;
            return this;
        }

        /**
         * Sets whether the method is abstract or not.
         *
         * @param {bool} isAbstract
         *
         * @return {FunctionBuilder}
        */
        setIsAbstract(isAbstract) {
            this.isAbstract = isAbstract;
            return this;
        }

        /**
         * Sets the name of the function.
         *
         * @param {String} name
         *
         * @return {FunctionBuilder}
        */
        setName(name) {
            this.name = name;
            return this;
        }

        /**
         * Sets the return type.
         *
         * @param {String|null} returnType
         *
         * @return {FunctionBuilder}
        */
        setReturnType(returnType) {
            this.returnType = returnType;
            return this;
        }

        /**
         * Sets the parameters to add.
         *
         * @param {Array} parameters
         *
         * @return {FunctionBuilder}
        */
        setParameters(parameters) {
            this.parameters = parameters;
            return this;
        }

        /**
         * Adds a parameter to the parameter list.
         *
         * @param {Object} parameter
         *
         * @return {FunctionBuilder}
        */
        addParameter(parameter) {
            this.parameters.push(parameter);
            return this;
        }

        /**
         * Sets the statements to add.
         *
         * @param {Array} statements
         *
         * @return {FunctionBuilder}
        */
        setStatements(statements) {
            this.statements = statements;
            return this;
        }

        /**
         * Adds a statement to the body of the function.
         *
         * @param {String} statement
         *
         * @return {FunctionBuilder}
        */
        addStatement(statement) {
            this.statements.push(statement);
            return this;
        }

        /**
         * Sets the tab text to prepend to each line.
         *
         * @param {String} tabText
         *
         * @return {FunctionBuilder}
        */
        setTabText(tabText) {
            this.tabText = tabText;
            return this;
        }

        /**
         * Sets the indentation level to use. The tab text is repeated this many times for each line.
         *
         * @param {Number} indentationLevel
         *
         * @return {FunctionBuilder}
        */
        setIndentationLevel(indentationLevel) {
            this.indentationLevel = indentationLevel;
            return this;
        }

        /**
         * Sets the maximum length a single line may occupy. After this, text will wrap.
         *
         * This primarily influences parameter lists, which will automatically be split over multiple lines if the
         * parameter list would otherwise exceed the maximum length.
         *
         * @param {Number|null} maxLineLength The length or null to disable the maximum.
         *
         * @return {FunctionBuilder}
        */
        setMaxLineLength(maxLineLength) {
            this.maxLineLength = maxLineLength;
            return this;
        }

        /**
         * Sets the parameters of the builder based on raw method data from the base service.
         *
         * @param {Object} data
         *
         * @return {FunctionBuilder}
        */
        setFromRawMethodData(data) {
            if (data.isPublic) {
                this.makePublic();

            } else if (data.isProtected) {
                this.makeProtected();

            } else if (data.isPrivate) {
                this.makePrivate();

            } else {
                this.makeGlobal();
            }

            this.setName(data.name);
            this.setIsStatic(data.isStatic);
            this.setIsAbstract(data.isAbstract);
            this.setReturnType(data.returnTypeHint);

            const parameters = [];

            for (const parameter of data.parameters) {
                parameters.push({
                    name         : `$${parameter.name}`,
                    typeHint     : parameter.typeHint,
                    isVariadic   : parameter.isVariadic,
                    isReference  : parameter.isReference,
                    defaultValue : parameter.defaultValue
                });
            }

            this.setParameters(parameters);

            return this;
        }

        /**
         * Builds the method using the preconfigured settings.
         *
         * @return {String}
        */
        build() {
            let output = '';

            const signature = this.buildSignature(false);

            if ((this.maxLineLength != null) && (signature.length > this.maxLineLength)) {
                output += this.buildSignature(true);
                output += ' {\n';

            } else {
                output += signature + '\n';
                output += this.buildLine('{');
            }

            for (const statement of this.statements) {
                output += this.tabText + this.buildLine(statement);
            }

            output += this.buildLine('}');

            return output;
        }

        /**
         * @param {Boolean} isMultiLine
         *
         * @return {String}
        */
        buildSignature(isMultiLine) {
            let signatureLine = '';

            if (this.isAbstract) {
                signatureLine += 'abstract ';
            }

            if (this.accessModifier != null) {
                signatureLine += `${this.accessModifier} `;
            }

            if (this.isStatic) {
                signatureLine += 'static ';
            }

            signatureLine += `function ${this.name}(`;

            const parameters = [];

            for (const parameter of this.parameters) {
                let parameterText = '';

                if (parameter.typeHint != null) {
                    parameterText += `${parameter.typeHint} `;
                }

                if (parameter.isVariadic) {
                    parameterText += '...';
                }

                if (parameter.isReference) {
                    parameterText += '&';
                }

                parameterText += `${parameter.name}`;

                if (parameter.defaultValue != null) {
                    parameterText += ` = ${parameter.defaultValue}`;
                }

                parameters.push(parameterText);
            }

            if (!isMultiLine) {
                signatureLine += parameters.join(', ');
                signatureLine += ')';

                signatureLine = this.addTabText(signatureLine);

            } else {
                signatureLine = this.buildLine(signatureLine);

                for (let i in parameters) {
                    let parameter = parameters[i];

                    if (i < (parameters.length - 1)) {
                        parameter += ',';
                    }

                    signatureLine += this.buildLine(parameter, this.indentationLevel + 1);
                }

                signatureLine += this.addTabText(')');
            }

            if (this.returnType != null) {
                signatureLine += `: ${this.returnType}`;
            }

            return signatureLine;
        }

        /**
         * @param {String}      content
         * @param {Number|null} indentationLevel
         *
         * @return {String}
        */
        buildLine(content, indentationLevel = null) {
            return this.addTabText(content, indentationLevel) + '\n';
        }

        /**
         * @param {String}      content
         * @param {Number|null} indentationLevel
         *
         * @return {String}
        */
        addTabText(content, indentationLevel = null) {
            if ((indentationLevel == null)) {
                ({ indentationLevel } = this);
            }

            const tabText = this.tabText.repeat(indentationLevel);

            return `${tabText}${content}`;
        }
    };
    FunctionBuilder.initClass();
    return FunctionBuilder;
})());

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let DocblockBuilder;
module.exports =

(DocblockBuilder = class DocblockBuilder {
    /**
     * @param  {Array}       parameters
     * @param  {String|null} returnType
     * @param  {boolean}     generateDescriptionPlaceholders
     * @param  {String}      tabText
     *
     * @return {String}
    */
    constructor() {
        this.buildForMethod = this.buildForMethod.bind(this);
        this.buildForProperty = this.buildForProperty.bind(this);
        this.buildByLines = this.buildByLines.bind(this);
    }

    buildForMethod(parameters, returnType, generateDescriptionPlaceholders, tabText) {
        if (generateDescriptionPlaceholders == null) { generateDescriptionPlaceholders = true; }
        if (tabText == null) { tabText = ''; }
        const lines = [];

        if (generateDescriptionPlaceholders) {
            lines.push('[Short description of the method]');
        }

        if (parameters.length > 0) {
            let descriptionPlaceholder = '';

            if (generateDescriptionPlaceholders) {
                lines.push('');

                descriptionPlaceholder = ' [Description]';
            }

            // Determine the necessary padding.
            const parameterTypeLengths = parameters.map(function(item) {
                if (item.type) { return item.type.length; } else { return 0; }
            });

            const parameterNameLengths = parameters.map(function(item) {
                if (item.name) { return item.name.length; } else { return 0; }
            });

            const longestTypeLength = Math.max(...parameterTypeLengths);
            const longestNameLength = Math.max(...parameterNameLengths);

            // Generate parameter lines.
            for (const parameter of parameters) {
                const typePadding     = longestTypeLength - parameter.type.length;
                const variablePadding = longestNameLength - parameter.name.length;

                const type     = parameter.type + ' '.repeat(typePadding);
                const variable = parameter.name + ' '.repeat(variablePadding);

                lines.push(`@param ${type} ${variable}${descriptionPlaceholder}`);
            }
        }

        if ((returnType != null) && (returnType !== 'void')) {
            if (generateDescriptionPlaceholders || (parameters.length > 0)) {
                lines.push('');
            }

            lines.push(`@return ${returnType}`);
        }

        return this.buildByLines(lines, tabText);
    }

    /**
     * @param  {String|null} type
     * @param  {boolean}     generateDescriptionPlaceholders
     * @param  {String}      tabText
     *
     * @return {String}
    */
    buildForProperty(type, generateDescriptionPlaceholders, tabText) {
        if (generateDescriptionPlaceholders == null) { generateDescriptionPlaceholders = true; }
        if (tabText == null) { tabText = ''; }
        const lines = [];

        if (generateDescriptionPlaceholders) {
            lines.push('[Short description of the property]');
            lines.push('');
        }

        lines.push(`@var ${type}`);

        return this.buildByLines(lines, tabText);
    }

    /**
     * @param  {Array}  lines
     * @param  {String} tabText
     *
     * @return {String}
    */
    buildByLines(lines, tabText) {
        if (tabText == null) { tabText = ''; }
        let docs = this.buildLine('/**', tabText);

        if (lines.length === 0) {
            // Ensure we always have at least one line.
            lines.push('');
        }

        for (const line of lines) {
            docs += this.buildDocblockLine(line, tabText);
        }

        docs += this.buildLine(' */', tabText);

        return docs;
    }

    /**
     * @param {String} content
     * @param {String} tabText
     *
     * @return {String}
    */
    buildDocblockLine(content, tabText) {
        if (tabText == null) { tabText = ''; }
        content = ` * ${content}`;

        return this.buildLine(content.trimRight(), tabText);
    }

    /**
     * @param {String}  content
     * @param {String}  tabText
     *
     * @return {String}
    */
    buildLine(content, tabText) {
        if (tabText == null) { tabText = ''; }
        return `${tabText}${content}\n`;
    }
});

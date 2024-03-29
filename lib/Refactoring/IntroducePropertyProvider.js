/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
const {Point} = require('atom');

const AbstractProvider = require('./AbstractProvider');

module.exports =

//#*
// Provides property generation for non-existent properties.
//#
class IntroducePropertyProvider extends AbstractProvider {
    /**
     * @param {Object} docblockBuilder
    */
    constructor(docblockBuilder) {
        super();

        /**
         * The docblock builder.
        */
        this.docblockBuilder = docblockBuilder;
    }

    /**
     * @inheritdoc
    */
    getIntentionProviders() {
        return [{
            grammarScopes: ['variable.other.property.php'],
            getIntentions: ({textEditor, bufferPosition}) => {
                const nameRange = textEditor.bufferRangeForScopeAtCursor('variable.other.property');

                if ((nameRange == null)) { return []; }
                if ((this.getCurrentProjectPhpVersion() == null)) { return []; }

                const name = textEditor.getTextInBufferRange(nameRange);

                return this.getIntentions(textEditor, bufferPosition, name);
            }
        }];
    }

    /**
     * @param {TextEditor} editor
     * @param {Point}      triggerPosition
     * @param {String}     name
    */
    getIntentions(editor, triggerPosition, name) {
        const failureHandler = () => {
            return [];
        };

        const successHandler = currentClassName => {
            if ((currentClassName == null)) { return []; }

            const nestedSuccessHandler = classInfo => {
                const intentions = [];

                if (!classInfo) { return intentions; }

                if (!(name in classInfo.properties)) {
                    intentions.push({
                        priority : 100,
                        icon     : 'gear',
                        title    : 'Introduce New Property',

                        selected : () => {
                            return this.introducePropertyFor(editor, classInfo, name);
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
     * @param {Object}     classData
     * @param {String}     name
    */
    introducePropertyFor(editor, classData, name) {
        const indentationLevel = editor.indentationForBufferRow(classData.range.start.line) + 1;

        const tabText = editor.getTabText().repeat(indentationLevel);

        const docblock = this.docblockBuilder.buildForProperty(
            'mixed',
            false,
            tabText
        );

        const property = `${tabText}protected $${name};\n\n`;

        const point = this.findLocationToInsertProperty(editor, classData);

        return editor.getBuffer().insert(point, docblock + property);
    }


    /**
     * @param {TextEditor} editor
     * @param {Object}     classData
     *
     * @return {Point}
    */
    findLocationToInsertProperty(editor, classData) {
        let startLine = null;

        // Try to place the new property underneath the existing properties.
        for (let name in classData.properties) {
            const propertyData = classData.properties[name];
            if (propertyData.declaringStructure.name === classData.name) {
                startLine = propertyData.range.end.line + 2;
            }
        }

        if ((startLine == null)) {
            // Ensure we don't end up somewhere in the middle of the class definition if it spans multiple lines.
            const lineCount = editor.getLineCount();

            for (
                let line = (classData.range.start.line + 1),
                    end = lineCount,
                    asc = (classData.range.start.line + 1) <= end;
                asc ? line <= end : line >= end;
                asc ? line++ : line--
            ) {
                const lineText = editor.lineTextForBufferRow(line);

                if ((lineText == null)) { continue; }

                for (
                    let i = 0, end1 = lineText.length - 1, asc1 = 0 <= end1;
                    asc1 ? i <= end1 : i >= end1;
                    asc1 ? i++ : i--
                ) {
                    if (lineText[i] === '{') {
                        startLine = line + 1;
                        break;
                    }
                }

                if (startLine != null) { break; }
            }
        }

        if ((startLine == null)) {
            startLine = classData.range.start.line + 2;
        }

        return new Point(startLine, -1);
    }
};

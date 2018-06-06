/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS104: Avoid inline assignments
 * DS202: Simplify dynamic range loops
 * DS205: Consider reworking code to avoid use of IIFEs
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let UseStatementHelper;
module.exports =

//#*
// Contains convenience methods for dealing with use statements.
//#
(UseStatementHelper = (function() {
    UseStatementHelper = class UseStatementHelper {
        static initClass() {
            /**
             * Regular expression that will search for a structure (class, interface, trait, ...).
             *
             * @var {RegExp}
            */
            this.prototype.structureStartRegex  = /(?:abstract class|class|trait|interface)\s+(\w+)/;

            /**
             * Regular expression that will search for a use statement.
             *
             * @var {RegExp}
            */
            this.prototype.useStatementRegex    = /(?:use)(?:[^\w\\])([\w\\]+)(?![\w\\])(?:(?:[ ]+as[ ]+)(\w+))?(?:;)/;

            /**
             * Whether to allow adding additional newlines to attempt to group use statements.
             *
             * @var {Boolean}
            */
            this.prototype.allowAdditionalNewlines  = true;
        }

        /**
         * @param {Boolean} allowAdditionalNewlines
        */
        constructor(allowAdditionalNewlines) {
            this.allowAdditionalNewlines = allowAdditionalNewlines;
        }

        /**
         * @param {Boolean} allowAdditionalNewlines
        */
        setAllowAdditionalNewlines(allowAdditionalNewlines) {
            this.allowAdditionalNewlines = allowAdditionalNewlines;
        }

        /**
         * Add the use for the given class if not already added.
         *
         * @param {TextEditor} editor    Atom text editor.
         * @param {String}     className Name of the class to add.
         *
         * @return {Number} The amount of lines added (including newlines), so you can reliably and easily offset your
         *                  rows. This could be zero if a use statement was already present.
        */
        addUseClass(editor, className) {
            let i, line, matches, scopeDescriptor;
            let asc, end;
            let asc1, end1;
            let bestUseRow = 0;
            let placeBelow = true;
            let doNewLine = true;
            const lineCount = editor.getLineCount();
            let previousMatchThatSharedNamespacePrefixRow = null;

            // First see if the use statement is already present. The next loop stops early (and can't do this).
            for (i = 0, end = lineCount - 1, asc = 0 <= end; asc ? i <= end : i >= end; asc ? i++ : i--) {
                line = editor.lineTextForBufferRow(i).trim();

                if (line.length === 0) { continue; }

                scopeDescriptor = editor.scopeDescriptorForBufferPosition([i, line.length]).getScopeChain();

                if (scopeDescriptor.indexOf('.comment') >= 0) {
                    continue;
                }

                if (line.match(this.structureStartRegex)) { break; }

                if (matches = this.useStatementRegex.exec(line)) {
                    if ((matches[1] === className) || ((matches[1][0] === '\\') &&
                        (matches[1].substr(1) === className))) {
                        return 0;
                    }
                }
            }

            // Determine an appropriate location to place the use statement.
            for (i = 0, end1 = lineCount - 1, asc1 = 0 <= end1; asc1 ? i <= end1 : i >= end1; asc1 ? i++ : i--) {
                line = editor.lineTextForBufferRow(i).trim();

                if (line.length === 0) { continue; }

                scopeDescriptor = editor.scopeDescriptorForBufferPosition([i, line.length]).getScopeChain();

                if (scopeDescriptor.indexOf('.comment') >= 0) {
                    continue;
                }

                if (line.match(this.structureStartRegex)) { break; }

                if (line.indexOf('namespace ') >= 0) {
                    bestUseRow = i;
                }

                if (matches = this.useStatementRegex.exec(line)) {
                    bestUseRow = i;

                    placeBelow = true;
                    const shareCommonNamespacePrefix = this.doShareCommonNamespacePrefix(className, matches[1]);

                    doNewLine = !shareCommonNamespacePrefix;

                    if (this.scoreClassName(className, matches[1]) <= 0) {
                        placeBelow = false;

                        // Normally we keep going until the sorting indicates we should stop, and then place the use
                        // statement above the 'incorrect' match, but if the previous use statement was a use statement
                        // that has the same namespace, we want to ensure we stick close to it instead of creating
                        // additional newlines (which the item from the same namespace already placed).
                        if (previousMatchThatSharedNamespacePrefixRow != null) {
                            placeBelow = true;
                            doNewLine = false;
                            bestUseRow = previousMatchThatSharedNamespacePrefixRow;
                        }

                        break;
                    }

                    previousMatchThatSharedNamespacePrefixRow = shareCommonNamespacePrefix ? i : null;
                }
            }

            // Insert the use statement itself.
            let lineEnding = editor.getBuffer().lineEndingForRow(0);

            if (!this.allowAdditionalNewlines) {
                doNewLine = false;
            }

            if (!lineEnding) {
                lineEnding = '\n';
            }

            let textToInsert = '';

            if (doNewLine && placeBelow) {
                textToInsert += lineEnding;
            }

            textToInsert += `use ${className};` + lineEnding;

            if (doNewLine && !placeBelow) {
                textToInsert += lineEnding;
            }

            const lineToInsertAt = bestUseRow + (placeBelow ? 1 : 0);
            editor.setTextInBufferRange([[lineToInsertAt, 0], [lineToInsertAt, 0]], textToInsert);

            return (1 + (doNewLine ? 1 : 0));
        }

        /**
         * Returns a boolean indicating if the specified class names share a common namespace prefix.
         *
         * @param {String} firstClassName
         * @param {String} secondClassName
         *
         * @return {Boolean}
        */
        doShareCommonNamespacePrefix(firstClassName, secondClassName) {
            const firstClassNameParts = firstClassName.split('\\');
            const secondClassNameParts = secondClassName.split('\\');

            firstClassNameParts.pop();
            secondClassNameParts.pop();

            if (firstClassNameParts.join('\\') === secondClassNameParts.join('\\')) {
                return true;
            }

            return false;
        }

        /**
         * Scores the first class name against the second, indicating how much they 'match' each other. This can be used
         * to e.g. find an appropriate location to place a class in an existing list of classes.
         *
         * @param {String} firstClassName
         * @param {String} secondClassName
         *
         * @return {Number} A floating point number that represents the score.
        */
        scoreClassName(firstClassName, secondClassName) {
            let maxLength = 0;

            const firstClassNameParts = firstClassName.split('\\');
            const secondClassNameParts = secondClassName.split('\\');

            maxLength = Math.min(firstClassNameParts.length, secondClassNameParts.length);

            const collator = new Intl.Collator;

            // At this point, both FQSEN's share a common namespace, e.g. A\B and A\B\C\D, or XMLElement and
            // XMLDocument. The one with the most namespace parts ends up last.
            if (firstClassNameParts.length < secondClassNameParts.length) {
                return -1;

            } else if (firstClassNameParts.length > secondClassNameParts.length) {
                return 1;
            }

            if (maxLength >= 2) {
                for (let i = 0, end = maxLength - 1, asc = 0 <= end; asc ? i <= end : i >= end; asc ? i++ : i--) {
                    if (firstClassNameParts[i] !== secondClassNameParts[i]) {
                        if (firstClassNameParts[i].length === secondClassNameParts[i].length) {
                            return collator.compare(firstClassNameParts[i], secondClassNameParts[i]);
                        }

                        return firstClassNameParts[i].length > secondClassNameParts[i].length ? 1 : -1;
                    }
                }
            }

            if (firstClassName.length === secondClassName.length) {
                return collator.compare(firstClassName, secondClassName);
            }

            // Both items have share the same namespace, sort from shortest to longest last word (class, interface,
            // ...).
            return firstClassName.length > secondClassName.length ? 1 : -1;
        }

        /**
         * Sorts the use statements in the specified file according to the same algorithm used by 'addUseClass'.
         *
         * @param {TextEditor} editor
        */
        sortUseStatements(editor) {
            let endLine = null;
            let startLine = null;
            const useStatements = [];

            for (let i = 0, end = editor.getLineCount(), asc = 0 <= end; asc ? i <= end : i >= end; asc ? i++ : i--) {
                var matches;
                const lineText = editor.lineTextForBufferRow(i);

                endLine = i;

                if (!lineText || (lineText.trim() === '')) {
                    continue;

                } else if (matches = this.useStatementRegex.exec(lineText)) {
                    if (!startLine) {
                        startLine = i;
                    }

                    let text = matches[1];

                    if (matches[2] != null) {
                        text += ` as ${matches[2]}`;
                    }

                    useStatements.push(text);

                // We still do the regex check here to prevent continuing when there are no use statements at all.
                } else if (startLine || this.structureStartRegex.test(lineText)) {
                    break;
                }
            }

            if (useStatements.length === 0) { return; }

            return editor.transact(() => {
                editor.setTextInBufferRange([[startLine, 0], [endLine, 0]], '');

                return (() => {
                    const result = [];
                    for (let useStatement of useStatements) {
                    // The leading slash is unnecessary, not recommended, and messes up sorting, take it out.
                        if (useStatement[0] === '\\') {
                            useStatement = useStatement.substr(1);
                        }

                        result.push(this.addUseClass(editor, useStatement, this.allowAdditionalNewlines));
                    }
                    return result;
                })();
            });
        }
    };
    UseStatementHelper.initClass();
    return UseStatementHelper;
})());

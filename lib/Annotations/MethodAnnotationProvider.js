/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS205: Consider reworking code to avoid use of IIFEs
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let MethodProvider;
const shell = require('shell');

const {Range} = require('atom');

const AbstractAnnotationProvider = require('./AbstractAnnotationProvider');

module.exports =

//#*
// Provides annotations for member methods that are overrides or interface implementations.
//#
(MethodProvider = class MethodProvider extends AbstractAnnotationProvider {
    /**
     * @inheritdoc
    */
    registerAnnotations(editor) {
        const path = editor.getPath();

        if (!path) { return null; }

        const successHandler = classInfo => {
            if (!classInfo) { return null; }

            return (() => {
                const result = [];
                for (let name in classInfo.methods) {
                    const method = classInfo.methods[name];
                    if (!method.override && ((method.implementations != null ? method.implementations.length : undefined) === 0)) { continue; }
                    if (method.declaringStructure.fqcn !== classInfo.fqcn) { continue; }

                    const range = new Range([method.startLine - 1, 0], [method.startLine, -1]);

                    result.push(this.placeAnnotation(editor, range, this.extractAnnotationInfo(method)));
                }
                return result;
            })();
        };

        const failureHandler = () => {};
        // Just do nothing.

        const getClassListHandler = classesInEditor => {
            const promises = [];

            for (let fqcn in classesInEditor) {
                const classInfo = classesInEditor[fqcn];
                promises.push(this.service.getClassInfo(fqcn).then(successHandler, failureHandler));
            }

            return Promise.all(promises);
        };

        return this.service.getClassListForFile(path).then(getClassListHandler, failureHandler);
    }

    /**
     * Fetches annotation info for the specified context.
     *
     * @param {Object} context
     *
     * @return {Object}
    */
    extractAnnotationInfo(context) {
        let extraData = null;
        let tooltipText = '';
        let lineNumberClass = '';

        if (context.override) {
            // NOTE: We deliberately show the declaring class here, not the structure (which could be a trait). However,
            // if the method is overriding a trait method from the *same* class, we show the trait name, as it would be
            // strange to put an annotation in "Foo" saying "Overrides method from Foo".
            let overriddenFromFqcn = context.override.declaringClass.fqcn;

            if (overriddenFromFqcn === context.declaringClass.fqcn) {
                overriddenFromFqcn = context.override.declaringStructure.fqcn;
            }

            extraData = context.override;

            if (!context.override.wasAbstract) {
                lineNumberClass = 'override';
                tooltipText = `Overrides method from ${overriddenFromFqcn}`;

            } else {
                lineNumberClass = 'abstract-override';
                tooltipText = `Implements abstract method from ${overriddenFromFqcn}`;
            }

        } else {
            // NOTE: We deliberately show the declaring class here, not the structure (which could be a trait).
            extraData = context.implementations[0];
            lineNumberClass = 'implementations';
            tooltipText = `Implements method for ${extraData.declaringStructure.fqcn}`;
        }

        extraData.fqcn = context.fqcn;

        return {
            lineNumberClass,
            tooltipText,
            extraData
        };
    }

    /**
     * @inheritdoc
    */
    handleMouseClick(event, editor, annotationInfo) {
        return atom.workspace.open(annotationInfo.extraData.declaringStructure.filename, {
            initialLine    : annotationInfo.extraData.declaringStructure.startLineMember - 1,
            searchAllPanes : true
        });
    }

    /**
     * @inheritdoc
    */
    removePopover() {
        if (this.attachedPopover) {
            this.attachedPopover.dispose();
            return this.attachedPopover = null;
        }
    }
});

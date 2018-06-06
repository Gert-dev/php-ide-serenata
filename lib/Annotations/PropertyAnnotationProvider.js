/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS205: Consider reworking code to avoid use of IIFEs
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let MethodProvider;
const {Range} = require('atom');

const AbstractAnnotationProvider = require('./AbstractAnnotationProvider');

module.exports =

//#*
// Provides annotations for member properties that are overrides.
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
                for (let name in classInfo.properties) {
                    const property = classInfo.properties[name];
                    if (!property.override) { continue; }
                    if (property.declaringStructure.fqcn !== classInfo.fqcn) { continue; }

                    const range = new Range([property.startLine - 1, 0], [property.startLine, -1]);

                    result.push(this.placeAnnotation(editor, range, this.extractAnnotationInfo(property)));
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
        // NOTE: We deliberately show the declaring class here, not the structure (which could be a trait). However,
        // if the method is overriding a trait method from the *same* class, we show the trait name, as it would be
        // strange to put an annotation in "Foo" saying "Overrides method from Foo".
        let overriddenFromFqcn = context.override.declaringClass.fqcn;

        if (overriddenFromFqcn === context.declaringClass.fqcn) {
            overriddenFromFqcn = context.override.declaringStructure.fqcn;
        }

        return {
            lineNumberClass : 'override',
            tooltipText     : `Overrides property from ${overriddenFromFqcn}`,
            extraData       : context.override
        };
    }

    /**
     * @inheritdoc
    */
    handleMouseClick(event, editor, annotationInfo) {
        // 'filename' can be false for overrides of members from PHP's built-in classes (e.g. Exception).
        if (annotationInfo.extraData.declaringStructure.filename) {
            return atom.workspace.open(annotationInfo.extraData.declaringStructure.filename, {
                initialLine    : annotationInfo.extraData.declaringStructure.startLineMember - 1,
                searchAllPanes : true
            });
        }
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

/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS103: Rewrite code to no longer use __guard__
 * DS205: Consider reworking code to avoid use of IIFEs
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let AbstractProvider;
module.exports =

//#*
// Base class for annotation providers.
//#
(AbstractProvider = (function() {
    AbstractProvider = class AbstractProvider {
        static initClass() {
            /**
             * List of markers that are present for each file.
             *
             * @var {Object}
            */
            this.prototype.markers = null;

            /**
             * A mapping of file names to a list of annotations that are inside the gutter.
             *
             * @var {Object}
            */
            this.prototype.annotations = null;

            /**
             * The service (that can be used to query the source code and contains utility methods).
            */
            this.prototype.service = null;
        }

        constructor() {
            // Constructer here because otherwise the object is shared between instances.
            this.markers  = {};
            this.annotations = {};
        }

        /**
         * Initializes this provider.
         *
         * @param {mixed} service
        */
        activate(service) {
            this.service = service;
            const dependentPackage = 'language-php';

            // It could be that the dependent package is already active, in that case we can continue immediately. If not,
            // we'll need to wait for the listener to be invoked
            if (atom.packages.isPackageActive(dependentPackage)) {
                this.doActualInitialization();
            }

            atom.packages.onDidActivatePackage(packageData => {
                if (packageData.name !== dependentPackage) { return; }

                return this.doActualInitialization();
            });

            return atom.packages.onDidDeactivatePackage(packageData => {
                if (packageData.name !== dependentPackage) { return; }

                return this.deactivate();
            });
        }

        /**
         * Does the actual initialization.
        */
        doActualInitialization() {
            atom.workspace.observeTextEditors(editor => {
                if (/text.html.php$/.test(editor.getGrammar().scopeName)) {
                    // Allow the active project to settle before registering for the first time.
                    return setTimeout(() => {
                        this.registerAnnotations(editor);
                        return this.registerEvents(editor);
                    }
                        , 100);
                }
            });

            // When you go back to only have one pane the events are lost, so need to re-register.
            atom.workspace.onDidDestroyPane(pane => {
                const panes = atom.workspace.getPanes();

                if (panes.length === 1) {
                    return this.registerEventsForPane(panes[0]);
                }
            });

            // Having to re-register events as when a new pane is created the old panes lose the events.
            atom.workspace.onDidAddPane(observedPane => {
                return (() => {
                    const result = [];

                    for (const pane of atom.workspace.getPanes()) {
                        if (pane !== observedPane) {
                            result.push(this.registerEventsForPane(pane));
                        } else {
                            result.push(undefined);
                        }
                    }

                    return result;
                })();
            });

            // Ensure annotations are updated.
            return this.service.onDidFinishIndexing(data => {
                const editor = this.findTextEditorByPath(data.path);

                if (editor != null) {
                    return this.rescan(editor);
                }
            });
        }

        /**
         * Retrieves the text editor that is managing the file with the specified path.
         *
         * @param {String} path
         *
         * @return {TextEditor|null}
        */
        findTextEditorByPath(path) {
            for (const textEditor of atom.workspace.getTextEditors()) {
                if (textEditor.getPath() === path) {
                    return textEditor;
                }
            }

            return null;
        }

        /**
         * Registers the necessary event handlers for the editors in the specified pane.
         *
         * @param {Pane} pane
        */
        registerEventsForPane(pane) {
            return (() => {
                const result = [];

                for (const paneItem of pane.items) {
                    if (atom.workspace.isTextEditor(paneItem)) {
                        if (/text.html.php$/.test(paneItem.getGrammar().scopeName)) {
                            result.push(this.registerEvents(paneItem));
                        } else {
                            result.push(undefined);
                        }
                    } else {
                        result.push(undefined);
                    }
                }

                return result;
            })();
        }

        /**
         * Deactives the provider.
        */
        deactivate() {
            return this.removeAnnotations();
        }

        /**
         * Registers the necessary event handlers.
         *
         * @param {TextEditor} editor TextEditor to register events to.
        */
        registerEvents(editor) {
            // Ticket #107 - Mouseout isn't generated until the mouse moves, even when scrolling (with the keyboard or
            // mouse). If the element goes out of the view in the meantime, its HTML element disappears, never removing
            // it.
            editor.onDidDestroy(() => {
                return this.removePopover();
            });

            editor.onDidStopChanging(() => {
                return this.removePopover();
            });

            const textEditorElement = atom.views.getView(editor);

            __guard__(textEditorElement.querySelector('.horizontal-scrollbar'), x => x.addEventListener('scroll', event => {
                return this.removePopover();
            }));

            __guard__(textEditorElement.querySelector('.vertical-scrollbar'), x1 => x1.addEventListener('scroll', event => {
                return this.removePopover();
            }));

            const gutterContainerElement = textEditorElement.querySelector('.gutter-container');

            const mouseOverHandler = event => {
                const annotation = this.getRelevantAnnotationForEvent(editor, event);

                if ((annotation == null)) { return; }

                return this.handleMouseOver(event, editor, annotation.annotationInfo);
            };

            const mouseOutHandler = event => {
                const annotation = this.getRelevantAnnotationForEvent(editor, event);

                if ((annotation == null)) { return; }

                return this.handleMouseOut(event, editor, annotation.annotationInfo);
            };

            const mouseDownHandler = event => {
                const annotation = this.getRelevantAnnotationForEvent(editor, event);

                if ((annotation == null)) { return; }

                // Don't collapse or expand the fold in the gutter, if there is any.
                event.stopPropagation();

                return this.handleMouseClick(event, editor, annotation.annotationInfo);
            };

            if (gutterContainerElement != null) {
                gutterContainerElement.addEventListener('mouseover', mouseOverHandler);
            }
            if (gutterContainerElement != null) {
                gutterContainerElement.addEventListener('mouseout', mouseOutHandler);
            }
            return (gutterContainerElement != null ? gutterContainerElement.addEventListener('mousedown', mouseDownHandler) : undefined);
        }


        /**
         * @param {TextEditor} editor
         * @param {Object} event
         *
         * @return {Object|null}
        */
        getRelevantAnnotationForEvent(editor, event) {
            if (event.target.className.indexOf('icon-right') !== -1) {
                const longTitle = editor.getLongTitle();

                const lineEventOccurredOn = parseInt(event.target.parentElement.dataset.bufferRow);

                if (longTitle in this.annotations) {
                    for (const annotation of this.annotations[longTitle]) {
                        if (annotation.line === lineEventOccurredOn) {
                            return annotation;
                        }
                    }
                }
            }

            return null;
        }

        /**
         * Registers the annotations.
         *
         * @param {TextEditor} editor The editor to search through.
         *
         * @return {Promise|null}
        */
        registerAnnotations(editor) {
            throw new Error('This method is abstract and must be implemented!');
        }

        /**
         * Places an annotation at the specified line and row text.
         *
         * @param {TextEditor} editor
         * @param {Range}      range
         * @param {Object}     annotationInfo
        */
        placeAnnotation(editor, range, annotationInfo) {
            const marker = editor.markBufferRange(range, {
                invalidate : 'touch'
            });

            const decoration = editor.decorateMarker(marker, {
                type: 'line-number',
                class: annotationInfo.lineNumberClass
            });

            const longTitle = editor.getLongTitle();

            if (!(longTitle in this.markers)) {
                this.markers[longTitle] = [];
            }

            this.markers[longTitle].push(marker);

            return this.registerAnnotationEventHandlers(editor, range.start.row, annotationInfo);
        }

        /**
         * Registers annotation event handlers for the specified row.
         *
         * @param {TextEditor} editor
         * @param {Number}     row
         * @param {Object}     annotationInfo
        */
        registerAnnotationEventHandlers(editor, row, annotationInfo) {
            const textEditorElement = atom.views.getView(editor);
            const gutterContainerElement = textEditorElement.querySelector('.gutter-container');

            return ((editor, gutterContainerElement, annotationInfo) => {
                const longTitle = editor.getLongTitle();

                if (!(longTitle in this.annotations)) {
                    this.annotations[longTitle] = [];
                }

                return this.annotations[longTitle].push({
                    line           : row,
                    annotationInfo
                });
            })(editor, gutterContainerElement, annotationInfo);
        }

        /**
         * Handles the mouse over event on an annotation.
         *
         * @param {Object}     event
         * @param {TextEditor} editor
         * @param {Object}     annotationInfo
        */
        handleMouseOver(event, editor, annotationInfo) {
            if (annotationInfo.tooltipText) {
                this.removePopover();

                this.attachedPopover = this.service.createAttachedPopover(event.target);
                this.attachedPopover.setText(annotationInfo.tooltipText);
                return this.attachedPopover.show();
            }
        }

        /**
         * Handles the mouse out event on an annotation.
         *
         * @param {Object}     event
         * @param {TextEditor} editor
         * @param {Object}     annotationInfo
        */
        handleMouseOut(event, editor, annotationInfo) {
            return this.removePopover();
        }

        /**
         * Handles the mouse click event on an annotation.
         *
         * @param {Object}     event
         * @param {TextEditor} editor
         * @param {Object}     annotationInfo
        */
        handleMouseClick(event, editor, annotationInfo) {}

        /**
         * Removes the existing popover, if any.
        */
        removePopover() {
            if (this.attachedPopover) {
                this.attachedPopover.dispose();
                return this.attachedPopover = null;
            }
        }

        /**
         * Removes any annotations that were created with the specified key.
         *
         * @param {String} key
        */
        removeAnnotationsByKey(key) {
            for (let i in this.markers[key]) {
                const marker = this.markers[key][i];
                marker.destroy();
            }

            this.markers[key] = [];
            return this.annotations[key] = [];
        }

        /**
         * Removes any annotations (across all editors).
        */
        removeAnnotations() {
            let markers;
            for (let key in this.markers) {
                markers = this.markers[key];
                this.removeAnnotationsByKey(key);
            }

            this.markers = {};
            return this.annotations = {};
        }

        /**
         * Rescans the editor, updating all annotations.
         *
         * @param {TextEditor} editor The editor to search through.
        */
        rescan(editor) {
            const key = editor.getLongTitle();
            const renamedKey = `tmp_${key}`;

            // We rename the markers and remove them afterwards to prevent flicker if the location of the marker does not
            // change.
            if (key in this.annotations) {
                this.annotations[renamedKey] = this.annotations[key];
                this.annotations[key] = [];
            }

            if (key in this.markers) {
                this.markers[renamedKey] = this.markers[key];
                this.markers[key] = [];
            }

            const result = this.registerAnnotations(editor);

            if (result != null) {
                return result.then(() => {
                    return this.removeAnnotationsByKey(renamedKey);
                });
            }
        }
    };
    AbstractProvider.initClass();
    return AbstractProvider;
})());

function __guard__(value, transform) {
    return (typeof value !== 'undefined' && value !== null) ? transform(value) : undefined;
}

/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let ExtractMethodView;
const {$, TextEditorView, View} = require('atom-space-pen-views');

const Parser = require('./Builder');

module.exports =

(ExtractMethodView = (function() {
    ExtractMethodView = class ExtractMethodView extends View {
        static initClass() {

            /**
             * The callback to invoke when the user confirms his selections.
             *
             * @type {Callback}
            */
            this.prototype.onDidConfirm   = null;

            /**
             * The callback to invoke when the user cancels the view.
             *
             * @type {Callback}
            */
            this.prototype.onDidCancel    = null;

            /**
             * Settings of how to generate new method that will be passed to the parser
             *
             * @type {Object}
            */
            this.prototype.settings       = null;

            /**
             * Builder to use when generating preview area
             *
             * @type {Builder}
            */
            this.prototype.builder        = null;
        }

        /**
         * Constructor.
         *
         * @param {Callback} onDidConfirm
         * @param {Callback} onDidCancel
        */
        constructor(onDidConfirm, onDidCancel = null) {
            super();

            this.onDidConfirm = onDidConfirm;
            this.onDidCancel = onDidCancel;

            this.settings = {
                generateDocs: true,
                methodName: '',
                visibility: 'private',
                tabs: false,
                arraySyntax: 'brackets',
                typeHinting: true,
                generateDescPlaceholders: false
            };
        }

        /**
         * Content to be displayed when this view is shown.
        */
        static content() {
            return this.div({class: 'php-ide-serenata-refactoring-extract-method'}, () => {
                this.div({outlet: 'methodNameForm'}, () => {
                    this.subview('methodNameEditor', new TextEditorView({mini:true, placeholderText: 'Enter a method name'}));
                    this.div({class: 'text-error error-message hide error-message--method-name'}, 'You must enter a method name!');
                    return this.div({class: 'settings-view'}, () => {
                        return this.div({class: 'section-body'}, () => {
                            this.div({class: 'control-group'}, () => {
                                return this.div({class: 'controls'}, () => {
                                    return this.label({class: 'control-label'}, () => {
                                        this.div({class: 'setting-title'}, 'Access Modifier');
                                        return this.select({outlet: 'accessMethodsInput', class: 'form-control'}, () => {
                                            this.option({value: 'public'}, 'Public');
                                            this.option({value: 'protected'}, 'Protected');
                                            return this.option({value: 'private', selected: 'selected'}, 'Private');
                                        });
                                    });
                                });
                            });
                            this.div({class: 'control-group'}, () => {
                                return this.label({class: 'control-label'}, () => {
                                    this.div({class: 'setting-title'}, 'Documentation');
                                    this.div({class: 'controls'}, () => {
                                        return this.div({class: 'checkbox'}, () => {
                                            return this.label(() => {
                                                this.input({outlet: 'generateDocInput', type: 'checkbox', checked: true});
                                                return this.div({class: 'setting-title'}, 'Generate documentation');
                                            });
                                        });
                                    });
                                    return this.div({class: 'controls generate-docs-control'}, () => {
                                        return this.div({class: 'checkbox'}, () => {
                                            return this.label(() => {
                                                this.input({outlet: 'generateDescPlaceholdersInput', type: 'checkbox'});
                                                return this.div({class: 'setting-title'}, 'Generate description placeholders');
                                            });
                                        });
                                    });
                                });
                            });
                            this.div({class: 'control-group'}, () => {
                                return this.label({class: 'control-label'}, () => {
                                    this.div({class: 'setting-title'}, 'Type hinting');
                                    return this.div({class: 'controls'}, () => {
                                        return this.div({class: 'checkbox'}, () => {
                                            return this.label(() => {
                                                this.input({outlet: 'generateTypeHints', type: 'checkbox', checked: true});
                                                return this.div({class: 'setting-title'}, 'Generate type hints');
                                            });
                                        });
                                    });
                                });
                            });
                            this.div({class: 'return-multiple-control control-group'}, () => {
                                return this.label({class: 'control-label'}, () => {
                                    this.div({class: 'setting-title'}, 'Method styling');
                                    return this.div({class: 'controls'}, () => {
                                        return this.div({class: 'checkbox'}, () => {
                                            return this.label(() => {
                                                this.input({outlet: 'arraySyntax', type: 'checkbox', checked: true});
                                                return this.div({class: 'setting-title'}, 'Use PHP 5.4 array syntax (square brackets)');
                                            });
                                        });
                                    });
                                });
                            });
                            return this.div({class: 'control-group'}, () => {
                                return this.div({class: 'controls'}, () => {
                                    return this.label({class: 'control-label'}, () => {
                                        this.div({class: 'setting-title'}, 'Preview');
                                        return this.div({class: 'preview-area'}, () => {
                                            return this.subview('previewArea', new TextEditorView(), {class: 'preview-area'});
                                        });
                                    });
                                });
                            });
                        });
                    });
                });
                return this.div({class: 'button-bar'}, () => {
                    this.button({class: 'btn btn-error inline-block-tight pull-left icon icon-circle-slash button--cancel'}, 'Cancel');
                    this.button({class: 'btn btn-success inline-block-tight pull-right icon icon-gear button--confirm'}, 'Extract');
                    return this.div({class: 'clear-float'});
                });
            });
        }

        /**
         * @inheritdoc
        */
        initialize() {
            atom.commands.add(this.element, {
                'core:confirm': event => {
                    this.confirm();
                    return event.stopPropagation();
                },
                'core:cancel': event => {
                    this.cancel();
                    return event.stopPropagation();
                }
            }
            );

            this.on('click', 'button', event => {
                if ($(event.target).hasClass('button--confirm')) { this.confirm(); }
                if ($(event.target).hasClass('button--cancel')) { return this.cancel(); }
            });

            this.methodNameEditor.getModel().onDidChange(() => {
                this.settings.methodName = this.methodNameEditor.getText();
                $('.php-ide-serenata-refactoring-extract-method .error-message--method-name').addClass('hide');

                return this.refreshPreviewArea();
            });

            $(this.accessMethodsInput[0]).change(event => {
                this.settings.visibility = $(event.target).val();
                return this.refreshPreviewArea();
            });

            $(this.generateDocInput[0]).change(event => {
                this.settings.generateDocs = !this.settings.generateDocs;
                if (this.settings.generateDocs === true) {
                    $('.php-ide-serenata-refactoring-extract-method .generate-docs-control').removeClass('hide');
                } else {
                    $('.php-ide-serenata-refactoring-extract-method .generate-docs-control').addClass('hide');
                }


                return this.refreshPreviewArea();
            });

            $(this.generateDescPlaceholdersInput[0]).change(event => {
                this.settings.generateDescPlaceholders = !this.settings.generateDescPlaceholders;
                return this.refreshPreviewArea();
            });

            $(this.generateTypeHints[0]).change(event => {
                this.settings.typeHinting = !this.settings.typeHinting;
                return this.refreshPreviewArea();
            });

            $(this.arraySyntax[0]).change(event => {
                if (this.settings.arraySyntax === 'word') {
                    this.settings.arraySyntax = 'brackets';
                } else {
                    this.settings.arraySyntax = 'word';
                }
                return this.refreshPreviewArea();
            });

            if (this.panel == null) { this.panel = atom.workspace.addModalPanel({item: this, visible: false}); }

            const previewAreaTextEditor = this.previewArea.getModel();
            previewAreaTextEditor.setGrammar(atom.grammars.grammarForScopeName('text.html.php'));

            this.on('click', document, event => {
                return event.stopPropagation();
            });

            return $(document).on('click', event => {
                if (this.panel != null ? this.panel.isVisible() : undefined) { return this.cancel(); }
            });
        }

        /**
         * Destroys the view and cleans up.
        */
        destroy() {
            this.panel.destroy();
            return this.panel = null;
        }

        /**
        * Shows the view and refreshes the preview area with the current settings.
        */
        present() {
            this.panel.show();
            this.methodNameEditor.focus();
            return this.methodNameEditor.setText('');
        }

        /**
         * Hides the panel.
        */
        hide() {
            if (this.panel != null) {
                this.panel.hide();
            }
            return this.restoreFocus();
        }

        /**
         * Called when the user confirms the extraction and will then call
         * onDidConfirm, if set.
        */
        confirm() {
            if (this.settings.methodName === '') {
                $('.php-ide-serenata-refactoring-extract-method .error-message--method-name').removeClass('hide');
                return false;
            }

            if (this.onDidConfirm) {
                this.onDidConfirm(this.getSettings());
            }

            return this.hide();
        }

        /**
         * Called when the user cancels the extraction and will then call
         * onDidCancel, if set.
        */
        cancel() {
            if (this.onDidCancel) {
                this.onDidCancel();
            }

            return this.hide();
        }

        /**
         * Updates the preview area using the current setttings.
        */
        refreshPreviewArea() {
            if (!this.panel.isVisible()) { return; }

            const successHandler = methodBody => {
                if (this.builder.hasReturnValues()) {
                    if (this.builder.hasMultipleReturnValues()) {
                        $('.php-ide-serenata-refactoring-extract-method .return-multiple-control').removeClass('hide');
                    } else {
                        $('.php-ide-serenata-refactoring-extract-method .return-multiple-control').addClass('hide');
                    }

                    $('.php-ide-serenata-refactoring-extract-method .return-control').removeClass('hide');
                } else {
                    $('.php-ide-serenata-refactoring-extract-method .return-control').addClass('hide');
                    $('.php-ide-serenata-refactoring-extract-method .return-multiple-control').addClass('hide');
                }

                return this.previewArea.getModel().getBuffer().setText(`<?php\n\n${methodBody}`);
            };

            const failureHandler = function() {};
            // Do nothing.

            return this.builder.buildMethod(this.getSettings()).then(successHandler, failureHandler);
        }

        /**
         * Stores the currently focused element so it can be returned focus after
         * this panel is hidden.
        */
        storeFocusedElement() {
            return this.previouslyFocusedElement = $(document.activeElement);
        }

        /**
         * Restores focus back to the element that was focused before this panel
         * was shown.
        */
        restoreFocus() {
            return (this.previouslyFocusedElement != null ? this.previouslyFocusedElement.focus() : undefined);
        }

        /**
         * Sets the builder to use when generating the preview area.
         *
         * @param {Builder} builder
        */
        setBuilder(builder) {
            return this.builder = builder;
        }

        /**
         * Gets the settings currently set
         *
         * @return {Object}
        */
        getSettings() {
            return this.settings;
        }
    };
    ExtractMethodView.initClass();
    return ExtractMethodView;
})());

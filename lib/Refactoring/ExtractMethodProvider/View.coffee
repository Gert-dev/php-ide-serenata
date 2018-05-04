{$, TextEditorView, View} = require 'atom-space-pen-views'

Parser = require('./Builder')

module.exports =

class ExtractMethodView extends View

    ###*
     * The callback to invoke when the user confirms his selections.
     *
     * @type {Callback}
    ###
    onDidConfirm  : null

    ###*
     * The callback to invoke when the user cancels the view.
     *
     * @type {Callback}
    ###
    onDidCancel   : null

    ###*
     * Settings of how to generate new method that will be passed to the parser
     *
     * @type {Object}
    ###
    settings      : null

    ###*
     * Builder to use when generating preview area
     *
     * @type {Builder}
    ###
    builder       : null

    ###*
     * Constructor.
     *
     * @param {Callback} onDidConfirm
     * @param {Callback} onDidCancel
    ###
    constructor: (@onDidConfirm, @onDidCancel = null) ->
        super()

        @settings = {
            generateDocs: true,
            methodName: '',
            visibility: 'protected',
            tabs: false,
            arraySyntax: 'brackets',
            typeHinting: true,
            generateDescPlaceholders: false
        }

    ###*
     * Content to be displayed when this view is shown.
    ###
    @content: ->
        @div class: 'php-integrator-refactoring-extract-method', =>
            @div outlet: 'methodNameForm', =>
                @subview 'methodNameEditor', new TextEditorView(mini:true, placeholderText: 'Enter a method name')
                @div class: 'text-error error-message hide error-message--method-name', 'You must enter a method name!'
                @div class: 'settings-view', =>
                    @div class: 'section-body', =>
                        @div class: 'control-group', =>
                            @div class: 'controls', =>
                                @label class: 'control-label', =>
                                    @div class: 'setting-title', 'Access Modifier'
                                    @select outlet: 'accessMethodsInput', class: 'form-control', =>
                                        @option value: 'public', 'Public'
                                        @option value: 'protected', selected: "selected", 'Protected'
                                        @option value: 'private', 'Private'
                        @div class: 'control-group', =>
                            @label class: 'control-label', =>
                                @div class: 'setting-title', 'Documentation'
                                @div class: 'controls', =>
                                        @div class: 'checkbox', =>
                                            @label =>
                                                @input outlet: 'generateDocInput', type: 'checkbox', checked: true
                                                @div class: 'setting-title', 'Generate documentation'
                                @div class: 'controls generate-docs-control', =>
                                    @div class: 'checkbox', =>
                                        @label =>
                                            @input outlet: 'generateDescPlaceholdersInput', type: 'checkbox'
                                            @div class: 'setting-title', 'Generate description placeholders'
                        @div class: 'control-group', =>
                            @label class: 'control-label', =>
                                @div class: 'setting-title', 'Type hinting'
                                @div class: 'controls', =>
                                    @div class: 'checkbox', =>
                                        @label =>
                                            @input outlet: 'generateTypeHints', type: 'checkbox', checked: true
                                            @div class: 'setting-title', 'Generate type hints'
                        @div class: 'return-multiple-control control-group', =>
                            @label class: 'control-label', =>
                                @div class: 'setting-title', 'Method styling'
                                @div class: 'controls', =>
                                    @div class: 'checkbox', =>
                                        @label =>
                                            @input outlet: 'arraySyntax', type: 'checkbox', checked: true
                                            @div class: 'setting-title', 'Use PHP 5.4 array syntax (square brackets)'
                        @div class: 'control-group', =>
                            @div class: 'controls', =>
                                @label class: 'control-label', =>
                                    @div class: 'setting-title', 'Preview'
                                    @div class: 'preview-area', =>
                                        @subview 'previewArea', new TextEditorView(), class: 'preview-area'
            @div class: 'button-bar', =>
                @button class: 'btn btn-error inline-block-tight pull-left icon icon-circle-slash button--cancel', 'Cancel'
                @button class: 'btn btn-success inline-block-tight pull-right icon icon-gear button--confirm', 'Extract'
                @div class: 'clear-float'

    ###*
     * @inheritdoc
    ###
    initialize: ->
        atom.commands.add @element,
            'core:confirm': (event) =>
                @confirm()
                event.stopPropagation()
            'core:cancel': (event) =>
                @cancel()
                event.stopPropagation()

        @on 'click', 'button', (event) =>
            @confirm()  if $(event.target).hasClass('button--confirm')
            @cancel()   if $(event.target).hasClass('button--cancel')

        @methodNameEditor.getModel().onDidChange () =>
            @settings.methodName = @methodNameEditor.getText()
            $('.php-integrator-refactoring-extract-method .error-message--method-name').addClass('hide');

            @refreshPreviewArea()

        $(@accessMethodsInput[0]).change (event) =>
            @settings.visibility = $(event.target).val()
            @refreshPreviewArea()

        $(@generateDocInput[0]).change (event) =>
            @settings.generateDocs = !@settings.generateDocs
            if @settings.generateDocs == true
                $('.php-integrator-refactoring-extract-method .generate-docs-control').removeClass('hide')
            else
                $('.php-integrator-refactoring-extract-method .generate-docs-control').addClass('hide')


            @refreshPreviewArea()

        $(@generateDescPlaceholdersInput[0]).change (event) =>
            @settings.generateDescPlaceholders = !@settings.generateDescPlaceholders
            @refreshPreviewArea()

        $(@generateTypeHints[0]).change (event) =>
            @settings.typeHinting = !@settings.typeHinting
            @refreshPreviewArea()

        $(@arraySyntax[0]).change (event) =>
            if @settings.arraySyntax == 'word'
                @settings.arraySyntax = 'brackets'
            else
                @settings.arraySyntax = 'word'
            @refreshPreviewArea()

        @panel ?= atom.workspace.addModalPanel(item: this, visible: false)

        previewAreaTextEditor = @previewArea.getModel()
        previewAreaTextEditor.setGrammar(atom.grammars.grammarForScopeName('text.html.php'))

        @on 'click', document, (event) =>
            event.stopPropagation()

        $(document).on 'click', (event) =>
            @cancel() if @panel?.isVisible()

    ###*
     * Destroys the view and cleans up.
    ###
    destroy: ->
        @panel.destroy()
        @panel = null

    ###*
    * Shows the view and refreshes the preview area with the current settings.
    ###
    present: ->
        @panel.show()
        @methodNameEditor.focus()
        @methodNameEditor.setText('')

    ###*
     * Hides the panel.
    ###
    hide: ->
        @panel?.hide()
        @restoreFocus()

    ###*
     * Called when the user confirms the extraction and will then call
     * onDidConfirm, if set.
    ###
    confirm: ->
        if @settings.methodName == ''
            $('.php-integrator-refactoring-extract-method .error-message--method-name').removeClass('hide');
            return false

        if @onDidConfirm
            @onDidConfirm(@getSettings())

        @hide()

    ###*
     * Called when the user cancels the extraction and will then call
     * onDidCancel, if set.
    ###
    cancel: ->
        if @onDidCancel
            @onDidCancel()

        @hide()

    ###*
     * Updates the preview area using the current setttings.
    ###
    refreshPreviewArea: ->
        return unless @panel.isVisible()

        successHandler = (methodBody) =>
            if @builder.hasReturnValues()
                if @builder.hasMultipleReturnValues()
                    $('.php-integrator-refactoring-extract-method .return-multiple-control').removeClass('hide')
                else
                    $('.php-integrator-refactoring-extract-method .return-multiple-control').addClass('hide')

                $('.php-integrator-refactoring-extract-method .return-control').removeClass('hide')
            else
                $('.php-integrator-refactoring-extract-method .return-control').addClass('hide')
                $('.php-integrator-refactoring-extract-method .return-multiple-control').addClass('hide')

            @previewArea.getModel().getBuffer().setText('<?php' + "\n\n" + methodBody)

        failureHandler = () ->
            # Do nothing.

        @builder.buildMethod(@getSettings()).then(successHandler, failureHandler)

    ###*
     * Stores the currently focused element so it can be returned focus after
     * this panel is hidden.
    ###
    storeFocusedElement: ->
        @previouslyFocusedElement = $(document.activeElement)

    ###*
     * Restores focus back to the element that was focused before this panel
     * was shown.
    ###
    restoreFocus: ->
        @previouslyFocusedElement?.focus()

    ###*
     * Sets the builder to use when generating the preview area.
     *
     * @param {Builder} builder
    ###
    setBuilder: (builder) ->
        @builder = builder

    ###*
     * Gets the settings currently set
     *
     * @return {Object}
    ###
    getSettings: ->
        return @settings

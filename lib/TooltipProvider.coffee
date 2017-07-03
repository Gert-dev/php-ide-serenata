{Disposable, CompositeDisposable} = require 'atom'

module.exports =

##*
# Provides tooltips.
##
class TooltipProvider
    ###*
     * The service (that can be used to query the source code and contains utility methods).
     *
     * @var {Object|null}
    ###
    service: null

    ###*
     * @var {Object|null}
    ###
    documentationPane: null

    ###*
     * @var {Promise|null}
    ###
    pendingDocumentationPanePromise: null

    ###*
     * @var {CompositeDisposable}
    ###
    disposables: null

    ###*
     * Initializes this provider.
     *
     * @param {mixed} service
    ###
    activate: (@service) ->
        dependentPackage = 'language-php'

        @disposables = new CompositeDisposable()

        # It could be that the dependent package is already active, in that case we can continue immediately. If not,
        # we'll need to wait for the listener to be invoked
        if atom.packages.isPackageActive(dependentPackage)
            @doActualInitialization()

        @disposables.add atom.packages.onDidActivatePackage (packageData) =>
            return if packageData.name != dependentPackage

            @doActualInitialization()

        @disposables.add atom.packages.onDidDeactivatePackage (packageData) =>
            return if packageData.name != dependentPackage

            @deactivate()

    ###*
     * Does the actual initialization.
    ###
    doActualInitialization: () ->
        @getDocumentationPane() # Ensure dock is always present.

    ###*
     * Deactives the provider.
    ###
    deactivate: () ->
        @getDocumentationPane().then (documentationPane) =>
            atom.workspace.paneForItem(documentationPane).destroyItem(documentationPane, true)

        @disposables.dispose()

    ###*
     * @return {Array}
    ###
    getIntentionProviders: () ->
        return [{
            grammarScopes: ['source.php']
            getIntentions: ({textEditor, bufferPosition}) =>
                return @getIntentions(textEditor, bufferPosition)
        }]

    ###*
     * @param {TextEditor} editor
     * @param {Point}      triggerPosition
     *
     * @return {Promise}
    ###
    getIntentions: (editor, triggerPosition) ->
        return [] if not @service.getCurrentProjectSettings()

        scopeChain = editor.scopeDescriptorForBufferPosition(triggerPosition).getScopeChain()

        return [] if scopeChain.length == 0

        # Skip whitespace and other noise
        return [] if scopeChain == '.text.html.php .meta.embedded.block.php .source.php'

        successHandler = (tooltip) =>
            return [] if not tooltip?

            return [{
                priority : 150
                icon     : 'book'
                title    : 'Show Documentation'

                selected : () =>
                    @setCurrentlyVisibleDocumentationTo(@formatTooltip(tooltip.contents))
                    @triggerDocumentationPane()
            }]

        failureHandler = () ->
            return []

        return @service.tooltipAt(editor, triggerPosition).then(successHandler, failureHandler)

    ###*
     * @param {String} tooltipContents
     *
     * @return {String}
    ###
    formatTooltip: (tooltipContents) ->
        marked = require 'marked'

        return @formatDocumentationContent(marked(tooltipContents))

    ###*
     * @param {String} documentationContent
     *
     * @return {String}
    ###
    formatDocumentationContent: (documentationContent) ->
        return '<div class="php-integrator-documentation-pane">' + documentationContent + '</div>'

    ###*
     * @param {String} documentationHtmlString
    ###
    setCurrentlyVisibleDocumentationTo: (documentationHtmlString) ->
        @getDocumentationPane().then (documentationPane) =>
            documentationPane.element.innerHTML = documentationHtmlString

    ###*
     *
    ###
    triggerDocumentationPane: () ->
        @getDocumentationPane().then (documentationPane) =>
            previouslyFocusedElement = document.activeElement

            atom.workspace.paneContainerForItem(documentationPane).activate()
            atom.workspace.paneForItem(documentationPane).activateItem(documentationPane)

            # Atom automatically moves focus to the dock when it is activated. As usually the user will just want
            # to see rudimentary information and doesn't need to scroll, we cater towards the most-used case and
            # automatically move focus back to the editor.
            previouslyFocusedElement.focus()

    ###*
     * @return {Promise}
    ###
    getDocumentationPane: () ->
        if @documentationPane?
            return new Promise (resolve, reject) =>
                resolve(@documentationPane)

        else if @pendingDocumentationPanePromise?
            return @pendingDocumentationPanePromise

        paneElement = document.createElement('div')
        paneElement.innerHTML = @formatDocumentationContent('''
            <div class="php-integrator-documentation-placeholder">
                <p><span class="icon icon-book"></span></p>

                <p>Documentation will be displayed here.</p>

                <p>
                    Activate <a href="https://github.com/steelbrain/intentions">intentions</a> on the desired element
                    and select the entry <strong>Show Documentation</strong>.
                </p>

                <p class="php-integrator-documentation-placeholder-note">
                    By default, intentions can be triggered via <span class="highlight">ctrl-enter</span> on Windows
                    and Linux and <span class="highlight">alt-enter</span> on macOS.
                </p>
            </div>
        ''')

        @pendingDocumentationPanePromise = atom.workspace.open({
            element: paneElement

            getTitle: () ->
                return 'PHP Documentation'

            getIconName: () ->
                return 'book'

            getURI: () ->
                return 'atom://php-integrator-base/php-documentation'

            getDefaultLocation: () ->
                return 'bottom'
        }, {
            activatePane: false
            activateItem: false
            searchAllPanes: true
        })

        return @pendingDocumentationPanePromise.then (documentationPane) =>
            disposable = atom.workspace.paneContainerForItem(documentationPane).onDidDestroyPaneItem (paneItem) =>
                if paneItem.item == documentationPane
                    @documentationPane = null
                    disposable.dispose()

            @pendingDocumentationPanePromise = null
            @documentationPane = documentationPane

            return @documentationPane

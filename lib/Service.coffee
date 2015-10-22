proxy   = require './proxy.coffee'
parser  = require './parser.coffee'
Utility = require './Utility.coffee'

Popover         = require './Widgets/Popover.coffee'
AttachedPopover = require './Widgets/AttachedPopover.coffee'

module.exports =

##*
# The service that is exposed to other packages.
##
class Service
    ###*
     * The progress bar that is used for long indexing operations.
    ###
    progressBar: null

    ###*
     * The config.
    ###
    config: null

    ###*
     * Constructor.
     *
     * @param {Config} config
    ###
    constructor: (@config) ->

    ###*
     * Activates the package.
    ###
    activate: ->
        @performFullIndex()

        atom.workspace.observeTextEditors (editor) =>
            editor.onDidSave (event) =>
                if editor.getGrammar().scopeName.match /text.html.php$/
                    proxy.clearCache()

                    # For Windows - Replace \ in class namespace to / because
                    # composer use / instead of \
                    path = event.path
                    for directory in atom.project.getDirectories()
                        if path.indexOf(directory.path) == 0
                            classPath = path.substr(0, directory.path.length+1)
                            path = path.substr(directory.path.length + 1)
                            break

                    proxy.refresh(classPath + Utility.normalizeSeparators(path))

        # TODO: This should be attaching to the config object, not directly to the atom config.
        atom.config.onDidChange 'php-integrator-base.phpCommand', () =>
            proxy.clearCache()

        atom.config.onDidChange 'php-integrator-base.composerCommand', () =>
            proxy.clearCache()

        atom.config.onDidChange 'php-integrator-base.autoloadScripts', () =>
            proxy.clearCache()

        atom.config.onDidChange 'php-integrator-base.classMapScripts', () =>
            proxy.clearCache()

    ###*
     * Sets the progress bar that can be used to display long operations.
    ###
    setProgressBar: (@progressBar) ->

    ###*
     * Performs a complete index of the current project.
    ###
    performFullIndex: () ->
        if @progressBar
            @progressBar.setLabel("Indexing...")
            @progressBar.show()

        proxy.refresh null, () =>
            if @progressBar
                @progressBar.hide()

    ###*
     * Deactivates the package.
    ###
    deactivate: ->

    ###*
     * Creates a popover with the specified constructor arguments.
    ###
    createPopover: () ->
        return new Popover(arguments...)

    ###*
     * Creates an attached popover with the specified constructor arguments.
    ###
    createAttachedPopover: () ->
        return new AttachedPopover(arguments...)

    ###*
     * Determines the full class name (without leading slash) of the specified class in the specified editor. If no
     * class name is passed, the full class name of the class defined in the current file is returned instead.
     *
     * @param {TextEditor}  editor    The editor that contains the class (needed to resolve relative class names).
     * @param {String|null} className The (local) name of the class to resolve.
     *
     * @example In a file with namespace A\B, determining C will lead to A\B\C.
    ###
    determineFullClassName: (editor, className = null) ->
        return parser.getFullClassName(editor, className)

    ###*
     * Retrieves a list of members the specified class has.
     *
     * @param {String} className The absolute and full path to the class, e.g. MyRoot\Foo\Bar.
     *
     * @return {Object}
    ###
    getClassMembers: (className) ->
        return proxy.methods(className)

    ###*
     * Gets the correct selector for the class or namespace that is part of the specified event.
     *
     * @param  {jQuery.Event}  event  A jQuery event.
     *
     * @return {object|null} A selector to be used with jQuery.
    ###
    getClassSelectorFromEvent: (event) ->
        return parser.getClassSelectorFromEvent(event)

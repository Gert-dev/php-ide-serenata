config = require './config.coffee'
proxy  = require './proxy.coffee'
parser = require './parser.coffee'

Popover  = require './widgets/popover.coffee'
AttachedPopover  = require './widgets/attached-popover.coffee'

module.exports =

class Service
    ###*
     * Activates the package.
    ###
    activate: ->
        proxy.init()

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

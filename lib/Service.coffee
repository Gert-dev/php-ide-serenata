Popover         = require './Widgets/Popover.coffee'
AttachedPopover = require './Widgets/AttachedPopover.coffee'

module.exports =

##*
# The service that is exposed to other packages.
##
class Service
    ###*
     * The proxy to use to contact the PHP side.
    ###
    proxy: null

    ###*
     * The parser to use to query the source code.
    ###
    parser: null

    ###*
     * Constructor.
     *
     * @param {CachingProxy} proxy
     * @param {Parser}       parser
    ###
    constructor: (@proxy, @parser) ->

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
     * Gets the correct selector for the class or namespace that is part of the specified event.
     *
     * @param  {jQuery.Event}  event  A jQuery event.
     *
     * @return {object|null} A selector to be used with jQuery.
    ###
    getClassSelectorFromEvent: (event) ->
        return @parser.getClassSelectorFromEvent(event)

    ###*
     * Clears the autocompletion cache. Most fetching operations such as fetching constants, autocompletion, fetching
     * members, etc. are cached when they are first retrieved. This clears the cache, forcing them to be retrieved
     # again. Clearing the cache is automatically performed, so this method is usually unnecessary.
    ###
    clearCache: () ->
        @proxy.clearCache()

    ###*
     * Retrieves a list of members the specified class has.
     *
     * @param {String} className The absolute and full path to the class, e.g. MyRoot\Foo\Bar.
     *
     * @return {Object}
    ###
    getConstants: () ->
        return @proxy.getConstants()

    ###*
     * Retrieves a list of available classes.
     *
     * @return {Object}
    ###
    getClassList: () ->
        return @proxy.getClassList()

    ###*
     * Retrieves a list of available global constants.
     *
     * @return {Object}
    ###
    getConstants: () ->
        return @proxy.getConstants()

    ###*
     * Retrieves a list of available global functions.
     *
     * @return {Object}
    ###
    getGlobalFunctions: () ->
        return @proxy.getGlobalFunctions()

    ###*
     * Retrieves a list of available members of the class (or interface, trait, ...) with the specified name.
     *
     * @param {string} className
     *
     * @return {Object}
    ###
    getClassMembers: (className) ->
        return @proxy.getClassMembers(className)

    ###*
     * Retrieves the members of the type that is returned by the member with the specified name in the specified class.
     * This is essentially the same as determining the return type of the method (or type of the member variable) with
     * the given name in the given class, and then calling {@see getMembers} for that type, hence autocompleting the
     * 'name' in 'className'.
     *
     * @param {string} className
     * @param {string} name
     *
     * @return {Object}
    ###
    autocomplete: (className, name) ->
        return @proxy.autocomplete(className, name)

    ###*
     * Returns information about parameters described in the docblock for the given method in the given class.
     *
     * @param {string} className
     * @param {string} name
     *
     * @return {Object}
    ###
    getDocParams: (className, name) ->
        return @proxy.getDocParams(className, name)

    ###*
     * Refreshes the specified file. If no file is specified, all files are refreshed (which can take a while for large
     * projects!). This method is asynchronous and will return immediately.
     *
     * @param {string}   filename The full path to the file to refresh.
     * @param {callback} callback The callback to invoke when the indexing process is finished.
    ###
    reindex: (filename, callback) ->
        @proxy.reindex(filename, callback)

    ###*
     * Determines the full class name (without leading slash) of the specified class in the specified editor. If no
     * class name is passed, the full class name of the class defined in the current file is returned instead.
     *
     * @param {TextEditor}  editor    The editor that contains the class (needed to resolve relative class names).
     * @param {String|null} className The (local) name of the class to resolve.
     *
     * @return {string|null}
     *
     * @example In a file with namespace A\B, determining C will lead to A\B\C.
    ###
    determineFullClassName: (editor, className = null) ->
        return @parser.determineFullClassName(editor, className)

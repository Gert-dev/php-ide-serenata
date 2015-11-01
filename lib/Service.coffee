Popover         = require './Widgets/Popover'
AttachedPopover = require './Widgets/AttachedPopover'

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
     * Clears the autocompletion cache. Most fetching operations such as fetching constants, autocompletion, fetching
     * members, etc. are cached when they are first retrieved. This clears the cache, forcing them to be retrieved
     # again. Clearing the cache is automatically performed, so this method is usually unnecessary.
    ###
    clearCache: () ->
        @proxy.clearCache()

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
    getGlobalConstants: () ->
        return @proxy.getGlobalConstants()

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
    getClassInfo: (className) ->
        return @proxy.getClassInfo(className)

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
     * Gets the correct selector for the class or namespace that is part of the specified event.
     *
     * @param  {jQuery.Event}  event  A jQuery event.
     *
     * @return {object|null} A selector to be used with jQuery.
    ###
    getClassSelectorFromEvent: (event) ->
        return @parser.getClassSelectorFromEvent(event)

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

    ###*
     * Retrieves all variables that are available at the specified buffer position.
     *
     * @param {TextEditor} editor
     * @param {Range}      bufferPosition
     *
     * @return {array}
    ###
    getAvailableVariables: (editor, bufferPosition) ->
        return @parser.getAvailableVariables(editor, bufferPosition)

    ###*
     * Retrieves the type of a variable, relative to the context at the specified buffer location. Class names will
     * be returned in their full form (full class name, but not necessarily with a leading slash).
     *
     * @param {TextEditor} editor
     * @param {Range}      bufferPosition
     * @param {string}     name
     *
     * @return {string|null}
    ###
    getVariableType: (editor, bufferPosition, name) ->
        return @parser.getVariableType(editor, bufferPosition, name)

    ###*
     * Retrieves contextual information about the class member at the specified location in the editor. This is
     * essentially the same as {@see getClassMember}, but will automatically determine the class based on the code at
     * the specified location.
     *
     * @param {TextEditor} editor         The text editor to use.
     * @param {Point}      bufferPosition The cursor location of the member.
     * @param {string}     name           The name of the member to retrieve information about.
     *
     * @return {Object|null}
    ###
    getClassMemberAt: (editor, bufferPosition, name) ->
        className = @getCalledClass(editor, bufferPosition)

        members = @getClassMember(className, name)

        # Methods and properties can share the same name, which one is being used depends on the context, so we have
        # to disambiguate in this case.
        if members.method and members.property
            if @parser.isFunctionCall(editor, bufferPosition)
                return members.method

            else
                return members.property

        return members.method if members.method
        return members.property if members.property
        return members.constant if members.constant

        return null

    ###*
     * Retrieves information about members of the specified class. Note that this always returns an object, as there may
     * be multiple members (e.g. methods and properties) sharing the same name. The object's properties are 'method',
     * 'property' and 'constant'.
     *
     * @param {string} className The full name of the class to examine.
     * @param {string} name      The name of the member to retrieve information about.
     *
     * @return {Object}
    ###
    getClassMember: (className, name) ->
        return {
            method   : @getClassMethod(className, name)
            property : @getClassProperty(className, name)
            constant : @getClassConstant(className, name)
        }

    ###*
     * Retrieves information about the specified method of the specified class.
     *
     * @param {string} className The full name of the class to examine.
     * @param {string} name      The name of the method to retrieve information about.
     *
     * @return {Object|null}
    ###
    getClassMethod: (className, name) ->
        classInfo = @proxy.getClassInfo(className)

        return if not classInfo or (classInfo.error? and classInfo.error != '')

        if name of classInfo.methods
            return classInfo.methods[name]

        return null

    ###*
     * Retrieves information about the specified property of the specified class.
     *
     * @param {string} className The full name of the class to examine.
     * @param {string} name      The name of the property to retrieve information about.
     *
     * @return {Object|null}
    ###
    getClassProperty: (className, name) ->
        classInfo = @proxy.getClassInfo(className)

        return if not classInfo or (classInfo.error? and classInfo.error != '')

        if name of classInfo.properties
            return classInfo.properties[name]

        return null

    ###*
     * Retrieves information about the specified constant of the specified class.
     *
     * @param {string} className The full name of the class to examine.
     * @param {string} name      The name of the constant to retrieve information about.
     *
     * @return {Object|null}
    ###
    getClassConstant: (className, name) ->
        classInfo = @proxy.getClassInfo(className)

        return if not classInfo or (classInfo.error? and classInfo.error != '')

        if name of classInfo.constants
            return classInfo.constants[name]

        return null

    ###*
     * Retrieves the class the specified member (method or property) is being invoked on.
     *
     * @param  {TextEditor} editor         The text editor to use.
     * @param  {Point}      bufferPosition The cursor location of the member, this should be at the operator :: or ->
     *                                      (but anywhere inside the name of the member itself is fine too).
     *
     * @return {string|null}
     *
     * @example Invoking it on MyMethod::foo()->bar() will ask what class 'bar' is invoked on, which will whatever type
     *          foo returns.
    ###
    getCalledClass: (editor, bufferPosition) ->
        callStack = @parser.retrieveSanitizedCallStackAt(editor, bufferPosition)

        return null if not callStack or callStack.length == 0

        return @parser.getClassNameFromCallStack(editor, bufferPosition, callStack)

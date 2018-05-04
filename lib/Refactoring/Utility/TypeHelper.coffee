module.exports =

class TypeHelper
    ###*
     * @var {Object|null} service
    ###
    service: null

    ###*
     * @param {Object} service
    ###
    setService: (@service) ->

    ###*
     * @return {Number}
    ###
    getCurrentProjectPhpVersion: () ->
        projectSettings = @service.getCurrentProjectSettings()

        if projectSettings?
            return projectSettings.phpVersion

        return 5.2 # Assume lowest supported version

    ###*
     * @param {String|null} typeSpecification
     *
     * @return {Object|null}
    ###
    getReturnTypeHintForTypeSpecification: (typeSpecification) ->
        return null if @getCurrentProjectPhpVersion() < 7.0

        returnTypeHint = @getTypeHintForTypeSpecification(typeSpecification)

        if not returnTypeHint? or returnTypeHint.shouldSetDefaultValueToNull
            return null

        return returnTypeHint.typeHint

    ###*
     * @param {String|null} typeSpecification
     *
     * @return {Object|null}
    ###
    getTypeHintForTypeSpecification: (typeSpecification) ->
        types = @getDocblockTypesFromDocblockTypeSpecification(typeSpecification)

        return @getTypeHintForDocblockTypes(types)

    ###*
     * @param {String|null} typeSpecification
     *
     * @return {Array}
    ###
    getDocblockTypesFromDocblockTypeSpecification: (typeSpecification) ->
        return [] if not typeSpecification?
        return typeSpecification.split('|')

    ###*
     * @param {Array}   types
     * @param {boolean} allowPhp7
     *
     * @return {Object|null}
    ###
    getTypeHintForDocblockTypes: (types) ->
        isNullable = false

        types = types.filter (type) =>
            if type == 'null'
                isNullable = true

            return type != 'null'

        typeHint = null
        previousTypeHint = null

        for type in types
            typeHint = @getTypeHintForDocblockType(type)

            if previousTypeHint? and typeHint != previousTypeHint
                # Several different type hints are necessary, we can't provide a common denominator.
                return null

            previousTypeHint = typeHint

        data = {
            typeHint                    : typeHint
            shouldSetDefaultValueToNull : false
        }

        return data if not typeHint?
        return data if not isNullable

        currentPhpVersion = @getCurrentProjectPhpVersion()

        if currentPhpVersion >= 7.1
            data.typeHint = '?' + typeHint
            data.shouldSetDefaultValueToNull = false

        else
            data.shouldSetDefaultValueToNull = true

        return data

    ###*
     * @param {String|null} type
     *
     * @return {String|null}
    ###
    getTypeHintForDocblockType: (type) ->
        return null if not type?
        return type if @isClassType(type)
        return @getScalarTypeHintForDocblockType(type)

    ###*
     * @param {String|null} type
     *
     * @return {boolean}
    ###
    isClassType: (type) ->
        return if (@getScalarTypeHintForDocblockType(type) == false) then true else false

    ###*
     * @param {String|null} type
     *
     * @return {String|null|false} Null if the type is recognized, but there is no type hint available, false of the
     *                             type is not recognized at all, and the type hint itself if it is recognized and there
     *                             is a type hint.
    ###
    getScalarTypeHintForDocblockType: (type) ->
        return null if not type?

        phpVersion = @getCurrentProjectPhpVersion()

        if phpVersion >= 7.1
            return 'iterable' if type == 'iterable'
            return 'void'     if type == 'void'

        else if phpVersion >= 7.0
            return 'string'   if type == 'string'
            return 'int'      if type == 'int'
            return 'bool'     if type == 'bool'
            return 'float'    if type == 'float'
            return 'resource' if type == 'resource'
            return 'bool'     if type == 'false'
            return 'bool'     if type == 'true'

        else
            return null       if type == 'string'
            return null       if type == 'int'
            return null       if type == 'bool'
            return null       if type == 'float'
            return null       if type == 'resource'
            return null       if type == 'false'
            return null       if type == 'true'

        return 'array'    if type == 'array'
        return 'callable' if type == 'callable'
        return 'self'     if type == 'self'
        return 'self'     if type == 'static'
        return 'array'    if /^.+\[\]$/.test(type)

        return null if type == 'object'
        return null if type == 'mixed'
        return null if type == 'void'
        return null if type == 'null'
        return null if type == 'parent'
        return null if type == '$this'

        return false

    ###*
     * Takes a type list (list of type objects) and turns them into a single docblock type specification.
     *
     * @param {Array} typeList
     *
     * @return {String}
    ###
    buildTypeSpecificationFromTypeArray: (typeList) ->
        typeNames = typeList.map (type) ->
            return type.type

        return @buildTypeSpecificationFromTypes(typeNames)

    ###*
     * Takes a list of type names and turns them into a single docblock type specification.
     *
     * @param {Array} typeNames
     *
     * @return {String}
    ###
    buildTypeSpecificationFromTypes: (typeNames) ->
        return 'mixed' if typeNames.length == 0

        return typeNames.join('|')

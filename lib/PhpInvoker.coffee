os = require "os"
child_process = require "child_process"

module.exports =

##*
# Invokes PHP.
##
class PhpInvoker
    ###*
     * Constructor.
     *
     * @param {Config} config
    ###
    constructor: (@config) ->

    ###*
     * Invokes PHP.
     *
     * NOTE: The composer:1.6.4 image uses the Alpine version of the "PHP 7.x" image of PHP, which at the time of
     # writing is PHP 7.2. The most important part is that the PHP version used for Composer installations is the same
     # as the one used for actually running the server to avoid outdated or too recent dependencies.
     *
     * @param {Array}  parameters
     * @param {Array}  additionalDockerRunParameters
     * @param {Object} options
     * @param {String} dockerImage
     *
     * @return {Process}
    ###
    invoke: (parameters, additionalDockerRunParameters = [], options = {}, dockerImage = 'composer:1.6.4') ->
        executionType = @config.get('core.phpExecutionType')

        if executionType == 'host'
            return child_process.spawn(@config.get('core.phpCommand'), parameters, options)

        command = 'docker'
        dockerParameters = @getDockerRunParameters(dockerImage, additionalDockerRunParameters)
        dockerParameters = dockerParameters.concat(parameters)

        if executionType == 'docker-polkit'
            dockerParameters = [command].concat(dockerParameters)
            command = 'pkexec'

        process = child_process.spawn(command, dockerParameters)

        console.debug(command, dockerParameters)

        # NOTE: Uncomment this to test failures
        process.stdout.on 'data', (data) =>
            console.debug('STDOUT', data.toString())

        process.stderr.on 'data', (data) =>
            console.debug('STDERR', data.toString())

        return process

    ###*
     * @param {Array}  additionalDockerRunParameters
     *
     * @return {Array}
    ###
    getDockerRunParameters: (dockerImage, additionalDockerRunParameters) ->
        parameters = ['run', '--rm=true']

        for src, dest of @getPathsToMountInDockerContainer()
            parameters.push('-v')
            parameters.push(src + ':' + dest)

        return parameters.concat(additionalDockerRunParameters).concat([dockerImage, 'php'])

    ###*
     * @return {Object}
    ###
    getPathsToMountInDockerContainer: () ->
        paths = {}
        paths[@config.get('storagePath')] = @config.get('storagePath')

        for path in @config.get('core.additionalDockerVolumes')
            parts = path.split(':')

            paths[parts[0]] = parts[1]

        for path in atom.project.getPaths()
            paths[path] = path

        return @normalizeVolumePaths(paths)

    ###*
     * @param {Object} pathMap
     *
     * @return {Object}
    ###
    normalizeVolumePaths: (pathMap) ->
        newPathMap = {}

        for src, dest of pathMap
            newPathMap[@normalizeVolumePath(src)] = @normalizeVolumePath(dest)

        return newPathMap

    ###*
     * @param {String} path
     *
     * @return {String}
    ###
    normalizeVolumePath: (path) ->
        if os.platform() != 'win32'
            return path

        matches = path.match(/^([A-Z]+):(.+)$/)

        # Path already normalized.
        if matches != null
            # On Windows, paths for Docker volumes are specified as
            # /c/Path/To/Item.
            path = '/' + matches[1].toLowerCase() + matches[2]

        return path.replace(/\\/g, '/')

    ###*
     * @param {String} path
     *
     * @return {String}
    ###
    denormalizeVolumePath: (path) ->
        if os.platform() != 'win32'
            return path

        matches = path.match(/^\/([a-z]+)\/(.+)$/)

        # Path already denormalized.
        if matches != null
            # On Windows, paths for Docker volumes are specified as
            # /c/Path/To/Item.
            path = matches[1].toUpperCase() + ':\\' + matches[2]

        return path.replace(/\//g, '\\')

    ###*
     * Retrieves a path normalized for the current platform *and* runtime.
     *
     * On Windows, we still need UNIX paths if we are using Docker as runtime,
     * but not if we are using the host PHP.
     *
     * @param {String} path
     *
     * @return {String}
    ###
    normalizePlatformAndRuntimePath: (path) ->
        if @config.get('core.phpExecutionType') == 'host'
            return path

        return @normalizeVolumePath(path)

    ###*
     * Retrieves a path denormalized for the current platform *and* runtime.
     *
     * On Windows, this converts Docker paths back to Windows paths.
     *
     * @param {String} path
     *
     * @return {String}
    ###
    denormalizePlatformAndRuntimePath: (path) ->
        if @config.get('core.phpExecutionType') == 'host'
            return path

        return @denormalizeVolumePath(path)

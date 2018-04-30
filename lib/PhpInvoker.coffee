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

        # NOTE: Uncomment this to test failures
        # process.stdout.on 'data', (data) =>
        #     console.log('STDOUT', data)
        #
        # process.stderr.on 'data', (data) =>
        #     console.log('STDERR', data)

        return process

    ###*
     * @param {String} dockerImage
     * @param {Array}  additionalDockerRunParameters
     *
     * @return {Array}
    ###
    getDockerRunParameters: (dockerImage, additionalDockerRunParameters) ->
        parameters = ['run']

        for src, dest of @getPathsToMountInDockerContainer()
            parameters.push('-v')
            parameters.push(src + ':' + dest)

        return parameters.concat(additionalDockerRunParameters).concat([dockerImage, 'php'])

    ###*
     * @return {Object}
    ###
    getPathsToMountInDockerContainer: () ->
        paths = {}
        paths[@config.get('packagePath')] = @config.get('packagePath')

        for path in @config.get('core.additionalDockerVolumes')
            parts = path.split(':')

            paths[parts[0]] = parts[1]

        for path in atom.project.getPaths()
            paths[path] = path

        return paths

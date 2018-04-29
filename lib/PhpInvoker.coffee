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
     * @param {Array} parameters
     * @param {Array} additionalDockerRunParameters
     *
     * @return {Process}
    ###
    invoke: (parameters, additionalDockerRunParameters = []) ->
        executionType = @config.get('core.phpExecutionType')

        if executionType == 'host'
            return child_process.spawn(@config.get('core.phpCommand'), parameters)

        command = 'docker'
        dockerParameters = @getDockerRunParameters(additionalDockerRunParameters)
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
     * @param {Array} additionalDockerRunParameters
     *
     * @return {Array}
    ###
    getDockerRunParameters: (additionalDockerRunParameters) ->
        parameters = ['run']

        for src, dest of @getPathsToMountInDockerContainer()
            parameters.push('-v')
            parameters.push(src + ':' + dest)

        return parameters.concat(additionalDockerRunParameters).concat(['php:7.2-cli', 'php'])

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

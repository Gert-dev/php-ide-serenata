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

        for path in @getPathsToMountInDockerContainer()
            parameters.push('-v')
            parameters.push(path + ':' + path)

        return parameters.concat(additionalDockerRunParameters).concat(['php:7.2-cli', 'php'])

    ###*
     * @return {Array}
    ###
    getPathsToMountInDockerContainer: () ->
        paths = [
            @config.get('packagePath')
        ]

        return paths.concat(atom.project.getPaths())

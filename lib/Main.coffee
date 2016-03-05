module.exports =
    ###*
     * Configuration settings.
    ###
    config:
        phpCommand:
            title       : 'PHP command'
            description : 'The path to your PHP binary (e.g. /usr/bin/php, php, ...).'
            type        : 'string'
            default     : 'php'
            order       : 1

    ###*
     * The name of the package.
    ###
    packageName: 'php-integrator-base'

    ###*
     * The configuration object.
    ###
    configuration: null

    ###*
     * The proxy object.
    ###
    proxy: null

    ###*
     * The parser object.
    ###
    parser: null

    ###*
     * Keeps track of files that are being indexed.
    ###
    indexMap: {}

    ###*
     * The exposed service.
    ###
    service: null

    ###*
     * The status bar manager.
    ###
    statusBarManager: null

    ###*
     * Tests the user's configuration.
     *
     * @return {boolean}
    ###
    testConfig: () ->
        ConfigTester = require './ConfigTester'

        configTester = new ConfigTester(@configuration)

        result = configTester.test()

        if not result
            errorMessage =
                "PHP is not correctly set up and as a result PHP integrator will not work. Please visit the settings
                 screen to correct this error. If you are not specifying an absolute path for PHP or Composer, make
                 sure they are in your PATH."

            atom.notifications.addError('Incorrect setup!', {'detail': errorMessage})

            return false

        return true

    ###*
     * Registers any commands that are available to the user.
    ###
    registerCommands: () ->
        fs = require 'fs'

        atom.commands.add 'atom-workspace', "php-integrator-base:index-project": =>
            return @performProjectIndex()

        atom.commands.add 'atom-workspace', "php-integrator-base:force-index-project": =>
            try
                fs.unlinkSync(@proxy.getIndexDatabasePath())

            catch error
                # If the file doesn't exist, just bail out.

            return @performProjectIndex()

        atom.commands.add 'atom-workspace', "php-integrator-base:configuration": =>
            return unless @testConfig()

            atom.notifications.addSuccess 'Success', {
                'detail' : 'Your PHP integrator configuration is working correctly!'
            }

    ###*
     * Registers listeners for config changes.
    ###
    registerConfigListeners: () ->
        @configuration.onDidChange 'phpCommand', () =>
            @performProjectIndex()

    ###*
     * Indexes the project aynschronously.
    ###
    performProjectIndex: () ->
        timerName = @packageName + " - Project indexing"

        console.time(timerName);

        projectPath = atom.project.getDirectories()[0]?.path

        if @statusBarManager
            @statusBarManager.setLabel("Indexing...")
            @statusBarManager.setProgress(null)
            @statusBarManager.show()

        successHandler = () =>
            if @statusBarManager
                @statusBarManager.setLabel("Indexing completed!")
                @statusBarManager.hide()

            console.timeEnd(timerName);

        failureHandler = () =>
            if @statusBarManager
                @statusBarManager.showMessage("Indexing failed!", "highlight-error")
                @statusBarManager.hide()

        progressHandler = (progress) =>
            progress = parseFloat(progress)

            if not isNaN(progress)
                if @statusBarManager
                    @statusBarManager.setProgress(progress)
                    @statusBarManager.setLabel("Indexing... (" + progress.toFixed(2) + " %)")

        pathStrings = ''

        for i,project of atom.project.getDirectories()
            pathStrings += project.path

        md5 = require 'md5'

        indexDatabaseName = md5(pathStrings)

        @proxy.setIndexDatabaseName(indexDatabaseName)

        @service.reindex(projectPath, null, progressHandler).then(successHandler, failureHandler)

    ###*
     * Indexes a file aynschronously.
     *
     * @param {string}      fileName The file to index.
     * @param {string|null} source   The source code of the file to index.
    ###
    performFileIndex: (fileName, source = null) ->
        successHandler = () =>
            return

        failureHandler = () =>
            return

        @service.reindex(fileName, source).then(successHandler, failureHandler)

    ###*
     * Performs a file index, but only if the file is not currently already being indexed (otherwise silently returns).
     *
     * @param {string}      fileName The file to index.
     * @param {string|null} source   The source code of the file to index.
    ###
    attemptFileIndex: (fileName, source = null) ->
        return if fileName of @indexMap

        @indexMap[fileName] = true

        successHandler = () =>
            delete @indexMap[fileName]

        failureHandler = () =>
            delete @indexMap[fileName]

        @performFileIndex(fileName, source).then(successHandler, failureHandler)

    ###*
     * Attaches items to the status bar.
     *
     * @param {mixed} statusBarService
    ###
    attachStatusBarItems: (statusBarService) ->
        if not @statusBarManager
            StatusBarManager = require "./Widgets/StatusBarManager"

            @statusBarManager = new StatusBarManager()
            @statusBarManager.initialize(statusBarService)
            @statusBarManager.setLabel("Indexing...")

    ###*
     * Detaches existing items from the status bar.
    ###
    detachStatusBarItems: () ->
        if @statusBarManager
            @statusBarManager.destroy()
            @statusBarManager = null

    ###*
     * Activates the package.
    ###
    activate: ->
        Service       = require './Service'
        AtomConfig    = require './AtomConfig'
        CachingProxy  = require './CachingProxy'
        CachingParser = require './CachingParser'
        {Emitter}     = require 'event-kit';

        @configuration = new AtomConfig(@packageName)

        # See also atom-autocomplete-php pull request #197 - Disabled for now because it does not allow the user to
        # reactivate or try again.
        # return unless @testConfig()
        @testConfig()

        @proxy = new CachingProxy(@configuration)

        emitter = new Emitter()
        @parser = new CachingParser(@proxy)

        @service = new Service(@proxy, @parser, emitter)

        @registerCommands()
        @registerConfigListeners()

        # In rare cases, the package is loaded faster than the project gets a chance to load. At that point, no project
        # directory is returned. The onDidChangePaths listener below will also catch that case.
        if atom.project.getDirectories().length > 0
            @performProjectIndex()

        atom.project.onDidChangePaths (projectPaths) =>
            # NOTE: This listener is also invoked at shutdown with an empty array as argument, this makes sure we don't
            # try to reindex then.
            if projectPaths.length > 0
                @performProjectIndex()

        atom.workspace.observeTextEditors (editor) =>
            # Wait a while for the editor to stabilize so we don't reindex multiple times after an editor opens just
            # because the contents are still loading.
            setTimeout ( =>
                editor.onDidStopChanging () =>
                    @onEditorDidStopChanging(editor)
            ), 1500

    ###*
     * Invoked when an editor stops changing.
     *
     * @param {TextEditor} editor
    ###
    onEditorDidStopChanging: (editor) ->
        return unless /text.html.php$/.test(editor.getGrammar().scopeName)

        path = editor.getPath()

        return if not path

        isContainedInProject = false

        for projectDirectory in atom.project.getDirectories()
            if path.indexOf(projectDirectory.path) != -1
                isContainedInProject = true
                break

        # Do not try to index files outside the project.
        if isContainedInProject
            @parser.clearCache(path)
            @attemptFileIndex(path, editor.getBuffer().getText())

    ###*
     * Deactivates the package.
    ###
    deactivate: ->

    ###*
     * Sets the status bar service, which is consumed by this package.
    ###
    setStatusBarService: (service) ->
        @attachStatusBarItems(service)

        # This method is usually invoked after the indexing has already started, hence we can't unconditionally hide it
        # here or it will never be made visible again. However, we also don't want it to be visible for new Atom windows
        # that don't contain a project.
        if atom.project.getDirectories().length == 0
            @statusBarManager.hide()

        {Disposable} = require 'atom'

        return new Disposable => @detachStatusBarItems()

    ###*
     * Retrieves the service exposed by this package.
    ###
    getService: ->
        return @service

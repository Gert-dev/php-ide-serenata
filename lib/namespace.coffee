###*
 * PHP files namespace management
###

module.exports =

    ###*
     * Add the good namespace to the given file
     * @param {TextEditor} editor
    ###
    createNamespace: (editor) ->
        proxy = require './php-proxy.coffee'

        composer    = proxy.composer()
        autoloaders = []

        if not composer
            return

        # Get elements from composer.json
        for psr, autoload of composer.autoload
            for namespace, src of autoload
                if namespace.endsWith("\\")
                    namespace = namespace.substr(0, namespace.length-1)

                autoloaders[src] = namespace

        # Get the current path of the file
        path = editor.getPath()
        for directory in atom.project.getDirectories()
            if path.indexOf(directory.path) == 0
                path = path.substr(directory.path.length+1)
                break

        # Path with \ replaced by / to be ok with composer.json
        path = path.replace(/\\/g, '/')

        # Get the root namespace
        namespace = null
        for src, name of autoloaders
            if path.indexOf(src) == 0
                path = path.substr(src.length)
                namespace = name
                break

        # No namespace found ? Let's leave
        if namespace == null
            return

        # If the path starts with "/", we remove it
        if path.indexOf("/") == 0
            path = path.substr(1)

        elements = path.split('/')

        # Build the namespace
        index = 1
        for element in elements
            if element == "" or index == elements.length
                continue

            namespace = if namespace == "" then element else namespace + "\\" + element
            index++

        text = editor.getText()
        index = 0

        # Search for the good place to write the namespace
        lines = text.split('\n')
        for line in lines
            line = line.trim()

            # If we found class keyword, we are not in namespace space, so return
            if line.indexOf('namespace ') == 0
                editor.setTextInBufferRange([[index,0], [index+1, 0]], "namespace #{namespace};\n")
                return
            else if line.trim() != "" and line.trim().indexOf("<?") != 0
                editor.setTextInBufferRange([[index,0], [index, 0]], "namespace #{namespace};\n\n")
                return

            index += 1

        editor.setTextInBufferRange([[2 ,0], [2, 0]], "namespace #{namespace};\n\n")

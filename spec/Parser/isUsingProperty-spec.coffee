Parser = require '../../lib/Parser'

describe "isUsingProperty", ->
    editor = null
    grammar = null
    parser = new Parser(null)

    beforeEach ->
        waitsForPromise ->
            atom.workspace.open().then (result) ->
                editor = result

        waitsForPromise ->
            atom.packages.activatePackage('language-php')

        runs ->
            grammar = atom.grammars.selectGrammar('.source.php')

        waitsFor ->
            grammar and editor

        runs ->
            editor.setGrammar(grammar)

    it "correctly returns false on method calls.", ->
        source =
            """
            <?php

            $this->test()->foo;
            """

        editor.setText(source)

        bufferPosition =
            row: editor.getLineCount() - 1
            column: 8

        expect(parser.isUsingProperty(editor, bufferPosition)).toBeFalsy()

    it "correctly returns true on property access.", ->
        source =
            """
            <?php

            $this->test->foo;
            """

        editor.setText(source)

        bufferPosition =
            row: editor.getLineCount() - 1
            column: 8

        expect(parser.isUsingProperty(editor, bufferPosition)).toBeTruthy()

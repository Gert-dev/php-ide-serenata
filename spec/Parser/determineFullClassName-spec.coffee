Parser = require '../../lib/Parser'

describe "determineFullClassName", ->
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

    it "returns null if there is no namespace.", ->
        source =
            """
            <?php
            """

        editor.setText(source)

        expect(parser.determineFullClassName(editor, null)).toEqual(null)


    it "correctly determines the current class name when there is no namespace.", ->
        source =
            """
            <?php

            class Foo
            {
                //
            }
            """

        editor.setText(source)

        expect(parser.determineFullClassName(editor, null)).toEqual('Foo')

    it "correctly determines the current class name when there is a namespace.", ->
        source =
            """
            <?php

            namespace My;

            class Foo
            {
                //
            }
            """

        editor.setText(source)

        expect(parser.determineFullClassName(editor, null)).toEqual('My\\Foo')

    it "correctly treats class names that are absolute with a leading slash.", ->
        source =
            """
            <?php

            namespace My;
            """

        editor.setText(source)

        expect(parser.determineFullClassName(editor, '\\My\\Foo')).toEqual('My\\Foo')

    it "ignores lines with comments.", ->
        source =
            """
            <?php

            // namespace MyComment;
            namespace My;

            // use Comment\\Bar;
            use Foo\\Bar;
            """

        editor.setText(source)

        expect(parser.determineFullClassName(editor, 'Bar')).toEqual('Foo\\Bar')

    it "parses use simple statements properly.", ->
        source =
            """
            <?php

            namespace My;

            use Foo\\Bar;
            """

        editor.setText(source)

        expect(parser.determineFullClassName(editor, 'Bar')).toEqual('Foo\\Bar')

    it "parses use statements with aliases properly.", ->
        source =
            """
            <?php

            namespace My;

            use Foo\\Bar as Alias;
            """

        editor.setText(source)

        expect(parser.determineFullClassName(editor, 'Bar')).toEqual('My\\Bar')
        expect(parser.determineFullClassName(editor, 'Alias')).toEqual('Foo\\Bar')

    it "correctly deals with class names with multiple segments and use statements.", ->
        source =
            """
            <?php

            namespace My;

            use Foo\\Bar;
            """

        editor.setText(source)

        expect(parser.determineFullClassName(editor, 'Bar\\Test')).toEqual('Foo\\Bar\\Test')

    it "correctly deals with class names with multiple segments relative to the current namespace.", ->
        source =
            """
            <?php

            namespace My;
            """

        editor.setText(source)

        expect(parser.determineFullClassName(editor, 'Bar\\Test')).toEqual('My\\Bar\\Test')

    it "does not touch basic types.", ->
        source =
            """
            <?php

            namespace My;
            """

        editor.setText(source)

        expect(parser.determineFullClassName(editor, 'int')).toEqual('int')
        expect(parser.determineFullClassName(editor, 'INT')).toEqual('INT')
        expect(parser.determineFullClassName(editor, 'bool')).toEqual('bool')
        expect(parser.determineFullClassName(editor, 'string')).toEqual('string')
        expect(parser.determineFullClassName(editor, 'STRING')).toEqual('STRING')

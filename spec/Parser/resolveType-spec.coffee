Parser = require '../../lib/Parser'

describe "resolveType", ->
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

    it "correctly treats class names that are absolute with a leading slash.", ->
        source =
            """
            <?php

            namespace My;
            """

        editor.setText(source)

        expect(parser.resolveType(editor, '\\My\\Foo')).toEqual('My\\Foo')

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

        expect(parser.resolveType(editor, 'Bar')).toEqual('Foo\\Bar')

    it "stops after the use statements.", ->
        source =
            """
            <?php

            namespace My;

            class Test
            {
                use Bar;
            }
            """

        editor.setText(source)

        expect(parser.resolveType(editor, 'Bar')).toEqual('My\\Bar')

    it "parses use simple statements properly.", ->
        source =
            """
            <?php

            namespace My;

            use Foo\\Bar;
            """

        editor.setText(source)

        expect(parser.resolveType(editor, 'Bar')).toEqual('Foo\\Bar')

    it "parses use statements with aliases properly.", ->
        source =
            """
            <?php

            namespace My;

            use Foo\\Bar as Alias;
            """

        editor.setText(source)

        expect(parser.resolveType(editor, 'Bar')).toEqual('My\\Bar')
        expect(parser.resolveType(editor, 'Alias')).toEqual('Foo\\Bar')

    it "correctly deals with class names with multiple segments and use statements.", ->
        source =
            """
            <?php

            namespace My;

            use Foo\\Bar;
            """

        editor.setText(source)

        expect(parser.resolveType(editor, 'Bar\\Test')).toEqual('Foo\\Bar\\Test')

    it "correctly deals with class names with multiple segments relative to the current namespace.", ->
        source =
            """
            <?php

            namespace My;
            """

        editor.setText(source)

        expect(parser.resolveType(editor, 'Bar\\Test')).toEqual('My\\Bar\\Test')

    it "does not touch basic types.", ->
        source =
            """
            <?php

            namespace My;
            """

        editor.setText(source)

        expect(parser.resolveType(editor, 'int')).toEqual('int')
        expect(parser.resolveType(editor, 'INT')).toEqual('INT')
        expect(parser.resolveType(editor, 'bool')).toEqual('bool')
        expect(parser.resolveType(editor, 'string')).toEqual('string')
        expect(parser.resolveType(editor, 'STRING')).toEqual('STRING')

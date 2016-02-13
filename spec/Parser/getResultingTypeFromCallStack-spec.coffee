Parser = require '../../lib/Parser'

describe "getResultingTypeFromCallStack", ->
    editor = null
    grammar = null

    proxyMock = {
        getClassListForFile: (file) ->
            return {
                'Bar' : @getClassInfo('Bar')
            }

        getGlobalFunctions: () ->
            return {
                global_function:
                    return:
                        type: '\\DateTime'
            }

        getClassInfo: (className) ->
            if className == '\\DateTime'
                return {
                    constants: {}
                    methods: {}
                    properties:
                        '':
                            return:
                                resolvedType: 'EXPECTED_TYPE_1'
                }

            else if className == '\\Closure'
                return {
                    name: '\\Closure'

                    methods:
                        bindTo:
                            return:
                                resolvedType: '\\Closure'
                }

            else if className == 'ParentClass'
                return {
                    constants: {}
                    methods: {}
                    properties:
                        testProperty:
                            return:
                                resolvedType: 'EXPECTED_TYPE_1'
                }

            else if className == 'Bar'
                return {
                    name: 'Bar'
                    parents: ['ParentClass']
                    startLine : 0
                    endLine   : 9999
                    methods: {}
                    constants: {}
                    properties:
                        '':
                            return:
                                resolvedType: 'EXPECTED_TYPE_1'

                        testProperty:
                            return:
                                resolvedType: 'EXPECTED_TYPE_1'
                }

            else if className == 'EXPECTED_TYPE_1'
                return {
                    name: 'EXPECTED_TYPE_1'
                    constants: {}
                    methods:
                        aMethod:
                            return:
                                resolvedType: 'EXPECTED_TYPE_2'
                }

            else if className == 'EXPECTED_TYPE_2'
                return {
                    name: 'EXPECTED_TYPE_2'
                    methods: {}
                    constants: {}

                    properties:
                        anotherProperty:
                            return:
                                resolvedType: 'EXPECTED_TYPE_3'
                }

            else if className == 'EXPECTED_TYPE_3'
                return {
                    methods: {}
                    name: 'EXPECTED_TYPE_3'
                }

        resolveType: (file, line, type) ->
            return type

        autocomplete: (className, element) ->
            return {name: 'EXPECTED_TYPE_1'} if className == 'ParentClass' and element == 'testProperty'
    }

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

    it "correctly deals with static class names.", ->
        source =
            """
            <?php

            Bar::$testProperty
            """

        editor.setText(source)

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['Bar', '$testProperty'])).toEqual('EXPECTED_TYPE_1')

    it "correctly deals with self.", ->
        source =
            """
            <?php

            class Bar
            {
                public function __construct()
                {
                    self::$testProperty
                }
            }
            """

        editor.setText(source)

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 3
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['self', '$testProperty'])).toEqual('EXPECTED_TYPE_1')

    it "correctly deals with static.", ->
        source =
            """
            <?php

            class Bar
            {
                public function __construct()
                {
                    static::$testProperty
                }
            }
            """

        editor.setText(source)

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 3
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['static', '$testProperty'])).toEqual('EXPECTED_TYPE_1')

    it "correctly deals with parent.", ->
        source =
            """
            <?php

            class Bar
            {
                public function __construct()
                {
                    parent::$testProperty
                }
            }
            """

        editor.setText(source)

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 3
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['parent', '$testProperty'])).toEqual('EXPECTED_TYPE_1')

    it "correctly deals with $this.", ->
        source =
            """
            <?php

            class Bar
            {
                public function __construct()
                {
                    $this->testProperty
                }
            }
            """

        editor.setText(source)

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 3
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['$this', 'testProperty'])).toEqual('EXPECTED_TYPE_1')

    it "correctly deals with variables.", ->
        source =
            """
            <?php

            $var = new Bar();
            $var->testProperty
            """

        editor.setText(source)

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['$var', 'testProperty'])).toEqual('EXPECTED_TYPE_1')

    it "correctly deals with global PHP functions.", ->
        source =
            """
            <?php

            global_function()->
            """

        editor.setText(source)

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['global_function()', ''])).toEqual('EXPECTED_TYPE_1')

    it "correctly deals with closures.", ->
        source =
            """
            <?php

            $var = function () {

            };

            $var->bindTo();
            """

        editor.setText(source)

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['$var', 'bindTo()'])).toEqual('\\Closure')

    it "correctly deals with new instances of classes.", ->
        source =
            """
            <?php

            (new Bar())->
            """

        editor.setText(source)

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['new Bar()', ''])).toEqual('EXPECTED_TYPE_1')

    it "correctly handles the new keyword with keywords such as static.", ->
        source =
            """
            <?php

            class Bar
            {
                public function __construct()
                {
                    $test = new static();
                }
            }
            """

        editor.setText(source)

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 3
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['new static()'])).toEqual('Bar')

    it "correctly handles the clone keyword.", ->
        source =
            """
            <?php

            $var = new \DateTime();

            $test = clone $var;
            """

        editor.setText(source)

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 3
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['clone $var'])).toEqual('\DateTime')

    it "correctly handles longer chains.", ->
        source =
            """
            <?php

            class Bar
            {
                public function __construct()
                {
                    $this->testProperty->aMethod()->anotherProperty;
                }
            }
            """

        editor.setText(source)

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 3
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['$this', 'testProperty', 'aMethod()', 'anotherProperty'])).toEqual('EXPECTED_TYPE_3')

    it "correctly detects basic types.", ->
        parser = new Parser({})

        expect(parser.getResultingTypeFromCallStack(editor, [0, 0], ['5'])).toEqual('int')
        expect(parser.getResultingTypeFromCallStack(editor, [0, 0], ['05'])).toEqual('int')
        expect(parser.getResultingTypeFromCallStack(editor, [0, 0], ['0x5'])).toEqual('int')
        expect(parser.getResultingTypeFromCallStack(editor, [0, 0], ['5.5'])).toEqual('float')
        expect(parser.getResultingTypeFromCallStack(editor, [0, 0], ['true'])).toEqual('bool')
        expect(parser.getResultingTypeFromCallStack(editor, [0, 0], ['false'])).toEqual('bool')
        expect(parser.getResultingTypeFromCallStack(editor, [0, 0], ['"test"'])).toEqual('string')
        expect(parser.getResultingTypeFromCallStack(editor, [0, 0], ['\'test\''])).toEqual('string')
        expect(parser.getResultingTypeFromCallStack(editor, [0, 0], ['[$test1, function() {}]'])).toEqual('array')
        expect(parser.getResultingTypeFromCallStack(editor, [0, 0], ['array($test1, function())'])).toEqual('array')

        expect(parser.getResultingTypeFromCallStack(editor, [0, 0], ['"
            test
        "'])).toEqual('string')

        expect(parser.getResultingTypeFromCallStack(editor, [0, 0], ['\'
            test
        \''])).toEqual('string')

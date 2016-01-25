Parser = require '../../lib/Parser'

describe "getResultingTypeFromCallStack", ->
    editor = null
    grammar = null

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

            Bar::testProperty
            """

        editor.setText(source)

        proxyMock = {
            getClassInfo: (className) ->
                if className == 'Bar'
                    return {
                        properties:
                            testProperty:
                                return:
                                    resolvedType: 'EXPECTED_TYPE'
                    }

                else if className == 'EXPECTED_TYPE'
                    return {
                        name: 'EXPECTED_TYPE'
                    }
        }

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['Bar', 'testProperty'])).toEqual('EXPECTED_TYPE')

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

        proxyMock = {
            getClassInfo: (className) ->
                if className == 'Bar'
                    return {
                        properties:
                            testProperty:
                                return:
                                    resolvedType: 'EXPECTED_TYPE'
                    }

                else if className == 'EXPECTED_TYPE'
                    return {
                        name: 'EXPECTED_TYPE'
                    }
        }

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 3
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['self', 'testProperty'])).toEqual('EXPECTED_TYPE')

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

        proxyMock = {
            getClassInfo: (className) ->
                if className == 'Bar'
                    return {
                        properties:
                            testProperty:
                                return:
                                    resolvedType: 'EXPECTED_TYPE'
                    }

                else if className == 'EXPECTED_TYPE'
                    return {
                        name: 'EXPECTED_TYPE'
                    }
        }

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 3
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['static', 'testProperty'])).toEqual('EXPECTED_TYPE')

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

        proxyMock = {
            getClassInfo: (className) ->
                return {parents: ['ParentClass']} if className == 'Bar'

                if className == 'ParentClass'
                    return {
                        properties:
                            testProperty:
                                return:
                                    resolvedType: 'EXPECTED_TYPE'
                    }

                else if className == 'EXPECTED_TYPE'
                    return {
                        name: 'EXPECTED_TYPE'
                    }


            autocomplete: (className, element) ->
                return {name: 'EXPECTED_TYPE'} if className == 'ParentClass' and element == 'testProperty'
        }

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 3
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['parent', 'testProperty'])).toEqual('EXPECTED_TYPE')

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

        proxyMock = {
            getClassInfo: (className) ->
                if className == 'Bar'
                    return {
                        properties:
                            testProperty:
                                return:
                                    resolvedType: 'EXPECTED_TYPE'
                    }

                else if className == 'EXPECTED_TYPE'
                    return {
                        name: 'EXPECTED_TYPE'
                    }
        }

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 3
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['$this', 'testProperty'])).toEqual('EXPECTED_TYPE')

    it "correctly deals with variables.", ->
        source =
            """
            <?php

            $var = new Bar();
            $var->testProperty
            """

        editor.setText(source)

        proxyMock = {
            getGlobalFunctions: () ->
                return []

            getClassInfo: (className) ->
                if className == 'Bar'
                    return {
                        properties:
                            testProperty:
                                return:
                                    resolvedType: 'EXPECTED_TYPE'
                    }

                else if className == 'EXPECTED_TYPE'
                    return {
                        name: 'EXPECTED_TYPE'
                    }
        }

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['$var', 'testProperty'])).toEqual('EXPECTED_TYPE')

    it "correctly deals with global PHP functions.", ->
        source =
            """
            <?php

            global_function()->
            """

        editor.setText(source)

        proxyMock = {
            getGlobalFunctions: () ->
                return {
                    global_function:
                        return:
                            type: '\\DateTime'
                }

            getClassInfo: (className) ->
                if className == '\\DateTime'
                    return {
                        properties:
                            '':
                                return:
                                    resolvedType: 'EXPECTED_TYPE'
                    }

                else if className == 'EXPECTED_TYPE'
                    return {
                        name: 'EXPECTED_TYPE'
                    }
        }

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['global_function()', ''])).toEqual('EXPECTED_TYPE')

    it "correctly deals with closures.", ->
        source =
            """
            <?php

            $var = function () {

            };

            $var->bindTo();
            """

        editor.setText(source)

        proxyMock = {
            getGlobalFunctions: () ->
                return []

            getClassInfo: (className) ->
                if className == '\\Closure'
                    return {
                        name: '\\Closure'

                        methods:
                            bindTo:
                                return:
                                    resolvedType: '\\Closure'
                    }
        }

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

        proxyMock = {
            getGlobalFunctions: () ->
                return {}

            getClassInfo: (className) ->
                if className == 'Bar'
                    return {
                        properties:
                            '':
                                return:
                                    resolvedType: 'EXPECTED_TYPE'
                    }

                else if className == 'EXPECTED_TYPE'
                    return {
                        name: 'EXPECTED_TYPE'
                    }
        }

        parser = new Parser(proxyMock)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getResultingTypeFromCallStack(editor, bufferPosition, ['new Bar()', ''])).toEqual('EXPECTED_TYPE')

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

        proxyMock = {
            getGlobalFunctions: () ->
                return {}



            getClassInfo: (className) ->
                if className == 'Bar'
                    return {
                        name: 'Bar'
                        methods: {}
                        properties:
                            testProperty:
                                return:
                                    resolvedType: 'EXPECTED_TYPE_1'
                    }

                if className == 'EXPECTED_TYPE_1'
                    return {
                        name: 'EXPECTED_TYPE_1'
                        methods:
                            aMethod:
                                return:
                                    resolvedType: 'EXPECTED_TYPE_2'
                    }

                if className == 'EXPECTED_TYPE_2'
                    return {
                        name: 'EXPECTED_TYPE_2'
                        methods: {}

                        properties:
                            anotherProperty:
                                return:
                                    resolvedType: 'EXPECTED_TYPE_3'
                    }

                return {name: 'EXPECTED_TYPE_3'} if className == 'EXPECTED_TYPE_3'
        }

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

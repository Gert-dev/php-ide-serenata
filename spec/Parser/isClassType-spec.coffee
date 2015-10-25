Parser = require '../../lib/Parser'

describe "isClassType", ->
    parser = new Parser(null)

    it "does not think built-in simple PHP types are classes.", ->
        expect(parser.isClassType("Foo")).toBeTruthy()
        expect(parser.isClassType("DateTime")).toBeTruthy()
        expect(parser.isClassType("SomewhatComplexClass")).toBeTruthy()
        expect(parser.isClassType("lowerCamelCase")).toBeFalsy()
        expect(parser.isClassType("int")).toBeFalsy()
        expect(parser.isClassType("bool")).toBeFalsy()
        expect(parser.isClassType("string")).toBeFalsy()

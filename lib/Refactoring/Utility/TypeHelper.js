/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let TypeHelper;
module.exports =

(TypeHelper = (function() {
    TypeHelper = class TypeHelper {
        static initClass() {
            /**
             * @var {Object|null} service
            */
            this.prototype.service = null;
        }

        /**
         * @param {Object} service
        */
        setService(service) {
            this.service = service;
        }

        /**
         * @return {Number}
        */
        getCurrentProjectPhpVersion() {
            const projectSettings = this.service.getCurrentProjectSettings();

            if (projectSettings != null) {
                return projectSettings.phpVersion;
            }

            return 5.2; // Assume lowest supported version
        }

        /**
         * @param {String|null} typeSpecification
         *
         * @return {Object|null}
        */
        getReturnTypeHintForTypeSpecification(typeSpecification) {
            if (this.getCurrentProjectPhpVersion() < 7.0) { return null; }

            const returnTypeHint = this.getTypeHintForTypeSpecification(typeSpecification);

            if ((returnTypeHint == null) || returnTypeHint.shouldSetDefaultValueToNull) {
                return null;
            }

            return returnTypeHint.typeHint;
        }

        /**
         * @param {String|null} typeSpecification
         *
         * @return {Object|null}
        */
        getTypeHintForTypeSpecification(typeSpecification) {
            const types = this.getDocblockTypesFromDocblockTypeSpecification(typeSpecification);

            return this.getTypeHintForDocblockTypes(types);
        }

        /**
         * @param {String|null} typeSpecification
         *
         * @return {Array}
        */
        getDocblockTypesFromDocblockTypeSpecification(typeSpecification) {
            if ((typeSpecification == null)) { return []; }
            return typeSpecification.split('|');
        }

        /**
         * @param {Array}   types
         * @param {boolean} allowPhp7
         *
         * @return {Object|null}
        */
        getTypeHintForDocblockTypes(types) {
            let isNullable = false;

            types = types.filter(type => {
                if (type === 'null') {
                    isNullable = true;
                }

                return type !== 'null';
            });

            let typeHint = null;
            let previousTypeHint = null;

            for (const type of types) {
                typeHint = this.getTypeHintForDocblockType(type);

                if ((previousTypeHint != null) && (typeHint !== previousTypeHint)) {
                    // Several different type hints are necessary, we can't provide a common denominator.
                    return null;
                }

                previousTypeHint = typeHint;
            }

            const data = {
                typeHint,
                shouldSetDefaultValueToNull : false
            };

            if ((typeHint == null)) { return data; }
            if (!isNullable) { return data; }

            const currentPhpVersion = this.getCurrentProjectPhpVersion();

            if (currentPhpVersion >= 7.1) {
                data.typeHint = `?${typeHint}`;
                data.shouldSetDefaultValueToNull = false;

            } else {
                data.shouldSetDefaultValueToNull = true;
            }

            return data;
        }

        /**
         * @param {String|null} type
         *
         * @return {String|null}
        */
        getTypeHintForDocblockType(type) {
            if ((type == null)) { return null; }
            if (this.isClassType(type)) { return type; }
            return this.getScalarTypeHintForDocblockType(type);
        }

        /**
         * @param {String|null} type
         *
         * @return {boolean}
        */
        isClassType(type) {
            if (this.getScalarTypeHintForDocblockType(type) === false) { return true; } else { return false; }
        }

        /**
         * @param {String|null} type
         *
         * @return {String|null|false} Null if the type is recognized, but there is no type hint available, false of the
         *                             type is not recognized at all, and the type hint itself if it is recognized and there
         *                             is a type hint.
        */
        getScalarTypeHintForDocblockType(type) {
            if ((type == null)) { return null; }

            const phpVersion = this.getCurrentProjectPhpVersion();

            if (phpVersion >= 7.1) {
                if (type === 'iterable') { return 'iterable'; }
                if (type === 'void') { return 'void'; }

            } else if (phpVersion >= 7.0) {
                if (type === 'string') { return 'string'; }
                if (type === 'int') { return 'int'; }
                if (type === 'bool') { return 'bool'; }
                if (type === 'float') { return 'float'; }
                if (type === 'resource') { return 'resource'; }
                if (type === 'false') { return 'bool'; }
                if (type === 'true') { return 'bool'; }

            } else {
                if (type === 'string') { return null; }
                if (type === 'int') { return null; }
                if (type === 'bool') { return null; }
                if (type === 'float') { return null; }
                if (type === 'resource') { return null; }
                if (type === 'false') { return null; }
                if (type === 'true') { return null; }
            }

            if (type === 'array') { return 'array'; }
            if (type === 'callable') { return 'callable'; }
            if (type === 'self') { return 'self'; }
            if (type === 'static') { return 'self'; }
            if (/^.+\[\]$/.test(type)) { return 'array'; }

            if (type === 'object') { return null; }
            if (type === 'mixed') { return null; }
            if (type === 'void') { return null; }
            if (type === 'null') { return null; }
            if (type === 'parent') { return null; }
            if (type === '$this') { return null; }

            return false;
        }

        /**
         * Takes a type list (list of type objects) and turns them into a single docblock type specification.
         *
         * @param {Array} typeList
         *
         * @return {String}
        */
        buildTypeSpecificationFromTypeArray(typeList) {
            const typeNames = typeList.map(type => type.type);

            return this.buildTypeSpecificationFromTypes(typeNames);
        }

        /**
         * Takes a list of type names and turns them into a single docblock type specification.
         *
         * @param {Array} typeNames
         *
         * @return {String}
        */
        buildTypeSpecificationFromTypes(typeNames) {
            if (typeNames.length === 0) { return 'mixed'; }

            return typeNames.join('|');
        }
    };
    TypeHelper.initClass();
    return TypeHelper;
})());

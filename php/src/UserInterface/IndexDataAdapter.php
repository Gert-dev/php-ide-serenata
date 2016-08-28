<?php

namespace PhpIntegrator\UserInterface;

use ArrayObject;
use UnexpectedValueException;

use PhpIntegrator\Analysis\Relations;
use PhpIntegrator\Analysis\DocblockAnalyzer;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

/**
 * Adapts and resolves data from the index as needed to receive an appropriate output data format.
 */
class IndexDataAdapter implements IndexDataAdapterInterface
{
    /**
     * @var Conversion\ConstantConverter
     */
    protected $constantConverter;

    /**
     * @var Conversion\ClasslikeConstantConverter
     */
    protected $classlikeConstantConverter;

    /**
     * @var Conversion\PropertyConverter
     */
    protected $propertyConverter;

    /**
     * @var Conversion\FunctionConverter
     */
    protected $functionConverter;

    /**
     * @var Conversion\MethodConverter
     */
    protected $methodConverter;

    /**
     * @var Conversion\ClasslikeConverter
     */
    protected $classlikeConverter;

    /**
     * @var Relations\InheritanceResolver
     */
    protected $inheritanceResolver;

    /**
     * @var Relations\InterfaceImplementationResolver
     */
    protected $interfaceImplementationResolver;

    /**
     * @var Relations\TraitUsageResolver
     */
    protected $traitUsageResolver;

    /**
     * The storage to use for accessing index data.
     *
     * @var IndexDataAdapterProviderInterface
     */
    protected $storage;

    /**
     * @var DocblockAnalyzer
     */
    protected $docblockAnalyzer;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var string[]
     */
    protected $resolutionStack = [];

    /**
     * @param Conversion\ConstantConverter              $constantConverter
     * @param Conversion\ClasslikeConstantConverter     $classlikeConstantConverter
     * @param Conversion\PropertyConverter              $propertyConverter
     * @param Conversion\FunctionConverter              $functionConverter
     * @param Conversion\MethodConverter                $methodConverter
     * @param Conversion\ClasslikeConverter             $classlikeConverter
     * @param Relations\InheritanceResolver             $inheritanceResolver
     * @param Relations\InterfaceImplementationResolver $interfaceImplementationResolver
     * @param Relations\TraitUsageResolver              $traitUsageResolver
     * @param IndexDataAdapterProviderInterface         $storage
     */
    public function __construct(
        Conversion\ConstantConverter $constantConverter,
        Conversion\ClasslikeConstantConverter $classlikeConstantConverter,
        Conversion\PropertyConverter $propertyConverter,
        Conversion\FunctionConverter $functionConverter,
        Conversion\MethodConverter $methodConverter,
        Conversion\ClasslikeConverter $classlikeConverter,
        Relations\InheritanceResolver $inheritanceResolver,
        Relations\InterfaceImplementationResolver $interfaceImplementationResolver,
        Relations\TraitUsageResolver $traitUsageResolver,
        IndexDataAdapterProviderInterface $storage
    ) {
        $this->constantConverter = $constantConverter;
        $this->classlikeConstantConverter = $classlikeConstantConverter;
        $this->propertyConverter = $propertyConverter;
        $this->functionConverter = $functionConverter;
        $this->methodConverter = $methodConverter;
        $this->classlikeConverter = $classlikeConverter;

        $this->inheritanceResolver = $inheritanceResolver;
        $this->interfaceImplementationResolver = $interfaceImplementationResolver;
        $this->traitUsageResolver = $traitUsageResolver;

        $this->storage = $storage;
    }

    /**
     * Retrieves information about the specified structural element.
     *
     * @param string $fqcn
     *
     * @throws UnexpectedValueException
     *
     * @return array
     */
    public function getStructureInfo($fqcn)
    {
        $this->resolutionStack = [$fqcn];

        return $this->getDirectStructureInfo($fqcn)->getArrayCopy();
    }

    /**
     * @param string $fqcn
     *
     * @throws UnexpectedValueException
     *
     * @return ArrayObject
     */
    protected function getDirectStructureInfo($fqcn)
    {
        $rawInfo = $this->storage->getStructureRawInfo($fqcn);

        if (!$rawInfo) {
            throw new UnexpectedValueException('The structural element "' . $fqcn . '" was not found!');
        }

        $id = $rawInfo['id'];

        return $this->resolveStructure(
            $rawInfo,
            $this->storage->getStructureRawParents($id),
            $this->storage->getStructureRawChildren($id),
            $this->storage->getStructureRawInterfaces($id),
            $this->storage->getStructureRawImplementors($id),
            $this->storage->getStructureRawTraits($id),
            $this->storage->getStructureRawTraitUsers($id),
            $this->storage->getStructureRawConstants($id),
            $this->storage->getStructureRawProperties($id),
            $this->storage->getStructureRawMethods($id)
        );
    }

    /**
     * @param string $fqcn
     * @param string $originFqcn
     *
     * @return ArrayObject
     */
    protected function getCheckedStructureInfo($fqcn, $originFqcn)
    {
        if (in_array($fqcn, $this->resolutionStack)) {
            throw new CircularDependencyException(
                "Circular dependency detected from {$originFqcn} to {$fqcn}!"
            );
        }

        $this->resolutionStack[] = $fqcn;

        $data = $this->getDirectStructureInfo($fqcn);

        array_pop($this->resolutionStack);

        return $data;
    }

    /**
     * Resolves structural element information from the specified raw data.
     *
     * @param array $element
     * @param array $parents
     * @param array $children
     * @param array $interfaces
     * @param array $implementors
     * @param array $traits
     * @param array $traitUsers
     * @param array $constants
     * @param array $properties
     * @param array $methods
     *
     * @return ArrayObject
     */
    public function resolveStructure(
        array $element,
        array $parents,
        array $children,
        array $interfaces,
        array $implementors,
        array $traits,
        array $traitUsers,
        array $constants,
        array $properties,
        array $methods
    ) {
        $result = $this->getDirectUnresolvedStructureInfo(
            $element,
            $parents,
            $children,
            $interfaces,
            $implementors,
            $traits,
            $traitUsers,
            $constants,
            $properties,
            $methods
        );

        foreach ($parents as $parent) {
            $parentInfo = $this->getCheckedStructureInfo($parent['fqcn'], $result['name']);

            $this->inheritanceResolver->resolveInheritanceOf($parentInfo, $result);
        }

        foreach ($interfaces as $interface) {
            $interfaceInfo = $this->getCheckedStructureInfo($interface['fqcn'], $result['name']);

            $this->interfaceImplementationResolver->resolveImplementationOf($interfaceInfo, $result);
        }

        $traitAliases = [];
        $traitPrecedences = [];

        if (!empty($traits)) {
            $traitAliases = $this->storage->getStructureTraitAliasesAssoc($element['id']);
            $traitPrecedences = $this->storage->getStructureTraitPrecedencesAssoc($element['id']);
        }

        foreach ($traits as $trait) {
            $traitInfo = $this->getCheckedStructureInfo($trait['fqcn'], $result['name']);

            $this->traitUsageResolver->resolveUseOf($traitInfo, $result, $traitAliases, $traitPrecedences);
        }

        $this->resolveSpecialTypes($result, $element['fqcn']);

        return $result;
    }

    /**
     * @param array $element
     * @param array $parents
     * @param array $children
     * @param array $interfaces
     * @param array $implementors
     * @param array $traits
     * @param array $traitUsers
     * @param array $constants
     * @param array $properties
     * @param array $methods
     *
     * @return ArrayObject
     */
    public function getDirectUnresolvedStructureInfo(
        array $element,
        array $parents,
        array $children,
        array $interfaces,
        array $implementors,
        array $traits,
        array $traitUsers,
        array $constants,
        array $properties,
        array $methods
    ) {
        $classlike = new ArrayObject($this->classlikeConverter->convert($element) + [
            'parents'            => [],
            'interfaces'         => [],
            'traits'             => [],

            'directParents'      => [],
            'directInterfaces'   => [],
            'directTraits'       => [],
            'directChildren'     => [],
            'directImplementors' => [],
            'directTraitUsers'   => [],

            'constants'          => [],
            'properties'         => [],
            'methods'            => []
        ]);

        foreach ($children as $child) {
            $classlike['directChildren'][] = $child['fqcn'];
        }

        foreach ($implementors as $implementor) {
            $classlike['directImplementors'][] = $implementor['fqcn'];
        }

        foreach ($traitUsers as $trait) {
            $classlike['directTraitUsers'][] = $trait['fqcn'];
        }

        foreach ($constants as $rawConstantData) {
            $classlike['constants'][$rawConstantData['name']] = $this->classlikeConstantConverter->convertForClass(
                $rawConstantData,
                $classlike
            );
        }

        foreach ($properties as $rawPropertyData) {
            $classlike['properties'][$rawPropertyData['name']] = $this->propertyConverter->convertForClass(
                $rawPropertyData,
                $classlike
            );
        }

        foreach ($methods as $rawMethodData) {
            $classlike['methods'][$rawMethodData['name']] = $this->methodConverter->convertForClass(
                $rawMethodData,
                $classlike
            );
        }

        foreach ($parents as $parent) {
            $classlike['parents'][] = $parent['fqcn'];
            $classlike['directParents'][] = $parent['fqcn'];
        }

        foreach ($interfaces as $interface) {
            $classlike['interfaces'][] = $interface['fqcn'];
            $classlike['directInterfaces'][] = $interface['fqcn'];
        }

        foreach ($traits as $trait) {
            $classlike['traits'][] = $trait['fqcn'];
            $classlike['directTraits'][] = $trait['fqcn'];
        }

        return $classlike;
    }

    /**
     * @param ArrayObject $result
     * @param string      $elementFqcn
     */
    protected function resolveSpecialTypes(ArrayObject $result, $elementFqcn)
    {
        // TODO: Revise this, needs to be dependency injected.
        $typeAnalyzer = new \PhpIntegrator\Analysis\Typing\TypeAnalyzer();
        // $typeAnalyzer = $this->getTypeAnalyzer();




        $doResolveTypes = function (array &$type) use ($elementFqcn, $typeAnalyzer) {
            if ($type['type'] === 'self') {
                // self takes the type from the classlike it is first resolved in, so only resolve it once to ensure
                // that it doesn't get overwritten.
                if ($type['resolvedType'] === 'self') {
                    $type['resolvedType'] = $typeAnalyzer->getNormalizedFqcn($elementFqcn);
                }
            } elseif ($type['type'] === '$this' || $type['type'] === 'static') {
                $type['resolvedType'] = $typeAnalyzer->getNormalizedFqcn($elementFqcn);
            } elseif ($typeAnalyzer->isClassType($type['fqcn'])) {
                $type['resolvedType'] = $typeAnalyzer->getNormalizedFqcn($type['fqcn']);
            } else {
                $type['resolvedType'] = $type['fqcn'];
            }
        };

        foreach ($result['methods'] as $name => &$method) {
            foreach ($method['parameters'] as &$parameter) {
                foreach ($parameter['types'] as &$type) {
                    $doResolveTypes($type);
                }
            }

            foreach ($method['returnTypes'] as &$returnType) {
                $doResolveTypes($returnType);
            }
        }

        foreach ($result['properties'] as $name => &$property) {
            foreach ($property['types'] as &$type) {
                $doResolveTypes($type);
            }
        }

        foreach ($result['constants'] as $name => &$constants) {
            foreach ($constants['types'] as &$type) {
                $doResolveTypes($type);
            }
        }
    }
}

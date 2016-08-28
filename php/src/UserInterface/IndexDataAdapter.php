<?php

namespace PhpIntegrator\UserInterface;

use ArrayObject;
use UnexpectedValueException;

use PhpIntegrator\Analysis\Relations;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

/**
 * Adapts and resolves data from the index as needed to receive an appropriate output data format.
 */
class IndexDataAdapter
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
     * @param TypeAnalyzer                              $typeAnalyzer
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
        IndexDataAdapterProviderInterface $storage,
        TypeAnalyzer $typeAnalyzer
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
        $this->typeAnalyzer = $typeAnalyzer;
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
    public function getClasslikeInfo($fqcn)
    {
        $this->resolutionStack = [];

        return $this->getCheckedClasslikeInfo($fqcn, '')->getArrayCopy();
    }

    /**
     * @param string $fqcn
     * @param string $originFqcn
     *
     * @return ArrayObject
     */
    protected function getCheckedClasslikeInfo($fqcn, $originFqcn)
    {
        if (in_array($fqcn, $this->resolutionStack)) {
            throw new CircularDependencyException(
                "Circular dependency detected from {$originFqcn} to {$fqcn}!"
            );
        }

        $this->resolutionStack[] = $fqcn;

        $data = $this->getUncheckedClasslikeInfo($fqcn);

        array_pop($this->resolutionStack);

        return $data;
    }

    /**
     * @param string $fqcn
     *
     * @throws UnexpectedValueException
     *
     * @return ArrayObject
     */
    protected function getUncheckedClasslikeInfo($fqcn)
    {
        $rawInfo = $this->storage->getStructureRawInfo($fqcn);

        if (!$rawInfo) {
            throw new UnexpectedValueException('The structural element "' . $fqcn . '" was not found!');
        }

        $id = $rawInfo['id'];

        $classlike = $this->fetchFlatClasslikeInfo(
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

        $traitAliases = [];
        $traitPrecedences = [];

        if (!empty($classlike['directTraits'])) {
            $traitAliases = $this->storage->getStructureTraitAliasesAssoc($id);
            $traitPrecedences = $this->storage->getStructureTraitPrecedencesAssoc($id);
        }

        $this->resolveClasslikeRelations($classlike, $traitAliases, $traitPrecedences);
        $this->resolveSpecialTypes($classlike, $classlike['name']);

        return $classlike;
    }

    /**
     * @param ArrayObject $classlike
     * @param array       $traitAliases
     * @param array       $traitPrecedences
     */
    protected function resolveClasslikeRelations(ArrayObject $classlike, array $traitAliases, array $traitPrecedences)
    {
        foreach ($classlike['directParents'] as $parent) {
            $parentInfo = $this->getCheckedClasslikeInfo($parent, $classlike['name']);

            $this->inheritanceResolver->resolveInheritanceOf($parentInfo, $classlike);
        }

        foreach ($classlike['directInterfaces'] as $interface) {
            $interfaceInfo = $this->getCheckedClasslikeInfo($interface, $classlike['name']);

            $this->interfaceImplementationResolver->resolveImplementationOf($interfaceInfo, $classlike);
        }

        foreach ($classlike['directTraits'] as $trait) {
            $traitInfo = $this->getCheckedClasslikeInfo($trait, $classlike['name']);

            $this->traitUsageResolver->resolveUseOf($traitInfo, $classlike, $traitAliases, $traitPrecedences);
        }
    }

    /**
     * Builds information about a classlike in a flat structure, meaning it doesn't resolve any inheritance or interface
     * implementations. Instead, it will only list members and data directly relevant to the classlike.
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
    public function fetchFlatClasslikeInfo(
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
        $typeAnalyzer = $this->typeAnalyzer;

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

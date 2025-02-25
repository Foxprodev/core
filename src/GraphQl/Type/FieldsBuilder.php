<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\GraphQl\Type;

use ApiPlatform\Core\DataProvider\Pagination;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Core\Util\Inflector;
use ApiPlatform\Exception\OperationNotFoundException;
use ApiPlatform\GraphQl\Resolver\Factory\ResolverFactoryInterface;
use ApiPlatform\GraphQl\Type\Definition\TypeInterface;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Operation;
use ApiPlatform\Metadata\GraphQl\Subscription;
use ApiPlatform\Metadata\Operation as ApiOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\Type as GraphQLType;
use GraphQL\Type\Definition\WrappingType;
use Psr\Container\ContainerInterface;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\NameConverter\AdvancedNameConverterInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Builds the GraphQL fields.
 *
 * @experimental
 *
 * @author Alan Poulain <contact@alanpoulain.eu>
 */
final class FieldsBuilder implements FieldsBuilderInterface
{
    private $propertyNameCollectionFactory;
    private $propertyMetadataFactory;
    private $resourceMetadataCollectionFactory;
    private $typesContainer;
    private $typeBuilder;
    private $typeConverter;
    private $itemResolverFactory;
    private $collectionResolverFactory;
    private $itemMutationResolverFactory;
    private $itemSubscriptionResolverFactory;
    private $filterLocator;
    private $pagination;
    private $nameConverter;
    private $nestingSeparator;

    public function __construct(PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory, TypesContainerInterface $typesContainer, TypeBuilderInterface $typeBuilder, TypeConverterInterface $typeConverter, ResolverFactoryInterface $itemResolverFactory, ResolverFactoryInterface $collectionResolverFactory, ResolverFactoryInterface $itemMutationResolverFactory, ResolverFactoryInterface $itemSubscriptionResolverFactory, ContainerInterface $filterLocator, Pagination $pagination, ?NameConverterInterface $nameConverter, string $nestingSeparator)
    {
        $this->propertyNameCollectionFactory = $propertyNameCollectionFactory;
        $this->propertyMetadataFactory = $propertyMetadataFactory;
        $this->resourceMetadataCollectionFactory = $resourceMetadataCollectionFactory;
        $this->typesContainer = $typesContainer;
        $this->typeBuilder = $typeBuilder;
        $this->typeConverter = $typeConverter;
        $this->itemResolverFactory = $itemResolverFactory;
        $this->collectionResolverFactory = $collectionResolverFactory;
        $this->itemMutationResolverFactory = $itemMutationResolverFactory;
        $this->itemSubscriptionResolverFactory = $itemSubscriptionResolverFactory;
        $this->filterLocator = $filterLocator;
        $this->pagination = $pagination;
        $this->nameConverter = $nameConverter;
        $this->nestingSeparator = $nestingSeparator;
    }

    /**
     * {@inheritdoc}
     */
    public function getNodeQueryFields(): array
    {
        return [
            'type' => $this->typeBuilder->getNodeInterface(),
            'args' => [
                'id' => ['type' => GraphQLType::nonNull(GraphQLType::id())],
            ],
            'resolve' => ($this->itemResolverFactory)(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getItemQueryFields(string $resourceClass, Operation $operation, string $queryName, array $configuration): array
    {
        $shortName = $operation->getShortName();
        $fieldName = lcfirst('item_query' === $queryName ? $shortName : $queryName.$shortName);
        $description = $operation->getDescription();
        $deprecationReason = $operation->getDeprecationReason();

        if ($fieldConfiguration = $this->getResourceFieldConfiguration(null, $description, $deprecationReason, new Type(Type::BUILTIN_TYPE_OBJECT, true, $resourceClass), $resourceClass, false, $queryName)) {
            $args = $this->resolveResourceArgs($configuration['args'] ?? [], $queryName, $shortName);
            $configuration['args'] = $args ?: $configuration['args'] ?? ['id' => ['type' => GraphQLType::nonNull(GraphQLType::id())]];

            return [$fieldName => array_merge($fieldConfiguration, $configuration)];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectionQueryFields(string $resourceClass, Operation $operation, string $queryName, array $configuration): array
    {
        $shortName = $operation->getShortName();
        $fieldName = lcfirst('collection_query' === $queryName ? $shortName : $queryName.$shortName);
        $description = $operation->getDescription();
        $deprecationReason = $operation->getDeprecationReason();

        if ($fieldConfiguration = $this->getResourceFieldConfiguration(null, $description, $deprecationReason, new Type(Type::BUILTIN_TYPE_OBJECT, false, null, true, null, new Type(Type::BUILTIN_TYPE_OBJECT, false, $resourceClass)), $resourceClass, false, $queryName)) {
            $args = $this->resolveResourceArgs($configuration['args'] ?? [], $queryName, $shortName);
            $configuration['args'] = $args ?: $configuration['args'] ?? $fieldConfiguration['args'];

            return [Inflector::pluralize($fieldName) => array_merge($fieldConfiguration, $configuration)];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getMutationFields(string $resourceClass, Operation $operation, string $mutationName): array
    {
        $mutationFields = [];
        $shortName = $operation->getShortName();
        $resourceType = new Type(Type::BUILTIN_TYPE_OBJECT, true, $resourceClass);
        $description = $operation->getDescription() ?? ucfirst("{$mutationName}s a $shortName.");
        $deprecationReason = $operation->getDeprecationReason();

        if ($fieldConfiguration = $this->getResourceFieldConfiguration(null, $description, $deprecationReason, $resourceType, $resourceClass, false, $mutationName)) {
            $fieldConfiguration['args'] += ['input' => $this->getResourceFieldConfiguration(null, null, $deprecationReason, $resourceType, $resourceClass, true, $mutationName)];
        }

        $mutationFields[$mutationName.$shortName] = $fieldConfiguration ?? [];

        return $mutationFields;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptionFields(string $resourceClass, Operation $operation, string $subscriptionName): array
    {
        $subscriptionFields = [];
        $shortName = $operation->getShortName();
        $resourceType = new Type(Type::BUILTIN_TYPE_OBJECT, true, $resourceClass);
        $description = $operation->getDescription() ?? sprintf('Subscribes to the action event of a %s.', $shortName);
        $deprecationReason = $operation->getDeprecationReason();

        if ($fieldConfiguration = $this->getResourceFieldConfiguration(null, $description, $deprecationReason, $resourceType, $resourceClass, false, $subscriptionName)) {
            $fieldConfiguration['args'] += ['input' => $this->getResourceFieldConfiguration(null, null, $deprecationReason, $resourceType, $resourceClass, true, $subscriptionName)];
        }

        if (!$fieldConfiguration) {
            return [];
        }

        // TODO: 3.0 change this
        if ('update_subscription' === $subscriptionName) {
            $subscriptionName = 'update';
        }

        $subscriptionFields[$subscriptionName.$operation->getShortName().'Subscribe'] = $fieldConfiguration;

        return $subscriptionFields;
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceObjectTypeFields(?string $resourceClass, Operation $operation, bool $input, string $operationName, int $depth = 0, ?array $ioMetadata = null): array
    {
        $fields = [];
        $idField = ['type' => GraphQLType::nonNull(GraphQLType::id())];
        $clientMutationId = GraphQLType::string();
        $clientSubscriptionId = GraphQLType::string();

        if (null !== $ioMetadata && \array_key_exists('class', $ioMetadata) && null === $ioMetadata['class']) {
            if ($input) {
                return ['clientMutationId' => $clientMutationId];
            }

            return [];
        }

        if ($operation instanceof Subscription && $input) {
            return [
                'id' => $idField,
                'clientSubscriptionId' => $clientSubscriptionId,
            ];
        }

        if ('delete' === $operationName) {
            $fields = [
                'id' => $idField,
            ];

            if ($input) {
                $fields['clientMutationId'] = $clientMutationId;
            }

            return $fields;
        }

        if (!$input || 'create' !== $operationName) {
            $fields['id'] = $idField;
        }

        ++$depth; // increment the depth for the call to getResourceFieldConfiguration.

        if (null !== $resourceClass) {
            foreach ($this->propertyNameCollectionFactory->create($resourceClass) as $property) {
                $context = [
                    'normalization_groups' => $operation->getNormalizationContext()['groups'] ?? null,
                    'denormalization_groups' => $operation->getDenormalizationContext()['groups'] ?? null,
                ];
                $propertyMetadata = $this->propertyMetadataFactory->create($resourceClass, $property, $context);

                if (
                    null === ($propertyType = $propertyMetadata->getType())
                    || (!$input && false === $propertyMetadata->isReadable())
                    || ($input && $operation instanceof Mutation && false === $propertyMetadata->isWritable())
                ) {
                    continue;
                }

                if ($fieldConfiguration = $this->getResourceFieldConfiguration($property, $propertyMetadata->getDescription(), $propertyMetadata->getAttribute('deprecation_reason', null), $propertyType, $resourceClass, $input, $operationName, $depth, null !== $propertyMetadata->getAttribute('security'))) {
                    $fields['id' === $property ? '_id' : $this->normalizePropertyName($property, $resourceClass)] = $fieldConfiguration;
                }
            }
        }

        if ($operation instanceof Mutation && $input) {
            $fields['clientMutationId'] = $clientMutationId;
        }

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveResourceArgs(array $args, string $operationName, string $shortName): array
    {
        foreach ($args as $id => $arg) {
            if (!isset($arg['type'])) {
                throw new \InvalidArgumentException(sprintf('The argument "%s" of the custom operation "%s" in %s needs a "type" option.', $id, $operationName, $shortName));
            }

            $args[$id]['type'] = $this->typeConverter->resolveType($arg['type']);
        }

        return $args;
    }

    /**
     * Get the field configuration of a resource.
     *
     * @see http://webonyx.github.io/graphql-php/type-system/object-types/
     */
    private function getResourceFieldConfiguration(?string $property, ?string $fieldDescription, ?string $deprecationReason, Type $type, string $rootResource, bool $input, string $operationName, int $depth = 0, bool $forceNullable = false): ?array
    {
        try {
            if (
                $this->typeBuilder->isCollection($type) &&
                $collectionValueType = method_exists(Type::class, 'getCollectionValueTypes') ? ($type->getCollectionValueTypes()[0] ?? null) : $type->getCollectionValueType()
            ) {
                $resourceClass = $collectionValueType->getClassName();
            } else {
                $resourceClass = $type->getClassName();
            }

            if (null === $graphqlType = $this->convertType($type, $input, $operationName, $resourceClass ?? '', $rootResource, $property, $depth, $forceNullable)) {
                return null;
            }

            $graphqlWrappedType = $graphqlType instanceof WrappingType ? $graphqlType->getWrappedType(true) : $graphqlType;
            $isStandardGraphqlType = \in_array($graphqlWrappedType, GraphQLType::getStandardTypes(), true);
            if ($isStandardGraphqlType) {
                $resourceClass = '';
            }

            $resourceMetadataCollection = $operation = null;
            if (!empty($resourceClass)) {
                $resourceMetadataCollection = $this->resourceMetadataCollectionFactory->create($resourceClass);
                try {
                    $operation = $resourceMetadataCollection->getGraphQlOperation($operationName);
                } catch (OperationNotFoundException $e) {
                }
            }

            // Check mercure attribute if it's a subscription at the root level.
            if ($operation instanceof Subscription && null === $property && !$operation->getMercure()) {
                return null;
            }

            $args = [];
            if (!$input && !$operation instanceof Mutation && !$operation instanceof Subscription && !$isStandardGraphqlType && $this->typeBuilder->isCollection($type)) {
                if ($this->pagination->isGraphQlEnabled($resourceClass, $operationName)) {
                    $args = $this->getGraphQlPaginationArgs($resourceClass, $operationName);
                }

                // Look for the collection operation if it exists
                if (!$operation && $resourceMetadataCollection) {
                    try {
                        $operation = $resourceMetadataCollection->getOperation(null, true);
                    } catch (OperationNotFoundException $e) {
                    }
                }

                $args = $this->getFilterArgs($args, $resourceClass, $operation, $rootResource, $property, $operationName, $depth);
            }

            if ($isStandardGraphqlType || $input) {
                $resolve = null;
            } elseif (($operation instanceof Mutation || $operation instanceof Subscription) && $depth <= 0) {
                $resolve = $operation instanceof Mutation ? ($this->itemMutationResolverFactory)($resourceClass, $rootResource, $operationName) : ($this->itemSubscriptionResolverFactory)($resourceClass, $rootResource, $operationName);
            } elseif ($this->typeBuilder->isCollection($type)) {
                $resolve = ($this->collectionResolverFactory)($resourceClass, $rootResource, $operationName);
            } else {
                $resolve = ($this->itemResolverFactory)($resourceClass, $rootResource, $operationName);
            }

            return [
                'type' => $graphqlType,
                'description' => $fieldDescription,
                'args' => $args,
                'resolve' => $resolve,
                'deprecationReason' => $deprecationReason,
            ];
        } catch (InvalidTypeException $e) {
            // just ignore invalid types
        }

        return null;
    }

    private function getGraphQlPaginationArgs(string $resourceClass, string $queryName): array
    {
        $paginationType = $this->pagination->getGraphQlPaginationType($resourceClass, $queryName);

        if ('cursor' === $paginationType) {
            return [
                'first' => [
                    'type' => GraphQLType::int(),
                    'description' => 'Returns the first n elements from the list.',
                ],
                'last' => [
                    'type' => GraphQLType::int(),
                    'description' => 'Returns the last n elements from the list.',
                ],
                'before' => [
                    'type' => GraphQLType::string(),
                    'description' => 'Returns the elements in the list that come before the specified cursor.',
                ],
                'after' => [
                    'type' => GraphQLType::string(),
                    'description' => 'Returns the elements in the list that come after the specified cursor.',
                ],
            ];
        }

        $paginationOptions = $this->pagination->getOptions();

        $args = [
            $paginationOptions['page_parameter_name'] => [
                'type' => GraphQLType::int(),
                'description' => 'Returns the current page.',
            ],
        ];

        if ($paginationOptions['client_items_per_page']) {
            $args[$paginationOptions['items_per_page_parameter_name']] = [
                'type' => GraphQLType::int(),
                'description' => 'Returns the number of items per page.',
            ];
        }

        return $args;
    }

    /**
     * @param Operation|ApiOperation|null $operation
     */
    private function getFilterArgs(array $args, ?string $resourceClass, $operation, string $rootResource, ?string $property, string $operationName, int $depth): array
    {
        if (null === $operation || null === $resourceClass) {
            return $args;
        }

        foreach ($operation->getFilters() as $filterId) {
            if (null === $this->filterLocator || !$this->filterLocator->has($filterId)) {
                continue;
            }

            foreach ($this->filterLocator->get($filterId)->getDescription($resourceClass) as $key => $value) {
                $nullable = isset($value['required']) ? !$value['required'] : true;
                $filterType = \in_array($value['type'], Type::$builtinTypes, true) ? new Type($value['type'], $nullable) : new Type('object', $nullable, $value['type']);
                $graphqlFilterType = $this->convertType($filterType, false, $operationName, $resourceClass, $rootResource, $property, $depth);

                if ('[]' === substr($key, -2)) {
                    $graphqlFilterType = GraphQLType::listOf($graphqlFilterType);
                    $key = substr($key, 0, -2).'_list';
                }

                /** @var string $key */
                $key = str_replace('.', $this->nestingSeparator, $key);

                parse_str($key, $parsed);
                if (\array_key_exists($key, $parsed) && \is_array($parsed[$key])) {
                    $parsed = [$key => ''];
                }
                array_walk_recursive($parsed, static function (&$value) use ($graphqlFilterType) {
                    $value = $graphqlFilterType;
                });
                $args = $this->mergeFilterArgs($args, $parsed, $operation, $key);
            }
        }

        return $this->convertFilterArgsToTypes($args);
    }

    /**
     * @param Operation|ApiOperation|null $operation
     */
    private function mergeFilterArgs(array $args, array $parsed, $operation = null, $original = ''): array
    {
        foreach ($parsed as $key => $value) {
            // Never override keys that cannot be merged
            if (isset($args[$key]) && !\is_array($args[$key])) {
                continue;
            }

            if (\is_array($value)) {
                $value = $this->mergeFilterArgs($args[$key] ?? [], $value);
                if (!isset($value['#name'])) {
                    $name = (false === $pos = strrpos($original, '[')) ? $original : substr($original, 0, (int) $pos);
                    $value['#name'] = ($operation ? $operation->getShortName() : '').'Filter_'.strtr($name, ['[' => '_', ']' => '', '.' => '__']);
                }
            }

            $args[$key] = $value;
        }

        return $args;
    }

    private function convertFilterArgsToTypes(array $args): array
    {
        foreach ($args as $key => $value) {
            if (strpos($key, '.')) {
                // Declare relations/nested fields in a GraphQL compatible syntax.
                $args[str_replace('.', $this->nestingSeparator, $key)] = $value;
                unset($args[$key]);
            }
        }

        foreach ($args as $key => $value) {
            if (!\is_array($value) || !isset($value['#name'])) {
                continue;
            }

            $name = $value['#name'];

            if ($this->typesContainer->has($name)) {
                $args[$key] = $this->typesContainer->get($name);
                continue;
            }

            unset($value['#name']);

            $filterArgType = GraphQLType::listOf(new InputObjectType([
                'name' => $name,
                'fields' => $this->convertFilterArgsToTypes($value),
            ]));

            $this->typesContainer->set($name, $filterArgType);

            $args[$key] = $filterArgType;
        }

        return $args;
    }

    /**
     * Converts a built-in type to its GraphQL equivalent.
     *
     * @throws InvalidTypeException
     */
    private function convertType(Type $type, bool $input, string $operationName, string $resourceClass, string $rootResource, ?string $property, int $depth, bool $forceNullable = false)
    {
        $graphqlType = $this->typeConverter->convertType($type, $input, $operationName, $resourceClass, $rootResource, $property, $depth);

        if (null === $graphqlType) {
            throw new InvalidTypeException(sprintf('The type "%s" is not supported.', $type->getBuiltinType()));
        }

        if (\is_string($graphqlType)) {
            if (!$this->typesContainer->has($graphqlType)) {
                throw new InvalidTypeException(sprintf('The GraphQL type %s is not valid. Valid types are: %s. Have you registered this type by implementing %s?', $graphqlType, implode(', ', array_keys($this->typesContainer->all())), TypeInterface::class));
            }

            $graphqlType = $this->typesContainer->get($graphqlType);
        }

        if ($this->typeBuilder->isCollection($type)) {
            return $this->pagination->isGraphQlEnabled($resourceClass, $operationName) && !$input ? $this->typeBuilder->getResourcePaginatedCollectionType($graphqlType, $resourceClass, $operationName) : GraphQLType::listOf($graphqlType);
        }

        $operation = null;
        $resourceClass = '' === $resourceClass ? $rootResource : $resourceClass;
        if ($resourceClass) {
            $resourceMetadataCollection = $this->resourceMetadataCollectionFactory->create($resourceClass);
            try {
                $operation = $resourceMetadataCollection->getGraphQlOperation($operationName);
            } catch (OperationNotFoundException $e) {
            }
        }

        return $forceNullable || !$graphqlType instanceof NullableType || $type->isNullable() || ($operation instanceof Mutation && 'update' === $operationName)
            ? $graphqlType
            : GraphQLType::nonNull($graphqlType);
    }

    private function normalizePropertyName(string $property, string $resourceClass): string
    {
        if (null === $this->nameConverter) {
            return $property;
        }
        if ($this->nameConverter instanceof AdvancedNameConverterInterface) {
            return $this->nameConverter->normalize($property, $resourceClass);
        }

        return $this->nameConverter->normalize($property);
    }
}

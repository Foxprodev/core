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

namespace ApiPlatform\Core\Tests\GraphQl\Resolver\Stage;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\SubresourceDataProviderInterface;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use ApiPlatform\Core\Tests\ProphecyTrait;
use ApiPlatform\GraphQl\Resolver\Stage\ReadStage;
use ApiPlatform\GraphQl\Serializer\ItemNormalizer;
use ApiPlatform\GraphQl\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use GraphQL\Type\Definition\ResolveInfo;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Alan Poulain <contact@alanpoulain.eu>
 */
class ReadStageTest extends TestCase
{
    use ExpectDeprecationTrait;
    use ProphecyTrait;

    /** @var ReadStage */
    private $readStage;
    private $resourceMetadataCollectionFactoryProphecy;
    private $iriConverterProphecy;
    private $collectionDataProviderProphecy;
    private $subresourceDataProviderProphecy;
    private $serializerContextBuilderProphecy;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->resourceMetadataCollectionFactoryProphecy = $this->prophesize(ResourceMetadataCollectionFactoryInterface::class);
        $this->iriConverterProphecy = $this->prophesize(IriConverterInterface::class);
        $this->collectionDataProviderProphecy = $this->prophesize(ContextAwareCollectionDataProviderInterface::class);
        $this->subresourceDataProviderProphecy = $this->prophesize(SubresourceDataProviderInterface::class);
        $this->serializerContextBuilderProphecy = $this->prophesize(SerializerContextBuilderInterface::class);

        $this->readStage = new ReadStage(
            $this->resourceMetadataCollectionFactoryProphecy->reveal(),
            $this->iriConverterProphecy->reveal(),
            $this->collectionDataProviderProphecy->reveal(),
            $this->subresourceDataProviderProphecy->reveal(),
            $this->serializerContextBuilderProphecy->reveal(),
            '_'
        );
    }

    /**
     * @dataProvider contextProvider
     *
     * @param object|iterable|null $expectedResult
     */
    public function testApplyDisabled(array $context, $expectedResult): void
    {
        $operationName = 'item_query';
        $resourceClass = 'myResource';
        $resourceMetadata = (new ApiResource())->withGraphQlOperations([
            $operationName => (new Query())->withRead(false),
        ]);
        $this->resourceMetadataCollectionFactoryProphecy->create($resourceClass)->willReturn(new ResourceMetadataCollection($resourceClass, [$resourceMetadata]));

        $result = ($this->readStage)($resourceClass, null, $operationName, $context);

        $this->assertSame($expectedResult, $result);
    }

    public function contextProvider(): array
    {
        return [
            'item context' => [['is_collection' => false], null],
            'collection context' => [['is_collection' => true], []],
        ];
    }

    /**
     * @dataProvider itemProvider
     *
     * @param object|null $item
     * @param object|null $expectedResult
     */
    public function testApplyItem(?string $identifier, $item, bool $throwNotFound, $expectedResult): void
    {
        $operationName = 'item_query';
        $resourceClass = 'myResource';
        $info = $this->prophesize(ResolveInfo::class)->reveal();
        $context = [
            'is_collection' => false,
            'is_mutation' => false,
            'is_subscription' => false,
            'args' => ['id' => $identifier],
            'info' => $info,
        ];
        $this->resourceMetadataCollectionFactoryProphecy->create($resourceClass)->willReturn(new ResourceMetadataCollection($resourceClass, [new ApiResource()]));

        $normalizationContext = ['normalization' => true];
        $this->serializerContextBuilderProphecy->create($resourceClass, $operationName, $context, true)->shouldBeCalled()->willReturn($normalizationContext);

        if ($throwNotFound) {
            $this->iriConverterProphecy->getItemFromIri($identifier, $normalizationContext)->willThrow(new ItemNotFoundException());
        } else {
            $this->iriConverterProphecy->getItemFromIri($identifier, $normalizationContext)->willReturn($item);
        }

        $result = ($this->readStage)($resourceClass, null, $operationName, $context);

        $this->assertSame($expectedResult, $result);
    }

    public function itemProvider(): array
    {
        $item = new \stdClass();

        return [
            'no identifier' => [null, $item, false, null],
            'identifier' => ['identifier', $item, false, $item],
            'identifier not found' => ['identifier_not_found', $item, true, null],
        ];
    }

    /**
     * @dataProvider itemMutationOrSubscriptionProvider
     *
     * @param object|null $item
     * @param object|null $expectedResult
     */
    public function testApplyMutationOrSubscription(bool $isMutation, bool $isSubscription, string $resourceClass, ?string $identifier, $item, bool $throwNotFound, $expectedResult, ?string $expectedExceptionClass = null, ?string $expectedExceptionMessage = null): void
    {
        $operationName = 'create';
        $info = $this->prophesize(ResolveInfo::class)->reveal();
        $context = [
            'is_collection' => false,
            'is_mutation' => $isMutation,
            'is_subscription' => $isSubscription,
            'args' => ['input' => ['id' => $identifier]],
            'info' => $info,
        ];
        $this->resourceMetadataCollectionFactoryProphecy->create($resourceClass)->willReturn(new ResourceMetadataCollection($resourceClass, [(new ApiResource())->withGraphQlOperations([$operationName => (new Query())->withShortName('shortName')])]));

        $normalizationContext = ['normalization' => true];
        $this->serializerContextBuilderProphecy->create($resourceClass, $operationName, $context, true)->shouldBeCalled()->willReturn($normalizationContext);

        if ($throwNotFound) {
            $this->iriConverterProphecy->getItemFromIri($identifier, $normalizationContext)->willThrow(new ItemNotFoundException());
        } else {
            $this->iriConverterProphecy->getItemFromIri($identifier, $normalizationContext)->willReturn($item);
        }

        if ($expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $result = ($this->readStage)($resourceClass, null, $operationName, $context);

        $this->assertSame($expectedResult, $result);
    }

    public function itemMutationOrSubscriptionProvider(): array
    {
        $item = new \stdClass();

        return [
            'no identifier' => [true, false, 'myResource', null, $item, false, null],
            'identifier' => [true, false, 'stdClass', 'identifier', $item, false, $item],
            'identifier bad item' => [true, false, 'myResource', 'identifier', $item, false, $item, \UnexpectedValueException::class, 'Item "identifier" did not match expected type "shortName".'],
            'identifier not found' => [true, false, 'myResource', 'identifier_not_found', $item, true, null, NotFoundHttpException::class, 'Item "identifier_not_found" not found.'],
            'no identifier (subscription)' => [false, true, 'myResource', null, $item, false, null],
            'identifier (subscription)' => [false, true, 'stdClass', 'identifier', $item, false, $item],
        ];
    }

    /**
     * @dataProvider collectionProvider
     */
    public function testApplyCollection(array $args, ?string $rootClass, ?array $source, array $expectedFilters, iterable $expectedResult): void
    {
        $operationName = 'collection_query';
        $resourceClass = 'myResource';
        $info = $this->prophesize(ResolveInfo::class)->reveal();
        $fieldName = 'subresource';
        $info->fieldName = $fieldName;
        $context = [
            'is_collection' => true,
            'is_mutation' => false,
            'is_subscription' => false,
            'args' => $args,
            'info' => $info,
            'source' => $source,
        ];
        $this->resourceMetadataCollectionFactoryProphecy->create($resourceClass)->willReturn(new ResourceMetadataCollection($resourceClass, [new ApiResource()]));

        $normalizationContext = ['normalization' => true];
        $this->serializerContextBuilderProphecy->create($resourceClass, $operationName, $context, true)->shouldBeCalled()->willReturn($normalizationContext);

        $this->subresourceDataProviderProphecy->getSubresource($resourceClass, ['id' => 3], $normalizationContext + ['filters' => $expectedFilters, 'property' => $fieldName, 'identifiers' => ['id' => [$resourceClass, 'id']], 'collection' => true], $operationName)->willReturn(['subresource']);

        $this->collectionDataProviderProphecy->getCollection($resourceClass, $operationName, $normalizationContext + ['filters' => $expectedFilters])->willReturn([]);

        $result = ($this->readStage)($resourceClass, $rootClass, $operationName, $context);

        $this->assertSame($expectedResult, $result);
    }

    public function testPreserveOrderOfOrderFiltersIfNested(): void
    {
        $operationName = 'collection_query';
        $resourceClass = 'myResource';
        $info = $this->prophesize(ResolveInfo::class)->reveal();
        $fieldName = 'subresource';
        $info->fieldName = $fieldName;
        $context = [
            'is_collection' => true,
            'is_mutation' => false,
            'is_subscription' => false,
            'args' => [
                'order' => [
                    'some_field' => 'ASC',
                    'localField' => 'ASC',
                ],
            ],
            'info' => $info,
            'source' => null,
        ];
        $this->resourceMetadataCollectionFactoryProphecy->create($resourceClass)->willReturn(new ResourceMetadataCollection($resourceClass, [new ApiResource()]));

        $normalizationContext = ['normalization' => true];
        $this->serializerContextBuilderProphecy->create($resourceClass, $operationName, $context, true)->shouldBeCalled()->willReturn($normalizationContext);

        ($this->readStage)($resourceClass, $resourceClass, $operationName, $context);

        $this->collectionDataProviderProphecy->getCollection($resourceClass, $operationName, Argument::that(function ($args) {
            // Prophecy does not check the order of items in associative arrays. Checking if some.field comes first manually
            return
            array_search('some.field', array_keys($args['filters']['order']), true) <
            array_search('localField', array_keys($args['filters']['order']), true);
        }))->shouldHaveBeenCalled();
    }

    public function collectionProvider(): array
    {
        return [
            'no root class' => [[], null, null, [], []],
            'nominal' => [
                ['filter_list' => 'filtered', 'filter_field_list' => ['filtered1', 'filtered2']],
                'myResource',
                null,
                ['filter_list' => 'filtered', 'filter_field_list' => ['filtered1', 'filtered2'], 'filter.list' => 'filtered', 'filter_field' => ['filtered1', 'filtered2'], 'filter.field' => ['filtered1', 'filtered2']],
                [],
            ],
            'with array filter syntax' => [
                ['filter' => [['filterArg1' => 'filterValue1'], ['filterArg2' => 'filterValue2']]],
                'myResource',
                null,
                ['filter' => ['filterArg1' => 'filterValue1', 'filterArg2' => 'filterValue2']],
                [],
            ],
            'with subresource' => [
                [],
                'myResource',
                ['subresource' => [], ItemNormalizer::ITEM_IDENTIFIERS_KEY => ['id' => 3], ItemNormalizer::ITEM_RESOURCE_CLASS_KEY => 'myResource'],
                [],
                ['subresource'],
            ],
        ];
    }

    /**
     * @group legacy
     */
    public function testApplyCollectionWithDeprecatedFilterSyntax(): void
    {
        $operationName = 'collection_query';
        $resourceClass = 'myResource';
        $info = $this->prophesize(ResolveInfo::class)->reveal();
        $fieldName = 'subresource';
        $info->fieldName = $fieldName;
        $context = [
            'is_collection' => true,
            'is_mutation' => false,
            'is_subscription' => false,
            'args' => ['filter' => [['filterArg1' => 'filterValue1', 'filterArg2' => 'filterValue2']]],
            'info' => $info,
            'source' => null,
        ];
        $this->resourceMetadataCollectionFactoryProphecy->create($resourceClass)->willReturn(new ResourceMetadataCollection($resourceClass, [new ApiResource()]));

        $normalizationContext = ['normalization' => true];
        $this->serializerContextBuilderProphecy->create($resourceClass, $operationName, $context, true)->shouldBeCalled()->willReturn($normalizationContext);

        $this->collectionDataProviderProphecy->getCollection($resourceClass, $operationName, $normalizationContext + ['filters' => ['filter' => ['filterArg1' => 'filterValue1', 'filterArg2' => 'filterValue2']]])->willReturn([]);

        $this->expectDeprecation('The filter syntax "filter: {filterArg1: "filterValue1", filterArg2: "filterValue2"}" is deprecated since API Platform 2.6, use the following syntax instead: "filter: [{filterArg1: "filterValue1"}, {filterArg2: "filterValue2"}]".');

        $result = ($this->readStage)($resourceClass, 'myResource', $operationName, $context);

        $this->assertSame([], $result);
    }
}

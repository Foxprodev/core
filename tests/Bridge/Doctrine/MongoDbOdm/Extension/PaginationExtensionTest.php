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

namespace ApiPlatform\Core\Tests\Bridge\Doctrine\MongoDbOdm\Extension;

use ApiPlatform\Core\Bridge\Doctrine\MongoDbOdm\Extension\PaginationExtension;
use ApiPlatform\Core\Bridge\Doctrine\MongoDbOdm\Paginator;
use ApiPlatform\Core\DataProvider\Pagination;
use ApiPlatform\Core\DataProvider\PaginatorInterface;
use ApiPlatform\Core\DataProvider\PartialPaginatorInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\Test\DoctrineMongoDbOdmSetup;
use ApiPlatform\Core\Tests\ProphecyTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Tests\Fixtures\TestBundle\Document\Dummy;
use Doctrine\ODM\MongoDB\Aggregation\Builder;
use Doctrine\ODM\MongoDB\Aggregation\Stage\Count;
use Doctrine\ODM\MongoDB\Aggregation\Stage\Facet;
use Doctrine\ODM\MongoDB\Aggregation\Stage\MatchStage as AggregationMatch;
use Doctrine\ODM\MongoDB\Aggregation\Stage\Skip;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Iterator\Iterator;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @author Alan Poulain <contact@alanpoulain.eu>
 *
 * @group mongodb
 */
class PaginationExtensionTest extends TestCase
{
    use ProphecyTrait;

    private $managerRegistryProphecy;
    private $resourceMetadataFactoryProphecy;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
        $this->resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataCollectionFactoryInterface::class);
    }

    public function testApplyToCollection()
    {
        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => (new GetCollection())->withPaginationEnabled(true)->withPaginationClientEnabled(true)->withPaginationItemsPerPage(40)])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory, [
            'page_parameter_name' => '_page',
        ]);

        $aggregationBuilderProphecy = $this->mockAggregationBuilder(40, 40);

        $context = ['filters' => ['pagination' => true, 'itemsPerPage' => 20, '_page' => 2]];

        $extension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $extension->applyToCollection($aggregationBuilderProphecy->reveal(), 'Foo', 'get', $context);
    }

    public function testApplyToCollectionWithItemPerPageZero()
    {
        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => (new GetCollection())->withPaginationEnabled(true)->withPaginationClientEnabled(true)->withPaginationItemsPerPage(0)])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory, [
            'items_per_page' => 0,
            'page_parameter_name' => '_page',
        ]);

        $aggregationBuilderProphecy = $this->mockAggregationBuilder(0, 0);

        $context = ['filters' => ['pagination' => true, 'itemsPerPage' => 0, '_page' => 1]];

        $extension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $extension->applyToCollection($aggregationBuilderProphecy->reveal(), 'Foo', 'get', $context);
    }

    public function testApplyToCollectionWithItemPerPageZeroAndPage2()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page should not be greater than 1 if limit is equal to 0');

        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => (new GetCollection())->withPaginationEnabled(true)->withPaginationClientEnabled(true)->withPaginationItemsPerPage(0)])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory, [
            'items_per_page' => 0,
            'page_parameter_name' => '_page',
        ]);

        $aggregationBuilderProphecy = $this->prophesize(Builder::class);
        $aggregationBuilderProphecy->facet()->shouldNotBeCalled();

        $context = ['filters' => ['pagination' => true, 'itemsPerPage' => 0, '_page' => 2]];

        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $extension->applyToCollection($aggregationBuilderProphecy->reveal(), 'Foo', 'get', $context);
    }

    public function testApplyToCollectionWithItemPerPageLessThan0()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit should not be less than 0');

        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => (new GetCollection())->withPaginationEnabled(true)->withPaginationClientEnabled(true)->withPaginationItemsPerPage(-20)])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory, [
            'items_per_page' => -20,
            'page_parameter_name' => '_page',
        ]);

        $aggregationBuilderProphecy = $this->prophesize(Builder::class);
        $aggregationBuilderProphecy->facet()->shouldNotBeCalled();

        $context = ['filters' => ['pagination' => true, 'itemsPerPage' => -20, '_page' => 2]];

        $extension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $extension->applyToCollection($aggregationBuilderProphecy->reveal(), 'Foo', 'get', $context);
    }

    public function testApplyToCollectionWithItemPerPageTooHigh()
    {
        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => (new GetCollection())->withPaginationEnabled(true)->withPaginationClientEnabled(true)->withPaginationClientItemsPerPage(true)])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory, [
            'page_parameter_name' => '_page',
            'maximum_items_per_page' => 300,
        ]);

        $aggregationBuilderProphecy = $this->mockAggregationBuilder(300, 300);

        $context = ['filters' => ['pagination' => true, 'itemsPerPage' => 301, '_page' => 2]];

        $extension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $extension->applyToCollection($aggregationBuilderProphecy->reveal(), 'Foo', 'get', $context);
    }

    public function testApplyToCollectionWithGraphql()
    {
        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => (new GetCollection())->withPaginationEnabled(true)->withPaginationClientEnabled(true)->withPaginationClientItemsPerPage(true)])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory);

        $aggregationBuilderProphecy = $this->mockAggregationBuilder(10, 5);

        $context = ['filters' => ['pagination' => true, 'first' => 5, 'after' => 'OQ=='], 'graphql_operation_name' => 'query'];

        $extension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $extension->applyToCollection($aggregationBuilderProphecy->reveal(), 'Foo', 'get', $context);
    }

    public function testApplyToCollectionWithGraphqlAndCountContext()
    {
        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => (new GetCollection())->withPaginationEnabled(true)->withPaginationClientEnabled(true)->withPaginationClientItemsPerPage(true)])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory);

        $aggregationBuilderProphecy = $this->mockAggregationBuilder(4, 5);
        $iteratorProphecy = $this->prophesize(Iterator::class);
        $iteratorProphecy->toArray()->willReturn([
            [
                'count' => 9,
            ],
        ]);
        $countProphecy = $this->prophesize(Count::class);
        $countProphecy->execute()->shouldBeCalled()->willReturn($iteratorProphecy->reveal());
        $aggregationBuilderProphecy->count('count')->shouldBeCalled()->willReturn($countProphecy->reveal());

        $context = ['filters' => ['pagination' => true, 'last' => 5], 'graphql_operation_name' => 'query'];

        $extension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $extension->applyToCollection($aggregationBuilderProphecy->reveal(), 'Foo', 'get', $context);
    }

    public function testApplyToCollectionNoFilters()
    {
        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => (new GetCollection())->withPaginationEnabled(true)])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory);

        $aggregationBuilderProphecy = $this->mockAggregationBuilder(0, 30);

        $context = [];

        $extension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $extension->applyToCollection($aggregationBuilderProphecy->reveal(), 'Foo', 'get', $context);
    }

    public function testApplyToCollectionPaginationDisabled()
    {
        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => new GetCollection()])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory, [
            'enabled' => false,
        ]);

        $aggregationBuilderProphecy = $this->prophesize(Builder::class);
        $aggregationBuilderProphecy->facet()->shouldNotBeCalled();

        $context = [];

        $extension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $extension->applyToCollection($aggregationBuilderProphecy->reveal(), 'Foo', 'get', $context);
    }

    public function testApplyToCollectionGraphQlPaginationDisabled()
    {
        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => new GetCollection()])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory, [], [
            'enabled' => false,
        ]);

        $aggregationBuilderProphecy = $this->prophesize(Builder::class);
        $aggregationBuilderProphecy->facet()->shouldNotBeCalled();

        $context = ['graphql_operation_name' => 'get'];

        $extension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $extension->applyToCollection($aggregationBuilderProphecy->reveal(), 'Foo', 'get', $context);
    }

    public function testApplyToCollectionWithMaximumItemsPerPage()
    {
        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => (new GetCollection())->withPaginationEnabled(true)->withPaginationClientEnabled(true)->withPaginationMaximumItemsPerPage(80)])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory, [
            'client_enabled' => true,
            'client_items_per_page' => true,
            'pagination_maximum_items_per_page' => 50,
        ]);

        $aggregationBuilderProphecy = $this->mockAggregationBuilder(0, 80);

        $context = ['filters' => ['pagination' => true, 'itemsPerPage' => 80, 'page' => 1]];

        $extension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $extension->applyToCollection($aggregationBuilderProphecy->reveal(), 'Foo', 'get', $context);
    }

    public function testSupportsResult()
    {
        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => (new GetCollection())->withPaginationEnabled(true)])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory);

        $extension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $this->assertTrue($extension->supportsResult('Foo', 'get'));
    }

    public function testSupportsResultClientNotAllowedToPaginate()
    {
        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => new GetCollection()])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory, [
            'enabled' => false,
            'client_enabled' => false,
        ]);

        $extension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $this->assertFalse($extension->supportsResult('Foo', 'get', ['filters' => ['pagination' => true]]));
    }

    public function testSupportsResultPaginationDisabled()
    {
        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => new GetCollection()])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory, [
            'enabled' => false,
        ]);

        $extension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $this->assertFalse($extension->supportsResult('Foo', 'get', ['filters' => ['enabled' => false]]));
    }

    public function testSupportsResultGraphQlPaginationDisabled()
    {
        $dummyMetadata = new ResourceMetadataCollection('Foo', [(new ApiResource())->withOperations(['get' => new GetCollection()])]);
        $this->resourceMetadataFactoryProphecy->create('Foo')->willReturn($dummyMetadata);
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory, [], [
            'enabled' => false,
        ]);

        $extension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );
        $this->assertFalse($extension->supportsResult('Foo', 'get', ['filters' => ['enabled' => false], 'graphql_operation_name' => 'query']));
    }

    public function testGetResult()
    {
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory);

        $fixturesPath = \dirname((string) (new \ReflectionClass(Dummy::class))->getFileName());
        $config = DoctrineMongoDbOdmSetup::createAnnotationMetadataConfiguration([$fixturesPath], true);
        $documentManager = DocumentManager::create(null, $config);

        $this->managerRegistryProphecy->getManagerForClass(Dummy::class)->willReturn($documentManager);

        $dummyMetadata = new ResourceMetadataCollection(Dummy::class, [(new ApiResource())->withOperations(['get' => new GetCollection()])]);
        $this->resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn($dummyMetadata);

        $iteratorProphecy = $this->prophesize(Iterator::class);
        $iteratorProphecy->toArray()->willReturn([
            [
                'count' => [
                    [
                        'count' => 9,
                    ],
                ],
            ],
        ]);

        $aggregationBuilderProphecy = $this->prophesize(Builder::class);
        $aggregationBuilderProphecy->execute([])->willReturn($iteratorProphecy->reveal());
        $aggregationBuilderProphecy->getPipeline()->willReturn([
            [
                '$facet' => [
                    'results' => [
                        ['$skip' => 3],
                        ['$limit' => 6],
                    ],
                    'count' => [
                        ['$count' => 'count'],
                    ],
                ],
            ],
        ]);

        $paginationExtension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );

        $result = $paginationExtension->getResult($aggregationBuilderProphecy->reveal(), Dummy::class, 'get');

        $this->assertInstanceOf(PartialPaginatorInterface::class, $result);
        $this->assertInstanceOf(PaginatorInterface::class, $result);
    }

    public function testGetResultWithExecuteOptions()
    {
        $resourceMetadataFactory = $this->resourceMetadataFactoryProphecy->reveal();

        $pagination = new Pagination($resourceMetadataFactory);

        $fixturesPath = \dirname((string) (new \ReflectionClass(Dummy::class))->getFileName());
        $config = DoctrineMongoDbOdmSetup::createAnnotationMetadataConfiguration([$fixturesPath], true);
        $documentManager = DocumentManager::create(null, $config);

        $this->managerRegistryProphecy->getManagerForClass(Dummy::class)->willReturn($documentManager);

        $dummyMetadata = new ResourceMetadataCollection(Dummy::class, [(new ApiResource())->withOperations(['get' => (new GetCollection())->withExtraProperties(['doctrine_mongodb' => ['execute_options' => ['allowDiskUse' => true]]])])]);
        $this->resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn($dummyMetadata);

        $iteratorProphecy = $this->prophesize(Iterator::class);
        $iteratorProphecy->toArray()->willReturn([
            [
                'count' => [
                    [
                        'count' => 9,
                    ],
                ],
            ],
        ]);

        $aggregationBuilderProphecy = $this->prophesize(Builder::class);
        $aggregationBuilderProphecy->execute(['allowDiskUse' => true])->willReturn($iteratorProphecy->reveal());
        $aggregationBuilderProphecy->getPipeline()->willReturn([
            [
                '$facet' => [
                    'results' => [
                        ['$skip' => 3],
                        ['$limit' => 6],
                    ],
                    'count' => [
                        ['$count' => 'count'],
                    ],
                ],
            ],
        ]);

        $paginationExtension = new PaginationExtension(
            $this->managerRegistryProphecy->reveal(),
            $resourceMetadataFactory,
            $pagination
        );

        $result = $paginationExtension->getResult($aggregationBuilderProphecy->reveal(), Dummy::class, 'get');

        $this->assertInstanceOf(PartialPaginatorInterface::class, $result);
        $this->assertInstanceOf(PaginatorInterface::class, $result);
    }

    private function mockAggregationBuilder($expectedOffset, $expectedLimit)
    {
        $skipProphecy = $this->prophesize(Skip::class);
        if ($expectedLimit > 0) {
            $skipProphecy->limit($expectedLimit)->shouldBeCalled();
        } else {
            $matchProphecy = $this->prophesize(AggregationMatch::class);
            $matchProphecy->field(Paginator::LIMIT_ZERO_MARKER_FIELD)->shouldBeCalled()->willReturn($matchProphecy);
            $matchProphecy->equals(Paginator::LIMIT_ZERO_MARKER)->shouldBeCalled();
            $skipProphecy->match()->shouldBeCalled()->willReturn($matchProphecy->reveal());
        }

        $resultsAggregationBuilderProphecy = $this->prophesize(Builder::class);
        $resultsAggregationBuilderProphecy->skip($expectedOffset)->shouldBeCalled()->willReturn($skipProphecy->reveal());

        $countProphecy = $this->prophesize(Count::class);

        $countAggregationBuilderProphecy = $this->prophesize(Builder::class);
        $countAggregationBuilderProphecy->count('count')->shouldBeCalled()->willReturn($countProphecy->reveal());

        $repositoryProphecy = $this->prophesize(DocumentRepository::class);
        $repositoryProphecy->createAggregationBuilder()->shouldBeCalled()->willReturn(
            $resultsAggregationBuilderProphecy->reveal(),
            $countAggregationBuilderProphecy->reveal()
        );

        $objectManagerProphecy = $this->prophesize(DocumentManager::class);
        $objectManagerProphecy->getRepository('Foo')->shouldBeCalled()->willReturn($repositoryProphecy->reveal());

        $this->managerRegistryProphecy->getManagerForClass('Foo')->shouldBeCalled()->willReturn($objectManagerProphecy->reveal());

        $facetProphecy = $this->prophesize(Facet::class);
        $facetProphecy->pipeline($skipProphecy)->shouldBeCalled()->willReturn($facetProphecy);
        $facetProphecy->pipeline($countProphecy)->shouldBeCalled()->willReturn($facetProphecy);
        $facetProphecy->field('count')->shouldBeCalled()->willReturn($facetProphecy);
        $facetProphecy->field('results')->shouldBeCalled()->willReturn($facetProphecy);

        $aggregationBuilderProphecy = $this->prophesize(Builder::class);
        $aggregationBuilderProphecy->facet()->shouldBeCalled()->willReturn($facetProphecy->reveal());

        return $aggregationBuilderProphecy;
    }
}

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

namespace ApiPlatform\Core\Tests\Metadata\Property\Factory;

use ApiPlatform\Core\Metadata\Property\Factory\CachedPropertyNameCollectionFactory;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyNameCollection;
use ApiPlatform\Core\Tests\ProphecyTrait;
use ApiPlatform\Tests\Fixtures\TestBundle\Entity\Dummy;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Baptiste Meyer <baptiste.meyer@gmail.com>
 */
class CachedPropertyNameCollectionFactoryTest extends TestCase
{
    use ProphecyTrait;

    public function testCreateWithItemHit()
    {
        $cacheItem = $this->prophesize(CacheItemInterface::class);
        $cacheItem->isHit()->willReturn(true)->shouldBeCalled();
        $cacheItem->get()->willReturn(new PropertyNameCollection(['id', 'name', 'description', 'dummy']))->shouldBeCalled();

        $cacheItemPool = $this->prophesize(CacheItemPoolInterface::class);
        $cacheItemPool->getItem($this->generateCacheKey())->willReturn($cacheItem->reveal())->shouldBeCalled();

        $decoratedPropertyNameCollectionFactory = $this->prophesize(PropertyNameCollectionFactoryInterface::class);

        $cachedPropertyNameCollectionFactory = new CachedPropertyNameCollectionFactory($cacheItemPool->reveal(), $decoratedPropertyNameCollectionFactory->reveal());
        $resultedPropertyNameCollection = $cachedPropertyNameCollectionFactory->create(Dummy::class);

        $expectedResult = new PropertyNameCollection(['id', 'name', 'description', 'dummy']);
        $this->assertEquals($expectedResult, $resultedPropertyNameCollection);
        $this->assertEquals($expectedResult, $cachedPropertyNameCollectionFactory->create(Dummy::class), 'Trigger the local cache');
    }

    public function testCreateWithItemNotHit()
    {
        $resourceNameCollection = new PropertyNameCollection(['id', 'name', 'description', 'dummy']);

        $decoratedPropertyNameCollectionFactory = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
        $decoratedPropertyNameCollectionFactory->create(Dummy::class, [])->willReturn($resourceNameCollection)->shouldBeCalled();

        $cacheItem = $this->prophesize(CacheItemInterface::class);
        $cacheItem->isHit()->willReturn(false)->shouldBeCalled();
        $cacheItem->set($resourceNameCollection)->willReturn($cacheItem->reveal())->shouldBeCalled();

        $cacheItemPool = $this->prophesize(CacheItemPoolInterface::class);
        $cacheItemPool->getItem($this->generateCacheKey())->willReturn($cacheItem->reveal())->shouldBeCalled();
        $cacheItemPool->save($cacheItem->reveal())->willReturn(true)->shouldBeCalled();

        $cachedPropertyNameCollectionFactory = new CachedPropertyNameCollectionFactory($cacheItemPool->reveal(), $decoratedPropertyNameCollectionFactory->reveal());
        $resultedPropertyNameCollection = $cachedPropertyNameCollectionFactory->create(Dummy::class);

        $expectedResult = new PropertyNameCollection(['id', 'name', 'description', 'dummy']);
        $this->assertEquals($expectedResult, $resultedPropertyNameCollection);
        $this->assertEquals($expectedResult, $cachedPropertyNameCollectionFactory->create(Dummy::class), 'Trigger the local cache');
    }

    public function testCreateWithGetCacheItemThrowsCacheException()
    {
        $decoratedPropertyNameCollectionFactory = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
        $decoratedPropertyNameCollectionFactory->create(Dummy::class, [])->willReturn(new PropertyNameCollection(['id', 'name', 'description', 'dummy']))->shouldBeCalled();

        $cacheException = new class() extends \Exception implements CacheException {};

        $cacheItemPool = $this->prophesize(CacheItemPoolInterface::class);
        $cacheItemPool->getItem($this->generateCacheKey())->willThrow($cacheException)->shouldBeCalled();

        $cachedPropertyNameCollectionFactory = new CachedPropertyNameCollectionFactory($cacheItemPool->reveal(), $decoratedPropertyNameCollectionFactory->reveal());
        $resultedPropertyNameCollection = $cachedPropertyNameCollectionFactory->create(Dummy::class);

        $expectedResult = new PropertyNameCollection(['id', 'name', 'description', 'dummy']);
        $this->assertEquals($expectedResult, $resultedPropertyNameCollection);
        $this->assertEquals($expectedResult, $cachedPropertyNameCollectionFactory->create(Dummy::class), 'Trigger the local cache');
    }

    private function generateCacheKey(string $resourceClass = Dummy::class, array $options = [])
    {
        return CachedPropertyNameCollectionFactory::CACHE_KEY_PREFIX.md5(serialize([$resourceClass, $options]));
    }
}

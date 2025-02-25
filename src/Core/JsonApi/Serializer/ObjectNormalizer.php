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

namespace ApiPlatform\Core\JsonApi\Serializer;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Core\Api\IriConverterInterface as LegacyIriConverterInterface;
use ApiPlatform\Core\Api\ResourceClassResolverInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\Util\ClassInfoTrait;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Decorates the output with JSON API metadata when appropriate, but otherwise
 * just passes through to the decorated normalizer.
 */
final class ObjectNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    use ClassInfoTrait;

    public const FORMAT = 'jsonapi';

    private $decorated;
    private $iriConverter;
    private $resourceClassResolver;
    /**
     * @var ResourceMetadataFactoryInterface|ResourceMetadataCollectionFactoryInterface
     */
    private $resourceMetadataFactory;

    public function __construct(NormalizerInterface $decorated, IriConverterInterface $iriConverter, ResourceClassResolverInterface $resourceClassResolver, $resourceMetadataFactory)
    {
        $this->decorated = $decorated;
        $this->iriConverter = $iriConverter;
        $this->resourceClassResolver = $resourceClassResolver;
        $this->resourceMetadataFactory = $resourceMetadataFactory;

        if ($iriConverter instanceof LegacyIriConverterInterface) {
            trigger_deprecation('api-platform/core', '2.7', sprintf('Use an implementation of "%s" instead of "%s".', IriConverterInterface::class, LegacyIriConverterInterface::class));
        }

        if (!$resourceMetadataFactory instanceof ResourceMetadataCollectionFactoryInterface) {
            trigger_deprecation('api-platform/core', '2.7', sprintf('Use "%s" instead of "%s".', ResourceMetadataCollectionFactoryInterface::class, ResourceMetadataFactoryInterface::class));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return self::FORMAT === $format && $this->decorated->supportsNormalization($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return $this->decorated instanceof CacheableSupportsMethodInterface && $this->decorated->hasCacheableSupportsMethod();
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = [])
    {
        if (isset($context['api_resource'])) {
            $originalResource = $context['api_resource'];
            unset($context['api_resource']);
        }

        $data = $this->decorated->normalize($object, $format, $context);
        if (!\is_array($data) || isset($context['api_attribute'])) {
            return $data;
        }

        if (isset($originalResource)) {
            $resourceClass = $this->resourceClassResolver->getResourceClass($originalResource);
            $resourceData = [
                'id' => $this->iriConverter->getIriFromItem($originalResource),
                'type' => $this->getResourceShortName($resourceClass),
            ];
        } else {
            $resourceData = [
                'id' => \function_exists('spl_object_id') ? spl_object_id($object) : spl_object_hash($object),
                'type' => (new \ReflectionClass($this->getObjectClass($object)))->getShortName(),
            ];
        }

        if ($data) {
            $resourceData['attributes'] = $data;
        }

        return ['data' => $resourceData];
    }

    // TODO: 3.0 remove
    private function getResourceShortName(string $resourceClass): string
    {
        /** @var ResourceMetadata|ResourceMetadataCollection */
        $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);

        if ($resourceMetadata instanceof ResourceMetadata) {
            return $resourceMetadata->getShortName();
        }

        return $resourceMetadata->getOperation()->getShortName();
    }
}

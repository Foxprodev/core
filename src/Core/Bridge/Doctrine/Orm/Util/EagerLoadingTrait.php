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

namespace ApiPlatform\Core\Bridge\Doctrine\Orm\Util;

use ApiPlatform\Core\Exception\RuntimeException;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * @author Antoine Bluchet <soyuka@gmail.com>
 *
 * TODO: remove in 3.0.
 *
 * @internal
 */
trait EagerLoadingTrait
{
    private $forceEager;
    private $fetchPartial;

    /**
     * @var ResourceMetadataCollectionFactoryInterface|ResourceMetadataFactoryInterface
     */
    private $resourceMetadataFactory;

    /**
     * Checks if an operation has a `force_eager` attribute.
     */
    private function shouldOperationForceEager(string $resourceClass, array $options): bool
    {
        if ($this->resourceMetadataFactory instanceof ResourceMetadataCollectionFactoryInterface) {
            throw new RuntimeException('Method should not be called.');
        }

        return $this->getBooleanOperationAttribute($resourceClass, $options, 'force_eager', $this->forceEager);
    }

    /**
     * Checks if an operation has a `fetch_partial` attribute.
     */
    private function shouldOperationFetchPartial(string $resourceClass, array $options): bool
    {
        if ($this->resourceMetadataFactory instanceof ResourceMetadataCollectionFactoryInterface) {
            throw new RuntimeException('Method should not be called.');
        }

        return $this->getBooleanOperationAttribute($resourceClass, $options, 'fetch_partial', $this->fetchPartial);
    }

    /**
     * Get the boolean attribute of an operation or the resource metadata.
     */
    private function getBooleanOperationAttribute(string $resourceClass, array $options, string $attributeName, bool $default): bool
    {
        $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);

        if (isset($options['collection_operation_name'])) {
            $attribute = $resourceMetadata->getCollectionOperationAttribute($options['collection_operation_name'], $attributeName, null, true);
        } elseif (isset($options['item_operation_name'])) {
            $attribute = $resourceMetadata->getItemOperationAttribute($options['item_operation_name'], $attributeName, null, true);
        } else {
            $attribute = $resourceMetadata->getAttribute($attributeName);
        }

        return \is_bool($attribute) ? $attribute : $default;
    }

    /**
     * Checks if the class has an associationMapping with FETCH=EAGER.
     *
     * @param array $checked array cache of tested metadata classes
     */
    private function hasFetchEagerAssociation(EntityManagerInterface $em, ClassMetadataInfo $classMetadata, array &$checked = []): bool
    {
        $checked[] = $classMetadata->name;

        foreach ($classMetadata->getAssociationMappings() as $mapping) {
            if (ClassMetadataInfo::FETCH_EAGER === $mapping['fetch']) {
                return true;
            }

            $related = $em->getClassMetadata($mapping['targetEntity']);

            if (\in_array($related->name, $checked, true)) {
                continue;
            }

            if (true === $this->hasFetchEagerAssociation($em, $related, $checked)) {
                return true;
            }
        }

        return false;
    }
}

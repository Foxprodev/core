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

namespace ApiPlatform\Core\Bridge\Symfony\Messenger;

use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Util\ClassInfoTrait;
use ApiPlatform\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Dispatches the given resource using the message bus of Symfony Messenger.
 *
 * @experimental
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class DataPersister implements ContextAwareDataPersisterInterface
{
    use ClassInfoTrait;
    use DispatchTrait;

    /**
     * @var ResourceMetadataCollectionFactoryInterface|ResourceMetadataFactoryInterface
     */
    private $resourceMetadataFactory;

    public function __construct($resourceMetadataFactory, MessageBusInterface $messageBus)
    {
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->messageBus = $messageBus;

        if (!$resourceMetadataFactory instanceof ResourceMetadataCollectionFactoryInterface) {
            trigger_deprecation('api-platform/core', '2.7', sprintf('Use "%s" instead of "%s".', ResourceMetadataCollectionFactoryInterface::class, ResourceMetadataFactoryInterface::class));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($data, array $context = []): bool
    {
        if ($this->resourceMetadataFactory instanceof ResourceMetadataCollectionFactoryInterface) {
            try {
                $resourceMetadataCollection = $this->resourceMetadataFactory->create($context['resource_class'] ?? $this->getObjectClass($data));
                $operation = $resourceMetadataCollection->getOperation($context['operation_name'] ?? null);

                return false !== ($operation->getMessenger() ?? false);
            } catch (OperationNotFoundException $e) {
                return false;
            }
        }

        try {
            $resourceMetadata = $this->resourceMetadataFactory->create($context['resource_class'] ?? $this->getObjectClass($data));
        } catch (ResourceClassNotFoundException $e) {
            return false;
        }

        if (null !== $operationName = $context['collection_operation_name'] ?? $context['item_operation_name'] ?? null) {
            return false !== $resourceMetadata->getTypedOperationAttribute(
                $context['collection_operation_name'] ?? false ? OperationType::COLLECTION : OperationType::ITEM,
                $operationName,
                'messenger',
                false,
                true
            );
        }

        if (isset($context['graphql_operation_name'])) {
            return false !== $resourceMetadata->getGraphqlAttribute($context['graphql_operation_name'], 'messenger', false, true);
        }

        return false !== $resourceMetadata->getAttribute('messenger', false);
    }

    /**
     * {@inheritdoc}
     */
    public function persist($data, array $context = [])
    {
        $envelope = $this->dispatch(
            (new Envelope($data))
                ->with(new ContextStamp($context))
        );

        $handledStamp = $envelope->last(HandledStamp::class);
        if (!$handledStamp instanceof HandledStamp) {
            return $data;
        }

        return $handledStamp->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function remove($data, array $context = [])
    {
        $this->dispatch(
            (new Envelope($data))
                ->with(new RemoveStamp())
        );
    }
}

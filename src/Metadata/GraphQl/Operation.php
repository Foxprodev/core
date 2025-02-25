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

namespace ApiPlatform\Metadata\GraphQl;

use ApiPlatform\Metadata\WithResourceTrait;

class Operation
{
    use WithResourceTrait;

    private $resolver;
    private $collection;
    private $args;
    private $shortName;
    private $class;
    /**
     * @var array|string
     */
    private $identifiers;
    private $compositeIdentifier;
    private $paginationEnabled;
    private $paginationType;
    private $paginationItemsPerPage;
    private $paginationMaximumItemsPerPage;
    private $paginationPartial;
    private $paginationClientEnabled;
    private $paginationClientItemsPerPage;
    private $paginationClientPartial;
    private $paginationFetchJoinCollection;
    private $paginationUseOutputWalkers;
    private $order;
    private $description;
    private $normalizationContext;
    private $denormalizationContext;
    private $security;
    private $securityMessage;
    private $securityPostDenormalize;
    private $securityPostDenormalizeMessage;
    private $deprecationReason;
    /**
     * @var string[]
     */
    private $filters;
    private $validationContext;
    /**
     * @var null
     */
    private $input;
    /**
     * @var null
     */
    private $output;
    /**
     * @var string|array|bool|null
     */
    private $mercure;
    /**
     * @var string|bool|null
     */
    private $messenger;
    private $elasticsearch;
    private $urlGenerationStrategy;
    private $read;
    private $deserialize;
    private $validate;
    private $write;
    private $serialize;
    private $fetchPartial;
    private $forceEager;
    private $priority;
    private $name;
    private $extraProperties;

    /**
     * @param string            $resolver
     * @param string            $shortName
     * @param string            $class
     * @param array             $identifiers
     * @param bool              $compositeIdentifier
     * @param bool              $paginationEnabled
     * @param string            $paginationType
     * @param int               $paginationItemsPerPage
     * @param int               $paginationMaximumItemsPerPage
     * @param bool              $paginationPartial
     * @param bool              $paginationClientEnabled
     * @param bool              $paginationClientItemsPerPage
     * @param bool              $paginationClientPartial
     * @param bool              $paginationFetchJoinCollection
     * @param bool              $paginationUseOutputWalkers
     * @param string            $description
     * @param string            $security
     * @param string            $securityMessage
     * @param string            $securityPostDenormalize
     * @param string            $securityPostDenormalizeMessage
     * @param string            $deprecationReason
     * @param string[]          $filters
     * @param bool|string|array $mercure
     * @param bool|string       $messenger
     * @param bool              $elasticsearch
     * @param int               $urlGenerationStrategy
     * @param bool              $fetchPartial
     * @param bool              $forceEager
     * @param mixed|null        $input
     * @param mixed|null        $output
     */
    public function __construct(
        ?string $resolver = null,
        bool $collection = false,
        ?array $args = null,
        ?string $shortName = null,
        ?string $class = null,
        $identifiers = [],
        ?bool $compositeIdentifier = null,
        ?bool $paginationEnabled = null,
        ?string $paginationType = null,
        ?int $paginationItemsPerPage = null,
        ?int $paginationMaximumItemsPerPage = null,
        ?bool $paginationPartial = null,
        ?bool $paginationClientEnabled = null,
        ?bool $paginationClientItemsPerPage = null,
        ?bool $paginationClientPartial = null,
        ?bool $paginationFetchJoinCollection = null,
        ?bool $paginationUseOutputWalkers = null,
        array $order = [],
        ?string $description = null,
        array $normalizationContext = [],
        array $denormalizationContext = [],
        ?string $security = null,
        ?string $securityMessage = null,
        ?string $securityPostDenormalize = null,
        ?string $securityPostDenormalizeMessage = null,
        ?string $deprecationReason = null,
        array $filters = [],
        array $validationContext = [],
        $input = null,
        $output = null,
        $mercure = null,
        $messenger = null,
        ?bool $elasticsearch = null,
        ?int $urlGenerationStrategy = null,
        bool $read = true,
        bool $deserialize = true,
        bool $validate = true,
        bool $write = true,
        bool $serialize = true,
        ?bool $fetchPartial = null,
        ?bool $forceEager = null,
        int $priority = 0,
        string $name = '',
        array $extraProperties = []
    ) {
        $this->resolver = $resolver;
        $this->collection = $collection;
        $this->args = $args;
        $this->shortName = $shortName;
        $this->class = $class;
        $this->identifiers = $identifiers;
        $this->compositeIdentifier = $compositeIdentifier;
        $this->paginationEnabled = $paginationEnabled;
        $this->paginationType = $paginationType;
        $this->paginationItemsPerPage = $paginationItemsPerPage;
        $this->paginationMaximumItemsPerPage = $paginationMaximumItemsPerPage;
        $this->paginationPartial = $paginationPartial;
        $this->paginationClientEnabled = $paginationClientEnabled;
        $this->paginationClientItemsPerPage = $paginationClientItemsPerPage;
        $this->paginationClientPartial = $paginationClientPartial;
        $this->paginationFetchJoinCollection = $paginationFetchJoinCollection;
        $this->paginationUseOutputWalkers = $paginationUseOutputWalkers;
        $this->order = $order;
        $this->description = $description;
        $this->normalizationContext = $normalizationContext;
        $this->denormalizationContext = $denormalizationContext;
        $this->security = $security;
        $this->securityMessage = $securityMessage;
        $this->securityPostDenormalize = $securityPostDenormalize;
        $this->securityPostDenormalizeMessage = $securityPostDenormalizeMessage;
        $this->deprecationReason = $deprecationReason;
        $this->filters = $filters;
        $this->validationContext = $validationContext;
        $this->input = $input;
        $this->output = $output;
        $this->mercure = $mercure;
        $this->messenger = $messenger;
        $this->elasticsearch = $elasticsearch;
        $this->urlGenerationStrategy = $urlGenerationStrategy;
        $this->read = $read;
        $this->deserialize = $deserialize;
        $this->validate = $validate;
        $this->write = $write;
        $this->serialize = $serialize;
        $this->fetchPartial = $fetchPartial;
        $this->forceEager = $forceEager;
        $this->priority = $priority;
        $this->name = $name;
        $this->extraProperties = $extraProperties;
    }

    public function withOperation(self $operation): self
    {
        return $this->copyFrom($operation);
    }

    public function getResolver(): ?string
    {
        return $this->resolver;
    }

    public function withResolver(?string $resolver = null): self
    {
        $self = clone $this;
        $self->resolver = $resolver;

        return $self;
    }

    public function isCollection(): bool
    {
        return $this->collection;
    }

    public function withCollection(bool $collection = false): self
    {
        $self = clone $this;
        $self->collection = $collection;

        return $self;
    }

    public function getArgs(): ?array
    {
        return $this->args;
    }

    public function withArgs(?array $args = null): self
    {
        $self = clone $this;
        $self->args = $args;

        return $self;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function withShortName(?string $shortName = null): self
    {
        $self = clone $this;
        $self->shortName = $shortName;

        return $self;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function withClass(?string $class = null): self
    {
        $self = clone $this;
        $self->class = $class;

        return $self;
    }

    public function getIdentifiers()
    {
        return $this->identifiers;
    }

    public function withIdentifiers($identifiers = []): self
    {
        $self = clone $this;
        $self->identifiers = $identifiers;

        return $self;
    }

    public function getCompositeIdentifier(): ?bool
    {
        return $this->compositeIdentifier;
    }

    public function withCompositeIdentifier(?bool $compositeIdentifier = null): self
    {
        $self = clone $this;
        $self->compositeIdentifier = $compositeIdentifier;

        return $self;
    }

    public function getPaginationEnabled(): ?bool
    {
        return $this->paginationEnabled;
    }

    public function withPaginationEnabled(?bool $paginationEnabled = null): self
    {
        $self = clone $this;
        $self->paginationEnabled = $paginationEnabled;

        return $self;
    }

    public function getPaginationType(): ?string
    {
        return $this->paginationType;
    }

    public function withPaginationType(?string $paginationType = null): self
    {
        $self = clone $this;
        $self->paginationType = $paginationType;

        return $self;
    }

    public function getPaginationItemsPerPage(): ?int
    {
        return $this->paginationItemsPerPage;
    }

    public function withPaginationItemsPerPage(?int $paginationItemsPerPage = null): self
    {
        $self = clone $this;
        $self->paginationItemsPerPage = $paginationItemsPerPage;

        return $self;
    }

    public function getPaginationMaximumItemsPerPage(): ?int
    {
        return $this->paginationMaximumItemsPerPage;
    }

    public function withPaginationMaximumItemsPerPage(?int $paginationMaximumItemsPerPage = null): self
    {
        $self = clone $this;
        $self->paginationMaximumItemsPerPage = $paginationMaximumItemsPerPage;

        return $self;
    }

    public function getPaginationPartial(): ?bool
    {
        return $this->paginationPartial;
    }

    public function withPaginationPartial(?bool $paginationPartial = null): self
    {
        $self = clone $this;
        $self->paginationPartial = $paginationPartial;

        return $self;
    }

    public function getPaginationClientEnabled(): ?bool
    {
        return $this->paginationClientEnabled;
    }

    public function withPaginationClientEnabled(?bool $paginationClientEnabled = null): self
    {
        $self = clone $this;
        $self->paginationClientEnabled = $paginationClientEnabled;

        return $self;
    }

    public function getPaginationClientItemsPerPage(): ?bool
    {
        return $this->paginationClientItemsPerPage;
    }

    public function withPaginationClientItemsPerPage(?bool $paginationClientItemsPerPage = null): self
    {
        $self = clone $this;
        $self->paginationClientItemsPerPage = $paginationClientItemsPerPage;

        return $self;
    }

    public function getPaginationClientPartial(): ?bool
    {
        return $this->paginationClientPartial;
    }

    public function withPaginationClientPartial(?bool $paginationClientPartial = null): self
    {
        $self = clone $this;
        $self->paginationClientPartial = $paginationClientPartial;

        return $self;
    }

    public function getPaginationFetchJoinCollection(): ?bool
    {
        return $this->paginationFetchJoinCollection;
    }

    public function withPaginationFetchJoinCollection(?bool $paginationFetchJoinCollection = null): self
    {
        $self = clone $this;
        $self->paginationFetchJoinCollection = $paginationFetchJoinCollection;

        return $self;
    }

    public function getPaginationUseOutputWalkers(): ?bool
    {
        return $this->paginationUseOutputWalkers;
    }

    public function withPaginationUseOutputWalkers(?bool $paginationUseOutputWalkers = null): self
    {
        $self = clone $this;
        $self->paginationUseOutputWalkers = $paginationUseOutputWalkers;

        return $self;
    }

    public function getOrder(): array
    {
        return $this->order;
    }

    public function withOrder(array $order = []): self
    {
        $self = clone $this;
        $self->order = $order;

        return $self;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function withDescription(?string $description = null): self
    {
        $self = clone $this;
        $self->description = $description;

        return $self;
    }

    public function getNormalizationContext(): array
    {
        return $this->normalizationContext;
    }

    public function withNormalizationContext(array $normalizationContext = []): self
    {
        $self = clone $this;
        $self->normalizationContext = $normalizationContext;

        return $self;
    }

    public function getDenormalizationContext(): array
    {
        return $this->denormalizationContext;
    }

    public function withDenormalizationContext(array $denormalizationContext = []): self
    {
        $self = clone $this;
        $self->denormalizationContext = $denormalizationContext;

        return $self;
    }

    public function getSecurity(): ?string
    {
        return $this->security;
    }

    public function withSecurity(?string $security = null): self
    {
        $self = clone $this;
        $self->security = $security;

        return $self;
    }

    public function getSecurityMessage(): ?string
    {
        return $this->securityMessage;
    }

    public function withSecurityMessage(?string $securityMessage = null): self
    {
        $self = clone $this;
        $self->securityMessage = $securityMessage;

        return $self;
    }

    public function getSecurityPostDenormalize(): ?string
    {
        return $this->securityPostDenormalize;
    }

    public function withSecurityPostDenormalize(?string $securityPostDenormalize = null): self
    {
        $self = clone $this;
        $self->securityPostDenormalize = $securityPostDenormalize;

        return $self;
    }

    public function getSecurityPostDenormalizeMessage(): ?string
    {
        return $this->securityPostDenormalizeMessage;
    }

    public function withSecurityPostDenormalizeMessage(?string $securityPostDenormalizeMessage = null): self
    {
        $self = clone $this;
        $self->securityPostDenormalizeMessage = $securityPostDenormalizeMessage;

        return $self;
    }

    public function getDeprecationReason(): ?string
    {
        return $this->deprecationReason;
    }

    public function withDeprecationReason(?string $deprecationReason = null): self
    {
        $self = clone $this;
        $self->deprecationReason = $deprecationReason;

        return $self;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function withFilters(array $filters = []): self
    {
        $self = clone $this;
        $self->filters = $filters;

        return $self;
    }

    public function getValidationContext(): array
    {
        return $this->validationContext;
    }

    public function withValidationContext(array $validationContext = []): self
    {
        $self = clone $this;
        $self->validationContext = $validationContext;

        return $self;
    }

    public function getInput()
    {
        return $this->input;
    }

    public function withInput($input = null): self
    {
        $self = clone $this;
        $self->input = $input;

        return $self;
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function withOutput($output = null): self
    {
        $self = clone $this;
        $self->output = $output;

        return $self;
    }

    /**
     * @return bool|string|array|null
     */
    public function getMercure()
    {
        return $this->mercure;
    }

    /**
     * @param bool|string|array|null $mercure
     *
     * @return $this
     */
    public function withMercure($mercure = null): self
    {
        $self = clone $this;
        $self->mercure = $mercure;

        return $self;
    }

    /**
     * @return bool|string|null
     */
    public function getMessenger()
    {
        return $this->messenger;
    }

    /**
     * @param bool|string|null $messenger
     *
     * @return $this
     */
    public function withMessenger($messenger = null): self
    {
        $self = clone $this;
        $self->messenger = $messenger;

        return $self;
    }

    public function getElasticsearch(): ?bool
    {
        return $this->elasticsearch;
    }

    public function withElasticsearch(?bool $elasticsearch = null): self
    {
        $self = clone $this;
        $self->elasticsearch = $elasticsearch;

        return $self;
    }

    public function getUrlGenerationStrategy(): ?int
    {
        return $this->urlGenerationStrategy;
    }

    public function withUrlGenerationStrategy(?int $urlGenerationStrategy = null): self
    {
        $self = clone $this;
        $self->urlGenerationStrategy = $urlGenerationStrategy;

        return $self;
    }

    public function canRead(): bool
    {
        return $this->read;
    }

    public function withRead(bool $read = true): self
    {
        $self = clone $this;
        $self->read = $read;

        return $self;
    }

    public function canDeserialize(): bool
    {
        return $this->deserialize;
    }

    public function withDeserialize(bool $deserialize = true): self
    {
        $self = clone $this;
        $self->deserialize = $deserialize;

        return $self;
    }

    public function canValidate(): bool
    {
        return $this->validate;
    }

    public function withValidate(bool $validate = true): self
    {
        $self = clone $this;
        $self->validate = $validate;

        return $self;
    }

    public function canWrite(): bool
    {
        return $this->write;
    }

    public function withWrite(bool $write = true): self
    {
        $self = clone $this;
        $self->write = $write;

        return $self;
    }

    public function canSerialize(): bool
    {
        return $this->serialize;
    }

    public function withSerialize(bool $serialize = true): self
    {
        $self = clone $this;
        $self->serialize = $serialize;

        return $self;
    }

    public function getFetchPartial(): ?bool
    {
        return $this->fetchPartial;
    }

    public function withFetchPartial(?bool $fetchPartial = null): self
    {
        $self = clone $this;
        $self->fetchPartial = $fetchPartial;

        return $self;
    }

    public function getForceEager(): ?bool
    {
        return $this->forceEager;
    }

    public function withForceEager(?bool $forceEager = null): self
    {
        $self = clone $this;
        $self->forceEager = $forceEager;

        return $self;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function withPriority(int $priority = 0): self
    {
        $self = clone $this;
        $self->priority = $priority;

        return $self;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function withName(string $name = ''): self
    {
        $self = clone $this;
        $self->name = $name;

        return $self;
    }

    public function getExtraProperties(): array
    {
        return $this->extraProperties;
    }

    public function withExtraProperties(array $extraProperties = []): self
    {
        $self = clone $this;
        $self->extraProperties = $extraProperties;

        return $self;
    }
}

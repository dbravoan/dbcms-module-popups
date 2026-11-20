<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Services;

use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Modules\Popups\DTOs\PopupContext;

final class PopupEligibility
{
    public function __construct(private readonly EntitySchemaRegistryInterface $schemas, private readonly ModuleManager $modules) {}

    /** @return array<string,string> */
    public function types(): array
    {
        $types = [];
        foreach ($this->schemas->all() as $schema) {
            $owner = $schema->ownerModule ?? $this->modules->ownerOfClass($schema->model);
            if ($schema->publicContent !== null && ($owner === null || $this->modules->isModuleEnabled($owner))) {
                $types[$schema->type] = $schema->label;
            }
        }

        return $types;
    }

    /** @param array<string,mixed> $configuration
     * @return list<string> */
    public function unavailable(array $configuration, PopupContext $context): array
    {
        $issues = [];
        foreach (['include', 'exclude'] as $kind) {
            $condition = (array) ($configuration['conditions'][$kind] ?? []);
            foreach ((array) ($condition['types'] ?? []) as $type) {
                if (! is_string($type) || ! array_key_exists($type, $this->types())) {
                    $issues[] = 'unavailable_type:'.(string) $type;
                }
            }
            if (! empty($condition['pages']) && ($context->entityType === null || $context->entityId === null)) {
                $issues[] = 'unavailable_page_context';
            }
            foreach (array_keys((array) ($condition['taxonomies'] ?? [])) as $taxonomy) {
                if (! array_key_exists($taxonomy, $context->taxonomies)) {
                    $issues[] = 'unavailable_taxonomy:'.$taxonomy;
                }
            }
        }

        return array_values(array_unique($issues));
    }

    /** @param array<string,mixed> $configuration */
    public function matches(array $configuration, PopupContext $context): bool
    {
        if ($this->unavailable($configuration, $context) !== []) {
            return false;
        }
        $locales = (array) ($configuration['locales'] ?? []);
        if ($locales !== [] && ! in_array($context->locale, $locales, true)) {
            return false;
        }
        $auth = $configuration['auth'] ?? 'any';
        if (($auth === 'guest' && $context->authenticated) || ($auth === 'authenticated' && ! $context->authenticated)) {
            return false;
        }
        $conditions = (array) ($configuration['conditions'] ?? []);
        $exclude = (array) ($conditions['exclude'] ?? []);
        // Any exclusion wins. Included dimensions are combined with AND.
        if ($this->pageMatches((array) ($exclude['pages'] ?? []), $context)
            || in_array($context->entityType, (array) ($exclude['types'] ?? []), true)
            || $this->taxonomyMatches((array) ($exclude['taxonomies'] ?? []), $context, false)) {
            return false;
        }
        $include = (array) ($conditions['include'] ?? []);
        $pages = (array) ($include['pages'] ?? []);
        $types = (array) ($include['types'] ?? []);

        return ($pages === [] || $this->pageMatches($pages, $context))
            && ($types === [] || in_array($context->entityType, $types, true))
            && $this->taxonomyMatches((array) ($include['taxonomies'] ?? []), $context, true);
    }

    /** @param array<mixed> $ids */
    private function pageMatches(array $ids, PopupContext $context): bool
    {
        return $context->entityType === 'page' && in_array($context->entityId, array_map(intval(...), $ids), true);
    }

    /** @param array<string,list<string>> $taxonomies */
    private function taxonomyMatches(array $taxonomies, PopupContext $context, bool $all): bool
    {
        foreach ($taxonomies as $taxonomy => $values) {
            $matches = array_intersect($values, $context->taxonomies[$taxonomy] ?? []) !== [];
            if ($all && ! $matches) {
                return false;
            }
            if (! $all && $matches) {
                return true;
            }
        }

        return $all;
    }
}

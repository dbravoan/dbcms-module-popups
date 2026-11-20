<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Services;

use DbCms\Core\Content\EntitySchema;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Validation\ValidationException;

/** Named editor references use the same owner publication and localization boundary as the site. */
final class PopupTargetReader
{
    public function __construct(private readonly PublicContentDiscoveryInterface $public, private readonly ModuleManager $modules) {}

    /** @return list<array{type:string,label:string,owner:string}> */
    public function types(): array
    {
        $types = [];
        foreach ($this->public->schemas() as $schema) {
            $owner = $schema->ownerModule ?? $this->modules->ownerOfClass($schema->model);
            $ownerName = $owner === null ? 'Núcleo' : (string) ($this->modules->getModuleManifest($owner)['name'] ?? $owner);
            $types[] = ['type' => $schema->type, 'label' => $schema->label.' · '.$ownerName, 'owner' => $owner ?? 'core'];
        }

        return $types;
    }

    /** @param list<int|string> $ids
     * @return array{items:list<array{id:int|string,title:string,type:string}>,next_offset:int|null} */
    public function search(string $type, string $marketCode, string $locale, string $search = '', int $offset = 0, int $limit = 20, array $ids = []): array
    {
        $schema = $this->public->schemas()[$type] ?? null;
        if ($schema === null) {
            throw ValidationException::withMessages(['type' => 'Este propietario no ofrece referencias públicas disponibles.']);
        }
        $market = $this->public->markets()->firstWhere('code', $marketCode);
        if (! $market instanceof Market || ! $market->languages->where('is_active', true)->contains('code', $locale)) {
            throw ValidationException::withMessages(['locale' => 'Elige un idioma activo del mercado seleccionado.']);
        }
        $title = $schema->translationAttributes()[EntitySchema::ROLE_TITLE] ?? null;
        if ($title === null) {
            throw ValidationException::withMessages(['type' => 'El propietario no declara nombres públicos para estas referencias.']);
        }
        $query = $this->public->query($type, $market, $locale);
        PublicTranslation::constrainResolvedField($query, $market, $locale, $title, 'like', '_%');
        if (trim($search) !== '') {
            PublicTranslation::constrainResolvedField($query, $market, $locale, $title, 'like', '%'.trim($search).'%');
        }
        if ($ids !== []) {
            $query->whereKey($ids);
        }
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        $rows = $query->orderBy($query->getModel()->getQualifiedKeyName())->offset($offset)->limit($limit + 1)->get();
        $items = [];
        foreach ($rows->take($limit) as $row) {
            $translation = PublicTranslation::resolve($row, $locale, $market);
            $label = trim((string) $translation?->getAttribute($title));
            $id = $row->getKey();
            if ($label !== '' && (is_int($id) || is_string($id))) {
                $items[] = ['id' => $id, 'title' => $label, 'type' => $type];
            }
        }

        return ['items' => $items, 'next_offset' => $rows->count() > $limit ? $offset + $limit : null];
    }
}

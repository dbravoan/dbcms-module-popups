<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\DTOs;

use DbCms\Core\Models\Market;
use Illuminate\Http\Request;

/** Target identity comes from the public owner, never route guesses or an admin model lookup. */
final readonly class PopupContext
{
    /** @param array<string,list<string>> $taxonomies */
    public function __construct(
        public Market $market,
        public string $locale,
        public ?string $entityType = null,
        public ?int $entityId = null,
        public array $taxonomies = [],
        public bool $authenticated = false,
    ) {}

    public static function fromRequest(Request $request, Market $market, string $locale): self
    {
        $taxonomies = [];
        foreach ((array) $request->attributes->get('cms.public.taxonomies', []) as $key => $values) {
            if (is_string($key) && is_array($values)) {
                $taxonomies[$key] = array_values(array_map(strval(...), array_filter($values, is_scalar(...))));
            }
        }
        $type = $request->attributes->get('cms.public.entity_type');
        $id = $request->attributes->get('cms.public.entity_id');

        return new self($market, $locale, is_string($type) ? $type : null, is_numeric($id) ? (int) $id : null, $taxonomies, $request->user() !== null);
    }
}

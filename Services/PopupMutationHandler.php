<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Services;

use DbCms\Core\Content\Contracts\EntityMutationHandlerInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Shared HTTP/MCP/import boundary: no consumer may bypass popup configuration rules. */
final class PopupMutationHandler implements EntityMutationHandlerInterface
{
    public const MODES = ['modal', 'fullscreen', 'top_bar', 'bottom_bar', 'side'];

    public const TRIGGERS = ['delay', 'scroll', 'element', 'click', 'inactivity', 'exit_intent', 'form_success'];

    /** @param array<string,mixed> $args
     * @return array<string,mixed> */
    public function prepare(Model $entity, array $args, bool $creating): array
    {
        $attributes = is_array($args['attributes'] ?? null) ? $args['attributes'] : [];
        if ($creating) {
            $attributes += ['mode' => 'modal', 'priority' => 0, 'configuration' => ['triggers' => [['type' => 'delay', 'seconds' => 3]], 'frequency' => ['mode' => 'page']]];
        }
        if (is_string($attributes['configuration'] ?? null)) {
            $attributes['configuration'] = json_decode($attributes['configuration'], true);
        }
        Validator::make($attributes, [
            'mode' => ['sometimes', 'required', Rule::in(self::MODES)],
            'priority' => 'sometimes|required|integer|between:-1000,1000',
            'is_active' => 'sometimes|boolean', 'is_published' => 'sometimes|boolean',
            'published_at' => 'nullable|date', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date',
            'configuration' => 'sometimes|required|array:conditions,triggers,locales,devices,auth,frequency,provenance',
            'configuration.conditions' => 'sometimes|array:include,exclude',
            'configuration.conditions.*' => 'array:pages,types,taxonomies',
            'configuration.conditions.*.pages' => 'sometimes|array',
            'configuration.conditions.*.pages.*' => 'integer|min:1',
            'configuration.conditions.*.types' => 'sometimes|array',
            'configuration.conditions.*.types.*' => 'string|regex:/^[a-z][a-z0-9_\-]*$/',
            'configuration.conditions.*.taxonomies' => 'sometimes|array',
            'configuration.conditions.*.taxonomies.*' => 'array|min:1',
            'configuration.conditions.*.taxonomies.*.*' => 'string|max:255',
            'configuration.triggers' => 'required_with:configuration|array|min:1',
            'configuration.triggers.*' => 'array:type,seconds,percent,selector,form_slug',
            'configuration.triggers.*.type' => ['required', Rule::in(self::TRIGGERS)],
            'configuration.triggers.*.seconds' => 'sometimes|numeric|between:0,86400',
            'configuration.triggers.*.percent' => 'sometimes|numeric|between:0,100',
            'configuration.triggers.*.selector' => 'sometimes|string|max:500',
            'configuration.triggers.*.form_slug' => 'sometimes|nullable|string|max:255',
            'configuration.locales' => 'sometimes|array', 'configuration.locales.*' => 'string|max:10',
            'configuration.devices' => 'sometimes|array', 'configuration.devices.*' => [Rule::in(['desktop', 'tablet', 'mobile'])],
            'configuration.auth' => ['sometimes', Rule::in(['any', 'guest', 'authenticated'])],
            'configuration.frequency' => 'sometimes|array:mode,seconds,purpose',
            'configuration.frequency.mode' => ['required_with:configuration.frequency', Rule::in(['page', 'session', 'once', 'cooldown'])],
            'configuration.frequency.seconds' => 'sometimes|integer|between:1,31536000',
            'configuration.frequency.purpose' => ['sometimes', Rule::in(['preferences', 'analytics', 'marketing'])],
            'configuration.provenance' => 'sometimes|array:referrers,utm',
            'configuration.provenance.referrers' => 'sometimes|array',
            'configuration.provenance.referrers.*' => 'string|regex:/^[a-z0-9][a-z0-9.\-]*$/i|max:253',
            'configuration.provenance.utm' => 'sometimes|array:utm_source,utm_medium,utm_campaign,utm_term,utm_content',
            'configuration.provenance.utm.*' => 'string|max:255',
        ])->validate();
        $configuration = $attributes['configuration'] ?? null;
        if (is_array($configuration)) {
            foreach ((array) ($configuration['triggers'] ?? []) as $index => $trigger) {
                if (! is_array($trigger)) {
                    continue;
                }
                $required = match ($trigger['type'] ?? '') {
                    'delay', 'inactivity' => 'seconds', 'scroll' => 'percent', 'element', 'click' => 'selector', default => null,
                };
                if ($required !== null && (! array_key_exists($required, $trigger) || $trigger[$required] === '')) {
                    throw ValidationException::withMessages(["configuration.triggers.{$index}.{$required}" => 'Este disparador necesita un valor.']);
                }
            }
            $frequency = (array) ($configuration['frequency'] ?? []);
            if (($frequency['mode'] ?? 'page') !== 'page' && empty($frequency['purpose'])) {
                throw ValidationException::withMessages(['configuration.frequency.purpose' => 'El almacenamiento entre visitas requiere una finalidad autorizada.']);
            }
            if (($frequency['mode'] ?? '') === 'cooldown' && empty($frequency['seconds'])) {
                throw ValidationException::withMessages(['configuration.frequency.seconds' => 'Indica el intervalo entre impresiones.']);
            }
        }
        $starts = $attributes['starts_at'] ?? $entity->getAttribute('starts_at');
        $ends = $attributes['ends_at'] ?? $entity->getAttribute('ends_at');
        if ($starts !== null && $ends !== null && strtotime((string) $starts) >= strtotime((string) $ends)) {
            throw ValidationException::withMessages(['ends_at' => 'El final debe ser posterior al inicio.']);
        }
        if (($attributes['is_published'] ?? $attributes['publish'] ?? $args['publish'] ?? false) && empty($attributes['published_at'])) {
            $attributes['published_at'] = $entity->getAttribute('published_at') ?? now();
        }

        return array_replace($args, ['attributes' => $attributes]);
    }
}

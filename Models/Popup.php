<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Models;

use DbCms\Core\Content\Contracts\TranslatableEntity;
use DbCms\Core\Contracts\Sectionable;
use DbCms\Core\Models\Market;
use DbCms\Core\Traits\MarketScoped;
use DbCms\Core\Traits\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $mode
 * @property bool $is_active
 * @property bool $is_published
 * @property int $priority
 * @property array<string,mixed> $configuration
 * @property Carbon|null $published_at
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property-read Collection<int,PopupTranslation> $translations
 * @property-read Collection<int,PopupSection> $sections
 */
class Popup extends Model implements Sectionable, TranslatableEntity
{
    use MarketScoped, Translatable;

    protected $fillable = ['mode', 'is_active', 'is_published', 'priority', 'configuration', 'published_at', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_published' => 'boolean', 'priority' => 'integer', 'configuration' => 'array', 'published_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /**
     * @return HasMany<PopupTranslation,$this> */
    public function translations(): HasMany
    {
        return $this->hasMany(PopupTranslation::class);
    }

    /**
     * @return HasMany<PopupSection,$this> */
    public function sections(): HasMany
    {
        return $this->hasMany(PopupSection::class)->orderBy('order')->orderBy('id');
    }

    /**
     * @return HasMany<PopupSection,$this> */
    public function publishedSections(): HasMany
    {
        return $this->sections()->where('is_active', true)->where('status', 'published');
    }

    /**
     * @return BelongsToMany<Market,$this> */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'market_popup');
    }

    public function sectionModelClass(): string
    {
        return PopupSection::class;
    }

    public function sectionTranslationModelClass(): string
    {
        return PopupSectionTranslation::class;
    }

    public function sectionForeignKey(): string
    {
        return 'popup_id';
    }

    /** @param Builder<Popup> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_active', true)->where('is_published', true)->whereNotNull('published_at')->where('published_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}

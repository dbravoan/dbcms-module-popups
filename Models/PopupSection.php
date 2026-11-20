<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property bool $is_active
 * @property string $status
 * @property int $order
 * @property-read Collection<int,PopupSectionTranslation> $translations
 */
final class PopupSection extends Model
{
    protected $fillable = ['popup_id', 'type', 'order', 'styling_json', 'is_active', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['styling_json' => 'array', 'is_active' => 'boolean', 'order' => 'integer', 'published_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Popup,$this> */
    public function popup(): BelongsTo
    {
        return $this->belongsTo(Popup::class);
    }

    /**
     * @return HasMany<PopupSectionTranslation,$this> */
    public function translations(): HasMany
    {
        return $this->hasMany(PopupSectionTranslation::class);
    }
}

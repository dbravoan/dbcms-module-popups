<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $title
 * @property string|null $slug
 * @property string $locale
 * @property int|null $market_id */
final class PopupTranslation extends Model
{
    protected $fillable = ['popup_id', 'locale', 'market_id', 'title', 'slug'];

    /**
     * @return BelongsTo<Popup,$this> */
    public function popup(): BelongsTo
    {
        return $this->belongsTo(Popup::class);
    }
}

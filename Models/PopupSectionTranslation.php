<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PopupSectionTranslation extends Model
{
    protected $fillable = ['popup_section_id', 'locale', 'market_id', 'content_json'];

    protected function casts(): array
    {
        return ['content_json' => 'array'];
    }

    /**
     * @return BelongsTo<PopupSection,$this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(PopupSection::class, 'popup_section_id');
    }
}

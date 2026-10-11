<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SectionTask extends Model
{
    use LogsActivity;

    protected $fillable = ['section_id', 'title', 'description', 'sort_order', 'requires_photo'];

    protected $casts = ['requires_photo' => 'boolean'];

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return HasMany<SectionCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(SectionCheck::class);
    }
}

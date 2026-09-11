<?php

namespace App\Models;

use App\Traits\CostingLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One ingredient line on an R&D costing sheet. The rest is App\Traits\CostingLine. */
class RndEntryLine extends Model
{
    use CostingLine;

    protected $fillable = ['rnd_entry_id'];

    public function rndEntry(): BelongsTo
    {
        return $this->belongsTo(RndEntry::class);
    }
}

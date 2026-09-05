<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class SectionTask extends Model
{
    use LogsActivity;
    protected $fillable = ['section_id', 'title', 'description', 'sort_order', 'requires_photo'];

    protected $casts = ['requires_photo' => 'boolean'];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function checks()
    {
        return $this->hasMany(SectionCheck::class);
    }
}

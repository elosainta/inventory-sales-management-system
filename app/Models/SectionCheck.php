<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SectionCheck extends Model
{
    protected $fillable = ['section_task_id', 'user_id', 'checked_date', 'photo_path'];

    protected $casts = ['checked_date' => 'date'];

    public function task()
    {
        return $this->belongsTo(SectionTask::class, 'section_task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

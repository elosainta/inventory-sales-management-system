<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use LogsActivity;
    // `user_id` is deliberately NOT fillable and has no relation: sections
    // stopped being assigned to a person in 1.10.49. The column and its
    // nullOnDelete foreign key stay so the old assignments are still readable
    // in the audit trail, but nothing writes or reads them any more.
    protected $fillable = ['name', 'description', 'active_days'];
    protected $casts = ['active_days' => 'array'];

    public function tasks()
    {
        return $this->hasMany(SectionTask::class)->orderBy('sort_order');
    }
}

<?php

namespace App\Traits;

use App\Models\Audit;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            Audit::create([
                'user_id'        => Auth::id(),
                'user_name'      => Auth::user()?->name,
                'action'         => 'created',
                'auditable_type' => get_class($model),
                'auditable_id'   => $model->id,
                'before'         => null,
                'after'          => $model->toArray(),
            ]);
        });

        static::updated(function ($model) {
            // getDirty()/getOriginal() read raw attributes and, unlike toArray(),
            // do not respect the model's $hidden - a password change would
            // otherwise write the hashed password straight into the audit log.
            $hide = array_flip($model->getHidden());

            Audit::create([
                'user_id'        => Auth::id(),
                'user_name'      => Auth::user()?->name,
                'action'         => 'updated',
                'auditable_type' => get_class($model),
                'auditable_id'   => $model->id,
                'before'         => array_diff_key(
                    array_intersect_key($model->getOriginal(), $model->getDirty()),
                    $hide
                ),
                'after'          => array_diff_key($model->getDirty(), $hide),
            ]);
        });

        static::deleted(function ($model) {
            Audit::create([
                'user_id'        => Auth::id(),
                'user_name'      => Auth::user()?->name,
                'action'         => 'deleted',
                'auditable_type' => get_class($model),
                'auditable_id'   => $model->id,
                'before'         => $model->toArray(),
                'after'          => null,
            ]);
        });
    }
}
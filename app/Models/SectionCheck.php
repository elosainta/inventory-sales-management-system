<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SectionCheck extends Model
{
    protected $fillable = ['section_task_id', 'user_id', 'checked_date', 'photo_path'];

    protected $casts = ['checked_date' => 'date'];

    /**
     * The app runs on UTC and stores UTC, which is 8 hours behind the kitchen:
     * a tick at 9:41pm printed as 13:41, and one before 8am landed on
     * yesterday's sheet. The prep pages speak kitchen time; storage does not
     * change. (Switching APP_TIMEZONE instead would reread every stored
     * timestamp in the app 8 hours off.)
     */
    public const TIMEZONE = 'Asia/Kuala_Lumpur';

    // The kitchen's date, as a midnight in the app's timezone — the same kind
    // of value today() gives, so queries and date comparisons are unchanged.
    public static function kitchenToday(): Carbon
    {
        return Carbon::parse(now(self::TIMEZONE)->toDateString());
    }

    // When it was ticked, on the kitchen clock.
    public function time(): string
    {
        return $this->updated_at->copy()->tz(self::TIMEZONE)->format('H:i');
    }

    /** @return BelongsTo<SectionTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(SectionTask::class, 'section_task_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

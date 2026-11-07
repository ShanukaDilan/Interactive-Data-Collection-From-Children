<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Audit-log create/update/delete of fillable attributes (only what changed).
 * Models may define `protected array $auditExcept = [...]` to keep sensitive fields out of the log.
 */
trait LogsChanges
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(str(class_basename($this))->snake()->toString())
            ->logFillable()
            ->logExcept($this->auditExcept ?? [])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}

<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Two relation methods that map to the same `orderBy` argument name.
 *
 * `admin_note()` and `adminNote()` both become `orderByAdminNote`, so only the relation
 * that is found first is offered. The class has no `#[Model]` attribute and is not part
 * of the generated schema: the QueryCollection tests instantiate it directly.
 */
class CollidingRelations extends Model
{
    public function adminNote(): BelongsTo
    {
        return $this->belongsTo(AdminNote::class);
    }

    public function admin_note(): BelongsTo
    {
        return $this->belongsTo(AdminNote::class);
    }
}

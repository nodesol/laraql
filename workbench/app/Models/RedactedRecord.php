<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A model whose columns are all hidden.
 *
 * Models like these are exposed through relations, so `orderBy` cannot offer any of
 * their columns and has to fall back to the relation name alone.
 */
class RedactedRecord extends Model
{
    protected $table = 'legacy_records';

    protected $hidden = ['id', 'name', 'created_at', 'updated_at'];
}

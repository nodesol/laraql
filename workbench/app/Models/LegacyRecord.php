<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A concrete Eloquent model without the LaraQL attribute.
 *
 * It is skipped while `laraql.models.auto_include` is false and exposed as soon
 * as the setting is enabled.
 */
class LegacyRecord extends Model
{
    protected $fillable = ['name'];
}

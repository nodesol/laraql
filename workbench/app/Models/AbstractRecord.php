<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Nodesol\LaraQL\Attributes\Model as LaraQL;

/**
 * Abstract models can never be instantiated for schema generation and are always skipped.
 */
#[LaraQL]
abstract class AbstractRecord extends Model
{
    protected $table = 'legacy_records';
}

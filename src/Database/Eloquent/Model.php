<?php

namespace Oktaax\Database\Eloquent;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Oktaax\Database\DB;

use function Illuminate\Support\enum_value;

/**
 * Eloquent's model API with connection lookup scoped to the active Oktaax request.
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
abstract class Model extends EloquentModel
{
    /**
     * Resolve without changing Illuminate's process-wide connection resolver.
     *
     * @param \UnitEnum|string|null $connection
     */
    public static function resolveConnection($connection = null): Connection
    {
        return DB::connection(
            $connection === null ? null : (string) enum_value($connection),
        );
    }
}

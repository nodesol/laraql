<?php

namespace Workbench\App\GraphQL;

/**
 * Resolver for the field that is defined by hand in workbench/graphql/schema.graphql.
 */
class HandWrittenPingQuery
{
    public function ping(): string
    {
        return 'pong';
    }
}

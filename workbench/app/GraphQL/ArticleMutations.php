<?php

namespace Workbench\App\GraphQL;

use Nodesol\LaraQL\Attributes\Mutation;

/**
 * Hand written mutations for the operations a model does not generate.
 */
#[Mutation(
    name: 'archive',
    return_type: 'Article',
    inputs: [
        'id' => 'ID!',
        'status' => 'String!',
    ],
    query: '@update',
)]
#[Mutation(
    name: 'purge',
    return_type: 'Article',
    inputs: [
        'id' => 'ID! @whereKey',
    ],
    query: '@delete',
)]
class ArticleMutations {}

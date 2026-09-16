<?php

namespace Workbench\App\GraphQL;

use Nodesol\LaraQL\Attributes\Type;

/**
 * A hand written object type: explicit columns, an override and its own paginator.
 */
#[Type(
    name: 'ArticleStats',
    create_paginator: true,
    columns: [
        'id' => 'ID!',
        'title' => 'String!',
    ],
    columns_override: [
        'title' => 'String! @deprecated(reason: "Use headline instead.")',
        'views' => 'Int!',
    ],
)]
class ArticleStats {}

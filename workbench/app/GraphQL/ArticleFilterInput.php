<?php

namespace Workbench\App\GraphQL;

use Nodesol\LaraQL\Attributes\Input;

#[Input(
    name: 'ArticleFilterInput',
    inputs: [
        'title' => 'String',
        'status' => 'String',
    ],
    inputs_override: [
        'status' => 'ArticleStatus',
        'limit' => 'Int = 10',
    ],
)]
class ArticleFilterInput {}

<?php

use Nodesol\LaraQL\Types\RelationTypes;

it('groups the eloquent relation types it understands', function () {
    expect(RelationTypes::RELATION_TYPES)->toBe([
        RelationTypes::HAS_ONE,
        RelationTypes::HAS_ONE_THROUGH,
        RelationTypes::BELONGS_TO,
        RelationTypes::HAS_MANY,
        RelationTypes::HAS_MANY_THROUGH,
        RelationTypes::BELONGS_TO_MANY,
        RelationTypes::MORPH_ONE,
        RelationTypes::MORPH_TO,
        RelationTypes::MORPH_MANY,
        RelationTypes::MORPH_TO_MANY,
    ])
        ->and(RelationTypes::SINGLE_RELATION_TYPES)->toBe([
            RelationTypes::HAS_ONE,
            RelationTypes::HAS_ONE_THROUGH,
            RelationTypes::BELONGS_TO,
            RelationTypes::MORPH_ONE,
            RelationTypes::MORPH_TO,
        ])
        ->and(RelationTypes::MULTIPLE_RELATION_TYPES)->toBe([
            RelationTypes::HAS_MANY,
            RelationTypes::HAS_MANY_THROUGH,
            RelationTypes::BELONGS_TO_MANY,
            RelationTypes::MORPH_MANY,
            RelationTypes::MORPH_TO_MANY,
        ]);
});

it('keeps every relation type in exactly one group', function () {
    expect(array_merge(RelationTypes::SINGLE_RELATION_TYPES, RelationTypes::MULTIPLE_RELATION_TYPES))
        ->toEqualCanonicalizing(RelationTypes::RELATION_TYPES);
});

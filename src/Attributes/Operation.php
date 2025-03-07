<?php

namespace Nodesol\LaraQL\Attributes;

interface Operation {
    public function getName() : string;
    public function getSchema() : string;
}

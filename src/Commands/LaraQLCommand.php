<?php

namespace Nodesol\LaraQL\Commands;

use Illuminate\Console\Command;

class LaraQLCommand extends Command
{
    public $signature = 'laraql';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}

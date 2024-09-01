<?php

namespace LaravelFirestore\LaravelFirestoreDriver\Commands;

use Illuminate\Console\Command;

class LaravelFirestoreDriverCommand extends Command
{
    public $signature = 'laravel-firestore-driver';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}

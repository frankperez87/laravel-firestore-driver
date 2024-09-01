<?php

namespace LaravelFirestore\LaravelFirestoreDriver\Commands;

use Illuminate\Console\Command;
use Kreait\Firebase\Factory;

class LaravelFirestoreDriverHealthCheckCommand extends Command
{
    public $signature = 'laravel-firestore-driver:health-check';

    public $description = 'Checks connection status of Firestore';

    public function handle(): int
    {
        $this->comment('Checking connection status of Firestore...');

        try {
            $firebase = (new Factory)->withServiceAccount(config('firestore-driver.credentials'));

            $firestore = $firebase->createFirestore();

            $collectionReference = $firestore->database()->collection('test_collection');

            $collectionReference->documents();

            $this->info('Connection to Firestore is successful!');
        } catch (\Exception $e) {
            $this->error('An unexpected error occurred: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->comment('All done');

        return self::SUCCESS;
    }
}

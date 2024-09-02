<?php

namespace LaravelFirestore\LaravelFirestoreDriver\Commands;

use Google\Cloud\Core\Exception\GoogleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LaravelFirestore\LaravelFirestoreDriver\FirestoreClientConnection;

class LaravelFirestoreDriverHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firestore:health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the health of the Firestore connection.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting Firestore health check...');

        try {
            // Attempt to get a Firestore connection
            /** @var FirestoreClientConnection $connection */
            $connection = DB::connection('firestore');

            // Try running a simple query to ensure the connection is valid
            $connection->table('health_check')->limit(1)->get();

            $this->info('Successfully connected to Firestore.');
        } catch (GoogleException $e) {
            $this->error('Failed to connect to Firestore: '.$e->getMessage());

            return 1;
        } catch (\Exception $e) {
            $this->error('An unexpected error occurred: '.$e->getMessage());

            return 1;
        }

        $this->info('Firestore connection is healthy.');

        return 0;
    }
}

<?php

namespace LaravelFirestore\LaravelFirestoreDriver;

use Google\Cloud\Firestore\FirestoreClient;
use LaravelFirestore\LaravelFirestoreDriver\Commands\LaravelFirestoreDriverHealthCheckCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelFirestoreDriverServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-firestore-driver')
            ->hasConfigFile('firestore-driver')
            ->hasCommand(LaravelFirestoreDriverHealthCheckCommand::class);
    }

    public function packageBooted()
    {
        app('db')->extend('firestore', function ($config) {
            $client = new FirestoreClient([
                'projectId' => $config['project_id'],
                'keyFilePath' => $config['key_file_path'] ?? null, // optional
            ]);

            // Ensure correct instantiation of FirestoreClientConnection
            return new FirestoreClientConnection(
                $client,
                $config,
                $config['options'] ?? [] // Handle options properly
            );
        });

        app()->singleton('firestore', function ($app) {
            $config = $app['config']['database.connections.firestore'];
            $client = new FirestoreClient([
                'projectId' => $config['project_id'],
                'keyFilePath' => $config['key_file_path'] ?? null, // optional
            ]);

            return new FirestoreClientConnection($client, $config);
        });
    }
}

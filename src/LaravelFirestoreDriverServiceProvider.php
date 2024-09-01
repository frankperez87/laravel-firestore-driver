<?php

namespace LaravelFirestore\LaravelFirestoreDriver;

use LaravelFirestore\LaravelFirestoreDriver\Commands\LaravelFirestoreDriverHealthCheckCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelFirestoreDriverServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-firestore-driver')
            ->hasConfigFile()
            ->hasCommand(LaravelFirestoreDriverHealthCheckCommand::class);
    }
}

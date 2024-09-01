<?php

namespace LaravelFirestore\LaravelFirestoreDriver;

use LaravelFirestore\LaravelFirestoreDriver\Commands\LaravelFirestoreDriverCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelFirestoreDriverServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-firestore-driver')
            ->hasConfigFile()
            // ->hasViews()
            // ->hasMigration('create_laravel_firestore_driver_table')
            ->hasCommand(LaravelFirestoreDriverCommand::class);
    }
}

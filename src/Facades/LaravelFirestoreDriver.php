<?php

namespace LaravelFirestore\LaravelFirestoreDriver\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \LaravelFirestore\LaravelFirestoreDriver\LaravelFirestoreDriver
 */
class LaravelFirestoreDriver extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \LaravelFirestore\LaravelFirestoreDriver\LaravelFirestoreDriver::class;
    }
}

<?php

namespace ThaLuffy\DataInserter;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

use ThaLuffy\DataInserter\Commands\InsertRecords;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->mergeConfigFrom($this->__configPath(), 'datainserter');

        $this->commands([
            InsertRecords::class,
        ]);

        $this->loadMigrationsFrom($this->__migrationPath());

        $this->publishes([
            $this->__migrationPath() => database_path('migrations')
        ], 'migrations');

        $this->publishes([
            $this->__configPath() => config_path('datainserter.php')
        ], 'config');
    }

    /**
     * Set the config path
     *
     * @return string
     */
    private function __migrationPath()
    {
        return __DIR__ . '/../database/migrations/';
    }

    /**
     * Set the config path
     *
     * @return string
     */
    private function __configPath()
    {
        return __DIR__ . '/../config/datainserter.php';
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
    }
}
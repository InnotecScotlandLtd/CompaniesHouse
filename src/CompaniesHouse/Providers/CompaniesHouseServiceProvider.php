<?php

namespace InnotecScotlandLtd\CompaniesHouse\Providers;

use Illuminate\Support\ServiceProvider;


class CompaniesHouseServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config' => base_path('config'),
        ]);
    }

    public function register()
    {

    }
}
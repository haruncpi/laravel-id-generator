<?php namespace Haruncpi\LaravelIdGenerator;

use Illuminate\Support\ServiceProvider;

class IdGeneratorServiceProvider extends ServiceProvider
{

    public function boot()
    {
        
    }

    
    public function register()
    {
        $this->app->make('Haruncpi\LaravelIdGenerator\IdGenerator');
    }

}

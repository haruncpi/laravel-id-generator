<?php namespace Haruncpi\IdGenerator;

use Illuminate\Support\ServiceProvider;

class IdGeneratorServiceProvider extends ServiceProvider
{

    public function boot()
    {
        
    }

    
    public function register()
    {
        $this->app->make('Haruncpi\IdGenerator\IdGenerator');
    }

}

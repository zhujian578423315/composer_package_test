<?php

namespace Test\test;

use Illuminate\Support\ServiceProvider;

class ThirdPackageServiceProvider extends ServiceProvider
{

    protected $defer = true; // 延迟加载服务

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('test', function ($app) {
                return new Test();
//            return new Packagetest($app['session'], $app['config']);

        });
    }

    /**

     * Get the services provided by the provider.

     *

     * @return array

     */

    public function provides()
    {

        // 因为延迟加载 所以要定义 provides 函数 具体参考laravel 文档

        return ['test'];

    }
}

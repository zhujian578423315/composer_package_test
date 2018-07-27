<?php

namespace ButcherClub\Resource;

use Illuminate\Support\ServiceProvider;

class ButcherClubServiceProvider extends ServiceProvider
{

    protected $defer = true; // 延迟加载服务

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/butcherclub.php' => config_path('butcherclub.php'), // 发布配置文件到 laravel 的config 下
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('butcherClue', function ($app) {
                return new ButcherClubController();
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

        return ['butcherClue'];

    }
}

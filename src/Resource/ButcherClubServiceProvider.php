<?php

namespace ButcherClub\Resource;

use App\Leyao\Event\Store\Commerce\PaymentTransaction\Created;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ButcherClubServiceProvider extends ServiceProvider
{

    protected $defer = false; // 延迟加载服务

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
//        //添加回调路由
        $this->app['router']->post('/ns_callback', 'ButcherClub\Resource\ButcherClubController@paymentCallback')->name('ns_callback');

        $this->app['events']->listen([Created::class], function (Created $event) {
            $payment_transaction = $event->payment_transaction;
            if ($payment_transaction->paymentConfig->code == 'ns'){
                $qrcode =  app('butcherClue')->getQRcode($payment_transaction);
                $info['success']          = true;
                $info['qrcode']           = $qrcode;
                $payment_transaction->details = array_merge($payment_transaction->details, ['info' => $info]);
                if (!$payment_transaction->save()) {
                    throw new HttpException(500, 'Update payment transaction failed, after payment request.');
                }
                return false;
            }else{
                //如果不是ns支付则走其他支付流程
                return true;
            }
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

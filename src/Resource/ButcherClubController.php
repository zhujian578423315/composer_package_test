<?php

namespace ButcherClub\Resource;


use App\Leyao\Commerce\Sauce\Factory\PaymentTransactionFactory;
use App\Leyao\Event\Store\Commerce\PaymentTransaction\NotifyCompleted;
use App\Models\Commerce\Goods\Payment\PaymentConfig;
use App\Models\Commerce\Order\Order;
use App\Models\Commerce\Order\PaymentTransaction;
use App\Models\Members\User;
use Dingo\Api\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ButcherClubController
{

    protected $store_id;

    function __construct()
    {

    }

    public function getQRcode($order_id, $payment_config_id, $amount)
    {
        $order = Order::findOrFail($order_id);
        $payment_config = PaymentConfig::findOrFail($payment_config_id);
        $url = $this->config('url');
        $key = $this->config('key');
        $shop_id = $this->config('shop_id');
        $out_trade_no = generate_payment_out_trade_no($order, $payment_config);
        $client = new Client();
        $body = [
            'food_names' => json_encode(['abc']),
            'price' => $amount,
            'out_trade_no' => $out_trade_no,
            'sign' => $this->sign($out_trade_no, $key),
            'shop_id' => $shop_id,
            'callback_url' => 'http://dev.leyao.webapp.wildstorm.cn:8080/delivery/eleme/callback'
        ];
        $res = $client->request('POST', $url, [RequestOptions::JSON => $body]);
        $content = $res->getBody()->getContents();
        $json = json_decode($content, true, 512, JSON_BIGINT_AS_STRING);

        if (isset($json['str'])) {
            $paymentTransaction = PaymentTransactionFactory::make(
                $order,
                $payment_config,
                $amount,
                \Auth::user()??User::first(),
                'CNY',
                generate_uuid());
            if (!$paymentTransaction->save()) {
                throw  new HttpException('425', '添加支付事务失败');
            }
            return $json['str'];
        } else {
            throw  new HttpException('425', $json['msg'] ?? '下单失败');
        }
    }

    public function paymentCallback(Request $request)
    {
        $input = $request->all();
        $sign = $input['sign'];
        $out_trade_no = $input['out_trade_no'];
        if ($sign != $this->sign($out_trade_no, $key = $this->config('key'))) {
            throw new  HttpException('425', '验签失败');
        }

        $payment_transaction = PaymentTransaction::where('out_trade_no', $out_trade_no)->firstOrfail();
        $this->store_id = $payment_transaction->store_id;
        if ($payment_transaction && in_array($payment_transaction->state,
                PaymentTransaction::COMPLETED_PAY_TRANSITIONS)
        ) {
            //若订单已经完成 则返回 success
            return $this->successResponse();
        }
        if ($input['code'] == 1) {
            $payment = $input['payment'];
            if ($payment == $payment_transaction->amount) {
                $payment_transaction->state =
                    ($payment_transaction->order->total == $payment_transaction->amount) ?
                        PaymentTransaction::TRANSITION_PAY : PaymentTransaction::TRANSITION_PARTIALLY_PAY;
                $paymentConfig = $this->nsPaymentConfig($input['payment_type']);
                if ($paymentConfig) {
                    $payment_transaction->payment_config_id = $paymentConfig->id;
                }
                if (!$payment_transaction->save()) {
                    throw new HttpException(500, 'Update payment transaction state failed.');
                }
                event(new NotifyCompleted($payment_transaction));
            }
        }
        return $this->faildResponse();
    }


    protected function sign($out_trade_no, $key)
    {
        return md5($key . $out_trade_no);
    }


    protected function config($key)
    {
        if (config('butcherclub.' . $key)) {
            return config('butcherclub.' . $key);
        }

        $config = include(__DIR__ . '/config/butcherclub.php');
        if (is_array($config)) {
            if (isset($config[$key])) {
                return $config[$key];
            }
        }
        return null;
    }

    protected function nsPaymentConfig($type)
    {
        if (is_null($this->store_id)) {
            return PaymentConfig::where('code', $type)->first();
        } else {
            return PaymentConfig::where('store_id', $this->store_id)->where('code', $type)->first();
        }
    }


    protected function successResponse()
    {
        return json_encode(["code" => [
            "errorcode" => "0",
            "errmsg" => "success"
        ],
            "data" => ""
        ]);
    }

    protected function faildResponse()
    {
        return json_encode(["code" => [
            "errorcode" => "0",
            "errmsg" => "faild"
        ],
            "data" => ""
        ]);
    }

}

<?php

namespace ButcherClub\Resource;


use App\Leyao\Commerce\Sauce\Factory\PaymentTransactionFactory;
use App\Leyao\Event\Store\Commerce\PaymentTransaction\NotifyCompleted;
use App\Models\Commerce\Goods\Payment\PaymentConfig;
use App\Models\Commerce\Order\Order;
use App\Models\Commerce\Order\PaymentTransaction;
use App\Models\Members\User;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ButcherClubController extends Controller
{

    protected $store_id;

    function __construct()
    {

    }

    public function getQRcode($payment_transaction)
    {
        $order = $payment_transaction->order;
        $payment_config = $payment_transaction->paymentConfig;
        $amount = $payment_transaction->amount;
        $url = $this->config('url');
        $body = $this->make_ns_item($order, $payment_config, $amount);
        try {
            $content = (new Client())->request('post', $url, [RequestOptions::JSON => $body])->getBody()->getContents();
        } catch (\Exception $e) {
            throw  new HttpException('425', '支付请求失败:'.$e->getMessage());
        }

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

    public function make_ns_item($order, $payment_config, $amount)
    {
        $ns_food_names = array();
        $ns_food_nums = array();
        $ns_food_prices = array();
//        $ns_food_ids = array();
//        $ns_food_codes = array();
        foreach ($order->items as $item) {
            if ($item->parent_id == null) {
                array_push($ns_food_names, $item->goods->title);
                array_push($ns_food_nums, $item->quantity);
                array_push($ns_food_prices, $item->unitPrice);
//                array_push($ns_food_ids,$item->goods_id);
//                array_push($ns_food_codes,$item->goods->code);
            }
        }
        $key = $this->config('key');
        $shop_id = $this->config('shop_id');
        $out_trade_no = generate_payment_out_trade_no($order, $payment_config);
        $body = [
            'food_names' => json_encode($ns_food_names),
            'food_nums' => json_encode($ns_food_nums),
            'food_prices' => json_encode($ns_food_prices),
//            'food_ids' => json_encode($ns_food_ids),
//            'food_codes' => json_encode($ns_food_codes),
            'price' => abs($amount),
            'discount'=>abs($order->adjustments_total),
            'out_trade_no' => $out_trade_no,
            'sign' => $this->sign($out_trade_no, $key),
            'shop_id' => $shop_id,
            'callback_url' => route('ns_callback')
        ];
        return $body;
    }


    public function paymentCallback(Request $request)
    {
        Log::info($request->all());
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
            if ($input['payment'] == $payment_transaction->amount) {
                DB::transaction(function () use ($payment_transaction, $input) {
                    $payment_transaction->state =
                        ($payment_transaction->order->total == $payment_transaction->amount) ?
                            PaymentTransaction::TRANSITION_PAY : PaymentTransaction::TRANSITION_PARTIALLY_PAY;
                    $payment_config = $this->nsPaymentConfig($input['payment_type']);
                    if ($payment_config) {
                        $payment_transaction->out_trade_no = generate_payment_out_trade_no($payment_transaction->order, $payment_config);
                        $payment_transaction->payment_config_id = $payment_config->id;
                    }
                    if (!$payment_transaction->save()) {
                        throw new HttpException(500, 'Update payment transaction state failed.');
                    }
                    event(new NotifyCompleted($payment_transaction));
                });
                return $this->successResponse();
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

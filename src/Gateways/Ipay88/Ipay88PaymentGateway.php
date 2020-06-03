<?php

namespace PaymentGateway\Gateways\Ipay88;

use PaymentGateway\PayableOrder;
use PaymentGateway\PaymentGateway as PaymentGatewayInterface;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\View;

class Ipay88PaymentGateway implements PaymentGatewayInterface
{
    protected $order;

    /**
     * Set the payable order.
     *
     * @param PayableOrder $order
     *
     * @return PaymentGatewayInterface
     */
    public function setOrder(PayableOrder $order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Get the payment form.
     *
     * @param array $attributes
     * @return string
     */
    public function getPaymentForm($attributes = [])
    {
        $order = $this->order;
        $hash = $this->calculatePaymentFormHash($order);

        View::addNamespace('payment', __DIR__);

        return View::make('payment::form')->with(compact('order', 'hash', 'attributes'));
    }

    /**
     * Calculate the hash for the PayableOrder.
     *
     * @param PayableOrder $order
     *
     * @return string
     */
    private function calculatePaymentFormHash(PayableOrder $order)
    {
        return sha1(
            config('payment.ipay88.merchantKey').
            $order->getPaymentOrderId().
            $order->getPaymentAmount().
            $order->getPaymentDescription().
            config('payment.ipay88.merchantCode')
        );
    }

    /**
     * Validate the gateway response.
     *
     * @param mixed $orderId
     * @param array $gatewayResponse
     *
     * @return bool
     */
    public function validateGatewayResponse($orderId, $gatewayResponse = null)
    {
        $gatewayResponse = $gatewayResponse ?: Input::all();

        return (new Ipay88ResponseValidator($orderId, $gatewayResponse))->validate();
    }

    /**
     * Determine the result of the payment
     * If gatewayResponse is null, Input::all() will be used.
     *
     * @param array $gatewayResponse
     *
     * @return string
     */
    public function getPaymentResult($gatewayResponse = null)
    {
        $gatewayResponse = $gatewayResponse ?: Input::all();

        switch ($gatewayResponse['Status']) {
            case 'AU':
                $paymentResult = self::PAYMENT_RESULT_OK;
                break;
            case 'DE':
                $paymentResult = self::PAYMENT_RESULT_DECLINED;
                break;
            case 'CA':
                $paymentResult = self::PAYMENT_RESULT_CANCELLED_BY_CARDHOLDER;
                break;
            case 'TI':
                $paymentResult = self::PAYMENT_TIMED_OUT;
                break;
            case 'EX':
                $paymentResult = self::PAYMENT_RESULT_FAILED;
                break;
            default:
                $paymentResult = self::PAYMENT_RESULT_FAILED;
                break;
        }

        return $paymentResult;
    }
}

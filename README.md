**THIS PACKAGE IS NOT MAINTAINED ANYMORE**

# Accept payments from payment gateways

[![Latest Version](https://img.shields.io/github/release/yihou/payment.svg?style=flat-square)](https://github.com/yihou/payment/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/payment.svg?style=flat-square)](https://packagist.org/packages/yihou/payment)

This Laravel package enables you to accept payments from payment gateways. 
Currently the only implementation is [iPay88](https://www.ipay88.com/).

## Installation
The package can be installed through Composer:

```
composer require spatie/payment
```

This service provider must be installed:

```php

//for laravel <=4.2: app/config/app.php

'providers' => [
    ...
    'PaymentGateway\PaymentServiceProvider'
    ...
];
```

## Configuration
You can publish the configuration file using this command:
```
php artisan config:publish yihou/payment
```

A configuration-file with some sensible defaults will be placed in your config/packages directory:


## General payment flow

Though there are multiple ways to pay an order, most payment gateways except you to follow the following flow in your checkout process:

### 1. The customer is redirected to the payment provider
After the customer has gone through the checkout process and is ready to pay, the customer must be redirected to site of the payment provider.

The redirection is accomplished by submitting a form with some hidden fields. The form must post to the site of the payment provider. The hidden fields minimally specify the amount that must be paid, the order id and a hash.

The hash is calculated using the hidden form fields and a non-public secret. The hash used by the payment provider to verify if the request is valid.


### 2. The customer pays on the site of the payment provider
The customer arrived on the site of the payment provider and gets to choose a payment method. All steps necessary to pay the order are taken care of by the payment provider. 

### 3. The customer gets redirected back
After having paid the order the customer is redirected back. In the redirection request to the shop-site some values are returned. The values are usually the order id, a paymentresult and a hash.

The hash is calculated out of some of the fields returned and a secret non-public value. This hash is used to verify if the request is valid and comes from the payment provider. It is paramount that this hash is thoroughly checked.

The payment result can be something like "payment ok", "customer cancelled payment" or "payment declined".

## Usage
This package can greatly help you with step 1. and 3. of the general flow

### 1. Redirecting to customer to the payment provider
Let's get technical. In the controller in which you will present a view to redirect to user to the payment provider you must inject the payment gateway like so:

```php
use PaymentGateway\PaymentGateway;

class CheckoutConfirmOrderController extends BaseController {


    /**
     * @var PaymentGateway
     */
    protected $paymentGateway;

    public function __construct(.. PaymentGateway $paymentGateway ...)
    {
        ...
        $this->paymentGateway = $paymentGateway;
        ...
    }
```

In the same controller in the method in which you present the view you must set the ```$order``` that you've probably build up during the checkout-process.

```php
public function showOrderDetails()
    {
        $order = $this->orderRepo->getCurrentOrder();
        $paymentGateway = $this->paymentGateway->setOrder($order);

        return View::make('front.checkout.confirmOrder')->with(compact('order', 'paymentGateway'));
    }
```

The ```$order``` you pass to the payment gateway must adhere to the ```PayableOrder```-interface:

```php

interface PayableOrder {

    /**
     * @return string
     */
    public function getPaymentOrderId();

    /**
     * @return double
     */
    public function getPaymentAmount();

    /**
     * @return string
     */
    public function getPaymentDescription();

    /**
     * @return string
     */
    public function getCustomerEmail();

    /**
     * @return string
     */
    public function getCustomerLanguage();


} 
```

So your Order-model should look something like

```php
....
use PaymentGateway\PayableOrder;

class Order extends Eloquent implements PayableOrder
{

...

    /**
     * @return string
     */
    public function getPaymentOrderId()
    {
        return $this->id;
    }

    /**
     * Should be in eurocents for most payments providers
     * @return double
     */
    public function getPaymentAmount()
    {
        return $this->getTotal() * 100;
    }

    /**
     * @return string
     */
    public function getPaymentDescription()
    {
        return 'Order ' . $this->id;
    }

    /**
     * @return string
     */
    public function getCustomerEmail()
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getCustomerLanguage()
    {
        return App::getLocale();
    }
}
```

Please note that for most payment providers the result of ```getPaymentAmount()```should be specified in eurocents.

After you've taken care of all steps above you can generate the form that will redirect the customer to the payment provider.

In your view you can simply use the ```getPaymentForm()```-method

```
{{ $paymentGateway->getPaymentForm() }}
```

The result of this form is something like:

```
<form method="POST" action="https://www.ebonline.be/test/mpi/authenticate" accept-charset="UTF-8">
    <input name="Uid" type="hidden" value="9063470101">
    <input name="Orderid" type="hidden" value="11">
    <input name="Amount" type="hidden" value="5163">
    <input name="Description" type="hidden" value="Order 11">
    <input name="Hash" type="hidden" value="dee1c95c13aa037ded1a97482be4d10cb9a25e92">
    <input name="Beneficiary" type="hidden" value="Shopname">
    <input name="Redirecttype" type="hidden" value="DIRECT">
    <input name="Redirecturl" type="hidden" value="http://shopname.com/verify-payment">
    <input type="submit" value="Pay order">
</form>
```
When clicking the submit button the customer gets redirected to the site of payment provider.

You can also pass html attributes for the form element as an array.

```
{{ $paymentGateway->getPaymentForm(['class' => 'form']) }}
```

```
<form method="POST" action="https://www.ebonline.be/test/mpi/authenticate" accept-charset="UTF-8" class="form">
<!-- ... -->
</form>
```

### 2. Verifying the payment
So now we've redirected the customer to the payment provider. The customer did some actions there (hopefully he or she paid the order) and now gets redirected back to our shop site.

The payment provider will redirect the customer to the url of the route that is specified in the ```paymentLandingPageRoute```-option of the config-file.

We must validate if the redirect to our site is a valid request (we don't want imposters to wrongfully place non-paid order).

In the controller that handles the request coming from the payment provider inject the ```PaymentGateway```

```php
use PaymentGateway\PaymentGateway;

class CheckoutPaymentVerificationController extends BaseController {

    protected $paymentGateway;

    public function __construct(PaymentGateway $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }
    
    ...
```

Then, in the same controller, in the method you use to handle the request coming from the payment provider, use the ```validateGatewayResponse```-method:

```php
  public function verifyPayment()
    {
        $this->paymentGateway->validateGatewayResponse(Checkout::getCurrentOrderId());
    }
```
That method requires the order id that you are expecting a payment for. Usually you should have stored that order id in session prior to redirecting to user to the site of payment provider.

Notice that in previous example ```Checkout::getCurrentOrderId()``` is used. If you want such an elegant syntax check out the [spatie/checkout-package](https://github.com/spatie/checkout).

If the ```validateGatewayResponse```-method concludes that the request was not valid a ```PaymentGateway\Exceptions\PaymentVerificationFailedException```-exception is thrown.
 
### 3. Getting the payment result
After you've verified that the redirect from the payment provider to your site is valid you can determine the result of the payment.

To determine the result you can use the ```getPaymentResult()```-method. It can return these constants:
- ```PaymentGateway\PaymentGateway::PAYMENT_RESULT_OK```: all is well, the order has been paid
- ```PaymentGateway\PaymentGateway::PAYMENT_RESULT_CANCELLED_BY_CARDHOLDER```: the customer has cancelled the payment
- ```PaymentGateway\PaymentGateway::PAYMENT_RESULT_DECLINED```: the customer tried to pay, but his payment got declined by that financial institution that handles the payment
- ```PaymentGateway\PaymentGateway::PAYMENT_RESULT_FAILED```: an unexpected error occured.


Here is an example controller in which we verify the payment-request and determine the result:
```php
use PaymentGateway\PaymentGateway;

class CheckoutPaymentVerificationController extends BaseController {

    protected $paymentGateway;

    public function __construct(PaymentGateway $paymentGateway)
    {

        $this->paymentGateway = $paymentGateway;
    }

    public function verifyPayment()
    {
        $this->paymentGateway->validateGatewayResponse(Checkout::getCurrentOrderId());

        switch($this->paymentGateway->getPaymentResult())
        {
            case PaymentGateway::PAYMENT_RESULT_OK:
                //take necessary actions to mark order as confirmed
                break;

            case PaymentGateway::PAYMENT_RESULT_CANCELLED_BY_CARDHOLDER:
                //take necessary actions to mark order as failed
                break;

            case PaymentGateway::PAYMENT_RESULT_DECLINED:
                //take necessary actions to mark order as failed
                break;

            case PaymentGateway::PAYMENT_RESULT_FAILED:
                //take necessary actions to mark order as failed
                break;

            case PaymentGateway::PAYMENT_TIMED_OUT:
                //take necessary actions to mark order as failed
                break;

            default:
                throw new Exception('Unknown payment gateway answer');
                break;

        }
    }
}
```
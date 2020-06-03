<?php

namespace PaymentGateway;

use Illuminate\Support\ServiceProvider;
use PaymentGateway\Gateways\Ipay88\Ipay88PaymentGateway;

class PaymentServiceProvider extends ServiceProvider
{
    public function boot()
    {
	$this->mergeConfigFrom(__DIR__.'/../config/payment.php', 'payment');
    $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'payment');

	$this->publishes([
		__DIR__.'/../resources/lang' => base_path('resources/lang/vendor/payment'),
	], 'lang');

	$this->publishes([
		__DIR__.'/../config/payment.php' => base_path('config/payment.php'),
	], 'config');
    }

    public function register()
    {
        $this->app->bind(PaymentGateway::class, Ipay88PaymentGateway::class);
    }
}

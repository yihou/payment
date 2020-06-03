<?php
return
    [
        'form' => [
            /*
             * The class or classes that you want to put on the submit button
             * of the payment form
             */
            'submitButtonClass' => 'test'
        ],

        'ipay88' => [
            'merchantKey' => env('IPAY88_MERCHANT_KEY'),
            'merchantCode' => env('IPAY88_MERCHANT_CODE'),
            'responseUrl' => env('IPAY88_RESPONSE_URL'),
            'backendResponseUrl' => env('IPAY88_BACKEND_RESPONSE_URL'),
        ],
    ];

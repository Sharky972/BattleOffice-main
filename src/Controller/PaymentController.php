<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Stripe\Checkout\Session;

class PaymentService extends AbstractController
{

    private $stripeSecretKey;

    public function __construct()
    {
        $this->stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'];

        \Stripe\Stripe::setApiKey($this->stripeSecretKey);
    }

    public function createSession($amount)
    {
        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'name' => 'Payment for your order',
                'description' => 'Order payment',
                'amount' => $amount * 100,
                'currency' => 'USD',
                'quantity' => 1
            ]],
            'success_url' => 'http://example.com/success',
            'cancel_url' => 'http://example.com/cancel',
        ]);

        return $session->id;
    }
}

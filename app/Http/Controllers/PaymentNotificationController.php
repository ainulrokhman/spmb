<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentNotificationController extends Controller
{
    /**
     * Midtrans notification webhook. The signature inside the payload
     * is verified by the payment service before anything changes.
     */
    public function midtrans(Request $request, PaymentService $payments)
    {
        $handled = $payments->handleMidtransNotification($request->all());

        return response()->json(['handled' => $handled], $handled ? 200 : 400);
    }
}

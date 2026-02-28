<?php

use App\Http\Controllers\WhatsappInstanceController;
use App\Http\Controllers\CrmInboxController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// WhatsApp Bun server webhook (protected by shared secret header)
Route::post('/whatsapp/webhook', [WhatsappInstanceController::class , 'webhook']);

// CRM incoming message webhook
Route::post('/crm/webhook/message', [CrmInboxController::class , 'incomingMessage']);

// Mercado Pago Payment Webhook
Route::post('/payments/webhook/{team?}', [\App\Http\Controllers\CrmPaymentController::class , 'webhook'])->name('api.payments.webhook');
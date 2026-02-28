<?php

use App\Http\Controllers\WhatsappInstanceController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\CrmLeadController;
use App\Http\Controllers\CrmContactController;
use App\Http\Controllers\CrmInboxController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
    'laravelVersion' => Application::VERSION,
    'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
            return Inertia::render('Dashboard');
        }
        )->name('dashboard');

        // WhatsApp Connections
        Route::get('/connections', [WhatsappInstanceController::class , 'index'])->name('connections');
        Route::post('/connections', [WhatsappInstanceController::class , 'store'])->name('connections.store');
        Route::post('/connections/{instance}/connect', [WhatsappInstanceController::class , 'connect'])->name('connections.connect');
        Route::post('/connections/{instance}/disconnect', [WhatsappInstanceController::class , 'disconnect'])->name('connections.disconnect');
        Route::delete('/connections/{instance}', [WhatsappInstanceController::class , 'destroy'])->name('connections.destroy');

        // CRM
        Route::get('/crm', [CrmController::class , 'index'])->name('crm');
        Route::post('/crm/pipelines', [CrmController::class , 'storePipeline'])->name('crm.pipelines.store');
        Route::delete('/crm/pipelines/{pipeline}', [CrmController::class , 'destroyPipeline'])->name('crm.pipelines.destroy');
        Route::post('/crm/pipelines/{pipeline}/stages', [CrmController::class , 'storeStage'])->name('crm.stages.store');
        Route::put('/crm/stages/{stage}', [CrmController::class , 'updateStage'])->name('crm.stages.update');
        Route::delete('/crm/stages/{stage}', [CrmController::class , 'destroyStage'])->name('crm.stages.destroy');
        Route::put('/crm/pipelines/{pipeline}/stages/reorder', [CrmController::class , 'reorderStages'])->name('crm.stages.reorder');
        Route::post('/crm/tags', [CrmController::class , 'storeTag'])->name('crm.tags.store');

        // CRM Leads
        Route::post('/crm/leads', [CrmLeadController::class , 'store'])->name('crm.leads.store');
        Route::get('/crm/leads/{lead}', [CrmLeadController::class , 'show'])->name('crm.leads.show');
        Route::put('/crm/leads/{lead}', [CrmLeadController::class , 'update'])->name('crm.leads.update');
        Route::put('/crm/leads/{lead}/move', [CrmLeadController::class , 'moveStage'])->name('crm.leads.move');
        Route::delete('/crm/leads/{lead}', [CrmLeadController::class , 'destroy'])->name('crm.leads.destroy');
        Route::post('/crm/leads/{lead}/notes', [CrmLeadController::class , 'storeNote'])->name('crm.leads.notes.store');
        Route::post('/crm/leads/{lead}/tasks', [CrmLeadController::class , 'storeTask'])->name('crm.leads.tasks.store');
        Route::put('/crm/leads/{lead}/tags', [CrmLeadController::class , 'syncTags'])->name('crm.leads.tags.sync');
        Route::put('/crm/tasks/{task}/complete', [CrmLeadController::class , 'completeTask'])->name('crm.tasks.complete');

        // CRM Contacts
        Route::get('/crm/contacts', [CrmContactController::class , 'index'])->name('crm.contacts.index');
        Route::post('/crm/contacts', [CrmContactController::class , 'store'])->name('crm.contacts.store');
        Route::put('/crm/contacts/{contact}', [CrmContactController::class , 'update'])->name('crm.contacts.update');
        Route::delete('/crm/contacts/{contact}', [CrmContactController::class , 'destroy'])->name('crm.contacts.destroy');

        // CRM Inbox (send only — all data flows via WebSocket)
        Route::post('/inbox/{conversation}/send', [CrmInboxController::class , 'send'])->name('crm.inbox.send');
        Route::post('/inbox/{conversation}/read', [CrmInboxController::class , 'markRead'])->name('crm.inbox.read');
        Route::get('/inbox/{conversation}/history', [CrmInboxController::class , 'loadHistory'])->name('crm.inbox.history');
        Route::get('/inbox/templates', [CrmInboxController::class , 'getTemplates'])->name('crm.inbox.templates');
        Route::get('/inbox/{conversation}/summarize', [CrmAiController::class , 'summarize'])->name('crm.inbox.summarize');
        // CRM Payments
        Route::get('/crm/payments', [\App\Http\Controllers\CrmPaymentController::class , 'index'])->name('crm.payments.index');
        Route::post('/crm/payments', [\App\Http\Controllers\CrmPaymentController::class , 'store'])->name('crm.payments.store');
        Route::post('/crm/payments/{payment}/resend', [\App\Http\Controllers\CrmPaymentController::class , 'resend'])->name('crm.payments.resend');

        // Team Payment Settings
        Route::put('/teams/{team}/payment-settings', [\App\Http\Controllers\TeamPaymentSettingsController::class , 'update'])->name('teams.payment-settings.update');
        Route::get('/teams/{team}/payment-settings/mercado-pago/connect', [\App\Http\Controllers\TeamPaymentSettingsController::class , 'connectMercadoPago'])->name('teams.payment-settings.mercado-pago.connect');
        Route::get('/teams/payment-settings/mercado-pago/callback', [\App\Http\Controllers\TeamPaymentSettingsController::class , 'callbackMercadoPago'])->name('teams.payment-settings.mercado-pago.callback');
    });
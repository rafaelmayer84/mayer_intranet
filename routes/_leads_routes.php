<?php

use App\Http\Controllers\LeadController;

// Central de Leads - Dashboard Marketing Juridico
Route::middleware(["auth", "user.active", "modulo:operacional.leads,visualizar"])->group(function () {
    Route::get("/leads", [LeadController::class, "index"])->name("leads.index");
    Route::get("/leads/{lead}", [LeadController::class, "show"])->name("leads.show");
    Route::delete("/leads/{lead}", [LeadController::class, "destroy"])->name("leads.destroy");
    Route::patch("/leads/{lead}/status", [LeadController::class, "updateStatus"])->name("leads.updateStatus");
    Route::post("/leads/{lead}/reprocess", [LeadController::class, "reprocess"])->name("leads.reprocess");
    Route::get("/api/leads/stats", [LeadController::class, "stats"])->name("leads.stats");
    Route::get("/leads/export-google-ads", [LeadController::class, "exportGoogleAds"])->name("leads.export-google-ads");
    Route::get("/leads/export", [LeadController::class, "export"])->name("leads.export");
});

// Webhook SendPulse - sem CSRF, sem auth (endpoint publico)
Route::post("/webhook/leads", [LeadController::class, "webhook"])->name("webhook.leads")->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

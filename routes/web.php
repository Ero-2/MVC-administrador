<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\OrdenController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DetalleOrdenController;

// Ruta para el chat IA
Route::post('/chat', [ChatController::class, 'ask'])->name('chat.ask');

// Rutas de prueba para Vanna
Route::get('/test-vanna', [ChatController::class, 'testVanna']);
Route::post('/train-vanna', [ChatController::class, 'train']);

// Rutas individuales
Route::get('/productos', [ProductoController::class, 'index']);
Route::get('/ventas', [OrdenController::class, 'index']);

// 🚀 RUTAS DEL TRACKING DE DETALLES DE ORDEN (MVC COMPLETO)
Route::prefix('detalle-orden')->group(function () {
    Route::get('/dashboard', [DetalleOrdenController::class, 'dashboard'])->name('detalle-orden.dashboard');
    Route::get('/pipeline/{estado?}', [DetalleOrdenController::class, 'pipeline'])->name('detalle-orden.pipeline');
    Route::get('/por-orden/{ordenId}', [DetalleOrdenController::class, 'porOrden'])->name('detalle-orden.por-orden');
    Route::get('/tracking/{id}', [DetalleOrdenController::class, 'tracking'])->name('detalle-orden.tracking');
    
    // ✅ Corregida para coincidir con el frontend
    Route::post('/{id}/estado', [DetalleOrdenController::class, 'updateEstado'])->name('detalle-orden.update-estado');
    
    Route::post('/bulk-update', [DetalleOrdenController::class, 'bulkUpdate'])->name('detalle-orden.bulk-update');
});

// Página principal
Route::get('/', [HomeController::class, 'index'])->name('home');
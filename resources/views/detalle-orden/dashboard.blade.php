<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tracking de Productos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Tracking de Productos</h1>
            <p class="text-gray-600 text-lg">Seguimiento y gestión del estado de productos por orden</p>
        </div>
        
        <!-- Pipeline Visual -->
        <div class="mb-8">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Pipeline de Estados</h2>
                <div class="flex items-center justify-between">
                    @foreach(\App\Models\DetalleOrden::PIPELINE as $key => $estado)
                        @if($estado['orden'] > 0)
                        <div class="flex flex-col items-center flex-1">
                            <div class="relative">
                                <div class="w-20 h-20 rounded-full {{ $estado['color'] }} flex items-center justify-center shadow-lg">
                                    <i class="{{ $estado['icono'] }} text-3xl text-white"></i>
                                </div>
                                <div class="absolute -bottom-2 -right-2 w-8 h-8 bg-white rounded-full shadow-md flex items-center justify-center border-2 border-gray-100">
                                    <span class="text-sm font-bold text-gray-700">{{ $estadisticas[$key]->total ?? 0 }}</span>
                                </div>
                            </div>
                            <span class="text-sm font-semibold text-gray-900 mt-4">{{ $estado['nombre'] }}</span>
                            <span class="text-xs text-gray-500 mt-1">items</span>
                        </div>
                        
                        @if(!$loop->last)
                        <div class="flex-1 h-1 bg-gray-200 mx-4 rounded-full" style="max-width: 80px;">
                            <div class="h-full bg-gradient-to-r from-gray-300 to-gray-400 rounded-full"></div>
                        </div>
                        @endif
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Detalles Recientes -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                    <div class="border-b border-gray-200 px-6 py-5 flex justify-between items-center">
                        <h2 class="text-2xl font-bold text-gray-900">Detalles Recientes</h2>
                        <a href="{{ route('detalle-orden.pipeline') }}" class="text-blue-600 hover:text-blue-800 text-sm font-semibold flex items-center gap-2">
                            Ver todos 
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="p-6">
                        @if($detallesRecientes->count() > 0)
                        <div class="space-y-4">
                            @foreach($detallesRecientes as $detalle)
                            <div class="border border-gray-200 rounded-xl p-5 hover:shadow-md transition-all duration-200 bg-white">
                                <div class="flex items-start gap-4">
                                    <!-- Icono de estado -->
                                    <div class="flex-shrink-0">
                                        <div class="w-14 h-14 rounded-full {{ $detalle->color_estado }} flex items-center justify-center shadow-sm">
                                            <i class="{{ $detalle->icono_estado }} text-2xl text-white"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Información del producto -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between gap-4 mb-3">
                                            <div>
                                                <h3 class="font-semibold text-gray-900 text-lg">
                                                    {{ $detalle->producto->Nombre ?? 'Producto #' . $detalle->IdInventario }}
                                                </h3>
                                                <div class="flex items-center gap-3 text-sm text-gray-600 mt-1">
                                                    <span class="font-medium">Orden #{{ $detalle->IdOrden }}</span>
                                                    <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                                                    <span>{{ $detalle->Cantidad }} uds</span>
                                                    <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                                                    <span class="font-semibold text-gray-900">${{ number_format($detalle->PrecioUnitario, 2) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Select de estado -->
                                        <div class="flex items-center gap-3">
                                            <label class="text-sm font-medium text-gray-700">Estado:</label>
                                            <select 
                                                onchange="cambiarEstado({{ $detalle->IdDetalleOrden }}, this.value)"
                                                class="flex-1 max-w-xs px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm font-semibold text-gray-900 bg-white">
                                                @foreach(\App\Models\DetalleOrden::PIPELINE as $key => $estado)
                                                    @if($estado['orden'] > 0)
                                                    <option value="{{ $key }}" {{ $detalle->status === $key ? 'selected' : '' }} class="text-gray-900 font-medium">
                                                        {{ $estado['nombre'] }}
                                                    </option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            
                                            <a href="{{ route('detalle-orden.tracking', $detalle->IdDetalleOrden) }}"
                                               class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors text-sm font-medium flex items-center gap-2">
                                                <i class="fas fa-external-link-alt"></i>
                                                Ver
                                            </a>
                                        </div>
                                        
                                        <!-- Barra de progreso -->
                                        <div class="mt-4">
                                            <div class="flex items-center justify-between text-xs text-gray-600 mb-2">
                                                <span class="font-medium">Progreso del proceso</span>
                                                <span class="font-semibold text-gray-900">{{ $detalle->progreso }}%</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                                <div class="h-full rounded-full transition-all duration-300 bg-gray-800" 
                                                     style="width: {{ $detalle->progreso }}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-center py-16 text-gray-500">
                            <i class="fas fa-inbox text-6xl mb-4 text-gray-300"></i>
                            <p class="text-lg font-medium">No hay detalles recientes</p>
                            <p class="text-sm mt-1">Los productos aparecerán aquí cuando se agreguen</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Órdenes Pendientes -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                    <div class="border-b border-gray-200 px-6 py-5">
                        <h2 class="text-xl font-bold text-gray-900">Órdenes con Pendientes</h2>
                    </div>
                    <div class="p-6">
                        @if($ordenesPendientes->count() > 0)
                        <div class="space-y-3">
                            @foreach($ordenesPendientes as $orden)
                            <a href="{{ route('detalle-orden.por-orden', $orden->IdOrden) }}"
                               class="block border border-gray-200 rounded-xl p-4 hover:shadow-md hover:border-blue-300 transition-all duration-200">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <p class="font-semibold text-gray-900 text-lg">Orden #{{ $orden->IdOrden }}</p>
                                        <p class="text-sm text-gray-600 mt-1">
                                            {{ \Carbon\Carbon::parse($orden->FechaOrden)->format('d/m/Y') }}
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-bold bg-red-100 text-red-800">
                                        <i class="fas fa-clock mr-1.5"></i>
                                        {{ $orden->pendientes_count }}/{{ $orden->total_detalles }}
                                    </span>
                                </div>
                                
                                <div class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Total:</span>
                                        <span class="font-semibold text-gray-900">${{ number_format($orden->Total, 2) }}</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                                        @php
                                            $progreso = $orden->total_detalles > 0 ? 
                                                (($orden->total_detalles - $orden->pendientes_count) / $orden->total_detalles) * 100 : 0;
                                        @endphp
                                        <div class="h-full rounded-full bg-gradient-to-r from-green-500 to-green-600 transition-all duration-300" 
                                             style="width: {{ $progreso }}%"></div>
                                    </div>
                                </div>
                            </a>
                            @endforeach
                        </div>
                        @else
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-check-circle text-5xl text-green-500 mb-3"></i>
                            <p class="text-base font-medium text-gray-900">¡Todo al día!</p>
                            <p class="text-sm text-gray-600 mt-1">No hay órdenes pendientes</p>
                        </div>
                        @endif
                    </div>
                </div>
                
                <!-- Acciones Rápidas -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <h3 class="font-bold text-gray-900 mb-4 text-lg">Acciones Rápidas</h3>
                    <div class="space-y-3">
                        <a href="{{ route('detalle-orden.pipeline') }}" 
                           class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-blue-100 text-blue-700 rounded-xl hover:from-blue-100 hover:to-blue-200 transition-all duration-200 shadow-sm">
                            <span class="font-medium"><i class="fas fa-project-diagram mr-2"></i> Ver Pipeline</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                        <a href="{{ route('detalle-orden.pipeline', 'pendiente') }}" 
                           class="flex items-center justify-between p-4 bg-gradient-to-r from-yellow-50 to-yellow-100 text-yellow-700 rounded-xl hover:from-yellow-100 hover:to-yellow-200 transition-all duration-200 shadow-sm">
                            <span class="font-medium"><i class="fas fa-clock mr-2"></i> Ver Pendientes</span>
                            <span class="px-2.5 py-1 bg-yellow-200 text-yellow-800 rounded-lg text-sm font-bold shadow-sm">
                                {{ $estadisticas['pendiente']->total ?? 0 }}
                            </span>
                        </a>
                        <a href="{{ route('detalle-orden.pipeline', 'liberado') }}" 
                           class="flex items-center justify-between p-4 bg-gradient-to-r from-green-50 to-green-100 text-green-700 rounded-xl hover:from-green-100 hover:to-green-200 transition-all duration-200 shadow-sm">
                            <span class="font-medium"><i class="fas fa-check-circle mr-2"></i> Ver Liberados</span>
                            <span class="px-2.5 py-1 bg-green-200 text-green-800 rounded-lg text-sm font-bold shadow-sm">
                                {{ $estadisticas['liberado']->total ?? 0 }}
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-4 right-4 bg-white rounded-xl shadow-2xl border border-gray-200 px-6 py-4 transform translate-y-32 transition-transform duration-300 z-50 max-w-md">
        <div class="flex items-start gap-3">
            <div id="toastIcon" class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center">
                <i class="fas fa-check text-white"></i>
            </div>
            <div class="flex-1">
                <p id="toastTitle" class="font-semibold text-gray-900 text-base"></p>
                <p id="toastMessage" class="text-sm text-gray-600 mt-0.5"></p>
            </div>
            <button onclick="hideToast()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <script>
    function showToast(title, message, type = 'success') {
        const toast = document.getElementById('toast');
        const icon = document.getElementById('toastIcon');
        const titleEl = document.getElementById('toastTitle');
        const messageEl = document.getElementById('toastMessage');
        
        titleEl.textContent = title;
        messageEl.textContent = message;
        
        // Configurar colores según el tipo
        if (type === 'success') {
            icon.className = 'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center bg-green-500';
            icon.innerHTML = '<i class="fas fa-check text-white"></i>';
        } else if (type === 'error') {
            icon.className = 'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center bg-red-500';
            icon.innerHTML = '<i class="fas fa-times text-white"></i>';
        } else {
            icon.className = 'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center bg-blue-500';
            icon.innerHTML = '<i class="fas fa-info text-white"></i>';
        }
        
        toast.style.transform = 'translateY(0)';
        
        setTimeout(() => {
            hideToast();
        }, 4000);
    }
    
    function hideToast() {
        const toast = document.getElementById('toast');
        toast.style.transform = 'translateY(8rem)';
    }
    
    async function cambiarEstado(detalleId, nuevoEstado) {
        const select = event.target;
        const estadoAnterior = select.dataset.estadoAnterior || select.value;
        
        // Desactivar el select mientras se procesa
        select.disabled = true;
        select.classList.add('opacity-50', 'cursor-wait');
        
        try {
            const response = await fetch(`/detalle-orden/${detalleId}/estado`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    status: nuevoEstado
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Actualizar el estado anterior guardado
                select.dataset.estadoAnterior = nuevoEstado;
                
                // Mostrar notificación de éxito
                showToast(
                    'Estado actualizado',
                    `El estado se cambió correctamente a "${data.data.nombre_estado}"`,
                    'success'
                );
                
                // Opcional: Recargar la página después de 1 segundo para actualizar estadísticas
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                // Revertir el select al estado anterior
                select.value = estadoAnterior;
                showToast(
                    'Error al actualizar',
                    data.message || 'No se pudo cambiar el estado',
                    'error'
                );
            }
        } catch (error) {
            console.error('Error:', error);
            // Revertir el select al estado anterior
            select.value = estadoAnterior;
            showToast(
                'Error de conexión',
                'No se pudo conectar con el servidor. Intenta nuevamente.',
                'error'
            );
        } finally {
            // Reactivar el select
            select.disabled = false;
            select.classList.remove('opacity-50', 'cursor-wait');
        }
    }
    
    // Guardar el estado inicial de todos los selects al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('select[onchange*="cambiarEstado"]').forEach(select => {
            select.dataset.estadoAnterior = select.value;
        });
    });
    </script>
    
</body>
</html>
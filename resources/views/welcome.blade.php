<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Laravel Dashboard</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('build')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <!-- AlpineJS -->
    <script src="https://unpkg.com/alpinejs" defer></script>
</head>

<body class="bg-gray-50 min-h-screen p-4 md:p-8 font-sans">

    <div class="max-w-6xl mx-auto">

        <h1 class="text-3xl md:text-4xl font-bold text-center text-gray-800 mb-8">Dashboard de Administración</h1>

        <!-- Ejemplo: Botón en la parte superior o central de la página -->
<div class="text-center mt-8">
    <a href="{{ route('detalle-orden.dashboard') }}" 
       class="inline-block px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg shadow hover:bg-blue-700 transition duration-300">
        📦 Ir al Dashboard de Tracking de Paquetes
    </a>
</div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8">

            <!-- TABLA DE PRODUCTOS -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200">
                <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4">
                    <h2 class="text-xl font-semibold text-white">Productos</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-700">
                        <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3">ID</th>
                                <th class="px-4 py-3">Nombre</th>
                                <th class="px-4 py-3">Precio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($productos as $p)
                                <tr class="border-b hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 font-mono">{{ $p->IdProducto }}</td>
                                    <td class="px-4 py-3">{{ $p->Nombre }}</td>
                                    <td class="px-4 py-3 font-medium text-green-600">${{ number_format($p->Precio, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TABLA DE VENTAS -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200">
                <div class="bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-4">
                    <h2 class="text-xl font-semibold text-white">Ventas Recientes</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-700">
                        <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3">Orden</th>
                                <th class="px-4 py-3">Usuario</th>
                                <th class="px-4 py-3">Pago</th>
                                <th class="px-4 py-3">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ordenes as $o)
                                <tr class="border-b hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 font-mono">{{ $o->IdOrden }}</td>
                                    <td class="px-4 py-3">#{{ $o->IdUsuario }}</td>
                                    <td class="px-4 py-3">
                                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                            {{ $o->MetodoPago }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-emerald-600">${{ number_format($o->Total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- CHAT IA (MODAL + BOTÓN) -->
    <div x-data="chatData()">

        <!-- BOTÓN FLOTANTE -->
        <button 
            @click="openAI = true"
            class="fixed bottom-6 right-6 bg-indigo-600 text-white p-4 rounded-full shadow-lg hover:bg-indigo-700 transition-transform duration-200 hover:scale-105 focus:outline-none z-50"
            aria-label="Abrir asistente IA"
        >
            💬
        </button>

        <!-- MODAL -->
        <div 
            x-show="openAI"
            x-transition
            class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center p-4 z-50"
            style="display: none;"
        >
            <div class="bg-white w-full max-w-lg rounded-xl shadow-2xl flex flex-col h-[80vh]">
                <div class="bg-indigo-600 text-white px-6 py-4 rounded-t-xl flex justify-between items-center">
                    <h2 class="text-lg font-bold">Asistente Inteligente</h2>
                    <button @click="openAI = false" class="text-white hover:text-indigo-200 text-2xl leading-none">
                        ✕
                    </button>
                </div>

                <!-- CONTENEDOR DEL CHAT -->
                <div 
                    id="chatBox"
                    class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50"
                >
                    <template x-for="(m, index) in mensajes" :key="index">
                        <div class="flex" :class="m.rol === 'user' ? 'justify-end' : 'justify-start'">
                            <div 
                                class="rounded-xl px-4 py-2 max-w-xs md:max-w-md"
                                :class="m.rol === 'user' 
                                    ? 'bg-indigo-600 text-white rounded-br-md' 
                                    : 'bg-gray-200 text-gray-800 rounded-bl-md'"
                            >
                                <div class="font-semibold text-xs mb-1" x-text="m.rol === 'user' ? 'Tú' : 'Asistente'"></div>
                                <div class="text-sm whitespace-pre-wrap" x-text="m.text"></div>
                            </div>
                        </div>
                    </template>
                    <div x-show="mensajes.length === 0" class="text-center text-gray-500 text-sm mt-10">
                        ¿En qué puedo ayudarte hoy?
                    </div>
                </div>

                <!-- INPUT -->
                <div class="p-4 border-t">
                    <div class="flex gap-2">
                        <input 
                            x-model="pregunta"
                            @keydown.enter="enviarPregunta()"
                            class="flex-1 border border-gray-300 rounded-full px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Escribe tu pregunta..."
                        >
                        <button 
                            @click="enviarPregunta()"
                            class="bg-indigo-600 text-white rounded-full px-4 py-2 hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="!pregunta.trim()"
                        >
                            ↵
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function chatData() {
        return {
            openAI: false,
            mensajes: [],
            pregunta: '',
            
            enviarPregunta() {
                const pregunta = this.pregunta.trim();
                if (!pregunta) return;

                // Agregar mensaje del usuario
                this.mensajes.push({ rol: 'user', text: pregunta });
                this.pregunta = '';

                // Auto-scroll
                this.$nextTick(() => {
                    const box = document.getElementById('chatBox');
                    if (box) box.scrollTop = box.scrollHeight;
                });

                // Agregar mensaje de "pensando..."
                this.mensajes.push({ rol: 'ai', text: '🤔 Analizando...' });

                // Enviar a Laravel
                fetch("{{ route('chat.ask') }}", { // Usar named route
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({ pregunta })
                })
                .then(response => response.json())
                .then(data => {
                    // Remover mensaje de "pensando..."
                    this.mensajes.pop();
                    
                    // Mostrar respuesta
                    this.mensajes.push({ rol: 'ai', text: data.respuesta || "No pude procesar tu pregunta." });

                    // Auto-scroll
                    this.$nextTick(() => {
                        const box = document.getElementById('chatBox');
                        if (box) box.scrollTop = box.scrollHeight;
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.mensajes.pop();
                    this.mensajes.push({ 
                        rol: 'ai', 
                        text: "⚠️ Error al conectar con el servidor. Intenta nuevamente." 
                    });
                });
            }
        }
    }
</script>

</body>
</html>
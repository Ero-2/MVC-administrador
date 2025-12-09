<?php

namespace App\Http\Controllers;

use App\Models\DetalleOrden;
use App\Models\Orden;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DetalleOrdenController extends Controller
{
    public function dashboard()
    {
        // Estadísticas por estado (minúsculas: 'status')
        $estadisticas = DetalleOrden::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // Asegurar que todos los estados del PIPELINE aparezcan (incluso con 0)
        foreach (DetalleOrden::PIPELINE as $estado => $config) {
            if (!isset($estadisticas[$estado])) {
                $estadisticas[$estado] = (object)['status' => $estado, 'total' => 0];
            }
        }

        // Detalles recientes con relaciones
        $detallesRecientes = DetalleOrden::with(['orden', 'producto'])
            ->orderBy('FechaCreacion', 'desc')
            ->limit(15)
            ->get();

        // ✅ Corrección: Usar el modelo Orden para contar órdenes pendientes
        $ordenesPendientes = Orden::whereHas('detalles', function ($query) {
                $query->where('status', 'pendiente');
            })
            ->withCount([
                'detalles as total_detalles',
                'detalles as pendientes_count' => function ($query) {
                    $query->where('status', 'pendiente');
                }
            ])
            ->orderBy('FechaOrden', 'desc') // ✅ FechaOrden ahora es Carbon
            ->limit(5)
            ->get();

        return view('detalle-orden.dashboard', compact(
            'estadisticas',
            'detallesRecientes',
            'ordenesPendientes'
        ));
    }

    public function porOrden($ordenId)
    {
        $orden = Orden::with('detalles.producto')->findOrFail($ordenId);

        // Filtrar solo detalles con estado válido
        $detallesValidos = $orden->detalles->filter(function ($detalle) {
            return isset(DetalleOrden::PIPELINE[$detalle->status]);
        });

        // Agrupar por estado
        $detallesPorEstado = $detallesValidos->groupBy('status');

        // Calcular progreso promedio solo con estados válidos
        $progresoOrden = $detallesValidos->avg(function ($detalle) {
            return $detalle->progreso ?? 0;
        }) ?? 0;

        return view('detalle-orden.por-orden', compact('orden', 'detallesPorEstado', 'progresoOrden'));
    }

    public function updateEstado(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string',
            'comentario' => 'nullable|string|max:500'
        ]);

        $detalle = DetalleOrden::with('producto')->findOrFail($id);

        if ($detalle->cambiarEstado($request->status)) {
            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado correctamente',
                'data' => [
                    'id' => $detalle->IdDetalleOrden,
                    'estado' => $detalle->status,
                    'nombre_estado' => $detalle->nombre_estado,
                    'color_estado' => $detalle->color_estado,
                    'icono_estado' => $detalle->icono_estado,
                    'progreso' => $detalle->progreso,
                    'producto' => $detalle->producto->Nombre ?? 'Producto #' . $detalle->IdInventario,
                    'comentario' => $request->comentario
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Transición de estado no permitida'
        ], 422);
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'detalles' => 'required|array',
            'detalles.*.id' => 'required|exists:DetalleOrden,IdDetalleOrden',
            'detalles.*.status' => 'required|string',
            'comentario' => 'nullable|string|max:500'
        ]);

        $actualizados = [];
        $fallados = [];

        foreach ($request->detalles as $item) {
            $detalle = DetalleOrden::find($item['id']);
            if ($detalle && $detalle->cambiarEstado($item['status'], $request->comentario)) {
                $actualizados[] = $detalle->IdDetalleOrden;
            } else {
                $fallados[] = $item['id'];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Actualización completada',
            'actualizados' => count($actualizados),
            'fallados' => count($fallados),
            'ids_actualizados' => $actualizados,
            'ids_fallados' => $fallados
        ]);
    }

    public function pipeline($estado = null)
    {
        $estadosDisponibles = DetalleOrden::PIPELINE;

        $query = DetalleOrden::with(['orden', 'producto'])
            ->where('status', '<>', 'cancelado')
            ->orderBy('FechaCreacion', 'desc');

        if ($estado && array_key_exists($estado, $estadosDisponibles)) {
            $query->where('status', $estado);
        }

        $detalles = $query->paginate(20);

        $conteoEstados = DetalleOrden::select('status', DB::raw('COUNT(*) as total'))
            ->where('status', '<>', 'cancelado')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('detalle-orden.pipeline', compact(
            'detalles',
            'estadosDisponibles',
            'conteoEstados',
            'estado'
        ));
    }

    public function tracking($id)
    {
        $detalle = DetalleOrden::with(['orden', 'producto'])->findOrFail($id);
        $estados = DetalleOrden::PIPELINE;
        $historial = []; // Por implementar

        return view('detalle-orden.tracking', compact('detalle', 'estados', 'historial'));
    }
}
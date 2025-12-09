<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * MINI IA: interpreta la intención del usuario
     * Para PostgreSQL con tablas en mayúsculas
     */
    private function miniIA($pregunta)
    {
        $p = strtolower($pregunta);

        // INTENCIÓN 1: Cuántos productos hay
        if (preg_match('/cu[aá]ntos.*productos/', $p)) {
            return [
                'sql' => 'SELECT COUNT(*) AS total FROM "Productos"', // ¡Comillas dobles para PostgreSQL!
                'tipo' => 'count',
                'msg' => "El total de productos es: "
            ];
        }

        // INTENCIÓN 2: Productos agotados (si tuvieras campo stock)
        if (preg_match('/(agotados|sin stock|no hay)/', $p)) {
            // Primero verificar si existe campo stock
            return [
                'sql' => 'SELECT COUNT(*) AS total FROM "Productos"',
                'tipo' => 'count',
                'msg' => "Productos en total (campo stock no disponible): "
            ];
        }

        // INTENCIÓN 3: Listar productos
        if (preg_match('/(lista|listar|mostrar).*productos/', $p)) {
            return [
                'sql' => 'SELECT "IdProducto", "Nombre", "Precio" FROM "Productos" LIMIT 10',
                'tipo' => 'lista',
                'msg' => "Lista de productos (primeros 10):"
            ];
        }

        // INTENCIÓN 4: Precio promedio
        if (preg_match('/(precio).*promedio/', $p)) {
            return [
                'sql' => 'SELECT AVG("Precio") AS promedio FROM "Productos"',
                'tipo' => 'promedio',
                'msg' => "El precio promedio es: "
            ];
        }

        // INTENCIÓN 5: Productos por rango de precio
        if (preg_match('/(productos|producto).*(caros|baratos|precio).*(mayor|menor|mas|menos)/', $p)) {
            if (preg_match('/mas.*(\d+)/', $p, $matches)) {
                $precio = $matches[1];
                return [
                    'sql' => 'SELECT "Nombre", "Precio" FROM "Productos" WHERE "Precio" > ' . $precio . ' ORDER BY "Precio" DESC',
                    'tipo' => 'lista_precios',
                    'msg' => "Productos con precio mayor a $" . $precio . ":"
                ];
            }
            return [
                'sql' => 'SELECT "Nombre", "Precio" FROM "Products" ORDER BY "Precio" DESC LIMIT 5',
                'tipo' => 'lista_precios',
                'msg' => "Productos más caros:"
            ];
        }

        // INTENCIÓN 6: Total de ventas
        if (preg_match('/(total|suma).*ventas/', $p)) {
            return [
                'sql' => 'SELECT SUM("Total") AS total_ventas FROM "Ordenes"',
                'tipo' => 'suma',
                'msg' => "El total de ventas es: "
            ];
        }

        // INTENCIÓN 7: Ventas recientes
        if (preg_match('/(ventas recientes|últimas ventas)/', $p)) {
            return [
                'sql' => 'SELECT "IdOrden", "Total", "FechaOrden", "MetodoPago" FROM "Ordenes" ORDER BY "FechaOrden" DESC LIMIT 5',
                'tipo' => 'lista_ventas',
                'msg' => "Ventas recientes:"
            ];
        }

        // INTENCIÓN 8: Órdenes por día
        if (preg_match('/(ventas|órdenes).*hoy|día/', $p)) {
            return [
                'sql' => "SELECT COUNT(*) AS total, SUM(\"Total\") AS monto_total FROM \"Ordenes\" WHERE DATE(\"FechaOrden\") = CURRENT_DATE",
                'tipo' => 'ventas_hoy',
                'msg' => "Ventas de hoy: "
            ];
        }

        // INTENCIÓN 9: Métodos de pago más usados
        if (preg_match('/(método|metodo).*pago/', $p)) {
            return [
                'sql' => 'SELECT "MetodoPago", COUNT(*) AS cantidad FROM "Ordenes" GROUP BY "MetodoPago" ORDER BY cantidad DESC',
                'tipo' => 'metodos_pago',
                'msg' => "Métodos de pago más usados:"
            ];
        }

        // INTENCIÓN 10: Buscar producto por nombre
        if (preg_match('/producto.*(llamado|nombre|como).*([a-zA-Z0-9\s]+)/', $p, $matches)) {
            $productoBuscado = trim($matches[2]);
            return [
                'sql' => "SELECT \"Nombre\", \"Precio\", \"Description\" FROM \"Productos\" WHERE LOWER(\"Nombre\") LIKE '%" . strtolower($productoBuscado) . "%'",
                'tipo' => 'buscar_producto',
                'msg' => "Resultados para '" . $productoBuscado . "':"
            ];
        }

        // INTENCIÓN 11: Productos más caros
        if (preg_match('/(productos más caros|más caros)/', $p)) {
            return [
                'sql' => 'SELECT "Nombre", "Precio" FROM "Productos" ORDER BY "Precio" DESC LIMIT 5',
                'tipo' => 'lista',
                'msg' => "Los 5 productos más caros:"
            ];
        }

        // INTENCIÓN 12: Productos más baratos
        if (preg_match('/(productos más baratos|más baratos)/', $p)) {
            return [
                'sql' => 'SELECT "Nombre", "Precio" FROM "Productos" WHERE "Precio" > 0 ORDER BY "Precio" ASC LIMIT 5',
                'tipo' => 'lista',
                'msg' => "Los 5 productos más baratos:"
            ];
        }

        return [
            'sql' => null,
            'tipo' => 'none',
            'msg' => "No entendí tu pregunta 🤔. Intenta otra forma.\n\nPuedes preguntar:\n• ¿Cuántos productos hay?\n• Muestra la lista de productos\n• ¿Cuál es el precio promedio?\n• Ventas recientes\n• Total de ventas\n• Métodos de pago más usados"
        ];
    }

    /**
     * CONTROLADOR DEL CHAT
     */
    public function ask(Request $request)
    {
        $request->validate([
            'pregunta' => 'required|string'
        ]);

        $pregunta = $request->input('pregunta');

        // Se usa la mini-IA
        $ia = $this->miniIA($pregunta);

        if ($ia['tipo'] === 'none' || $ia['sql'] === null) {
            return response()->json([
                'respuesta' => $ia['msg']
            ]);
        }

        try {
            // Ejecutar la consulta SQL
            $resultados = DB::select($ia['sql']);
            
            $respuesta = $ia['msg'] . "\n";

            // Formatear respuesta según el tipo
            switch ($ia['tipo']) {
                case 'count':
                    $respuesta .= "**" . ($resultados[0]->total ?? 0) . "**";
                    break;
                    
                case 'promedio':
                    $promedio = $resultados[0]->promedio ?? 0;
                    $respuesta .= "**$" . number_format($promedio, 2) . "**";
                    break;
                    
                case 'suma':
                    $total = $resultados[0]->total_ventas ?? 0;
                    $respuesta .= "**$" . number_format($total, 2) . "**";
                    break;
                    
                case 'lista':
                case 'lista_precios':
                case 'buscar_producto':
                    if (empty($resultados)) {
                        $respuesta .= "No se encontraron resultados.";
                    } else {
                        foreach ($resultados as $item) {
                            if (isset($item->Nombre) && isset($item->Precio)) {
                                $respuesta .= "\n• **{$item->Nombre}** - $" . number_format($item->Precio, 2);
                                if (isset($item->Description) && strlen($item->Description) > 0) {
                                    $respuesta .= " ({$item->Description})";
                                }
                            } elseif (isset($item->IdProducto)) {
                                $respuesta .= "\n• #{$item->IdProducto} - {$item->Nombre}";
                            }
                        }
                    }
                    break;
                    
                case 'lista_ventas':
                    if (empty($resultados)) {
                        $respuesta .= "No hay ventas registradas.";
                    } else {
                        foreach ($resultados as $venta) {
                            $fecha = isset($venta->FechaOrden) ? date('d/m/Y H:i', strtotime($venta->FechaOrden)) : 'Sin fecha';
                            $respuesta .= "\n• **Orden #{$venta->IdOrden}** - $" . number_format($venta->Total, 2);
                            $respuesta .= " ({$venta->MetodoPago}) - {$fecha}";
                        }
                    }
                    break;
                    
                case 'ventas_hoy':
                    $total = $resultados[0]->total ?? 0;
                    $monto = $resultados[0]->monto_total ?? 0;
                    $respuesta .= "**{$total} órdenes** por un total de **$" . number_format($monto, 2) . "**";
                    break;
                    
                case 'metodos_pago':
                    if (empty($resultados)) {
                        $respuesta .= "No hay datos de pagos.";
                    } else {
                        foreach ($resultados as $metodo) {
                            $respuesta .= "\n• **{$metodo->MetodoPago}**: {$metodo->cantidad} veces";
                        }
                    }
                    break;
                    
                default:
                    $respuesta .= json_encode($resultados, JSON_PRETTY_PRINT);
            }

            // Agregar información adicional
            if (count($resultados) > 0 && $ia['tipo'] !== 'count' && $ia['tipo'] !== 'promedio' && $ia['tipo'] !== 'suma') {
                $respuesta .= "\n\n📊 **Total de resultados:** " . count($resultados);
            }

            return response()->json([
                'respuesta' => $respuesta,
                'sql_generado' => $ia['sql'],
                'resultado' => $resultados
            ]);

        } catch (\Exception $e) {
            \Log::error('Error en ChatController: ' . $e->getMessage());
            
            return response()->json([
                'respuesta' => "❌ Error al consultar la base de datos:\n\n" . 
                    $e->getMessage() . 
                    "\n\n💡 **Posible solución:** Verifica que las tablas 'Products' y 'Ordenes' existan en PostgreSQL.",
                'sql_generado' => $ia['sql'],
                'error' => $e->getMessage()
            ]);
        }
    }
}
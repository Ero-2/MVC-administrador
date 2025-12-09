<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleOrden extends Model
{
    use HasFactory;

    protected $table = 'DetalleOrden';
    protected $primaryKey = 'IdDetalleOrden';
    public $timestamps = false;

    protected $fillable = [
        'IdOrden',
        'IdInventario',
        'Cantidad',
        'PrecioUnitario',
        'status',
        'FechaCreacion',
        'FechaModificacion',
        'UsuarioCreacion',
        'UsuarioModificacion'
    ];

    protected $casts = [
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
        'PrecioUnitario' => 'decimal:2',
        'Cantidad' => 'integer'
    ];

    /**
     * Pipeline de estados con configuración completa
     */
    const PIPELINE = [
        'pendiente' => [
            'nombre' => 'Pendiente',
            'descripcion' => 'En espera de procesamiento',
            'color' => 'bg-yellow-500 text-white',
            'icono' => 'far fa-clock',
            'orden' => 1,
            'progreso' => 0
        ],
        'preparando' => [
            'nombre' => 'En preparación',
            'descripcion' => 'Siendo preparado para envío',
            'color' => 'bg-blue-500 text-white',
            'icono' => 'fas fa-box-open',
            'orden' => 2,
            'progreso' => 25
        ],
        'revisado' => [
            'nombre' => 'Revisado',
            'descripcion' => 'Control de calidad completado',
            'color' => 'bg-purple-500 text-white',
            'icono' => 'fas fa-check-double',
            'orden' => 3,
            'progreso' => 50
        ],
        'liberado' => [
            'nombre' => 'Liberado',
            'descripcion' => 'Listo para entrega',
            'color' => 'bg-green-500 text-white',
            'icono' => 'fas fa-check-circle',
            'orden' => 4,
            'progreso' => 75
        ],
        'entregado' => [
            'nombre' => 'Entregado',
            'descripcion' => 'Entregado al cliente',
            'color' => 'bg-teal-600 text-white',
            'icono' => 'fas fa-truck',
            'orden' => 5,
            'progreso' => 100
        ],
        'cancelado' => [
            'nombre' => 'Cancelado',
            'descripcion' => 'Orden cancelada',
            'color' => 'bg-red-500 text-white',
            'icono' => 'fas fa-times-circle',
            'orden' => 0,
            'progreso' => 0
        ],
    ];

    /**
     * Matriz de transiciones permitidas entre estados
     */
    const TRANSICIONES = [
        'pendiente' => ['preparando', 'cancelado'],
        'preparando' => ['revisado', 'pendiente', 'cancelado'],
        'revisado' => ['liberado', 'preparando', 'cancelado'],
        'liberado' => ['entregado', 'revisado', 'cancelado'],
        'entregado' => ['revisado'], // Puede retroceder si hay problemas
        'cancelado' => ['pendiente'], // Puede reactivarse
    ];

    // ==================== RELACIONES ====================

    /**
     * Relación con la orden
     */
    public function orden()
    {
        return $this->belongsTo(Orden::class, 'IdOrden', 'IdOrden');
    }

    /**
     * Relación con el producto
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'IdInventario', 'IdProducto');
    }

    // ==================== ACCESSORS ====================

    /**
     * Obtiene el nombre del estado actual
     */
    public function getNombreEstadoAttribute()
    {
        return self::PIPELINE[$this->status]['nombre'] ?? ucfirst($this->status);
    }

    /**
     * Obtiene las clases CSS del color del estado
     */
    public function getColorEstadoAttribute()
    {
        return self::PIPELINE[$this->status]['color'] ?? 'bg-gray-500 text-white';
    }

    /**
     * Obtiene el icono FontAwesome del estado
     */
    public function getIconoEstadoAttribute()
    {
        return self::PIPELINE[$this->status]['icono'] ?? 'fas fa-circle';
    }

    /**
     * Obtiene el orden numérico del estado
     */
    public function getOrdenEstadoAttribute()
    {
        return self::PIPELINE[$this->status]['orden'] ?? 99;
    }

    /**
     * Obtiene los estados a los que puede transicionar
     */
    public function getEstadosSiguientesAttribute()
    {
        return self::TRANSICIONES[$this->status] ?? [];
    }

    /**
     * Calcula el subtotal (Cantidad * Precio)
     */
    public function getSubtotalAttribute()
    {
        return $this->Cantidad * $this->PrecioUnitario;
    }

    /**
     * Calcula el porcentaje de progreso basado en el estado
     */
    public function getProgresoAttribute()
    {
        return self::PIPELINE[$this->status]['progreso'] ?? 0;
    }

    /**
     * Obtiene la descripción del estado
     */
    public function getDescripcionEstadoAttribute()
    {
        return self::PIPELINE[$this->status]['descripcion'] ?? '';
    }

    // ==================== MÉTODOS ====================

    /**
     * Cambia el estado del detalle de orden
     * 
     * @param string $nuevoEstado El nuevo estado
     * @param string|null $comentario Comentario opcional
     * @return bool True si se cambió exitosamente
     */
    public function cambiarEstado($nuevoEstado, $comentario = null)
    {
        // Validar que el estado existe
        if (!array_key_exists($nuevoEstado, self::PIPELINE)) {
            return false;
        }

        // Validar transición permitida
        if (!in_array($nuevoEstado, $this->estados_siguientes)) {
            // Opcional: permitir cambios administrativos sin restricción
            // return false;
        }

        // Guardar estado anterior para historial
        $estadoAnterior = $this->status;

        // Actualizar estado
        $this->status = $nuevoEstado;
        $this->FechaModificacion = now();
        $this->UsuarioModificacion = auth()->check() ? auth()->user()->name : 'sistema';
        $this->save();

        // Aquí podrías registrar el cambio en una tabla de historial
        // $this->registrarHistorial($estadoAnterior, $nuevoEstado, $comentario);

        return true;
    }

    /**
     * Verifica si puede transicionar a un estado específico
     * 
     * @param string $estado
     * @return bool
     */
    public function puedeTransicionarA($estado)
    {
        return in_array($estado, $this->estados_siguientes);
    }

    /**
     * Avanza al siguiente estado en el pipeline
     * 
     * @return bool
     */
    public function avanzarEstado()
    {
        $estadosSiguientes = $this->estados_siguientes;
        
        if (empty($estadosSiguientes)) {
            return false;
        }

        // Tomar el primer estado disponible que no sea cancelado
        $siguienteEstado = collect($estadosSiguientes)
            ->filter(fn($e) => $e !== 'cancelado')
            ->first();

        if ($siguienteEstado) {
            return $this->cambiarEstado($siguienteEstado);
        }

        return false;
    }

    /**
     * Marca el detalle como cancelado
     * 
     * @param string|null $motivo
     * @return bool
     */
    public function cancelar($motivo = null)
    {
        return $this->cambiarEstado('cancelado', $motivo);
    }

    /**
     * Verifica si el detalle está en un estado final
     * 
     * @return bool
     */
    public function estaFinalizado()
    {
        return in_array($this->status, ['entregado', 'cancelado']);
    }

    /**
     * Verifica si el detalle está cancelado
     * 
     * @return bool
     */
    public function estaCancelado()
    {
        return $this->status === 'cancelado';
    }

    /**
     * Verifica si el detalle está entregado
     * 
     * @return bool
     */
    public function estaEntregado()
    {
        return $this->status === 'entregado';
    }

    // ==================== SCOPES ====================

    /**
     * Scope para filtrar por estado
     */
    public function scopePorEstado($query, $estado)
    {
        return $query->where('status', $estado);
    }

    /**
     * Scope para obtener solo pendientes
     */
    public function scopePendientes($query)
    {
        return $query->where('status', 'pendiente');
    }

    /**
     * Scope para obtener por orden
     */
    public function scopePorOrden($query, $ordenId)
    {
        return $query->where('IdOrden', $ordenId);
    }

    /**
     * Scope para obtener detalles activos (no cancelados)
     */
    public function scopeActivos($query)
    {
        return $query->where('status', '!=', 'cancelado');
    }

    /**
     * Scope para obtener detalles finalizados
     */
    public function scopeFinalizados($query)
    {
        return $query->whereIn('status', ['entregado', 'cancelado']);
    }

    /**
     * Scope para ordenar por estado
     */
    public function scopeOrdenadosPorEstado($query)
    {
        return $query->orderByRaw("
            CASE status
                WHEN 'pendiente' THEN 1
                WHEN 'preparando' THEN 2
                WHEN 'revisado' THEN 3
                WHEN 'liberado' THEN 4
                WHEN 'entregado' THEN 5
                WHEN 'cancelado' THEN 6
                ELSE 99
            END
        ");
    }

    // ==================== MÉTODOS ESTÁTICOS ====================

    /**
     * Obtiene todos los estados disponibles
     * 
     * @return array
     */
    public static function obtenerEstados()
    {
        return array_keys(self::PIPELINE);
    }

    /**
     * Obtiene estados activos (orden > 0)
     * 
     * @return array
     */
    public static function obtenerEstadosActivos()
    {
        return array_keys(array_filter(self::PIPELINE, fn($e) => $e['orden'] > 0));
    }

    /**
     * Obtiene la configuración de un estado específico
     * 
     * @param string $estado
     * @return array|null
     */
    public static function obtenerConfigEstado($estado)
    {
        return self::PIPELINE[$estado] ?? null;
    }
}
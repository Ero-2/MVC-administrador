<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Orden extends Model
{
    use HasFactory;

    protected $table = 'Ordenes';
    protected $primaryKey = 'IdOrden';
    public $timestamps = false;

    protected $fillable = [
        'idusuario',
        'total',
        'direccionenvio',
        'metodopago',
        'FechaOrden' // Aseguramos que FechaOrden esté en fillable
    ];

    // ✅ Añadimos el casting para que FechaOrden sea un objeto Carbon
    protected $casts = [
        'FechaOrden' => 'datetime', // Esto convierte el string a Carbon
        'total' => 'decimal:2',
    ];

    // ✅ Relación: una orden tiene muchos detalles
    public function detalles()
    {
        return $this->hasMany(DetalleOrden::class, 'IdOrden', 'IdOrden');
    }
}
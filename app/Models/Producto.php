<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'Productos';
    protected $primaryKey = 'IdProducto';

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'idmarca'
    ];

    public $timestamps = false;
}




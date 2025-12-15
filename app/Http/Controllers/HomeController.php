<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Orden;

class HomeController extends Controller
{
    public function index()
    {
        // Paginación de productos
        $productos = Producto::paginate(15);
        
        // Paginación de órdenes SIN ordenar por created_at
        // Ya que esa columna no existe en tu tabla
        $ordenes = Orden::paginate(15);

        return view('welcome', compact('productos', 'ordenes'));
    }
}
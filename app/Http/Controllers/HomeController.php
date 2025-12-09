<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Orden;

class HomeController extends Controller
{
    public function index()
{
    $productos = Producto::all();
    $ordenes = Orden::all();

    return view('welcome', compact('productos', 'ordenes'));
}

}

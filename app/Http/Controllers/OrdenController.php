<?php

namespace App\Http\Controllers;

use App\Models\Orden;

class OrdenController extends Controller
{
    public function index()
    {
        $ordenes = Orden::all();
        return view('ordenes.index', compact('ordenes'));
    }
}

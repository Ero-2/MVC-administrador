<!DOCTYPE html>
<html>
<head>
    <title>Ventas</title>
</head>
<body>

<h1>Lista de Ventas</h1>

<table border="1">
    <tr>
        <th>ID Orden</th>
        <th>ID Usuario</th>
        <th>Total</th>
        <th>Método de Pago</th>
        <th>Dirección Envío</th>
    </tr>

    @foreach($ordenes as $o)
    <tr>
        <td>{{ $o->IdOrden }}</td>
        <td>{{ $o->IdUsuario }}</td>
        <td>{{ $o->Total }}</td>
        <td>{{ $o->MetodoPago }}</td>
        <td>{{ $o->DireccionEnvio }}</td>
    </tr>
    @endforeach

</table>

</body>
</html>

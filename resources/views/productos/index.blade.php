<!DOCTYPE html>
<html>
<head>
    <title>Productos</title>
</head>
<body>

<h1>Lista de Productos</h1>

<table border="1">
    <tr>
        <th>ID</th>
        <th>Nombre</th>
        <th>Descripción</th>
        <th>Precio</th>
    </tr>

    @foreach($productos as $p)
    <tr>
        <td>{{ $p->IdProducto }}</td>
        <td>{{ $p->Nombre }}</td>
        <td>{{ $p->Descripcion }}</td>
        <td>{{ $p->Precio }}</td>
    </tr>
    @endforeach

</table>

</body>
</html>

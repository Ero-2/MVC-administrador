<!DOCTYPE html>
<html>
<head>
    <title>Chat IA</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-10">

<h1 class="text-3xl font-bold mb-6">Chat de Análisis (IA Local)</h1>

<div class="max-w-xl mx-auto bg-white shadow p-6 rounded-xl">

    <form id="chatForm" class="flex gap-3">
        <input id="pregunta" class="border p-2 flex-1 rounded" placeholder="Pregunta sobre ventas o productos..." />
        <button class="bg-blue-600 text-white px-4 rounded">Enviar</button>
    </form>

    <pre id="respuesta" class="mt-6 p-4 bg-gray-200 rounded whitespace-pre-wrap"></pre>

</div>

<script>
document.getElementById("chatForm").onsubmit = async e => {
    e.preventDefault();

    const pregunta = document.getElementById("pregunta").value;

    const res = await fetch("/chat", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json"
        },
        body: JSON.stringify({ pregunta })
    });

    const data = await res.json();

    document.getElementById("respuesta").textContent =
        "➡ SQL generado:\n" + data.sql_generado +
        "\n\n➡ Resultado:\n" + JSON.stringify(data.resultado, null, 2) +
        "\n\n➡ Análisis IA:\n" + data.respuesta;
};
</script>

</body>
</html>

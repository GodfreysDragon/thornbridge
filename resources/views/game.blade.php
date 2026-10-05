<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="npc-driver" content="{{ config('ai.driver') }}">
    <title>Thornbridge: an AI NPC with a server referee</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="min-h-screen bg-ink-950 text-parchment-100 antialiased">
    <div id="app"></div>
</body>
</html>

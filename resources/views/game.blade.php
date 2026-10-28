<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="npc-driver" content="{{ app(\App\Ai\Contracts\NpcBrain::class) instanceof \App\Ai\ScriptedNpcBrain ? 'scripted' : 'claude' }}">
    <title>Thornbridge: an AI NPC with a server referee</title>
    @php($description = 'Talk your way past a troll voiced by Claude. The model only proposes what he does; a Laravel server rules on every move, so lies and prompt injection get nowhere.')
    <meta name="description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Thornbridge: an AI NPC with a server referee">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ asset('og-image.jpg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="min-h-screen bg-ink-950 text-parchment-100 antialiased">
    <div id="app"></div>
</body>
</html>

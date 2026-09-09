<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f0006e">
    <meta name="description" content="CCI — sua rede de corretores, imóveis e negociações.">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" href="/icons/icon-512.png">
    <link rel="apple-touch-icon" href="/icons/icon-512.png">
    <title>CCI · Central de Corretores de Imóveis</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
    <noscript>Ative o JavaScript para acessar a CCI.</noscript>
</body>
</html>

<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>{{ $og['title'] }}</title>

    <meta property="og:title" content="{{ $og['title'] }}" />
    <meta property="og:description" content="{{ $og['desc'] }}" />
    <meta property="og:image" content="{{ $og['image'] }}" />
    <meta property="og:url" content="{{ $og['url'] }}" />
    <meta property="og:type" content="website" />
    <meta name="twitter:card" content="summary_large_image" />

    @vite('resources/js/app.jsx') <!-- atau script SPA kamu -->
</head>

<body>
    <div id="root"></div>
</body>

</html>

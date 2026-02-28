<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    <script>
        window.reverbConfig = {
            key: "{{ config('reverb.apps.apps.0.key') ?: env('REVERB_APP_KEY') }}",
            host: "{{ env('REVERB_HOST') }}",
            port: "{{ env('REVERB_PORT', 80) }}",
            scheme: "{{ env('REVERB_SCHEME', 'https') }}"
        };
    </script>
    @routes
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>

<body class="font-sans antialiased" id="app_body">
    @inertia
</body>

</html>
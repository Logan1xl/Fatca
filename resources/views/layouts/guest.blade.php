<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CBC FATCA') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.scss', 'resources/js/app.js'])
        
        <style>
            body {
                background: linear-gradient(135deg, #1a1a1a 0%, #000000 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .auth-card {
                background: white;
                border-radius: 1.5rem;
                box-shadow: 0 1rem 3rem rgba(0,0,0,0.2);
                width: 100%;
                max-width: 450px;
                overflow: hidden;
            }
            .auth-header {
                background: #f8f9fa;
                padding: 3rem 2rem;
                text-align: center;
                border-bottom: 1px solid #eee;
            }
        </style>
    </head>
    <body>
        <div class="auth-card animate__animated animate__fadeIn">
            {{ $slot }}
        </div>
    </body>
</html>

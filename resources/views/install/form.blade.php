<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Install &mdash; VisitorPass</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-100">
        <div class="min-h-screen flex flex-col items-center pt-12">
            <div class="w-full sm:max-w-md px-6 py-4 bg-white shadow-md sm:rounded-lg">
                <h1 class="text-lg font-semibold mb-4">Connect your database</h1>

                @if (session('install_error'))
                    <div class="mb-4 text-sm text-red-600">{{ session('install_error') }}</div>
                @endif

                @if ($errors->any())
                    <ul class="mb-4 text-sm text-red-600 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ url('/install') }}">
                    @csrf

                    <label class="block text-sm font-medium mb-1">DB Host</label>
                    <input type="text" name="db_host" value="{{ old('db_host', $values['db_host']) }}" class="w-full border rounded px-3 py-2 mb-3">

                    <label class="block text-sm font-medium mb-1">DB Port</label>
                    <input type="text" name="db_port" value="{{ old('db_port', $values['db_port']) }}" class="w-full border rounded px-3 py-2 mb-3">

                    <label class="block text-sm font-medium mb-1">Database Name</label>
                    <input type="text" name="db_database" value="{{ old('db_database', $values['db_database']) }}" class="w-full border rounded px-3 py-2 mb-3">

                    <label class="block text-sm font-medium mb-1">DB Username</label>
                    <input type="text" name="db_username" value="{{ old('db_username', $values['db_username']) }}" class="w-full border rounded px-3 py-2 mb-3">

                    <label class="block text-sm font-medium mb-1">DB Password</label>
                    <input type="password" name="db_password" class="w-full border rounded px-3 py-2 mb-3">

                    <label class="block text-sm font-medium mb-1">Site URL</label>
                    <input type="text" name="app_url" value="{{ old('app_url', $values['app_url']) }}" class="w-full border rounded px-3 py-2 mb-4">

                    <button type="submit" class="w-full bg-gray-800 text-white rounded px-3 py-2">Connect &amp; continue</button>
                </form>
            </div>
        </div>
    </body>
</html>

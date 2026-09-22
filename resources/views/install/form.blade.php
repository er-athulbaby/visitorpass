<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Install &mdash; VisitorPass</title>
        <style>
            body { font-family: sans-serif; background: #f3f4f6; margin: 0; padding: 3rem 1rem; }
            .card { max-width: 28rem; margin: 0 auto; background: #fff; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            h1 { font-size: 1.125rem; font-weight: 600; margin: 0 0 1rem; }
            .error { color: #dc2626; font-size: 0.875rem; margin-bottom: 1rem; }
            label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem; }
            input { width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 0.25rem; padding: 0.5rem 0.75rem; margin-bottom: 0.75rem; }
            button { width: 100%; background: #1f2937; color: #fff; border: none; border-radius: 0.25rem; padding: 0.5rem 0.75rem; cursor: pointer; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Connect your database</h1>

            @if (session('install_error'))
                <div class="error">{{ session('install_error') }}</div>
            @endif

            @if ($errors->any())
                <ul class="error">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ url('/install') }}">
                @csrf

                <label>DB Host</label>
                <input type="text" name="db_host" value="{{ old('db_host', $values['db_host']) }}">

                <label>DB Port</label>
                <input type="text" name="db_port" value="{{ old('db_port', $values['db_port']) }}">

                <label>Database Name</label>
                <input type="text" name="db_database" value="{{ old('db_database', $values['db_database']) }}">

                <label>DB Username</label>
                <input type="text" name="db_username" value="{{ old('db_username', $values['db_username']) }}">

                <label>DB Password</label>
                <input type="password" name="db_password">

                <label>Site URL</label>
                <input type="text" name="app_url" value="{{ old('app_url', $values['app_url']) }}">

                <button type="submit">Connect &amp; continue</button>
            </form>
        </div>
    </body>
</html>

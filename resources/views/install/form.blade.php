<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Install &mdash; VisitorPass</title>
        <style>
            /* Inline on purpose: this page must work before assets are built or Vite is available. */
            *, *::before, *::after { box-sizing: border-box; }
            body { font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #F8FAFC; color: #0F172A; margin: 0; padding: 4rem 1rem; font-size: 14px; }
            .card { max-width: 30rem; margin: 0 auto; background: #fff; padding: 2rem; border: 1px solid #E2E8F0; border-radius: 12px; box-shadow: 0 1px 2px rgba(15,23,42,0.04); }
            .step { display: inline-block; font-size: 12px; font-weight: 600; color: #2563EB; background: #EFF6FF; border-radius: 999px; padding: 2px 10px; margin-bottom: 12px; }
            h1 { font-size: 20px; line-height: 28px; font-weight: 700; letter-spacing: -0.01em; margin: 0 0 4px; }
            .lead { color: #64748B; margin: 0 0 24px; line-height: 20px; }
            .error { color: #991B1B; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px; padding: 10px 12px; font-size: 13px; margin: 0 0 16px; }
            ul.error { padding-inline-start: 28px; }
            label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; }
            input { width: 100%; min-height: 44px; border: 1px solid #E2E8F0; border-radius: 8px; padding: 8px 12px; margin-bottom: 16px; font: inherit; color: inherit; background: #fff; transition: border-color .15s, box-shadow .15s; }
            input:focus { outline: none; border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37,99,235,0.2); }
            button { width: 100%; min-height: 48px; background: #2563EB; color: #fff; border: none; border-radius: 8px; padding: 10px 16px; font: inherit; font-weight: 600; cursor: pointer; transition: background-color .15s; }
            button:hover { background: #1D4ED8; }
            button:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,0.35); }
            .footer { text-align: center; color: #64748B; font-size: 12px; margin-top: 24px; }
            .footer a { color: inherit; }
        </style>
    </head>
    <body>
        <div class="card">
            <span class="step">Step 1 of 2</span>
            <h1>Connect your database</h1>
            <p class="lead">Enter the MySQL details from your hosting control panel. VisitorPass creates its tables automatically.</p>

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
                <input type="hidden" name="token" value="{{ request('token') }}">

                <label for="db_host">DB Host</label>
                <input id="db_host" type="text" name="db_host" value="{{ old('db_host', $values['db_host']) }}">

                <label for="db_port">DB Port</label>
                <input id="db_port" type="text" name="db_port" value="{{ old('db_port', $values['db_port']) }}">

                <label for="db_database">Database Name</label>
                <input id="db_database" type="text" name="db_database" value="{{ old('db_database', $values['db_database']) }}">

                <label for="db_username">DB Username</label>
                <input id="db_username" type="text" name="db_username" value="{{ old('db_username', $values['db_username']) }}">

                <label for="db_password">DB Password</label>
                <input id="db_password" type="password" name="db_password">

                <label for="app_url">Site URL</label>
                <input id="app_url" type="text" name="app_url" value="{{ old('app_url', $values['app_url']) }}">

                <button type="submit">Connect &amp; continue</button>
            </form>
        </div>
        <p class="footer">v1.0 &middot; Developed by <a href="https://deverra.me" target="_blank" rel="noopener">DeVerra Technologies</a></p>
    </body>
</html>

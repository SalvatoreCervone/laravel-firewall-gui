<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — Access Denied</title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
            -webkit-font-smoothing: antialiased;
        }

        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .panel {
            width: 100%;
            max-width: 480px;
            background-color: #1e293b;
            border: 1px solid #334155;
            border-radius: 0.75rem;
            padding: 2.25rem 2rem;
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.35), 0 8px 10px -6px rgb(0 0 0 / 0.25);
            text-align: center;
        }

        .icon-wrap {
            width: 56px;
            height: 56px;
            margin: 0 auto 1.25rem;
            border-radius: 9999px;
            background-color: rgb(220 38 38 / 0.15);
            border: 1px solid rgb(248 113 113 / 0.35);
            color: #f87171;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .status-code {
            display: inline-block;
            padding: 0.2rem 0.65rem;
            border-radius: 9999px;
            background-color: rgb(220 38 38 / 0.15);
            border: 1px solid rgb(248 113 113 / 0.3);
            color: #fca5a5;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
        }

        h1 {
            margin: 0 0 0.6rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: #f8fafc;
        }

        .message {
            margin: 0 0 1.5rem;
            font-size: 0.95rem;
            line-height: 1.6;
            color: #cbd5e1;
        }

        .meta {
            background-color: #0f172a;
            border: 1px solid #334155;
            border-radius: 0.5rem;
            padding: 0.875rem 1rem;
            font-size: 0.825rem;
            color: #94a3b8;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            text-align: left;
        }

        .meta-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            color: #f1f5f9;
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="panel" aria-labelledby="blocked-heading">
            <div class="icon-wrap" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                </svg>
            </div>

            <span class="status-code">HTTP 403 Forbidden</span>
            <h1 id="blocked-heading">Access Denied</h1>
            <p class="message">{{ $message ?? 'Access denied by firewall.' }}</p>

            <div class="meta">
                <div class="meta-row">
                    <span>Client IP</span>
                    <code class="mono">{{ $ip ?? request()->ip() }}</code>
                </div>
                @if (isset($decision) && $decision !== null && ! empty($decision->reason))
                    <div class="meta-row">
                        <span>Reference</span>
                        <span class="mono">{{ $decision->action }}</span>
                    </div>
                @endif
            </div>
        </section>
    </main>
</body>
</html>

@php
    $resolvedIp = $currentIp ?? request()->ip();
    $resolvedEnabled = $isEnabled ?? (bool) config('firewall-gui.enabled', true);
    $resolvedDryRun = $isDryRun ?? (bool) config('firewall-gui.dry_run', false);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Firewall') — Laravel Firewall GUI</title>
    <style>
        :root {
            --bg-canvas: #f1f5f9;
            --bg-surface: #ffffff;
            --bg-subtle: #f8fafc;
            --bg-sidebar: #0f172a;
            --bg-sidebar-hover: #1e293b;
            --bg-sidebar-active: #1d4ed8;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --text-sidebar: #cbd5e1;
            --text-sidebar-bright: #f8fafc;
            --border-color: #e2e8f0;
            --border-strong: #cbd5e1;

            --green-bg: #dcfce7;
            --green-text: #166534;
            --green-border: #86efac;
            --green-solid: #16a34a;

            --red-bg: #fee2e2;
            --red-text: #991b1b;
            --red-border: #fca5a5;
            --red-solid: #dc2626;

            --amber-bg: #fef3c7;
            --amber-text: #92400e;
            --amber-border: #fcd34d;
            --amber-solid: #d97706;

            --blue-bg: #dbeafe;
            --blue-text: #1e40af;
            --blue-border: #93c5fd;
            --blue-solid: #2563eb;
            --blue-solid-hover: #1d4ed8;

            --slate-bg: #e2e8f0;
            --slate-text: #334155;

            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --shadow-sm: 0 1px 2px 0 rgb(15 23 42 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(15 23 42 / 0.07), 0 2px 4px -2px rgb(15 23 42 / 0.05);
            --font-sans: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: var(--font-sans);
            font-size: 15px;
            line-height: 1.5;
            color: var(--text-primary);
            background-color: var(--bg-canvas);
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: var(--blue-solid);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .app-shell {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 248px;
            flex-shrink: 0;
            background-color: var(--bg-sidebar);
            color: var(--text-sidebar);
            display: flex;
            flex-direction: column;
            border-right: 1px solid #1e293b;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1.25rem 1.25rem 1rem;
            border-bottom: 1px solid #1e293b;
        }

        .sidebar-brand-icon {
            width: 34px;
            height: 34px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .sidebar-brand-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-sidebar-bright);
            letter-spacing: -0.01em;
            line-height: 1.2;
        }

        .sidebar-brand-sub {
            font-size: 0.72rem;
            color: #94a3b8;
        }

        .sidebar-nav {
            padding: 1rem 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            flex: 1;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 0.875rem;
            border-radius: var(--radius-md);
            color: var(--text-sidebar);
            font-size: 0.9rem;
            font-weight: 500;
            transition: background-color 0.15s ease, color 0.15s ease;
            text-decoration: none;
        }

        .nav-link:hover {
            background-color: var(--bg-sidebar-hover);
            color: var(--text-sidebar-bright);
            text-decoration: none;
        }

        .nav-link.is-active {
            background-color: var(--bg-sidebar-active);
            color: #ffffff;
        }

        .nav-link svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            opacity: 0.9;
        }

        .sidebar-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid #1e293b;
            font-size: 0.75rem;
            color: #94a3b8;
        }

        /* Main area */
        .main-wrapper {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background-color: var(--bg-surface);
            border-bottom: 1px solid var(--border-color);
            padding: 0.875rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .mobile-menu-btn {
            display: none;
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.4rem 0.55rem;
            color: var(--text-primary);
            cursor: pointer;
        }

        .page-title {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            flex-wrap: wrap;
        }

        .ip-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.65rem;
            background-color: var(--bg-subtle);
            border: 1px solid var(--border-color);
            border-radius: 9999px;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .ip-pill code {
            font-family: var(--font-mono);
            font-weight: 600;
            color: var(--text-primary);
        }

        .main-content {
            padding: 1.5rem;
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        /* Badges & Pills */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1.3;
            white-space: nowrap;
        }

        .badge-green {
            background-color: var(--green-bg);
            color: var(--green-text);
            border: 1px solid var(--green-border);
        }

        .badge-red {
            background-color: var(--red-bg);
            color: var(--red-text);
            border: 1px solid var(--red-border);
        }

        .badge-amber {
            background-color: var(--amber-bg);
            color: var(--amber-text);
            border: 1px solid var(--amber-border);
        }

        .badge-blue {
            background-color: var(--blue-bg);
            color: var(--blue-text);
            border: 1px solid var(--blue-border);
        }

        .badge-slate {
            background-color: var(--slate-bg);
            color: var(--slate-text);
            border: 1px solid var(--border-strong);
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            background-color: currentColor;
        }

        /* Alerts */
        .alert {
            padding: 0.875rem 1rem;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            font-size: 0.9rem;
        }

        .alert-success {
            background-color: var(--green-bg);
            border-color: var(--green-border);
            color: var(--green-text);
        }

        .alert-warning {
            background-color: var(--amber-bg);
            border-color: var(--amber-border);
            color: var(--amber-text);
        }

        .alert-error {
            background-color: var(--red-bg);
            border-color: var(--red-border);
            color: var(--red-text);
        }

        .alert ul {
            margin: 0.35rem 0 0 1.25rem;
            padding: 0;
        }

        .alert-dismiss {
            background: transparent;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 1.1rem;
            line-height: 1;
            opacity: 0.75;
            padding: 0 0.25rem;
        }

        .alert-dismiss:hover {
            opacity: 1;
        }

        /* Cards */
        .card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .card-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .card-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card-subtitle {
            margin: 0.15rem 0 0;
            font-size: 0.825rem;
            color: var(--text-muted);
        }

        .card-body {
            padding: 1.25rem;
        }

        /* Grids */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 1rem;
        }

        .stat-card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.1rem 1.25rem;
            box-shadow: var(--shadow-sm);
            border-top: 3px solid var(--border-strong);
        }

        .stat-card.stat-green { border-top-color: var(--green-solid); }
        .stat-card.stat-red { border-top-color: var(--red-solid); }
        .stat-card.stat-blue { border-top-color: var(--blue-solid); }
        .stat-card.stat-amber { border-top-color: var(--amber-solid); }

        .stat-label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
        }

        .stat-value {
            margin-top: 0.35rem;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.1;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.25rem;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 0.875rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .form-group-full {
            grid-column: 1 / -1;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem 1.25rem;
            align-items: start;
        }

        @media (max-width: 720px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-hint {
            font-size: 0.78rem;
            color: var(--text-muted);
            line-height: 1.35;
        }

        .settings-toggles {
            grid-column: 1 / -1;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1.75rem;
            padding: 0.25rem 0;
        }

        .settings-actions {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-top: 0.5rem;
            border-top: 1px solid var(--border-color);
        }

        .settings-inline-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.875rem;
            align-items: flex-end;
        }

        .settings-inline-row .form-group {
            flex: 1 1 260px;
        }

        .recipient-groups {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem 0.75rem;
        }

        .recipient-groups .checkbox-label {
            padding: 0.15rem 0;
            font-size: 0.8rem;
        }

        label {
            font-size: 0.825rem;
            font-weight: 600;
            color: var(--text-secondary);
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="datetime-local"],
        select,
        textarea {
            width: 100%;
            padding: 0.5rem 0.7rem;
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.875rem;
            color: var(--text-primary);
            background-color: var(--bg-surface);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        textarea {
            font-family: var(--font-mono);
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--blue-solid);
            box-shadow: 0 0 0 3px rgb(37 99 235 / 0.15);
        }

        .checkbox-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
            padding: 0.45rem 0;
        }

        .checkbox-label input[type="checkbox"] {
            width: 1rem;
            height: 1rem;
            accent-color: var(--blue-solid);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.5rem 0.9rem;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
            white-space: nowrap;
        }

        .btn:hover {
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--blue-solid);
            color: #ffffff;
        }
        .btn-primary:hover { background-color: var(--blue-solid-hover); }

        .btn-secondary {
            background-color: var(--bg-surface);
            border-color: var(--border-strong);
            color: var(--text-primary);
        }
        .btn-secondary:hover { background-color: var(--bg-subtle); }

        .btn-success {
            background-color: var(--green-solid);
            color: #ffffff;
        }
        .btn-success:hover { background-color: #15803d; }

        .btn-danger {
            background-color: var(--red-solid);
            color: #ffffff;
        }
        .btn-danger:hover { background-color: #b91c1c; }

        .btn-outline-danger {
            background-color: transparent;
            border-color: var(--red-border);
            color: var(--red-text);
        }
        .btn-outline-danger:hover { background-color: var(--red-bg); }

        .btn-outline-success {
            background-color: transparent;
            border-color: var(--green-border);
            color: var(--green-text);
        }
        .btn-outline-success:hover { background-color: var(--green-bg); }

        .btn-sm {
            padding: 0.3rem 0.6rem;
            font-size: 0.78rem;
        }

        /* Tables */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.875rem;
        }

        thead th {
            padding: 0.75rem 1rem;
            background-color: var(--bg-subtle);
            border-bottom: 1px solid var(--border-color);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            white-space: nowrap;
        }

        tbody td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background-color: #f8fafc;
        }

        .mono {
            font-family: var(--font-mono);
            font-size: 0.84rem;
        }

        .text-muted {
            color: var(--text-muted);
        }

        .actions-cell {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
        }

        .inline-form {
            display: inline-flex;
            margin: 0;
        }

        /* Collapsible details */
        details.collapsible {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background-color: var(--bg-surface);
            box-shadow: var(--shadow-sm);
        }

        details.collapsible summary {
            padding: 0.95rem 1.25rem;
            font-weight: 600;
            cursor: pointer;
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            user-select: none;
        }

        details.collapsible summary::-webkit-details-marker {
            display: none;
        }

        details.collapsible summary::after {
            content: '+';
            font-size: 1.1rem;
            color: var(--text-muted);
        }

        details.collapsible[open] summary::after {
            content: '−';
        }

        details.collapsible[open] summary {
            border-bottom: 1px solid var(--border-color);
        }

        .collapsible-body {
            padding: 1.25rem;
        }

        /* Pagination */
        .pagination-wrap {
            padding: 0.875rem 1.25rem;
            border-top: 1px solid var(--border-color);
            background-color: var(--bg-subtle);
        }

        .pagination-wrap nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.85rem;
        }

        .pagination-wrap ul.pagination {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        .pagination-wrap li a,
        .pagination-wrap li span,
        .pagination-wrap nav a,
        .pagination-wrap nav span[aria-current="page"] > span,
        .pagination-wrap nav span[aria-disabled="true"] > span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2rem;
            height: 2rem;
            padding: 0 0.55rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background-color: var(--bg-surface);
            color: var(--text-primary);
            font-size: 0.82rem;
            text-decoration: none;
        }

        .pagination-wrap nav svg {
            width: 14px;
            height: 14px;
        }

        .pagination-wrap li.active span,
        .pagination-wrap nav span[aria-current="page"] > span {
            background-color: var(--blue-solid);
            border-color: var(--blue-solid);
            color: #ffffff;
            font-weight: 600;
        }

        .pagination-wrap li.disabled span,
        .pagination-wrap nav span[aria-disabled="true"] > span {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .empty-state {
            text-align: center;
            padding: 2.5rem 1rem;
            color: var(--text-muted);
        }

        /* Responsive Sidebar */
        .sidebar-backdrop {
            display: none;
        }

        @media (max-width: 860px) {
            .mobile-menu-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                z-index: 50;
                transform: translateX(-100%);
                transition: transform 0.2s ease;
            }

            .sidebar.is-open {
                transform: translateX(0);
            }

            .sidebar-backdrop.is-open {
                display: block;
                position: fixed;
                inset: 0;
                background: rgb(15 23 42 / 0.5);
                z-index: 40;
            }

            .main-content {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <aside class="sidebar" id="sidebar" aria-label="Firewall Navigation">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
            </div>
            <div>
                <div class="sidebar-brand-title">{{ __('firewall-gui::ui.nav.brand_title') }}</div>
                <div class="sidebar-brand-sub">{{ __('firewall-gui::ui.nav.brand_sub') }}</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('firewall-gui.dashboard') }}"
               class="nav-link {{ request()->routeIs('firewall-gui.dashboard') ? 'is-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <rect x="3" y="3" width="7" height="9" rx="1"/>
                    <rect x="14" y="3" width="7" height="5" rx="1"/>
                    <rect x="14" y="12" width="7" height="9" rx="1"/>
                    <rect x="3" y="16" width="7" height="5" rx="1"/>
                </svg>
                <span>{{ __('firewall-gui::ui.nav.dashboard') }}</span>
            </a>

            <a href="{{ route('firewall-gui.rules.index') }}"
               class="nav-link {{ request()->routeIs('firewall-gui.rules.*') ? 'is-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M9 11l3 3L22 4"/>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
                <span>{{ __('firewall-gui::ui.nav.rules') }}</span>
            </a>

            <a href="{{ route('firewall-gui.groups.index') }}"
               class="nav-link {{ request()->routeIs('firewall-gui.groups.*') ? 'is-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>
                <span>{{ __('firewall-gui::ui.nav.groups') }}</span>
            </a>

            <a href="{{ route('firewall-gui.logs.index') }}"
               class="nav-link {{ request()->routeIs('firewall-gui.logs.*') ? 'is-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
                <span>{{ __('firewall-gui::ui.nav.logs') }}</span>
            </a>

            <a href="{{ route('firewall-gui.settings.index') }}"
               class="nav-link {{ request()->routeIs('firewall-gui.settings.*') ? 'is-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.09a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.09a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
                <span>{{ __('firewall-gui::ui.nav.settings') }}</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            {{ __('firewall-gui::ui.nav.mode') }} <strong>{{ config('firewall-gui.mode', 'blacklist_only') }}</strong>
        </div>
    </aside>

    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button"
                        class="mobile-menu-btn"
                        id="mobile-menu-btn"
                        aria-controls="sidebar"
                        aria-expanded="false"
                        aria-label="Toggle navigation menu">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <h1 class="page-title">@yield('title', 'Firewall')</h1>
            </div>

            <div class="topbar-right">
                <span class="ip-pill" title="Your detected client IP address">
                    {{ __('firewall-gui::ui.nav.your_ip') }} <code>{{ $resolvedIp }}</code>
                </span>

                @if (! $resolvedEnabled)
                    <span class="badge badge-slate">
                        <span class="status-dot" aria-hidden="true"></span>
                        {{ __('firewall-gui::ui.nav.status_disabled') }}
                    </span>
                @elseif ($resolvedDryRun)
                    <span class="badge badge-amber">
                        <span class="status-dot" aria-hidden="true"></span>
                        {{ __('firewall-gui::ui.nav.status_dry_run') }}
                    </span>
                @else
                    <span class="badge badge-green">
                        <span class="status-dot" aria-hidden="true"></span>
                        {{ __('firewall-gui::ui.nav.status_active') }}
                    </span>
                @endif
            </div>
        </header>

        <main class="main-content">
            @if (session('success'))
                <div class="alert alert-success" role="status">
                    <div>{{ session('success') }}</div>
                    <button type="button" class="alert-dismiss" aria-label="Dismiss alert">&times;</button>
                </div>
            @endif

            @if (session('warning'))
                <div class="alert alert-warning" role="status">
                    <div>{{ session('warning') }}</div>
                    <button type="button" class="alert-dismiss" aria-label="Dismiss alert">&times;</button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-error" role="alert">
                    <div>{{ session('error') }}</div>
                    <button type="button" class="alert-dismiss" aria-label="Dismiss alert">&times;</button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <div>
                        <strong>Please fix the following {{ $errors->count() === 1 ? 'error' : 'errors' }}:</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <button type="button" class="alert-dismiss" aria-label="Dismiss errors">&times;</button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
    (function () {
        var sidebar = document.getElementById('sidebar');
        var backdrop = document.getElementById('sidebar-backdrop');
        var menuBtn = document.getElementById('mobile-menu-btn');

        function setMenuOpen(open) {
            if (!sidebar || !backdrop || !menuBtn) return;
            sidebar.classList.toggle('is-open', open);
            backdrop.classList.toggle('is-open', open);
            menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        if (menuBtn) {
            menuBtn.addEventListener('click', function () {
                setMenuOpen(!sidebar.classList.contains('is-open'));
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', function () {
                setMenuOpen(false);
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar && sidebar.classList.contains('is-open')) {
                setMenuOpen(false);
            }
        });

        document.addEventListener('click', function (event) {
            var dismissBtn = event.target.closest('.alert-dismiss');
            if (dismissBtn) {
                var alertEl = dismissBtn.closest('.alert');
                if (alertEl) alertEl.remove();
            }
        });

        document.addEventListener('submit', function (event) {
            var form = event.target;
            var msg = form.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) {
                event.preventDefault();
            }
        });

        function parseIpv4(ip) {
            var parts = ip.trim().split('.');
            if (parts.length !== 4) return null;
            var num = 0;
            for (var i = 0; i < 4; i++) {
                if (!/^\d+$/.test(parts[i])) return null;
                var n = Number(parts[i]);
                if (n < 0 || n > 255) return null;
                num = (num * 256) + n;
            }
            return num >>> 0;
        }

        function formatIpv4(num) {
            return [
                (num >>> 24) & 255,
                (num >>> 16) & 255,
                (num >>> 8) & 255,
                num & 255
            ].join('.');
        }

        function describeIpInput(raw) {
            var val = raw.trim();
            if (!val) return '';

            if (val.indexOf('/') !== -1) {
                var cidrParts = val.split('/');
                if (cidrParts.length !== 2 || !/^\d+$/.test(cidrParts[1])) return '';
                var prefix = Number(cidrParts[1]);
                var ipNum = parseIpv4(cidrParts[0]);
                if (ipNum !== null && prefix >= 0 && prefix <= 32) {
                    var mask = prefix === 0 ? 0 : (0xFFFFFFFF << (32 - prefix)) >>> 0;
                    var network = (ipNum & mask) >>> 0;
                    var broadcast = (network | (~mask >>> 0)) >>> 0;
                    var total = Math.pow(2, 32 - prefix);
                    return 'Affected IPs: ' + formatIpv4(network) + ' – ' + formatIpv4(broadcast) + ' (' + total.toLocaleString() + ' IPs)';
                }
                if (cidrParts[0].indexOf(':') !== -1 && prefix >= 0 && prefix <= 128) {
                    var hostBits = 128 - prefix;
                    return 'IPv6 CIDR /' + prefix + ' (' + (hostBits <= 53 ? Math.pow(2, hostBits).toLocaleString() : '2^' + hostBits) + ' IPs)';
                }
                return '';
            }

            if (val.indexOf('-') !== -1) {
                var rangeParts = val.split('-');
                if (rangeParts.length !== 2) return '';
                var startNum = parseIpv4(rangeParts[0]);
                var endNum = parseIpv4(rangeParts[1]);
                if (startNum !== null && endNum !== null && startNum <= endNum) {
                    var count = endNum - startNum + 1;
                    return 'Affected IPs: ' + formatIpv4(startNum) + ' – ' + formatIpv4(endNum) + ' (' + count.toLocaleString() + ' IPs)';
                }
                return '';
            }

            if (parseIpv4(val) !== null) {
                return 'Single IPv4: ' + val + ' (1 IP)';
            }

            return '';
        }

        document.querySelectorAll('[data-ip-preview]').forEach(function (input) {
            var targetId = input.getAttribute('data-ip-preview');
            var target = document.getElementById(targetId);
            if (!target) return;

            var update = function () {
                var text = describeIpInput(input.value);
                target.textContent = text;
                target.style.display = text ? 'block' : 'none';
            };

            input.addEventListener('input', update);
            update();
        });
    })();
</script>
</body>
</html>

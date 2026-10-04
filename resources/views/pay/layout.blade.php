<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') — بهجة</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --ink: #1f2937; --muted: #6b7280; --line: #e5e7eb; --brand: #0d9488; --surface: #ffffff; --page: #f3f4f6; }
        @media (prefers-color-scheme: dark) {
            :root { --ink: #f3f4f6; --muted: #9ca3af; --line: #374151; --surface: #111827; --page: #030712; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--page); color: var(--ink); font-family: 'Cairo', system-ui, sans-serif; }
        main { max-width: 440px; margin: 0 auto; padding: 24px 16px; }
        .card { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: var(--muted); font-size: 14px; }
        .amount { font-size: 36px; font-weight: 800; margin: 16px 0; }
        .btn { display: block; width: 100%; text-align: center; background: var(--brand); color: #fff; border: 0; border-radius: 12px; padding: 14px; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; margin-top: 12px; }
        .btn.secondary { background: transparent; color: var(--brand); border: 2px solid var(--brand); }
        .code { font-family: ui-monospace, monospace; font-size: 32px; font-weight: 800; letter-spacing: 4px; text-align: center; padding: 16px; border: 2px dashed var(--brand); border-radius: 12px; margin: 16px 0; direction: ltr; }
        .note { font-size: 13px; color: var(--muted); margin-top: 16px; }
        .error { color: #b91c1c; font-size: 14px; }
    </style>
</head>
<body>
    <main>@yield('content')</main>
</body>
</html>

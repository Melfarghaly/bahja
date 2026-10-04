{{-- Installable app (PWA): manifest, icons and the service worker. --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#134E4A">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="بهجة">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
</script>

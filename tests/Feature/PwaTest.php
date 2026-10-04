<?php

/*
| The staff app is installable: a valid manifest with real icons, a service
| worker that never caches pages or API data, and the tags in the layouts.
*/

it('ships a valid manifest whose icons exist', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)->toMatchArray(['lang' => 'ar', 'dir' => 'rtl', 'display' => 'standalone', 'start_url' => '/app?source=pwa'])
        ->and(collect($manifest['icons'])->pluck('sizes'))->toContain('192x192', '512x512')
        ->and(collect($manifest['icons'])->pluck('purpose'))->toContain('maskable');

    foreach ([...$manifest['icons'], ...collect($manifest['shortcuts'])->flatMap(fn ($s) => $s['icons'])] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});

it('never lets the service worker cache pages or API responses', function () {
    $worker = file_get_contents(public_path('sw.js'));

    expect($worker)->toContain("request.mode === 'navigate'")
        ->toContain("caches.match('/offline.html')")
        ->not->toMatch('/cache\\.put\\(request[^)]*\\)[^\\n]*api/i');

    // Only static paths reach cache.put.
    preg_match('/const isStatic = .*?;/s', $worker, $static);
    expect($static[0])->toContain('/build/')->toContain('/icons/')->not->toContain('/api');
    expect(public_path('offline.html'))->toBeFile();
});

it('links the manifest and registers the worker in the staff and sign-in layouts', function () {
    [$tenant, $owner] = createNurseryWithOwner();

    $this->actingAs($owner)->get(route('nursery.dashboard'))
        ->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
        ->assertSee("navigator.serviceWorker.register('/sw.js')", false);

    auth()->logout();
    $this->get(route('login'))->assertOk()->assertSee('/manifest.webmanifest', false);
});

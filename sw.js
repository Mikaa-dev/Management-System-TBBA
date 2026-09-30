/**
 * Service Worker — The Bridge Business Alliance (TBBA) Mini ERP
 *
 * v16
 *
 * Strategy:
 * - Firebase Messaging shares this same service worker.
 * - PHP pages / AJAX / authenticated business data: NETWORK ONLY.
 * - JavaScript & CSS: NETWORK FIRST.
 * - Images/fonts/static files: STALE-WHILE-REVALIDATE.
 * - Navigation while offline: show /offline.html.
 * - Never cache POST/PUT/PATCH/DELETE.
 */

importScripts('/firebase-messaging-sw.js');


/* =========================================================
   CACHE VERSION
   ========================================================= */

const CACHE_NAME = 'tbba-erp-static-v16';


/* =========================================================
   PRECACHE
   ========================================================= */

const PRECACHE_ASSETS = [
    '/offline.html',
    '/manifest.json?v=4',

    '/assets/images/logo.png',
    '/assets/images/app_icon_v2.png',
    '/assets/images/default-avatar.svg',

    '/assets/css/style.css',
    '/assets/css/esign.css',

    '/assets/js/app.js'
];


/* =========================================================
   PRECACHE HELPER
   ========================================================= */

async function precacheAsset(cache, asset) {
    try {

        const response = await fetch(
            asset,
            {
                cache: 'reload'
            }
        );

        if (!response.ok) {

            console.warn(
                '[Service Worker] Precache skipped:',
                asset,
                response.status
            );

            return;
        }

        await cache.put(
            asset,
            response
        );

    } catch (error) {

        console.warn(
            '[Service Worker] Precache failed:',
            asset,
            error
        );
    }
}


/* =========================================================
   INSTALL
   ========================================================= */

self.addEventListener(
    'install',

    (event) => {

        event.waitUntil(
            (async () => {

                const cache =
                    await caches.open(
                        CACHE_NAME
                    );

                await Promise.all(
                    PRECACHE_ASSETS.map(
                        (asset) =>
                            precacheAsset(
                                cache,
                                asset
                            )
                    )
                );

                console.log(
                    '[Service Worker] Static assets prepared:',
                    CACHE_NAME
                );

                /*
                 * Activate new worker immediately.
                 */
                await self.skipWaiting();

            })()
        );
    }
);


/* =========================================================
   ACTIVATE
   ========================================================= */

self.addEventListener(
    'activate',

    (event) => {

        event.waitUntil(
            (async () => {

                const cacheNames =
                    await caches.keys();

                await Promise.all(
                    cacheNames.map(
                        (cacheName) => {

                            /*
                             * Delete old TBBA caches.
                             *
                             * v15 akan dibuang bila v16 aktif.
                             */
                            if (
                                cacheName.startsWith(
                                    'tbba-erp-'
                                )
                                &&
                                cacheName !==
                                CACHE_NAME
                            ) {

                                console.log(
                                    '[Service Worker] Deleting old cache:',
                                    cacheName
                                );

                                return caches.delete(
                                    cacheName
                                );
                            }

                            return Promise.resolve(
                                false
                            );
                        }
                    )
                );

                /*
                 * New Service Worker controls
                 * current tabs immediately.
                 */
                await self.clients.claim();

                console.log(
                    '[Service Worker] Activated:',
                    CACHE_NAME
                );

            })()
        );
    }
);


/* =========================================================
   DYNAMIC APP REQUEST
   ========================================================= */

function isDynamicAppRequest(
    url,
    request
) {

    /*
     * Page navigation is always dynamic.
     */
    if (
        request.mode ===
        'navigate'
    ) {
        return true;
    }


    /*
     * Root / index.php.
     */
    if (
        url.pathname === '/'
        ||
        url.pathname ===
        '/index.php'
        ||
        url.pathname.endsWith(
            '/index.php'
        )
    ) {
        return true;
    }


    /*
     * Router parameters.
     */
    if (
        url.searchParams.has(
            'page'
        )
        ||
        url.searchParams.has(
            'action'
        )
    ) {
        return true;
    }


    return false;
}


/* =========================================================
   STATIC ASSET CHECK
   ========================================================= */

function isStaticAsset(
    url,
    request
) {

    if (
        url.origin !==
        self.location.origin
    ) {
        return false;
    }


    if (
        url.pathname.startsWith(
            '/assets/'
        )
    ) {
        return true;
    }


    if (
        url.pathname ===
        '/manifest.json'
    ) {
        return true;
    }


    return [
        'style',
        'script',
        'image',
        'font'
    ].includes(
        request.destination
    );
}


/* =========================================================
   SCRIPT / CSS CHECK
   ========================================================= */

function isCodeAsset(
    url,
    request
) {

    if (
        url.origin !==
        self.location.origin
    ) {
        return false;
    }


    /*
     * Browser already tells us destination.
     */
    if (
        request.destination ===
        'script'
        ||
        request.destination ===
        'style'
    ) {
        return true;
    }


    /*
     * Extra protection based on extension.
     */
    return (
        url.pathname.endsWith(
            '.js'
        )
        ||
        url.pathname.endsWith(
            '.css'
        )
    );
}


/* =========================================================
   NETWORK FIRST
   JS / CSS
   ========================================================= */

async function networkFirst(
    request
) {

    const cache =
        await caches.open(
            CACHE_NAME
        );

    try {

        /*
         * cache:no-store ensures browser HTTP cache
         * also doesn't return an old script.
         */
        const networkResponse =
            await fetch(
                request,
                {
                    cache: 'no-store'
                }
            );


        if (
            networkResponse
            &&
            networkResponse.ok
            &&
            networkResponse.type ===
            'basic'
        ) {

            /*
             * Cache using EXACT request URL.
             *
             * Example:
             * logbook.js?v=2.5.1
             *
             * remains separate from:
             * logbook.js?v=2.4.0
             */
            await cache.put(
                request,
                networkResponse.clone()
            );
        }


        return networkResponse;

    } catch (error) {

        /*
         * Network unavailable:
         * fallback to exact cached version.
         *
         * IMPORTANT:
         * NO ignoreSearch:true.
         */
        const cachedResponse =
            await cache.match(
                request
            );


        if (cachedResponse) {
            return cachedResponse;
        }


        throw error;
    }
}


/* =========================================================
   STALE WHILE REVALIDATE
   Images / fonts / other static assets
   ========================================================= */

async function staleWhileRevalidate(
    request
) {

    const cache =
        await caches.open(
            CACHE_NAME
        );


    /*
     * Exact request match.
     *
     * DO NOT USE:
     *
     * ignoreSearch: true
     *
     * kerana:
     *
     * file.js?v=1
     * file.js?v=2
     *
     * mestilah dianggap berlainan.
     */
    const cachedResponse =
        await cache.match(
            request
        );


    const networkPromise =
        fetch(
            request
        )

        .then(
            async (
                networkResponse
            ) => {

                if (
                    networkResponse
                    &&
                    networkResponse.ok
                    &&
                    networkResponse.type ===
                    'basic'
                ) {

                    await cache.put(
                        request,
                        networkResponse.clone()
                    );
                }


                return networkResponse;
            }
        )

        .catch(
            (error) => {

                if (
                    !cachedResponse
                ) {
                    throw error;
                }


                return cachedResponse;
            }
        );


    return (
        cachedResponse
        ||
        networkPromise
    );
}


/* =========================================================
   FETCH
   ========================================================= */

self.addEventListener(
    'fetch',

    (event) => {

        const request =
            event.request;


        /*
         * Jangan cache POST / PUT /
         * PATCH / DELETE.
         */
        if (
            request.method !==
            'GET'
        ) {
            return;
        }


        const url =
            new URL(
                request.url
            );


        /*
         * External CDN / Firebase /
         * Google kekal browser/network.
         */
        if (
            url.origin !==
            self.location.origin
        ) {
            return;
        }


        /* =================================================
           DYNAMIC PHP / AJAX
           NETWORK ONLY
           ================================================= */

        if (
            isDynamicAppRequest(
                url,
                request
            )
        ) {

            /*
             * Normal page navigation.
             */
            if (
                request.mode ===
                'navigate'
            ) {

                event.respondWith(

                    fetch(
                        request,
                        {
                            cache: 'no-store'
                        }
                    )

                    .catch(
                        async () => {

                            const offline =
                                await caches.match(
                                    '/offline.html'
                                );


                            if (offline) {
                                return offline;
                            }


                            return new Response(
                                'TBBA Portal is currently offline.',
                                {
                                    status: 503,

                                    headers: {
                                        'Content-Type':
                                            'text/plain; charset=UTF-8'
                                    }
                                }
                            );
                        }
                    )
                );


                return;
            }


            /*
             * AJAX:
             * don't intercept.
             * Browser goes directly to network.
             */
            return;
        }


        /* =================================================
           JS / CSS
           NETWORK FIRST
           ================================================= */

        if (
            isCodeAsset(
                url,
                request
            )
        ) {

            event.respondWith(
                networkFirst(
                    request
                )
            );


            return;
        }


        /* =================================================
           OTHER STATIC ASSETS
           STALE WHILE REVALIDATE
           ================================================= */

        if (
            isStaticAsset(
                url,
                request
            )
        ) {

            event.respondWith(
                staleWhileRevalidate(
                    request
                )
            );
        }

    }
);
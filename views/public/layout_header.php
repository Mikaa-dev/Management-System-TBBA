<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../core/Auth.php';

$isLoggedIn = Auth::check();
$currentPage = $_GET['page'] ?? 'home';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title>
        The Bridge Business Alliance Sdn. Bhd. | Official Corporate Website
    </title>

    <meta
        name="description"
        content="Official website of The Bridge Business Alliance Sdn. Bhd. (TBBA), providing ICT and computing solutions, industrial machinery, and agriculture and trading services across Malaysia."
    >

    <meta name="theme-color" content="#0F172A">

    <!-- Open Graph -->
    <meta property="og:type" content="website">

    <meta
        property="og:title"
        content="The Bridge Business Alliance Sdn. Bhd. | Official Corporate Website"
    >

    <meta
        property="og:description"
        content="Corporate solutions across ICT and computing, industrial machinery, and agriculture and trading."
    >

    <link
        rel="canonical"
        href="https://www.thebridgebusiness.com/"
    >

    <!-- CSRF -->
    <meta
        name="csrf-token"
        content="<?= Helper::csrfToken() ?>"
    >

    <script>
        window.csrfToken = "<?= Helper::csrfToken() ?>";
    </script>


    <!-- ==================================================
         FAVICON
    =================================================== -->

    <link
        rel="icon"
        type="image/png"
        href="/assets/images/favicon.png"
    >

    <link
        rel="shortcut icon"
        type="image/png"
        href="/assets/images/favicon.png"
    >

    <link
        rel="apple-touch-icon"
        href="/assets/images/favicon.png"
    >


    <!-- ==================================================
         PWA MANIFEST
    =================================================== -->

    <link
        rel="manifest"
        href="/manifest.json?v=4"
    >

    <meta
        name="apple-mobile-web-app-capable"
        content="yes"
    >

    <meta
        name="apple-mobile-web-app-status-bar-style"
        content="black-translucent"
    >

    <meta
        name="apple-mobile-web-app-title"
        content="TBBA ERP"
    >


    <!-- ==================================================
         GOOGLE FONTS
    =================================================== -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- ==================================================
         FONTAWESOME
    =================================================== -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0"
        crossorigin="anonymous"
    >


    <!-- ==================================================
         PUBLIC CONTACT CSS
    =================================================== -->

    <link
        rel="stylesheet"
        href="assets/css/public-contact-hub.css?v=<?= (int)(@filemtime(dirname(__DIR__, 2) . '/assets/css/public-contact-hub.css') ?: 1) ?>"
    >


    <style>

        :root {

            --primary: #1E3A8A;
            --primary-light: #2563EB;
            --primary-dark: #0F172A;

            --accent: #38BDF8;
            --accent-gold: #F59E0B;

            --text-main: #0F172A;
            --text-muted: #64748B;

            --bg-light: #F8FAFC;
            --bg-white: #FFFFFF;

            --border: #E2E8F0;

            --shadow-sm:
                0 4px 6px -1px rgba(0, 0, 0, 0.05);

            --shadow-md:
                0 10px 15px -3px rgba(0, 0, 0, 0.08);

            --shadow-lg:
                0 20px 25px -5px rgba(0, 0, 0, 0.1);

            --radius: 16px;

            --transition:
                all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }


        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {

            font-family:
                'Plus Jakarta Sans',
                sans-serif;

            color:
                var(--text-main);

            background-color:
                var(--bg-light);

            line-height: 1.6;

            overflow-x: hidden;
        }


        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {

            font-family:
                'Outfit',
                sans-serif;

            font-weight: 700;

            color:
                var(--primary-dark);

            line-height: 1.25;
        }


        a {

            text-decoration: none;

            color: inherit;

            transition:
                var(--transition);
        }



        /* ==================================================
           TOP BAR
        =================================================== */

        .top-bar {

            background:
                var(--primary-dark);

            color:
                #94A3B8;

            font-size:
                13px;

            padding:
                8px 0;

            border-bottom:
                1px solid rgba(255,255,255,0.08);
        }


        .top-bar .container {

            display: flex;

            justify-content:
                space-between;

            align-items:
                center;

            flex-wrap:
                wrap;

            gap:
                10px;
        }


        .top-bar-info {

            display: flex;

            gap:
                24px;

            flex-wrap:
                wrap;
        }


        .top-bar-info span {

            display: flex;

            align-items:
                center;

            gap:
                8px;
        }


        .top-bar-info i {

            color:
                var(--accent);
        }


        .top-bar-socials {

            display: flex;

            gap:
                16px;
        }


        .top-bar-socials a:hover {

            color:
                #FFFFFF;
        }



        /* ==================================================
           CONTAINER
        =================================================== */

        .container {

            max-width:
                1240px;

            margin:
                0 auto;

            padding:
                0 24px;
        }



        /* ==================================================
           NAVBAR
        =================================================== */

        .navbar {

            background:
                rgba(255,255,255,0.92);

            backdrop-filter:
                blur(16px);

            -webkit-backdrop-filter:
                blur(16px);

            position:
                sticky;

            top:
                0;

            z-index:
                1000;

            border-bottom:
                1px solid var(--border);

            box-shadow:
                var(--shadow-sm);

            transition:
                var(--transition);
        }


        .navbar .container {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            height:
                84px;

            position:
                relative;
        }



        /* ==================================================
           BRAND & LOGO
        =================================================== */

        .nav-brand {

            display:
                flex;

            align-items:
                center;

            gap:
                14px;
        }


        .nav-logo-img {

            height:
                54px;

            width:
                auto;

            object-fit:
                contain;

            background:
                transparent;

            padding:
                0;

            filter:
                drop-shadow(
                    0 2px 6px
                    rgba(0,0,0,0.35)
                )
                brightness(1.1)
                contrast(1.05);

            transition:
                var(--transition);
        }


        .nav-logo-img:hover {

            transform:
                scale(1.03);
        }


        .nav-brand-text {

            display:
                flex;

            flex-direction:
                column;
        }


        .nav-brand-title {

            font-family:
                'Outfit',
                sans-serif;

            font-size:
                19px;

            font-weight:
                800;

            color:
                var(--primary-dark);

            letter-spacing:
                -0.5px;

            line-height:
                1.1;
        }


        .nav-brand-subtitle {

            font-size:
                10px;

            font-weight:
                700;

            color:
                var(--primary-light);

            letter-spacing:
                0.8px;

            text-transform:
                uppercase;

            margin-top:
                2px;
        }



        /* ==================================================
           NAVIGATION LINKS
        =================================================== */

        .nav-links {

            display:
                flex;

            align-items:
                center;

            gap:
                28px;

            list-style:
                none;

            position:
                absolute;

            left:
                50%;

            transform:
                translateX(-50%);
        }


        .nav-toggle {

            display:
                none;

            width:
                46px;

            height:
                46px;

            align-items:
                center;

            justify-content:
                center;

            border:
                1px solid var(--border);

            border-radius:
                12px;

            background:
                #FFFFFF;

            color:
                var(--primary-dark);

            font-size:
                20px;

            cursor:
                pointer;

            box-shadow:
                var(--shadow-sm);
        }


        .nav-toggle:hover,
        .nav-toggle:focus-visible {

            border-color:
                #93C5FD;

            color:
                var(--primary-light);

            outline:
                none;
        }


        .nav-link {

            font-size:
                15px;

            font-weight:
                600;

            color:
                var(--text-main);

            position:
                relative;

            padding:
                8px 0;
        }


        .nav-link::after {

            content:
                '';

            position:
                absolute;

            bottom:
                0;

            left:
                0;

            width:
                0%;

            height:
                2px;

            background:
                var(--primary-light);

            transition:
                var(--transition);

            border-radius:
                2px;
        }


        .nav-link:hover,
        .nav-link.active {

            color:
                var(--primary-light);
        }


        .nav-link:hover::after,
        .nav-link.active::after {

            width:
                100%;
        }



        /* ==================================================
           SECTION COMMONS
        =================================================== */

        .section-padding {

            padding:
                90px 0;
        }


        .section-header {

            text-align:
                center;

            max-width:
                750px;

            margin:
                0 auto 50px auto;
        }


        .section-tag {

            display:
                inline-block;

            background:
                #EFF6FF;

            color:
                var(--primary-light);

            font-size:
                12px;

            font-weight:
                700;

            text-transform:
                uppercase;

            letter-spacing:
                1.5px;

            padding:
                6px 14px;

            border-radius:
                50px;

            margin-bottom:
                14px;
        }


        .section-title {

            font-size:
                38px;

            font-weight:
                800;

            color:
                var(--primary-dark);

            margin-bottom:
                16px;

            letter-spacing:
                -0.8px;
        }


        .section-subtitle {

            font-size:
                17px;

            color:
                var(--text-muted);

            line-height:
                1.7;
        }



        /* ==================================================
           TOAST
        =================================================== */

        .toast {

            min-width:
                320px;

            max-width:
                450px;

            background:
                #0F172A;

            color:
                #FFFFFF;

            padding:
                16px 20px;

            border-radius:
                12px;

            box-shadow:
                0 10px 25px rgba(0,0,0,0.3);

            display:
                flex;

            align-items:
                flex-start;

            justify-content:
                space-between;

            gap:
                14px;

            animation:
                slideInRight 0.3s forwards;

            border:
                1px solid rgba(255,255,255,0.1);

            border-left:
                5px solid #38BDF8;
        }


        .toast.success {

            border-left:
                5px solid #10B981;
        }


        .toast.error {

            border-left:
                5px solid #EF4444;
        }



        /* ==================================================
           ANIMATIONS
        =================================================== */

        @keyframes slideInRight {

            from {

                transform:
                    translateX(100%);

                opacity:
                    0;
            }

            to {

                transform:
                    translateX(0);

                opacity:
                    1;
            }
        }


        @keyframes fadeOut {

            from {

                opacity:
                    1;
            }

            to {

                opacity:
                    0;

                transform:
                    translateY(10px);
            }
        }


        @keyframes fadeIn {

            from {

                opacity:
                    0;

                transform:
                    translateY(15px);
            }

            to {

                opacity:
                    1;

                transform:
                    translateY(0);
            }
        }


        .animate-fade {

            animation:
                fadeIn 0.8s
                cubic-bezier(0.16,1,0.3,1)
                forwards;
        }



        /* ==================================================
           TABLET
        =================================================== */

        @media (max-width: 1100px) {

            .nav-links {

                display:
                    flex;

                position:
                    absolute;

                top:
                    calc(100% + 1px);

                right:
                    20px;

                left:
                    20px;

                transform:
                    none;

                flex-direction:
                    column;

                align-items:
                    stretch;

                gap:
                    4px;

                padding:
                    12px;

                border:
                    1px solid var(--border);

                border-radius:
                    0 0 18px 18px;

                background:
                    rgba(255,255,255,0.98);

                box-shadow:
                    var(--shadow-lg);

                opacity:
                    0;

                visibility:
                    hidden;

                pointer-events:
                    none;

                transform:
                    translateY(-10px);

                transition:
                    opacity 0.2s ease,
                    transform 0.2s ease,
                    visibility 0.2s ease;
            }


            .nav-links.open {

                opacity:
                    1;

                visibility:
                    visible;

                pointer-events:
                    auto;

                transform:
                    translateY(0);
            }


            .nav-links .nav-link {

                display:
                    block;

                width:
                    100%;

                padding:
                    13px 15px;

                border-radius:
                    10px;
            }


            .nav-links .nav-link:hover,
            .nav-links .nav-link:focus-visible {

                background:
                    #EFF6FF;
            }


            .nav-links .nav-link::after {

                display:
                    none;
            }


            .nav-toggle {

                display:
                    inline-flex;
            }


            .top-bar-info span:nth-child(2),
            .top-bar-info span:nth-child(3) {

                display:
                    none;
            }
        }



        /* ==================================================
           MOBILE
        =================================================== */

        @media (max-width: 768px) {

            html,
            body {

                max-width:
                    100vw !important;

                overflow-x:
                    hidden !important;

                box-sizing:
                    border-box !important;
            }


            .container {

                padding:
                    0 16px !important;

                max-width:
                    100vw !important;

                box-sizing:
                    border-box !important;
            }


            .navbar .container {

                height:
                    auto;

                min-height:
                    70px;

                padding:
                    12px 16px !important;

                flex-wrap:
                    wrap;
            }


            .nav-brand-title {

                font-size:
                    16px;
            }


            .nav-logo-img {

                height:
                    44px;
            }


            .section-padding {

                padding:
                    50px 0;
            }


            .section-title {

                font-size:
                    28px;
            }


            .top-bar .container {

                justify-content:
                    center;

                text-align:
                    center;
            }


            .about-layout,
            .services-grid,
            .partners-grid,
            .contact-layout,
            .location-grid,
            .inquiry-contact-fields,
            .public-footer-grid {

                grid-template-columns:
                    1fr !important;

                width:
                    100% !important;

                max-width:
                    100% !important;

                box-sizing:
                    border-box !important;
            }


            #hero {

                min-height:
                    720px !important;

                padding:
                    100px 0 110px !important;
            }


            #about,
            #services,
            #contact {

                padding:
                    72px 0 !important;
            }


            .services-grid > div {

                padding:
                    30px 24px !important;
            }


            .inquiry-card {

                padding:
                    30px 24px !important;
            }


            .contact-layout {

                gap:
                    32px !important;

                margin-bottom:
                    42px !important;
            }


            .contact-map {

                height:
                    380px !important;
            }
        }



        @media (max-width: 576px) {

            .nav-brand-subtitle {

                max-width:
                    190px;

                overflow:
                    hidden;

                text-overflow:
                    ellipsis;

                white-space:
                    nowrap;
            }


            #toast-container {

                right:
                    10px !important;

                bottom:
                    max(
                        10px,
                        env(safe-area-inset-bottom,0px)
                    ) !important;

                left:
                    10px !important;

                width:
                    auto !important;
            }


            #toast-container .toast {

                width:
                    100% !important;

                min-width:
                    0 !important;

                max-width:
                    none !important;
            }


            #hero {

                min-height:
                    680px !important;

                padding-top:
                    82px !important;
            }


            .top-bar-socials {

                display:
                    none;
            }


            .about-layout {

                gap:
                    34px !important;
            }


            .partners-grid {

                gap:
                    14px !important;
            }


            .contact-map {

                height:
                    330px !important;
            }
        }



        /* ==================================================
           REDUCED MOTION
        =================================================== */

        @media (prefers-reduced-motion: reduce) {

            html {

                scroll-behavior:
                    auto;
            }


            *,
            *::before,
            *::after {

                animation-duration:
                    0.01ms !important;

                animation-iteration-count:
                    1 !important;

                scroll-behavior:
                    auto !important;

                transition-duration:
                    0.01ms !important;
            }
        }



        /* ==================================================
           SAFE AREA
        =================================================== */

        @supports (padding: max(0px)) {

            @media (max-width: 768px) {

                .top-bar .container,
                .navbar .container {

                    padding-right:
                        max(
                            16px,
                            env(safe-area-inset-right)
                        ) !important;

                    padding-left:
                        max(
                            16px,
                            env(safe-area-inset-left)
                        ) !important;
                }


                .top-bar {

                    padding-top:
                        max(
                            8px,
                            env(safe-area-inset-top)
                        ) !important;
                }
            }
        }

    </style>

</head>


<body>


<!-- ==================================================
     TOP BAR
=================================================== -->

<div class="top-bar">

    <div class="container">

        <div class="top-bar-info">

            <span>
                <i class="fa-solid fa-phone"></i>
                03-8322 1818
            </span>

            <span>
                <i class="fa-solid fa-location-dot"></i>
                Cyberjaya | Kangar | Arau
            </span>

        </div>


        <div class="top-bar-socials">

            <a
                href="index.php?page=home#contact"
                title="Business inquiries"
            >
                <i class="fa-solid fa-envelope"></i>

                Business Inquiries
            </a>

        </div>

    </div>

</div>



<!-- ==================================================
     NAVBAR
=================================================== -->

<nav class="navbar">

    <div class="container">


        <!-- BRAND -->

        <a
            href="index.php?page=home"
            class="nav-brand"
        >

            <img
                src="assets/images/logo.png"
                alt="TBBA Logo"
                class="nav-logo-img"
            >


            <div class="nav-brand-text">

                <span class="nav-brand-title">
                    TBBA
                </span>

                <span class="nav-brand-subtitle">
                    The Bridge Business Alliance
                </span>

            </div>

        </a>



        <!-- NAVIGATION -->

        <ul
            class="nav-links"
            id="publicNavLinks"
        >

            <li>
                <a
                    href="index.php?page=home#hero"
                    class="nav-link"
                >
                    Home
                </a>
            </li>


            <li>
                <a
                    href="index.php?page=home#about"
                    class="nav-link"
                >
                    About Us
                </a>
            </li>


            <li>
                <a
                    href="index.php?page=home#services"
                    class="nav-link"
                >
                    Our Expertise
                </a>
            </li>


            <li>
                <a
                    href="index.php?page=home#clients"
                    class="nav-link"
                >
                    Track Record
                </a>
            </li>


            <?php if (!empty($publicGalleryItems)): ?>

                <li>

                    <a
                        href="index.php?page=home#gallery"
                        class="nav-link"
                    >
                        Gallery
                    </a>

                </li>

            <?php endif; ?>


            <li>

                <a
                    href="index.php?page=home#contact"
                    class="nav-link"
                >
                    Contact Us
                </a>

            </li>

        </ul>



        <!-- MOBILE MENU BUTTON -->

        <button
            type="button"
            class="nav-toggle"
            aria-label="Open navigation menu"
            aria-controls="publicNavLinks"
            aria-expanded="false"
        >

            <i
                class="fa-solid fa-bars"
                aria-hidden="true"
            ></i>

        </button>

    </div>

</nav>



<!-- ==================================================
     MOBILE NAVIGATION SCRIPT
=================================================== -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const toggle =
            document.querySelector('.nav-toggle');

        const menu =
            document.querySelector('.nav-links');


        if (!toggle || !menu) {
            return;
        }


        function closeMenu() {

            menu.classList.remove('open');

            toggle.setAttribute(
                'aria-expanded',
                'false'
            );

            toggle.setAttribute(
                'aria-label',
                'Open navigation menu'
            );

            const icon =
                toggle.querySelector('i');

            if (icon) {

                icon.className =
                    'fa-solid fa-bars';
            }
        }



        toggle.addEventListener(
            'click',
            function () {

                const isOpen =
                    menu.classList.toggle('open');


                toggle.setAttribute(
                    'aria-expanded',
                    isOpen ? 'true' : 'false'
                );


                toggle.setAttribute(
                    'aria-label',
                    isOpen
                        ? 'Close navigation menu'
                        : 'Open navigation menu'
                );


                const icon =
                    toggle.querySelector('i');


                if (icon) {

                    icon.className =
                        isOpen
                            ? 'fa-solid fa-xmark'
                            : 'fa-solid fa-bars';
                }
            }
        );



        menu
            .querySelectorAll('a')
            .forEach(
                function (link) {

                    link.addEventListener(
                        'click',
                        closeMenu
                    );
                }
            );



        document.addEventListener(
            'click',
            function (event) {

                if (
                    !menu.contains(event.target) &&
                    !toggle.contains(event.target)
                ) {

                    closeMenu();
                }
            }
        );



        document.addEventListener(
            'keydown',
            function (event) {

                if (event.key === 'Escape') {

                    closeMenu();
                }
            }
        );



        window.addEventListener(
            'resize',
            function () {

                if (window.innerWidth > 1100) {

                    closeMenu();
                }
            }
        );

    }
);

</script>
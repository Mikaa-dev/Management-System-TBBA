<?php
// Our Team & Activities video.
// Upload the final video to:
// public_html/assets/videos/team-activities.mp4
$teamVideoFile = dirname(__DIR__, 2) . '/assets/videos/team-activities1.mp4';
$teamVideoUrl = '/assets/videos/team-activities.mp4?v=2';
$teamVideoAvailable = is_file($teamVideoFile);

include __DIR__ . '/layout_header.php';
?>

<!-- 1. HERO SECTION -->

<style>
.hero-slide {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    opacity: 0;
    transition: opacity 1.5s ease-in-out, transform 6.5s ease-out;
    transform: scale(1.06);
}
.hero-slide.active {
    opacity: 1;
    transform: scale(1.0);
}
.slider-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.35);
    cursor: pointer;
    padding: 0;
    border: 0;
    appearance: none;
    transition: all 0.3s ease;
}
.slider-indicator.active {
    width: 32px;
    border-radius: 10px;
    background: #38BDF8;
    box-shadow: 0 0 12px rgba(56, 189, 248, 0.8);
}
.hero-actions {
    display: flex;
    justify-content: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 42px;
}
.hero-cta {
    min-height: 54px;
    padding: 14px 28px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-size: 15px;
    font-weight: 800;
}
.hero-cta-primary {
    background: var(--accent);
    color: var(--primary-dark);
    box-shadow: 0 10px 25px -5px rgba(56, 189, 248, 0.4);
}
.hero-cta-secondary {
    border: 1px solid rgba(255, 255, 255, 0.45);
    background: rgba(15, 23, 42, 0.28);
    color: #FFFFFF;
    backdrop-filter: blur(10px);
}
.hero-cta:hover {
    transform: translateY(-2px);
}
.trust-strip {
    position: relative;
    z-index: 5;
    margin-top: -42px;
}
.trust-strip-inner {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.98);
    box-shadow: 0 20px 44px rgba(15, 23, 42, 0.12);
}
.trust-item {
    display: flex;
    align-items: center;
    gap: 14px;
    min-height: 112px;
    padding: 24px;
    border-right: 1px solid var(--border);
}
.trust-item:last-child {
    border-right: 0;
}
.trust-item .trust-icon {
    display: inline-flex;
    width: 46px;
    height: 46px;
    flex: 0 0 46px;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    background: #EFF6FF;
    color: var(--primary-light);
    font-size: 19px;
}
.trust-item strong,
.trust-item span {
    display: block;
}
.trust-item strong {
    color: var(--primary-dark);
    font-family: 'Outfit', sans-serif;
    font-size: 18px;
}
.trust-item span {
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 600;
}
.about-highlights {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-top: 28px;
}
.about-highlight {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: #F8FAFC;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
}
.about-highlight i {
    color: #2563EB;
}
.company-gallery-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}
.company-gallery-count {
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 700;
}
.company-gallery-controls {
    display: flex;
    gap: 8px;
}
.company-gallery-nav {
    display: inline-flex;
    width: 44px;
    height: 44px;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: #FFFFFF;
    color: var(--primary-dark);
    cursor: pointer;
    box-shadow: var(--shadow-sm);
}
.company-gallery-nav:hover:not(:disabled) {
    border-color: #93C5FD;
    color: var(--primary-light);
}
.company-gallery-nav:disabled {
    cursor: not-allowed;
    opacity: 0.38;
}
.company-gallery-shell {
    position: relative;
    overflow: hidden;
}
.company-gallery-grid {
    display: flex;
    gap: 18px;
    overflow-x: auto;
    padding: 4px 3px 18px;
    scroll-behavior: smooth;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    overscroll-behavior-inline: contain;
}
.company-gallery-grid::-webkit-scrollbar {
    display: none;
}
.company-gallery-card {
    position: relative;
    flex: 0 0 calc((100% - 36px) / 3);
    height: 380px;
    min-width: 0;
    overflow: hidden;
    border: 0;
    border-radius: 20px;
    background: #0F172A;
    cursor: pointer;
    box-shadow: var(--shadow-md);
    text-align: left;
    scroll-snap-align: start;
}
.company-gallery-card img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}
.company-gallery-card:hover img,
.company-gallery-card:focus-visible img {
    transform: scale(1.045);
}
.company-gallery-card:focus-visible {
    outline: 3px solid #38BDF8;
    outline-offset: 3px;
}
.company-gallery-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 24px;
    background: linear-gradient(180deg, transparent 28%, rgba(15, 23, 42, 0.9) 100%);
    color: #FFFFFF;
}
.company-gallery-overlay span {
    margin-bottom: 5px;
    color: #7DD3FC;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}
.company-gallery-overlay strong {
    font-family: 'Outfit', sans-serif;
    font-size: 20px;
}
.gallery-dialog {
    width: min(940px, calc(100vw - 32px));
    max-height: calc(100vh - 32px);
    margin: auto;
    overflow: hidden;
    border: 0;
    border-radius: 22px;
    background: #0F172A;
    color: #FFFFFF;
    box-shadow: 0 30px 80px rgba(15, 23, 42, 0.5);
}
.gallery-dialog::backdrop {
    background: rgba(2, 6, 23, 0.82);
    backdrop-filter: blur(6px);
}
.gallery-dialog img {
    display: block;
    width: 100%;
    max-height: calc(100vh - 150px);
    object-fit: contain;
    background: #020617;
}
.gallery-dialog-caption {
    padding: 18px 22px 22px;
}
.gallery-dialog-caption span {
    display: block;
    color: #7DD3FC;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}
.gallery-dialog-caption strong {
    display: block;
    margin-top: 4px;
    font-family: 'Outfit', sans-serif;
    font-size: 21px;
}
.gallery-dialog-close {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 2;
    width: 42px;
    height: 42px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.78);
    color: #FFFFFF;
    cursor: pointer;
}
@media (max-width: 900px) {
    .trust-strip-inner {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
    .trust-item:nth-child(2) {
        border-right: 0;
    }
    .trust-item:nth-child(-n+2) {
        border-bottom: 1px solid var(--border);
    }
    .company-gallery-card {
        flex-basis: calc((100% - 18px) / 2);
    }
}
@media (max-width: 560px) {
    .hero-actions {
        flex-direction: column;
        align-items: stretch;
    }
    .hero-cta {
        width: 100%;
    }
    .trust-strip {
        margin-top: -26px;
    }
    .trust-strip-inner,
    .about-highlights {
        grid-template-columns: 1fr !important;
    }
    .trust-item {
        min-height: 90px;
        border-right: 0;
        border-bottom: 1px solid var(--border);
    }
    .trust-item:last-child {
        border-bottom: 0;
    }
    .company-gallery-card {
        flex-basis: 88%;
        height: 310px;
    }
    .company-gallery-toolbar {
        align-items: center;
    }
    .company-gallery-nav {
        width: 40px;
        height: 40px;
    }
}

/* Public-sector portfolio */
.public-sector-showcase {
    position: relative;
    overflow: hidden;
    padding: 48px;
    border: 1px solid #BFDBFE;
    border-radius: 28px;
    background:
        radial-gradient(circle at 100% 0%, rgba(37, 99, 235, 0.14), transparent 34%),
        linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
    box-shadow: var(--shadow-md);
}
.public-sector-showcase::before {
    content: '';
    position: absolute;
    top: -95px;
    right: -75px;
    width: 260px;
    height: 260px;
    border: 42px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    pointer-events: none;
}
.public-sector-intro {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 32px;
    margin-bottom: 32px;
}
.public-sector-intro-copy {
    max-width: 750px;
}
.public-sector-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    padding: 7px 16px;
    border-radius: 999px;
    background: #1E3A8A;
    color: #FFFFFF;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.public-sector-intro h3 {
    margin: 0 0 12px;
    color: var(--primary-dark);
    font-size: clamp(26px, 3vw, 36px);
    font-weight: 800;
    line-height: 1.2;
}
.public-sector-intro p {
    margin: 0;
    color: var(--text-muted);
    font-size: 15px;
    line-height: 1.75;
}
.public-sector-count {
    flex: 0 0 auto;
    min-width: 145px;
    padding: 18px 22px;
    border: 1px solid rgba(255, 255, 255, 0.82);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.68);
    text-align: center;
    backdrop-filter: blur(8px);
}
.public-sector-count strong {
    display: block;
    color: #1E3A8A;
    font-size: 30px;
    line-height: 1;
}
.public-sector-count span {
    display: block;
    margin-top: 7px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.7px;
    text-transform: uppercase;
}
.public-sector-grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}
.public-sector-card {
    display: flex;
    min-height: 100%;
    flex-direction: column;
    padding: 28px;
    border: 1px solid rgba(191, 219, 254, 0.9);
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 12px 30px rgba(30, 58, 138, 0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
}
.public-sector-card:hover {
    transform: translateY(-6px);
    border-color: #93C5FD;
    box-shadow: 0 20px 38px rgba(30, 58, 138, 0.14);
}
.public-sector-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 22px;
}
.public-sector-icon {
    display: inline-flex;
    width: 52px;
    height: 52px;
    flex: 0 0 52px;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    background: #EFF6FF;
    color: #1E3A8A;
    font-size: 23px;
}
.public-sector-card.mindef .public-sector-icon {
    background: #FFF7ED;
    color: #C2410C;
}
.public-sector-card.felda .public-sector-icon {
    background: #ECFDF5;
    color: #047857;
}
.public-sector-type {
    padding: 5px 10px;
    border-radius: 999px;
    background: #F1F5F9;
    color: #475569;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.65px;
    text-transform: uppercase;
}
.public-sector-card h4 {
    margin: 0 0 5px;
    color: var(--primary-dark);
    font-size: 23px;
    font-weight: 800;
    line-height: 1.25;
}
.public-sector-name {
    min-height: 42px;
    margin-bottom: 17px;
    color: #64748B;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.55;
}
.public-sector-card p {
    margin: 0 0 22px;
    color: var(--text-muted);
    font-size: 14px;
    line-height: 1.7;
}
.public-sector-focus {
    margin-top: auto;
    padding-top: 18px;
    border-top: 1px solid #E2E8F0;
}
.public-sector-focus-label {
    display: block;
    margin-bottom: 10px;
    color: #94A3B8;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}
.public-sector-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}
.public-sector-tags span {
    padding: 5px 9px;
    border: 1px solid #DBEAFE;
    border-radius: 8px;
    background: #F8FAFC;
    color: #334155;
    font-size: 11px;
    font-weight: 700;
}
.public-sector-assurance {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 24px;
    padding-top: 22px;
    border-top: 1px solid rgba(147, 197, 253, 0.55);
    color: #475569;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
}
.public-sector-assurance i {
    color: #2563EB;
}
@media (max-width: 992px) {
    .public-sector-grid {
        grid-template-columns: 1fr 1fr;
    }
    .public-sector-card:first-child {
        grid-column: span 2;
    }
    .public-sector-name {
        min-height: 0;
    }
}
@media (max-width: 640px) {
    .public-sector-showcase {
        padding: 26px 18px;
        border-radius: 22px;
    }
    .public-sector-intro {
        align-items: stretch;
        flex-direction: column;
        gap: 20px;
    }
    .public-sector-count {
        align-self: flex-start;
    }
    .public-sector-grid {
        grid-template-columns: 1fr;
    }
    .public-sector-card:first-child {
        grid-column: auto;
    }
    .public-sector-card {
        padding: 23px;
    }
    .public-sector-assurance {
        align-items: flex-start;
        text-align: left;
    }
}
</style>

<section id="hero" style="position: relative; color: #FFFFFF; padding: 130px 0 140px 0; overflow: hidden; min-height: 82vh; display: flex; align-items: center; justify-content: center;">
    <!-- Background Slides (Fortune 500 Corporate Themes) -->
    <div id="heroSliderContainer" style="position: absolute; inset: 0; z-index: 0;">
        <!-- Slide 1: Modern Corporate Skyscraper HQ -->
        <div class="hero-slide active" style="background-image: url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1920&q=80');"></div>
        <!-- Slide 2: Enterprise IT Infrastructure & Datacenter -->
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1920&q=80');"></div>
        <!-- Slide 3: Executive Boardroom & Corporate Governance -->
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1920&q=80');"></div>
        <!-- Slide 4: Global Supply Chain & Industrial Operations -->
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1920&q=80');"></div>
    </div>

<!-- Enterprise Dark Overlay for Crisp Text Contrast -->
<div style="position: absolute; inset: 0; background: linear-gradient(135deg, rgba(15,23,42,0.88) 0%, rgba(30,58,138,0.75) 50%, rgba(15,23,42,0.92) 100%); z-index: 1;"></div>

<!-- Background Subtle Glows -->
<div style="position: absolute; top: -10%; left: -5%; width: 450px; height: 450px; background: radial-gradient(circle, rgba(56,189,248,0.18) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: 1;"></div>
<div style="position: absolute; bottom: -15%; right: -5%; width: 550px; height: 550px; background: radial-gradient(circle, rgba(37,99,235,0.25) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: 1;"></div>

<!-- Hero Content -->
<div class="container animate-fade" style="position: relative; z-index: 2;">
    <div style="max-width: 860px; margin: 0 auto; text-align: center;">
        <div style="display: inline-flex; align-items: center; gap: 10px; background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.2); padding: 8px 22px; border-radius: 50px; margin-bottom: 28px; font-size: 13px; font-weight: 700; color: #38BDF8; backdrop-filter: blur(12px); letter-spacing: 0.5px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
            <i class="fa-solid fa-certificate" style="color: #60A5FA;"></i>Established April 2019 • Corporate HQ Cyberjaya
        </div>
        
        <h1 style="font-size: clamp(38px, 5.5vw, 64px); font-weight: 800; color: #FFFFFF; line-height: 1.15; margin-bottom: 24px; letter-spacing: -1.5px; text-shadow: 0 4px 12px rgba(0,0,0,0.4);">
            <span style="display: block; font-size: clamp(16px, 2vw, 22px); font-weight: 600; color: #94A3B8; letter-spacing: 0.5px; margin-bottom: 12px; text-shadow: none;">The Bridge Business Alliance Sdn. Bhd.</span>
            Bridging Businesses.<br>
            <span style="background: linear-gradient(to right, #38BDF8, #60A5FA, #FFFFFF); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Building the Future.</span>
        </h1>
        
        <p style="font-size: clamp(16px, 2vw, 20px); color: #E2E8F0; line-height: 1.7; margin-bottom: 44px; max-width: 760px; margin-left: auto; margin-right: auto; font-weight: 400; text-shadow: 0 2px 8px rgba(0,0,0,0.5);">
            <strong>The Bridge Business Alliance Sdn. Bhd.</strong> is a premier corporate entity leading cross-industry solutions in ICT & Computer Supply, Industrial Machinery, and Agriculture & Trading.
        </p>
        
        <div class="hero-actions">
            <a href="#services" class="hero-cta hero-cta-primary">
                <span>Our Expertise</span>
                <i class="fa-solid fa-arrow-down"></i>
            </a>
            <a href="#contact" class="hero-cta hero-cta-secondary">
                <span>Request a Quotation</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <!-- Slider Indicator Dots -->
        <div style="display: flex; gap: 12px; justify-content: center; align-items: center;">
            <button type="button" class="slider-indicator active" data-hero-slide="0" aria-label="Show corporate headquarters image" aria-current="true"></button>
            <button type="button" class="slider-indicator" data-hero-slide="1" aria-label="Show ICT infrastructure image" aria-current="false"></button>
            <button type="button" class="slider-indicator" data-hero-slide="2" aria-label="Show corporate governance image" aria-current="false"></button>
            <button type="button" class="slider-indicator" data-hero-slide="3" aria-label="Show supply chain image" aria-current="false"></button>
        </div>
    </div>
</div>

</section>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const hero = document.getElementById('hero');
    const slides = Array.from(document.querySelectorAll('.hero-slide'));
    const dots = Array.from(document.querySelectorAll('[data-hero-slide]'));
    if (!slides.length) return;

    let currentSlide = 0;
    let slideTimer = null;

    function showHeroSlide(index) {
        slides[currentSlide].classList.remove('active');
        if (dots[currentSlide]) {
            dots[currentSlide].classList.remove('active');
            dots[currentSlide].setAttribute('aria-current', 'false');
        }

        currentSlide = (index + slides.length) % slides.length;
        slides[currentSlide].classList.add('active');
        if (dots[currentSlide]) {
            dots[currentSlide].classList.add('active');
            dots[currentSlide].setAttribute('aria-current', 'true');
        }
    }

    function scheduleNextSlide() {
        window.clearTimeout(slideTimer);
        slideTimer = window.setTimeout(function advanceHero() {
            showHeroSlide(currentSlide + 1);
            slideTimer = window.setTimeout(advanceHero, 4500);
        }, 4500);
    }

    dots.forEach(function(dot) {
        dot.addEventListener('click', function() {
            showHeroSlide(Number(dot.dataset.heroSlide));
            scheduleNextSlide();
        });
    });

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            window.clearTimeout(slideTimer);
        } else {
            scheduleNextSlide();
        }
    });

    slides.slice(1).forEach(function(slide) {
        const match = slide.style.backgroundImage.match(/url\(["']?(.*?)["']?\)/);
        if (match && match[1]) {
            const image = new Image();
            image.src = match[1];
        }
    });

    if (hero) hero.dataset.sliderReady = 'true';
    scheduleNextSlide();
});
</script>

<div class="trust-strip" aria-label="Company highlights">
    <div class="container">
        <div class="trust-strip-inner">
            <div class="trust-item">
                <span class="trust-icon"><i class="fa-solid fa-calendar-check"></i></span>
                <div><strong>Since 2019</strong><span>Established corporate experience</span></div>
            </div>
            <div class="trust-item">
                <span class="trust-icon"><i class="fa-solid fa-building"></i></span>
                <div><strong>4 Locations</strong><span>Cyberjaya, Kangar and Arau</span></div>
            </div>
            <div class="trust-item">
                <span class="trust-icon"><i class="fa-solid fa-layer-group"></i></span>
                <div><strong>3 Core Divisions</strong><span>Integrated business solutions</span></div>
            </div>
            <div class="trust-item">
                <span class="trust-icon"><i class="fa-solid fa-handshake"></i></span>
                <div><strong>Cross-Sector</strong><span>Public and private sector support</span></div>
            </div>
        </div>
    </div>
</div>

<!-- 2. ABOUT US -->

<section id="about" style="background: #FFFFFF; padding: 100px 0; border-bottom: 1px solid var(--border);">
    <div class="container">
        <div class="about-layout" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 60px; align-items: center;">

        <div>
            <span class="section-tag">About Company</span>
            <h2 class="section-title" style="margin-bottom: 24px;">The Bridge Business Alliance Sdn. Bhd.</h2>
            <p style="font-size: 16px; color: var(--text-muted); line-height: 1.8; margin-bottom: 20px;">
                Officially established in <strong>April 2019</strong>, The Bridge Business Alliance Sdn. Bhd. has grown rapidly into one of the most trusted corporate supply and service providers.
            </p>
            <p style="font-size: 16px; color: var(--text-muted); line-height: 1.8; margin-bottom: 0;">
                With a mission to deliver high-performance solutions across key industries, our operations are spearheaded from our corporate headquarters and office in <strong>Cyberjaya, Selangor</strong>, and supported by our retail branch network in <strong>Kangar and Arau, Perlis</strong>.
            </p>
            
            <div class="about-highlights" aria-label="TBBA service strengths">
                <div class="about-highlight"><i class="fa-solid fa-diagram-project"></i> Coordinated end-to-end delivery</div>
                <div class="about-highlight"><i class="fa-solid fa-shield-halved"></i> Public-sector ready operations</div>
                <div class="about-highlight"><i class="fa-solid fa-boxes-stacked"></i> Multi-industry sourcing capability</div>
                <div class="about-highlight"><i class="fa-solid fa-headset"></i> Deployment and after-sales support</div>
            </div>
        </div>

        <!-- Mission & SSM Card -->
        <div style="background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); color: #FFFFFF; border-radius: 24px; padding: 44px; position: relative; box-shadow: var(--shadow-lg); overflow: hidden;">
            <div style="position: absolute; top: 0; right: 0; width: 250px; height: 250px; background: radial-gradient(circle, rgba(56,189,248,0.15) 0%, rgba(0,0,0,0) 70%); border-radius: 50%;"></div>
            
            <div style="position: relative; z-index: 2;">
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.1); color: var(--accent-gold); font-size: 12px; font-weight: 700; padding: 4px 14px; border-radius: 50px; margin-bottom: 24px;">
                    <i class="fa-solid fa-star"></i> Proven Track Record
                </div>

                <h3 style="font-size: 26px; font-weight: 800; margin-bottom: 16px; color: #FFFFFF;">Cross-Industry Mission</h3>
                <p style="font-size: 15px; color: #CBD5E1; line-height: 1.8; margin-bottom: 32px;">
                    We are deeply committed to bridging business, public sector, and commercial needs through the supply of advanced ICT technology, high-quality industrial machinery, and sustainable agricultural trade chains.
                </p>

                <div style="border-top: 1px solid rgba(255,255,255,0.15); padding-top: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <span style="display: block; font-size: 12px; color: #94A3B8; text-transform: uppercase;">Cyberjaya Office Line</span>
                        <strong style="font-size: 20px; color: #FFFFFF;">03-8322 1818</strong>
                    </div>
                    <a href="#services" style="background: var(--accent); color: var(--primary-dark); font-weight: 700; font-size: 14px; padding: 12px 24px; border-radius: 50px;">
                        <span>Explore Services &rarr;</span>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

</section>

<!-- 3. OUR SERVICES (3 Core Business Pillars) -->

<section id="services" class="section-padding" style="position: relative; overflow: hidden; min-height: 80vh; display: flex; align-items: center; padding: 100px 0;">
    <div aria-hidden="true" style="position: absolute; inset: 0; z-index: 0; background: radial-gradient(circle at 85% 15%, rgba(56,189,248,0.22), transparent 28%), radial-gradient(circle at 8% 88%, rgba(37,99,235,0.28), transparent 30%), linear-gradient(135deg, #0F172A 0%, #172554 52%, #0F3B4A 100%);"></div>

<!-- Enterprise Dark Navy Overlay -->
<div style="position: absolute; inset: 0; background: linear-gradient(135deg, rgba(15,23,42,0.88) 0%, rgba(30,58,138,0.80) 50%, rgba(15,23,42,0.92) 100%); z-index: 1;"></div>

<!-- Background Subtle Glows -->
<div style="position: absolute; top: -10%; right: -5%; width: 500px; height: 500px; background: radial-gradient(circle, rgba(56,189,248,0.18) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: 1;"></div>
<div style="position: absolute; bottom: -10%; left: -5%; width: 500px; height: 500px; background: radial-gradient(circle, rgba(37,99,235,0.25) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: 1;"></div>

<div class="container" style="position: relative; z-index: 2; width: 100%;">
    <div class="section-header" style="margin-bottom: 50px;">
        <span style="display: inline-block; background: rgba(56,189,248,0.15); color: #38BDF8; border: 1px solid rgba(56,189,248,0.3); padding: 6px 18px; border-radius: 50px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 16px; backdrop-filter: blur(10px);">Core Expertise</span>
        <h2 style="font-size: clamp(32px, 4vw, 44px); font-weight: 800; color: #FFFFFF; margin-bottom: 16px; letter-spacing: -1px; text-shadow: 0 4px 12px rgba(0,0,0,0.4);">3 Core Business Pillars</h2>
        <p style="color: #E2E8F0; font-size: 17px; max-width: 680px; margin: 0 auto; line-height: 1.7; text-shadow: 0 2px 8px rgba(0,0,0,0.5);">
            We offer comprehensive, reliable, and high-quality solutions driven under three specialized business divisions.
        </p>
    </div>

    <div class="services-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 36px;">
        
        <!-- Pillar 1: ICT & Computing -->
        <div style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.4); border-radius: 20px; padding: 44px 36px; box-shadow: 0 15px 35px rgba(0,0,0,0.25); transition: var(--transition); display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden;" onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 25px 45px rgba(59,130,246,0.35)'; this.style.borderColor='#3B82F6';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.25)'; this.style.borderColor='rgba(255, 255, 255, 0.4)';">
            <div style="position: absolute; top: 0; right: 0; width: 120px; height: 120px; background: radial-gradient(circle, rgba(59,130,246,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 0 0 0 100%;"></div>
            <div>
                <div style="width: 72px; height: 72px; border-radius: 18px; background: #EFF6FF; color: var(--primary-light); display: flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 28px; box-shadow: 0 8px 16px rgba(37,99,235,0.15);">
                    <i class="fa-solid fa-laptop-code"></i>
                </div>
                <span style="font-size: 12px; font-weight: 800; color: var(--primary-light); text-transform: uppercase; letter-spacing: 1px;">Pillar 01</span>
                <h3 style="font-size: 24px; font-weight: 800; margin: 8px 0 16px 0; color: var(--primary-dark);">ICT & Computing Solutions</h3>
                <p style="color: var(--text-muted); font-size: 15px; line-height: 1.7; margin-bottom: 28px;">
                    Supplying computer hardware, software, server systems, printing devices, and IT equipment on a retail and bulk basis for government and private sectors.
                </p>
            </div>
            <div style="border-top: 1px solid var(--border); padding-top: 20px; display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 13px; font-weight: 600; color: var(--primary-dark);"><i class="fa-solid fa-check-double" style="color: #10B981; margin-right: 6px;"></i> Government Bulk & Retail</span>
                <a href="#contact" style="color: var(--primary-light); font-weight: 700; font-size: 14px;">Inquire &rarr;</a>
            </div>
        </div>

        <!-- Pillar 2: Industrial Machinery -->
        <div style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.4); border-radius: 20px; padding: 44px 36px; box-shadow: 0 15px 35px rgba(0,0,0,0.25); transition: var(--transition); display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden;" onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 25px 45px rgba(16,185,129,0.35)'; this.style.borderColor='#10B981';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.25)'; this.style.borderColor='rgba(255, 255, 255, 0.4)';">
            <div style="position: absolute; top: 0; right: 0; width: 120px; height: 120px; background: radial-gradient(circle, rgba(16,185,129,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 0 0 0 100%;"></div>
            <div>
                <div style="width: 72px; height: 72px; border-radius: 18px; background: #ECFDF5; color: #10B981; display: flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 28px; box-shadow: 0 8px 16px rgba(16,185,129,0.15);">
                    <i class="fa-solid fa-industry"></i>
                </div>
                <span style="font-size: 12px; font-weight: 800; color: #10B981; text-transform: uppercase; letter-spacing: 1px;">Pillar 02</span>
                <h3 style="font-size: 24px; font-weight: 800; margin: 8px 0 16px 0; color: var(--primary-dark);">Industrial Machinery</h3>
                <p style="color: var(--text-muted); font-size: 15px; line-height: 1.7; margin-bottom: 28px;">
                    Selling and supplying industrial machinery ranging from light to heavy-duty equipment, including industrial sewing machines and related mechanical components.
                </p>
            </div>
            <div style="border-top: 1px solid var(--border); padding-top: 20px; display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 13px; font-weight: 600; color: var(--primary-dark);"><i class="fa-solid fa-check-double" style="color: #10B981; margin-right: 6px;"></i> Sewing Machines & Parts</span>
                <a href="#contact" style="color: #10B981; font-weight: 700; font-size: 14px;">Inquire &rarr;</a>
            </div>
        </div>

        <!-- Pillar 3: Agriculture & Trading -->
        <div style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.4); border-radius: 20px; padding: 44px 36px; box-shadow: 0 15px 35px rgba(0,0,0,0.25); transition: var(--transition); display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden;" onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 25px 45px rgba(245,158,11,0.35)'; this.style.borderColor='#F59E0B';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.25)'; this.style.borderColor='rgba(255, 255, 255, 0.4)';">
            <div style="position: absolute; top: 0; right: 0; width: 120px; height: 120px; background: radial-gradient(circle, rgba(245,158,11,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 0 0 0 100%;"></div>
            <div>
                <div style="width: 72px; height: 72px; border-radius: 18px; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 28px; box-shadow: 0 8px 16px rgba(217,119,6,0.15);">
                    <i class="fa-solid fa-wheat-awn"></i>
                </div>
                <span style="font-size: 12px; font-weight: 800; color: #D97706; text-transform: uppercase; letter-spacing: 1px;">Pillar 03</span>
                <h3 style="font-size: 24px; font-weight: 800; margin: 8px 0 16px 0; color: var(--primary-dark);">Agriculture & Trading</h3>
                <p style="color: var(--text-muted); font-size: 15px; line-height: 1.7; margin-bottom: 28px;">
                    Actively involved in agribusiness activities, supply of modern agricultural inputs, and cross-border import and export trading networks.
                </p>
            </div>
            <div style="border-top: 1px solid var(--border); padding-top: 20px; display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 13px; font-weight: 600; color: var(--primary-dark);"><i class="fa-solid fa-check-double" style="color: #10B981; margin-right: 6px;"></i> Agribusiness, Import & Export</span>
                <a href="#contact" style="color: #D97706; font-weight: 700; font-size: 14px;">Inquire &rarr;</a>
            </div>
        </div>

    </div>
</div>

</section>

<!-- 4. OUR CLIENTS & TRACK RECORD -->

<section id="clients" class="section-padding" style="background: #FFFFFF; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Track Record & Clients</span>
            <h2 class="section-title">Trusted by Government Sectors</h2>
            <p class="section-subtitle">
                Public and government institution confidence stands as solid proof of our commitment to delivery quality and project punctuality.
            </p>
        </div>

    <div class="public-sector-showcase">
        <div class="public-sector-intro">
            <div class="public-sector-intro-copy">
                <div class="public-sector-badge">
                    <i class="fa-solid fa-shield-halved"></i> Public Sector Portfolio
                </div>
                <h3>Supporting Malaysia's Essential Institutions</h3>
                <p>
                    Our public-sector capabilities bring together ICT hardware, operational equipment, industrial machinery and dependable supply coordination for institutional needs.
                </p>
            </div>
            <div class="public-sector-count" aria-label="Three highlighted public sector institutions">
                <strong>3</strong>
                <span>Institutions Highlighted</span>
            </div>
        </div>

        <div class="public-sector-grid">
            <article class="public-sector-card moe">
                <div class="public-sector-card-top">
                    <div class="public-sector-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                    <span class="public-sector-type">Education</span>
                </div>
                <h4>MOE</h4>
                <div class="public-sector-name">Ministry of Education Malaysia</div>
                <p>
                    Authorized distribution and supply of Brother printing equipment for the school ICT hardware rental programme across Northern Region government schools.
                </p>
                <div class="public-sector-focus">
                    <span class="public-sector-focus-label">Project Focus</span>
                    <div class="public-sector-tags">
                        <span>ICT Hardware Rental</span>
                        <span>Brother Printing</span>
                        <span>Northern Region</span>
                    </div>
                </div>
            </article>

            <article class="public-sector-card mindef">
                <div class="public-sector-card-top">
                    <div class="public-sector-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <span class="public-sector-type">Defence</span>
                </div>
                <h4>MINDEF</h4>
                <div class="public-sector-name">Ministry of Defence Malaysia</div>
                <p>
                    Supporting institutional requirements through TBBA's core capabilities in computer hardware, peripherals, printing solutions and operational equipment supply.
                </p>
                <div class="public-sector-focus">
                    <span class="public-sector-focus-label">Capability Focus</span>
                    <div class="public-sector-tags">
                        <span>ICT Equipment</span>
                        <span>Computer Peripherals</span>
                        <span>Operational Supply</span>
                    </div>
                </div>
            </article>

            <article class="public-sector-card felda">
                <div class="public-sector-card-top">
                    <div class="public-sector-icon"><i class="fa-solid fa-seedling"></i></div>
                    <span class="public-sector-type">Land Development</span>
                </div>
                <h4>FELDA</h4>
                <div class="public-sector-name">Federal Land Development Authority</div>
                <p>
                    Supporting organisational and field operations through TBBA's registered capabilities in ICT equipment, industrial machinery and agriculture-related supply solutions.
                </p>
                <div class="public-sector-focus">
                    <span class="public-sector-focus-label">Capability Focus</span>
                    <div class="public-sector-tags">
                        <span>ICT Supply</span>
                        <span>Industrial Machinery</span>
                        <span>Agro Support</span>
                    </div>
                </div>
            </article>
        </div>

        <div class="public-sector-assurance">
            <i class="fa-solid fa-circle-check"></i>
            <span>Capability-led delivery backed by coordinated procurement, deployment and after-sales support.</span>
        </div>
    </div>

</section>

<!-- 4.1. OUR TEAM & ACTIVITIES VIDEO -->

<section id="gallery" class="section-padding team-video-section">
    <style>
        .team-video-section {
            position: relative;
            overflow: hidden;
            padding: 100px 0;
            border-bottom: 1px solid var(--border);
            background:
                radial-gradient(circle at 12% 20%, rgba(37, 99, 235, 0.08), transparent 28%),
                radial-gradient(circle at 88% 80%, rgba(56, 189, 248, 0.08), transparent 28%),
                linear-gradient(180deg, #F8FAFC 0%, #FFFFFF 100%);
        }

    .team-video-shell {
        position: relative;
        max-width: 1120px;
        margin: 0 auto;
        overflow: hidden;
        border: 1px solid #DCE6F2;
        border-radius: 28px;
        background: #07111F;
        box-shadow: 0 30px 70px rgba(15, 23, 42, 0.18);
    }

    .team-video-frame {
        position: relative;
        width: 100%;
        aspect-ratio: 16 / 9;
        overflow: hidden;
        background:
            radial-gradient(circle at 30% 30%, rgba(59, 130, 246, 0.28), transparent 35%),
            linear-gradient(135deg, #0F172A 0%, #172554 55%, #0C4A6E 100%);
    }

    .team-video-frame video {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
        background: #020617;
    }

    .team-video-placeholder {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px;
        color: #FFFFFF;
        text-align: center;
    }

    .team-video-placeholder-inner {
        max-width: 640px;
    }

    .team-video-placeholder-icon {
        display: inline-flex;
        width: 82px;
        height: 82px;
        align-items: center;
        justify-content: center;
        margin-bottom: 22px;
        border: 1px solid rgba(255,255,255,0.22);
        border-radius: 50%;
        background: rgba(255,255,255,0.10);
        color: #7DD3FC;
        font-size: 30px;
        backdrop-filter: blur(10px);
        box-shadow: 0 16px 38px rgba(2,6,23,0.24);
    }

    .team-video-placeholder h3 {
        margin: 0 0 10px;
        color: #FFFFFF;
        font-family: 'Outfit', sans-serif;
        font-size: clamp(24px, 3vw, 34px);
        font-weight: 800;
    }

    .team-video-placeholder p {
        margin: 0;
        color: #CBD5E1;
        font-size: 14px;
        line-height: 1.75;
    }

    .team-video-info {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        padding: 24px 28px;
        border-top: 1px solid rgba(255,255,255,0.08);
        background: linear-gradient(135deg, #0F172A 0%, #111F3D 100%);
    }

    .team-video-info-copy strong,
    .team-video-info-copy span {
        display: block;
    }

    .team-video-info-copy strong {
        color: #FFFFFF;
        font-family: 'Outfit', sans-serif;
        font-size: 18px;
        font-weight: 800;
    }

    .team-video-info-copy span {
        margin-top: 4px;
        color: #94A3B8;
        font-size: 12px;
        line-height: 1.5;
    }

    .team-video-badge {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        gap: 8px;
        padding: 8px 13px;
        border: 1px solid rgba(56, 189, 248, 0.25);
        border-radius: 999px;
        background: rgba(56, 189, 248, 0.10);
        color: #7DD3FC;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.6px;
        text-transform: uppercase;
    }

    @media (max-width: 700px) {
        .team-video-section {
            padding: 70px 0;
        }

        .team-video-shell {
            border-radius: 20px;
        }

        .team-video-info {
            align-items: flex-start;
            flex-direction: column;
            padding: 20px;
        }

        .team-video-placeholder {
            padding: 24px;
        }

        .team-video-placeholder-icon {
            width: 66px;
            height: 66px;
            font-size: 24px;
        }
    }
</style>

  <section id="activities-video" class="section-padding" style="background: #F8FAFC; border-bottom: 1px solid var(--border);">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Inside TBBA</span>
            <h2 class="section-title">Our Team & Activities</h2>
            <p class="section-subtitle">A closer look at the people, collaborations, project deliveries and milestones behind TBBA.</p>
        </div>
    <div class="container">
        <div class="team-video-shell">
            <div class="team-video-frame">
                <?php if ($teamVideoAvailable): ?>
                    <video
                        id="teamActivitiesVideo"
                        controls
                        playsinline
                        preload="metadata"
                        aria-label="TBBA Our Team and Activities video"
                    >
                        <source src="<?= htmlspecialchars($teamVideoUrl, ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                        Your browser does not support HTML5 video.
                    </video>
                <?php else: ?>
                    <div class="team-video-placeholder">
                        <div class="team-video-placeholder-inner">
                            <div class="team-video-placeholder-icon">
                                <i class="fa-solid fa-play"></i>
                            </div>
                            <h3>Our Team & Activities Video</h3>
                            <p>
                                Video Coming Soon.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- 4.5. STRATEGIC TECHNOLOGY & BUSINESS PARTNERS -->
<style>
/* =========================================================
   BUSINESS PARTNERS SECTION
   Desktop : 3 cards per row
   Tablet  : 2 cards per row
   Mobile  : 1 card per row
========================================================= */

#partners {
    position: relative;
    overflow: hidden;
}

/* =========================
   PARTNERS GRID
========================= */
#partners .partners-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
    max-width: 1120px;
    margin: 0 auto;
}

/* =========================
   PARTNER CARD
========================= */
#partners .partner-card {
    min-width: 0;
    min-height: 218px;

    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;

    padding: 26px 20px 22px;

    border: 1px solid #E2E8F0;
    border-radius: 18px;

    background: #FFFFFF;

    box-shadow:
        0 8px 24px rgba(15, 23, 42, 0.06);

    text-align: center;

    transition:
        transform 0.28s ease,
        box-shadow 0.28s ease,
        border-color 0.28s ease;
}

/* Hover */
#partners .partner-card:hover {
    transform: translateY(-5px);

    border-color: #BFDBFE;

    box-shadow:
        0 16px 34px rgba(30, 64, 175, 0.11);
}

/* =========================
   LOGO CONTAINER
========================= */
#partners .partner-logo-box {
    width: 100%;
    height: 86px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 16px;

    padding: 8px 16px;

    border-radius: 12px;

    background: #FFFFFF;
}

/* =========================
   LOGO IMAGE
========================= */
#partners .partner-logo-box img {
    display: block;

    width: auto;
    height: auto;

    max-width: 150px;
    max-height: 62px;

    object-fit: contain;
}

/* Brother */
#partners .partner-card:nth-child(1) .partner-logo-box img {
    max-width: 135px;
    max-height: 64px;
}

/* Dell */
#partners .partner-card:nth-child(2) .partner-logo-box img {
    max-width: 120px;
    max-height: 62px;
}

/* Epson */
#partners .partner-card:nth-child(3) .partner-logo-box img {
    max-width: 145px;
    max-height: 58px;
}

/* Konica Minolta */
#partners .partner-card:nth-child(4) .partner-logo-box img {
    max-width: 145px;
    max-height: 58px;
}

/* Sharp */
#partners .partner-card:nth-child(5) .partner-logo-box img {
    max-width: 145px;
    max-height: 55px;
}

/* H3C */
#partners .partner-card:nth-child(6) .partner-logo-box img {
    max-width: 135px;
    max-height: 58px;
}


/* =========================
   FALLBACK LOGO
   Akan muncul kalau SVG gagal load
========================= */
#partners .partner-logo-fallback {
    display: none;

    width: 100%;
    height: 100%;

    align-items: center;
    justify-content: center;

    color: #0F172A;

    font-family: 'Outfit', sans-serif;
    font-size: 18px;
    font-weight: 800;

    letter-spacing: 0.3px;
}


/* =========================
   PARTNER NAME
========================= */
#partners .partner-name {
    min-height: 22px;

    margin-bottom: 10px;

    color: #0F172A;

    font-size: 15px;
    font-weight: 800;

    letter-spacing: -0.2px;

    line-height: 1.4;
}


/* =========================
   PARTNER CATEGORY / FOCUS
========================= */
#partners .partner-focus {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    min-height: 28px;

    margin-top: auto;

    padding: 6px 12px;

    border: 1px solid #DBEAFE;
    border-radius: 999px;

    background: #F8FAFC;

    color: #334155;

    font-size: 11px;
    font-weight: 700;

    line-height: 1.25;
}


/* =========================
   FOOTNOTE
========================= */
#partners .partners-footnote {
    display: flex;

    align-items: center;
    justify-content: center;

    gap: 9px;

    max-width: 760px;

    margin: 28px auto 0;

    color: #64748B;

    font-size: 12px;
    font-weight: 600;

    line-height: 1.6;

    text-align: center;
}

#partners .partners-footnote i {
    color: #2563EB;
}


/* =========================================================
   TABLET
========================================================= */
@media (max-width: 900px) {

    #partners .partners-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));

        max-width: 760px;
    }

    #partners .partner-card {
        width: 100%;
    }
}


/* =========================================================
   MOBILE
========================================================= */
@media (max-width: 560px) {

    #partners .partners-grid {
        grid-template-columns: 1fr;

        gap: 16px;
    }

    #partners .partner-card {
        width: 100%;

        min-height: 200px;

        padding: 24px 18px 20px;
    }

    #partners .partner-logo-box {
        height: 78px;

        margin-bottom: 14px;
    }

    #partners .partner-logo-box img {
        max-width: 135px;
        max-height: 56px;
    }

    #partners .partners-footnote {
        align-items: flex-start;

        padding: 0 10px;

        text-align: left;
    }
}
</style>


<section
    id="partners"
    class="section-padding"
    style="
        background: linear-gradient(
            180deg,
            #FFFFFF 0%,
            #F8FAFC 100%
        );

        border-top: 1px solid var(--border);
        border-bottom: 1px solid var(--border);

        overflow: hidden;
        position: relative;
    "
>

    <!-- BACKGROUND GLOW -->
    <div
        aria-hidden="true"
        style="
            position: absolute;

            top: 50%;
            left: 50%;

            transform: translate(-50%, -50%);

            width: 80%;
            height: 80%;

            background:
                radial-gradient(
                    circle,
                    rgba(37, 99, 235, 0.045) 0%,
                    rgba(0, 0, 0, 0) 70%
                );

            pointer-events: none;
        "
    ></div>


    <div
        class="container"
        style="
            position: relative;
            z-index: 2;
        "
    >

        <!-- =========================
             SECTION HEADER
        ========================== -->
        <div
            class="section-header"
            style="margin-bottom: 42px;"
        >

            <span
                class="section-tag"
                style="
                    background: #EFF6FF;
                    color: var(--primary-light);
                    border: 1px solid #BFDBFE;
                "
            >
                Business Partners
            </span>


            <h2 class="section-title">
                Strategic Technology Alliance
            </h2>


            <p
                class="section-subtitle"
                style="max-width: 760px;"
            >
                We collaborate with leading technology manufacturers
                and hardware brands to deliver dependable,
                enterprise-ready ICT solutions.
            </p>

        </div>


        <!-- =========================
             PARTNERS GRID
        ========================== -->
        <div class="partners-grid">


            <!-- =====================
                 1. BROTHER
            ====================== -->
            <article class="partner-card">

                <div class="partner-logo-box">

                    <img
                        src="/assets/images/partner-logos/bro.svg"
                        alt="Brother"
                        loading="lazy"
                        decoding="async"
                        onerror="
                            this.style.display='none';
                            this.nextElementSibling.style.display='flex';
                        "
                    >

                    <div class="partner-logo-fallback">
                        BROTHER
                    </div>

                </div>


                <div class="partner-name">
                    Brother at your side
                </div>


                <span class="partner-focus">
                    Commercial Printing Systems
                </span>

            </article>



            <!-- =====================
                 2. DELL
            ====================== -->
            <article class="partner-card">

                <div class="partner-logo-box">

                    <img
                        src="/assets/images/partner-logos/dell.svg"
                        alt="Dell Technologies"
                        loading="lazy"
                        decoding="async"
                        onerror="
                            this.style.display='none';
                            this.nextElementSibling.style.display='flex';
                        "
                    >

                    <div class="partner-logo-fallback">
                        DELL
                    </div>

                </div>


                <div class="partner-name">
                    Dell Technologies
                </div>


                <span class="partner-focus">
                    Enterprise Servers &amp; PCs
                </span>

            </article>



            <!-- =====================
                 3. EPSON
            ====================== -->
            <article class="partner-card">

                <div class="partner-logo-box">

                    <img
                        src="/assets/images/partner-logos/epson.svg"
                        alt="Epson"
                        loading="lazy"
                        decoding="async"
                        onerror="
                            this.style.display='none';
                            this.nextElementSibling.style.display='flex';
                        "
                    >

                    <div class="partner-logo-fallback">
                        EPSON
                    </div>

                </div>


                <div class="partner-name">
                    Epson
                </div>


                <span class="partner-focus">
                    Commercial Printing Systems
                </span>

            </article>



            <!-- =====================
                 4. KONICA MINOLTA
            ====================== -->
            <article class="partner-card">

                <div class="partner-logo-box">

                    <img
                        src="/assets/images/partner-logos/konica-minolta.svg"
                        alt="Konica Minolta"
                        loading="lazy"
                        decoding="async"
                        onerror="
                            this.style.display='none';
                            this.nextElementSibling.style.display='flex';
                        "
                    >

                    <div class="partner-logo-fallback">
                        KONICA MINOLTA
                    </div>

                </div>


                <div class="partner-name">
                    Konica Minolta
                </div>


                <span class="partner-focus">
                    Industrial &amp; Office Copiers
                </span>

            </article>



            <!-- =====================
                 5. SHARP
            ====================== -->
            <article class="partner-card">

                <div class="partner-logo-box">

                    <img
                        src="/assets/images/partner-logos/sharp.svg"
                        alt="Sharp Corporation"
                        loading="lazy"
                        decoding="async"
                        onerror="
                            this.style.display='none';
                            this.nextElementSibling.style.display='flex';
                        "
                    >

                    <div class="partner-logo-fallback">
                        SHARP
                    </div>

                </div>


                <div class="partner-name">
                    Sharp Corporation
                </div>


                <span class="partner-focus">
                    Smart Displays &amp; Office Electronics
                </span>

            </article>



            <!-- =====================
                 6. H3C
            ====================== -->
            <article class="partner-card">

                <div class="partner-logo-box">

                    <img
                        src="/assets/images/partner-logos/h3c.svg"
                        alt="H3C Technologies"
                        loading="lazy"
                        decoding="async"
                        onerror="
                            this.style.display='none';
                            this.nextElementSibling.style.display='flex';
                        "
                    >

                    <div class="partner-logo-fallback">
                        H3C
                    </div>

                </div>


                <div class="partner-name">
                    H3C Technologies
                </div>


                <span class="partner-focus">
                    Network &amp; Server Solutions
                </span>

            </article>


        </div>


        <!-- =========================
             PARTNERS FOOTNOTE
        ========================== -->
        <div class="partners-footnote">

            <i class="fa-solid fa-handshake"></i>

            <span>
                Technology partnerships supporting scalable infrastructure,
                workplace productivity and enterprise deployment.
            </span>

        </div>

    </div>

</section>

<!-- 5. CONTACT US & OFFICIAL LOCATIONS -->

<section id="contact" class="section-padding" style="position: relative; overflow: hidden; min-height: 85vh; display: flex; align-items: center; padding: 100px 0;">
    <div aria-hidden="true" style="position: absolute; inset: 0; z-index: 0; background: radial-gradient(circle at 12% 16%, rgba(56,189,248,0.2), transparent 28%), radial-gradient(circle at 90% 84%, rgba(37,99,235,0.28), transparent 34%), linear-gradient(135deg, #0F172A 0%, #1E3A8A 58%, #0F172A 100%);"></div>

<!-- Enterprise Dark Navy Overlay -->
<div style="position: absolute; inset: 0; background: linear-gradient(135deg, rgba(15,23,42,0.90) 0%, rgba(30,58,138,0.82) 50%, rgba(15,23,42,0.94) 100%); z-index: 1;"></div>

<!-- Background Subtle Glows -->
<div style="position: absolute; top: -10%; left: -5%; width: 500px; height: 500px; background: radial-gradient(circle, rgba(56,189,248,0.18) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: 1;"></div>
<div style="position: absolute; bottom: -10%; right: -5%; width: 500px; height: 500px; background: radial-gradient(circle, rgba(37,99,235,0.25) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: 1;"></div>

<div class="container" style="position: relative; z-index: 2; width: 100%;">
    <div class="section-header" style="margin-bottom: 50px;">
        <span style="display: inline-block; background: rgba(56,189,248,0.15); color: #38BDF8; border: 1px solid rgba(56,189,248,0.3); padding: 6px 18px; border-radius: 50px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 16px; backdrop-filter: blur(10px);">Contact Us</span>
        <h2 style="font-size: clamp(32px, 4vw, 44px); font-weight: 800; color: #FFFFFF; margin-bottom: 16px; letter-spacing: -1px; text-shadow: 0 4px 12px rgba(0,0,0,0.4);">Ready to Collaborate?</h2>
        <p style="color: #E2E8F0; font-size: 17px; max-width: 680px; margin: 0 auto; line-height: 1.7; text-shadow: 0 2px 8px rgba(0,0,0,0.5);">
            Reach out via our office hotline or submit your inquiries through our interactive form below.
        </p>
    </div>

    <div class="contact-layout" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 50px; margin-bottom: 70px;">
        
        <!-- Left Column: Official Locations & Executive Support -->
        <div>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
                <h3 style="font-size: 26px; font-weight: 800; color: #FFFFFF; margin: 0; text-shadow: 0 2px 8px rgba(0,0,0,0.4);">Official Locations & Contact</h3>
                <span style="background: rgba(16, 185, 129, 0.2); color: #34D399; border: 1px solid rgba(16, 185, 129, 0.4); padding: 4px 12px; border-radius: 50px; font-size: 11px; font-weight: 700; text-transform: uppercase;">Verified Entity</span>
            </div>

            <div class="location-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <!-- HQ Card (Frosted Glass) -->
                <div style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(16px); padding: 24px; border-radius: 20px; border: 1px solid rgba(59, 130, 246, 0.5); box-shadow: 0 15px 35px rgba(0,0,0,0.25); transition: var(--transition);" onmouseover="this.style.transform='translateY(-6px)'; this.style.boxShadow='0 25px 45px rgba(59,130,246,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.25)';">
                    <div style="display: flex; gap: 14px; align-items: flex-start;">
                        <div style="width: 48px; height: 48px; border-radius: 14px; background: #EFF6FF; color: var(--primary-light); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; box-shadow: 0 8px 16px rgba(37,99,235,0.15);">
                            <i class="fa-solid fa-building"></i>
                        </div>
                        <div style="flex-grow: 1;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
                                <h4 style="font-size: 16px; font-weight: 800; color: var(--primary-dark); margin: 0;">HQ</h4>
                                <span style="background: #DBEAFE; color: #1E40AF; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 6px;">MAIN HQ</span>
                            </div>
                            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6; margin: 0 0 12px 0;">
                                Suite 4805-3-8, Block 4805,<br>
                                CBD Perdana 2, Jalan Perdana Flora,<br>
                                63000 Cyberjaya, Selangor.
                            </p>
                            <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--border); padding-top: 12px; flex-wrap: wrap; gap: 8px;">
                                <span style="font-size: 13px; font-weight: 700; color: var(--primary-light); display: flex; align-items: center; gap: 6px;"><i class="fa-solid fa-phone"></i> 03-8322 1818</span>
                                <a href="tel:0383221818" style="background: #EFF6FF; color: var(--primary-light); font-size: 11px; font-weight: 700; padding: 5px 12px; border-radius: 50px; text-decoration: none; transition: var(--transition);" onmouseover="this.style.background='#DBEAFE';" onmouseout="this.style.background='#EFF6FF';"><i class="fa-solid fa-phone-volume" style="margin-right: 4px;"></i> Call</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cyberjaya Branch Card -->
                <div style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(16px); padding: 24px; border-radius: 20px; border: 1px solid rgba(59, 130, 246, 0.4); box-shadow: 0 15px 35px rgba(0,0,0,0.25); transition: var(--transition);" onmouseover="this.style.transform='translateY(-6px)'; this.style.boxShadow='0 25px 45px rgba(59,130,246,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.25)';">
                    <div style="display: flex; gap: 14px; align-items: flex-start;">
                        <div style="width: 48px; height: 48px; border-radius: 14px; background: #EFF6FF; color: #3B82F6; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; box-shadow: 0 8px 16px rgba(59,130,246,0.15);">
                            <i class="fa-solid fa-city"></i>
                        </div>
                        <div style="flex-grow: 1;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
                                <h4 style="font-size: 16px; font-weight: 800; color: var(--primary-dark); margin: 0;">Cyberjaya Office</h4>
                                <span style="background: #E0F2FE; color: #0369A1; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 6px;">BRANCH</span>
                            </div>
                            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6; margin: 0;">
                                C-3A-3, iTech tower,<br>
                                Jalan Impact, Cyber 6,<br>
                                63000 Cyberjaya, Selangor.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Kangar Branch Card -->
                <div style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(16px); padding: 24px; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.4); box-shadow: 0 15px 35px rgba(0,0,0,0.25); transition: var(--transition);" onmouseover="this.style.transform='translateY(-6px)'; this.style.boxShadow='0 25px 45px rgba(16,185,129,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.25)';">
                    <div style="display: flex; gap: 14px; align-items: flex-start;">
                        <div style="width: 48px; height: 48px; border-radius: 14px; background: #ECFDF5; color: #10B981; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; box-shadow: 0 8px 16px rgba(16,185,129,0.15);">
                            <i class="fa-solid fa-store"></i>
                        </div>
                        <div style="flex-grow: 1;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
                                <h4 style="font-size: 16px; font-weight: 800; color: var(--primary-dark); margin: 0;">Kangar Branch</h4>
                                <span style="background: #D1FAE5; color: #065F46; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 6px;">RETAIL</span>
                            </div>
                            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6; margin: 0;">
                                No. 29 (First Floor), Jalan Medan Satu,<br>
                                Medan Niaga Padang Behor,<br>
                                01000 Kangar, Perlis.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Arau Branch Card -->
                <div style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(16px); padding: 24px; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.4); box-shadow: 0 15px 35px rgba(0,0,0,0.25); transition: var(--transition);" onmouseover="this.style.transform='translateY(-6px)'; this.style.boxShadow='0 25px 45px rgba(16,185,129,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.25)';">
                    <div style="display: flex; gap: 14px; align-items: flex-start;">
                        <div style="width: 48px; height: 48px; border-radius: 14px; background: #ECFDF5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; box-shadow: 0 8px 16px rgba(16,185,129,0.15);">
                            <i class="fa-solid fa-shop"></i>
                        </div>
                        <div style="flex-grow: 1;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
                                <h4 style="font-size: 16px; font-weight: 800; color: var(--primary-dark); margin: 0;">Arau Branch</h4>
                                <span style="background: #D1FAE5; color: #065F46; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 6px;">RETAIL</span>
                            </div>
                            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6; margin: 0;">
                                No. 24 Tingkat 1, Kompleks Bazar MPK,<br>
                                Jalan Arau Jejawi,<br>
                                02600 Arau, Perlis.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Public Inquiry Form (Frosted Glass) -->
        <div class="inquiry-card" style="background: rgba(255, 255, 255, 0.96); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.4); border-radius: 24px; padding: 44px; box-shadow: 0 25px 50px rgba(0,0,0,0.3); position: relative;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 10px;">
                <h3 style="font-size: 24px; font-weight: 800; color: var(--primary-dark); margin: 0;">Public Inquiry Form</h3>
            </div>
            <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 28px;">
                Your inquiry will be directed to our corporate division and we will get back to you as soon as possible.
            </p>

            <form id="publicContactForm" onsubmit="submitPublicInquiry(event)">
                <input type="hidden" id="inqCsrfToken" value="<?= htmlspecialchars(Helper::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;">
                    <label for="inqWebsite">Website</label>
                    <input type="text" id="inqWebsite" tabindex="-1" autocomplete="off">
                </div>
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 13px; font-weight: 700; color: var(--primary-dark); margin-bottom: 6px;">Full Name *</label>
                    <input type="text" id="inqName" required placeholder="Name" style="width: 100%; padding: 14px 16px; border: 1px solid var(--border); border-radius: 10px; font-size: 14px; font-family: inherit; transition: var(--transition); outline: none; background: #FFFFFF;" onfocus="this.style.borderColor='var(--primary-light)'; this.style.boxShadow='0 0 0 4px rgba(37,99,235,0.15)';" onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';">
                </div>

                <div class="inquiry-contact-fields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 700; color: var(--primary-dark); margin-bottom: 6px;">Email Address *</label>
                        <input type="email" id="inqEmail" required placeholder="example@gmail.com" style="width: 100%; padding: 14px 16px; border: 1px solid var(--border); border-radius: 10px; font-size: 14px; font-family: inherit; transition: var(--transition); outline: none; background: #FFFFFF;" onfocus="this.style.borderColor='var(--primary-light)'; this.style.boxShadow='0 0 0 4px rgba(37,99,235,0.15)';" onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 700; color: var(--primary-dark); margin-bottom: 6px;">Phone Number</label>
                        <input type="tel" id="inqPhone" placeholder="01xxxx" style="width: 100%; padding: 14px 16px; border: 1px solid var(--border); border-radius: 10px; font-size: 14px; font-family: inherit; transition: var(--transition); outline: none; background: #FFFFFF;" onfocus="this.style.borderColor='var(--primary-light)'; this.style.boxShadow='0 0 0 4px rgba(37,99,235,0.15)';" onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';">
                    </div>
                </div>

                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 13px; font-weight: 700; color: var(--primary-dark); margin-bottom: 6px;">Inquiry Category *</label>
                    <select id="inqSubject" style="width: 100%; padding: 14px 16px; border: 1px solid var(--border); border-radius: 10px; font-size: 14px; font-family: inherit; transition: var(--transition); outline: none; background: #FFFFFF; cursor: pointer;" onfocus="this.style.borderColor='var(--primary-light)'; this.style.boxShadow='0 0 0 4px rgba(37,99,235,0.15)';" onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';">
                        <option value="ICT & Computing Supply">ICT & Computing Supply</option>
                        <option value="Industrial & Sewing Machinery">Industrial & Sewing Machinery</option>
                        <option value="Agriculture & Trading">Agriculture & Trading</option>
                        <option value="MOE Hardware Rental Project">MOE Hardware Rental Project</option>
                        <option value="General Corporate Inquiry">General Corporate Inquiry</option>
                    </select>
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 13px; font-weight: 700; color: var(--primary-dark); margin-bottom: 6px;">Inquiry Message *</label>
                    <textarea id="inqMessage" required rows="4" placeholder="Please state your hardware specifications or inquiries here..." style="width: 100%; padding: 14px 16px; border: 1px solid var(--border); border-radius: 10px; font-size: 14px; font-family: inherit; transition: var(--transition); outline: none; resize: vertical; background: #FFFFFF;" onfocus="this.style.borderColor='var(--primary-light)'; this.style.boxShadow='0 0 0 4px rgba(37,99,235,0.15)';" onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';"></textarea>
                </div>

                <button type="submit" id="inqSubmitBtn" style="width: 100%; background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%); color: #FFFFFF; font-family: 'Outfit', sans-serif; font-size: 16px; font-weight: 700; padding: 16px; border-radius: 50px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 8px 20px rgba(37,99,235,0.35); transition: var(--transition);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 25px rgba(37,99,235,0.45)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 20px rgba(37,99,235,0.35)';">
                    <i class="fa-solid fa-paper-plane" id="inqBtnIcon"></i>
                    <span id="inqBtnText">Submit Inquiry Now</span>
                </button>
            </form>
        </div>

    </div>

    <!-- Cinematic Google Maps Embed with Glass Header -->
    <div class="contact-map" style="border-radius: 24px; overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.3); border: 1px solid rgba(255, 255, 255, 0.2); height: 440px; position: relative; background: rgba(15, 23, 42, 0.8);">
        <div style="background: rgba(255, 255, 255, 0.96); backdrop-filter: blur(16px); padding: 16px 24px; font-size: 14px; font-weight: 700; color: var(--primary-dark); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <span style="display: flex; align-items: center; gap: 10px;"><i class="fa-solid fa-map-location-dot" style="color: var(--primary-light); font-size: 18px;"></i> Headquarters Location: Suite 4805-3-8, Block 4805, CBD Perdana 2, Cyberjaya, Selangor</span>
            <a href="https://www.google.com/maps?q=The+Bridge+Business+Alliance+Sdn+Bhd" target="_blank" style="background: #EFF6FF; color: var(--primary-light); font-size: 13px; font-weight: 700; padding: 6px 16px; border-radius: 50px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: var(--transition);" onmouseover="this.style.background='#DBEAFE';" onmouseout="this.style.background='#EFF6FF';">Open in Google Maps <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 11px;"></i></a>
        </div>
        <iframe 
            src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d15938.472967850603!2d101.632191!3d2.9255882!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31cdb7ac79d087a5%3A0x3b1ca2bf7eb42dcc!2sThe%20Bridge%20Business%20Alliance%20Sdn%20Bhd!5e0!3m2!1sen!2smy!4v1783410884173!5m2!1sen!2smy" 
            width="100%" 
            height="100%" 
            style="border:0;" 
            allowfullscreen="" 
            loading="lazy" 
            referrerpolicy="strict-origin-when-cross-origin">
        </iframe>
    </div>
</div>

</section>

<script>
async function submitPublicInquiry(e) {
    e.preventDefault();
    
    const btn = document.getElementById('inqSubmitBtn');
    const icon = document.getElementById('inqBtnIcon');
    const text = document.getElementById('inqBtnText');
    
    const name = document.getElementById('inqName').value.trim();
    const email = document.getElementById('inqEmail').value.trim();
    const phone = document.getElementById('inqPhone').value.trim();
    const subject = document.getElementById('inqSubject').value;
    const message = document.getElementById('inqMessage').value.trim();

    if (!name || !email || !message) {
        if (typeof App !== 'undefined' && App.showToast) {
            App.showToast('error', 'Please fill in your name, email, and inquiry message.');
        } else {
            alert('Please fill in your name, email, and inquiry message.');
        }
        return;
    }

    btn.disabled = true;
    btn.style.opacity = '0.7';
    icon.className = 'fa-solid fa-spinner fa-spin';
    text.innerText = 'Submitting Inquiry...';

    try {
        const formData = new FormData();
        formData.append('name', name);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('subject', subject);
        formData.append('message', message);
        formData.append('csrf_token', document.getElementById('inqCsrfToken').value);
        formData.append('website', document.getElementById('inqWebsite').value);

        const response = await fetch('/index.php?action=submit_inquiry', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.status === 'success') {
            if (typeof App !== 'undefined' && App.showToast) {
                App.showToast('success', data.message);
            } else {
                alert(data.message);
            }
            document.getElementById('publicContactForm').reset();
        } else {
            if (typeof App !== 'undefined' && App.showToast) {
                App.showToast('error', data.message || 'An error occurred during submission.');
            } else {
                alert(data.message || 'An error occurred.');
            }
        }
    } catch (err) {
        console.error(err);
        if (typeof App !== 'undefined' && App.showToast) {
            App.showToast('error', 'Network communication error. Please try again.');
        } else {
            alert('Network communication error. Please try again.');
        }
    } finally {
        btn.disabled = false;
        btn.style.opacity = '1';
        icon.className = 'fa-solid fa-paper-plane';
        text.innerText = 'Submit Inquiry Now';
    }
}
</script>

<?php include __DIR__ . '/layout_footer.php'; ?>
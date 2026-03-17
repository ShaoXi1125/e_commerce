<?php require_once 'config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-commerce | Home</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f8fbf9; color: #1b2530; }

        /* ── Hero ── */
        .hero {
            background:
                radial-gradient(circle at 8% 30%, rgba(15,143,111,.22), transparent 42%),
                radial-gradient(circle at 92% 70%, rgba(39,124,198,.18), transparent 36%),
                linear-gradient(135deg, #f0faf5, #e6f4ff);
            padding: 90px 0 80px;
        }
        .hero-pill {
            display: inline-block;
            background: rgba(15,143,111,.12);
            color: #0b6f56;
            border: 1px solid rgba(15,143,111,.25);
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 600;
            letter-spacing: .07em;
            text-transform: uppercase;
            padding: 6px 14px;
            margin-bottom: 18px;
        }
        .hero h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(2.4rem, 5vw, 3.6rem);
            font-weight: 800;
            line-height: 1.06;
            letter-spacing: -.025em;
            max-width: 14ch;
        }
        .hero h1 span { color: #0f8f6f; }
        .hero p.lead { max-width: 42ch; opacity: .82; font-size: 1.05rem; line-height: 1.6; }
        .btn-primary-custom {
            background: linear-gradient(135deg,#0f8f6f,#0b6f56);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-weight: 700;
            padding: 13px 28px;
            font-size: 15px;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(11,111,86,.30);
            color: #fff;
        }
        .btn-outline-custom {
            border: 1.5px solid rgba(27,37,48,.22);
            border-radius: 12px;
            color: #1b2530;
            font-weight: 600;
            padding: 12px 28px;
            font-size: 15px;
            background: rgba(255,255,255,.7);
            transition: border-color .15s ease, background .15s ease;
        }
        .btn-outline-custom:hover {
            border-color: #0f8f6f;
            color: #0b6f56;
            background: rgba(15,143,111,.06);
        }
        .hero-img-wrap {
            position: relative;
        }
        .hero-img-wrap::before {
            content: '';
            position: absolute;
            inset: -16px;
            border-radius: 28px;
            background: rgba(15,143,111,.10);
            transform: rotate(-2deg);
            z-index: 0;
        }
        .hero-img-wrap img { position: relative; z-index: 1; border-radius: 22px; }

        /* ── Stats strip ── */
        .stats-strip {
            background: #fff;
            border-top: 1px solid #e2ede9;
            border-bottom: 1px solid #e2ede9;
        }
        .stat-item { padding: 22px 0; }
        .stat-item .num {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f8f6f;
            line-height: 1;
        }
        .stat-item .lbl { font-size: 13px; color: #6b7c8d; margin-top: 4px; }

        /* ── Section headings ── */
        .section-label {
            display: inline-block;
            background: rgba(15,143,111,.1);
            color: #0b6f56;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            padding: 5px 12px;
            margin-bottom: 10px;
        }
        .section-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 700;
            letter-spacing: -.018em;
            line-height: 1.1;
        }

        /* ── Feature cards ── */
        .feature-card {
            background: #fff;
            border: 1px solid #e2ede9;
            border-radius: 18px;
            padding: 28px 24px;
            height: 100%;
            transition: box-shadow .2s ease, transform .2s ease;
        }
        .feature-card:hover { box-shadow: 0 12px 32px rgba(10,36,60,.09); transform: translateY(-3px); }
        .feature-icon {
            width: 50px; height: 50px;
            border-radius: 14px;
            background: rgba(15,143,111,.12);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            color: #0f8f6f;
            margin-bottom: 16px;
        }
        .feature-card h5 {
            font-weight: 700;
            font-size: 1.05rem;
            margin-bottom: 8px;
        }
        .feature-card p { font-size: 14px; color: #6b7c8d; margin: 0; line-height: 1.55; }

        /* ── Category cards ── */
        .category-card {
            border-radius: 18px;
            overflow: hidden;
            position: relative;
            height: 180px;
            background: linear-gradient(135deg, #e3f5ef, #d4ede5);
            cursor: pointer;
            transition: transform .2s ease, box-shadow .2s ease;
            display: flex; align-items: flex-end;
        }
        .category-card:hover { transform: translateY(-4px); box-shadow: 0 14px 28px rgba(10,36,60,.12); }
        .category-card .cc-inner {
            padding: 18px 20px;
            background: linear-gradient(to top, rgba(11,111,86,.5), transparent);
            width: 100%;
        }
        .category-card .cc-inner strong { color: #fff; font-size: 1rem; font-weight: 700; display: block; }
        .category-card .cc-inner span { color: rgba(255,255,255,.8); font-size: 13px; }
        .category-card .cc-emoji {
            position: absolute;
            top: 16px; right: 20px;
            font-size: 2.4rem;
            opacity: .75;
        }

        /* ── CTA banner ── */
        .cta-banner {
            background: linear-gradient(135deg, #0f8f6f, #0b6f56);
            border-radius: 24px;
            padding: 52px 48px;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .cta-banner::before {
            content: '';
            position: absolute;
            width: 280px; height: 280px;
            border-radius: 50%;
            background: rgba(255,255,255,.06);
            top: -80px; right: -60px;
        }
        .cta-banner::after {
            content: '';
            position: absolute;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: rgba(255,255,255,.05);
            bottom: -60px; left: 30px;
        }
        .cta-banner h2 { font-family:'Space Grotesk',sans-serif; font-weight:800; font-size: clamp(1.6rem,3vw,2.2rem); letter-spacing:-.02em; margin-bottom:10px; }
        .cta-banner p { opacity:.88; font-size:15px; max-width:44ch; margin-bottom:0; }
        .btn-cta-white {
            background: #fff;
            color: #0b6f56;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            padding: 13px 28px;
            font-size: 15px;
            transition: transform .15s ease, box-shadow .15s ease;
            white-space: nowrap;
        }
        .btn-cta-white:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(0,0,0,.18); color: #0b6f56; }

        /* ── Footer ── */
        footer { background: #1b2530; color: rgba(255,255,255,.7); font-size: 14px; }
        footer a { color: rgba(255,255,255,.6); text-decoration: none; }
        footer a:hover { color: #fff; }
        .footer-brand { font-family: 'Space Grotesk',sans-serif; font-weight: 700; font-size: 1.2rem; color: #fff; }
        .footer-divider { border-color: rgba(255,255,255,.1); }
    </style>
</head>
<body>
<?php include 'layout/nav.php'; ?>

<!-- ════════════════════ HERO ════════════════════ -->
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="hero-pill">✦ New arrivals every week</span>
                <h1>Shop the things you <span>love,</span> delivered fast.</h1>
                <p class="lead mt-3 mb-4">Discover thousands of products at unbeatable prices. Free shipping on orders over RM 50.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="#categories" class="btn btn-primary-custom">
                        <i class="fas fa-shopping-bag me-2"></i>Shop Now
                    </a>
                    <a href="#features" class="btn btn-outline-custom">Learn More</a>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <div class="hero-img-wrap text-center">
                    <div style="font-size:11rem;line-height:1;filter:drop-shadow(0 18px 32px rgba(11,111,86,.20));">🛍️</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════ STATS ════════════════════ -->
<div class="stats-strip">
    <div class="container">
        <div class="row text-center g-0">
            <div class="col-6 col-md-3 stat-item border-end">
                <div class="num">10K+</div>
                <div class="lbl">Products</div>
            </div>
            <div class="col-6 col-md-3 stat-item border-end">
                <div class="num">50K+</div>
                <div class="lbl">Happy Customers</div>
            </div>
            <div class="col-6 col-md-3 stat-item border-end">
                <div class="num">99%</div>
                <div class="lbl">Satisfaction Rate</div>
            </div>
            <div class="col-6 col-md-3 stat-item">
                <div class="num">24/7</div>
                <div class="lbl">Customer Support</div>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════ FEATURES ════════════════════ -->
<section class="py-5 mt-2" id="features">
    <div class="container py-3">
        <div class="text-center mb-5">
            <span class="section-label">Why choose us</span>
            <h2 class="section-title mt-1">Shopping made <em>effortless</em></h2>
        </div>
        <div class="row g-4">
            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-shipping-fast"></i></div>
                    <h5>Free Shipping</h5>
                    <p>Orders above RM 50 ship completely free to your doorstep.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <h5>Secure Payments</h5>
                    <p>Your data and transactions are protected end-to-end.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-undo-alt"></i></div>
                    <h5>Easy Returns</h5>
                    <p>Changed your mind? Return within 30 days, no questions asked.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-headset"></i></div>
                    <h5>24/7 Support</h5>
                    <p>Our team is always here to help you with any query.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════ CATEGORIES ════════════════════ -->
<section class="py-5 bg-white" id="categories">
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
            <div>
                <span class="section-label">Browse</span>
                <h2 class="section-title mt-1 mb-0">Shop by category</h2>
            </div>
            <a href="#" class="text-decoration-none text-success fw-semibold" style="font-size:14px;">View all <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
        <div class="row g-3">
            <div class="col-6 col-md-4 col-lg-2">
                <div class="category-card" style="background:linear-gradient(135deg,#fef3e2,#fde8c8);">
                    <span class="cc-emoji">👗</span>
                    <div class="cc-inner" style="background:linear-gradient(to top,rgba(180,90,10,.5),transparent);">
                        <strong>Fashion</strong><span>1,200+ items</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="category-card" style="background:linear-gradient(135deg,#e8f0ff,#d5e4ff);">
                    <span class="cc-emoji">💻</span>
                    <div class="cc-inner" style="background:linear-gradient(to top,rgba(30,60,180,.5),transparent);">
                        <strong>Electronics</strong><span>840+ items</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="category-card" style="background:linear-gradient(135deg,#fde8f0,#fcd0e0);">
                    <span class="cc-emoji">💄</span>
                    <div class="cc-inner" style="background:linear-gradient(to top,rgba(160,30,80,.5),transparent);">
                        <strong>Beauty</strong><span>660+ items</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="category-card" style="background:linear-gradient(135deg,#e5f5e8,#caecce);">
                    <span class="cc-emoji">🌿</span>
                    <div class="cc-inner">
                        <strong>Home & Garden</strong><span>980+ items</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="category-card" style="background:linear-gradient(135deg,#fff3e0,#ffe0b2);">
                    <span class="cc-emoji">⚽</span>
                    <div class="cc-inner" style="background:linear-gradient(to top,rgba(180,80,10,.5),transparent);">
                        <strong>Sports</strong><span>520+ items</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="category-card" style="background:linear-gradient(135deg,#f3e5f5,#e1bee7);">
                    <span class="cc-emoji">📚</span>
                    <div class="cc-inner" style="background:linear-gradient(to top,rgba(100,30,140,.5),transparent);">
                        <strong>Books</strong><span>340+ items</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════ CTA BANNER ════════════════════ -->
<section class="py-5">
    <div class="container py-2">
        <div class="cta-banner d-flex flex-column flex-md-row align-items-center justify-content-between gap-4">
            <div style="position:relative;z-index:1;">
                <h2 class="mb-2">Ready to start shopping?</h2>
                <p>Create a free account today and unlock exclusive member deals.</p>
            </div>
            <div class="d-flex gap-3 flex-shrink-0" style="position:relative;z-index:1;">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="member_register.php" class="btn btn-cta-white">
                        Create Account <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                <?php else: ?>
                    <a href="#categories" class="btn btn-cta-white">
                        Browse Products <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════ FOOTER ════════════════════ -->
<footer class="py-5 mt-3">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="footer-brand mb-2">🛍 E-commerce</div>
                <p style="font-size:13.5px;max-width:32ch;line-height:1.6;">Your one-stop shop for quality products at great prices, delivered right to your door.</p>
            </div>
            <div class="col-6 col-md-2">
                <div class="fw-semibold text-white mb-3" style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;">Shop</div>
                <ul class="list-unstyled" style="font-size:13.5px;line-height:2;">
                    <li><a href="#">New Arrivals</a></li>
                    <li><a href="#">Best Sellers</a></li>
                    <li><a href="#">On Sale</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-2">
                <div class="fw-semibold text-white mb-3" style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;">Account</div>
                <ul class="list-unstyled" style="font-size:13.5px;line-height:2;">
                    <li><a href="member_login.php">Login</a></li>
                    <li><a href="member_register.php">Register</a></li>
                    <li><a href="userProfile.php">My Profile</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <div class="fw-semibold text-white mb-3" style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;">Stay Updated</div>
                <p style="font-size:13.5px;">Subscribe for deals and new arrivals.</p>
                <form class="d-flex gap-2" onsubmit="return false;">
                    <input type="email" class="form-control form-control-sm" placeholder="you@example.com" style="background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.15);color:#fff;">
                    <button class="btn btn-sm btn-primary-custom flex-shrink-0">Subscribe</button>
                </form>
            </div>
        </div>
        <hr class="footer-divider">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2" style="font-size:12.5px;">
            <span>&copy; <?php echo date('Y'); ?> E-commerce. All rights reserved.</span>
            <div class="d-flex gap-3">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
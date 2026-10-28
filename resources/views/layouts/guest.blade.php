@props(['title' => 'BeetleOps HRMS', 'subtitle' => 'Human Resource Management System'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <script>
        // Immediately apply inverted mode if stored in localStorage to prevent flickering
        if (localStorage.getItem('inverted-bw') === 'true') {
            document.documentElement.classList.add('inverted-bw');
        }
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' — BeetleOps HRMS' : config('app.name', 'BeetleOps HRMS') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('Logo-circle.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}?v={{ file_exists(public_path('css/custom.css')) ? filemtime(public_path('css/custom.css')) : time() }}">
</head>
<body class="d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="text-center mb-4">
                    <img src="{{ asset('images/logo.png') }}" id="guestLogo" alt="BeetleOps Logo" class="img-fluid mb-2" style="max-height: 55px; object-fit: contain; cursor: pointer;">
                    <script>
                        if (localStorage.getItem('inverted-bw') === 'true') {
                            document.getElementById('guestLogo').src = "{{ asset('images/logo-dark.png') }}";
                        }
                        
                        document.getElementById('guestLogo').addEventListener('click', function() {
                            var html = document.documentElement;
                            html.classList.toggle('inverted-bw');
                            var isInverted = html.classList.contains('inverted-bw');
                            localStorage.setItem('inverted-bw', isInverted ? 'true' : 'false');
                            this.src = isInverted ? "{{ asset('images/logo-dark.png') }}" : "{{ asset('images/logo.png') }}";
                            
                            if (typeof toggleGamifiedGrid === 'function') {
                                toggleGamifiedGrid(isInverted);
                            }
                        });
                    </script>
                    <h1 class="h3 fw-bold text-white mb-1">
                        {{ $title }}
                    </h1>
                    @if($subtitle)
                        <p class="text-white opacity-50 small mb-0">{{ $subtitle }}</p>
                    @endif
                </div>
                <div class="spotify-card border border-secondary border-opacity-25">
                    <div class="px-4 pb-4 pt-4">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Gamified Background Grid Game Script -->
    <script>
        (function() {
            const canvas = document.createElement('canvas');
            canvas.id = 'gamifiedCanvas';
            canvas.style.position = 'fixed';
            canvas.style.top = '0';
            canvas.style.left = '0';
            canvas.style.width = '100vw';
            canvas.style.height = '100vh';
            canvas.style.zIndex = '-1';
            canvas.style.pointerEvents = 'none';
            canvas.style.display = 'none';
            document.body.appendChild(canvas);

            const ctx = canvas.getContext('2d');
            let dots = [];
            let sparks = [];
            let score = 0;
            let animationFrameId = null;
            let isActive = false;
            let width = 0;
            let height = 0;

            let mouse = { x: -9999, y: -9999 };

            function resize() {
                width = window.innerWidth;
                height = window.innerHeight;
                canvas.width = width;
                canvas.height = height;
                initGrid();
            }

            const SPACING = 24;

            function initGrid() {
                dots = [];
                const cols = Math.ceil(width / SPACING) + 1;
                const rows = Math.ceil(height / SPACING) + 1;

                for (let c = 0; c < cols; c++) {
                    for (let r = 0; r < rows; r++) {
                        const ox = c * SPACING;
                        const oy = r * SPACING;
                        dots.push({
                            x: ox,
                            y: oy,
                            ox: ox,
                            oy: oy,
                            vx: 0,
                            vy: 0,
                            radius: 2,
                            color: 'rgba(0, 0, 0, 0.12)',
                            popped: false,
                            popTime: 0
                        });
                    }
                }
            }

            function triggerShockwave(clickX, clickY) {
                if (!isActive) return;
                
                dots.forEach(dot => {
                    if (dot.popped) return;
                    const dx = dot.x - clickX;
                    const dy = dot.y - clickY;
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    
                    if (dist < 150) {
                        const angle = Math.atan2(dy, dx);
                        const force = (150 - dist) / 10;
                        dot.vx += Math.cos(angle) * force;
                        dot.vy += Math.sin(angle) * force;
                        
                        if (dist < 80 && Math.random() < 0.6) {
                            popDot(dot);
                        }
                    }
                });
            }

            function popDot(dot) {
                dot.popped = true;
                dot.popTime = Date.now();
                score++;
                updateScoreboard();

                const colors = ['#1db954', '#3b82f6', '#ef4444', '#f59e0b', '#a855f7'];
                const numSparks = 6 + Math.floor(Math.random() * 4);
                for (let i = 0; i < numSparks; i++) {
                    const angle = Math.random() * Math.PI * 2;
                    const speed = 1 + Math.random() * 3;
                    sparks.push({
                        x: dot.x,
                        y: dot.y,
                        vx: Math.cos(angle) * speed,
                        vy: Math.sin(angle) * speed,
                        color: colors[Math.floor(Math.random() * colors.length)],
                        alpha: 1,
                        size: 1.5 + Math.random() * 2
                    });
                }
            }

            let scoreboard = document.createElement('div');
            scoreboard.id = 'gamifiedScoreboard';
            scoreboard.style.position = 'fixed';
            scoreboard.style.bottom = '20px';
            scoreboard.style.right = '20px';
            scoreboard.style.padding = '8px 16px';
            scoreboard.style.borderRadius = '50px';
            scoreboard.style.background = 'rgba(0, 0, 0, 0.75)';
            scoreboard.style.border = '1px solid rgba(0, 0, 0, 0.1)';
            scoreboard.style.color = '#ffffff';
            scoreboard.style.fontSize = '0.82rem';
            scoreboard.style.fontWeight = 'bold';
            scoreboard.style.zIndex = '1000';
            scoreboard.style.display = 'none';
            scoreboard.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            scoreboard.style.pointerEvents = 'none';
            scoreboard.style.transition = 'transform 0.1s ease';
            scoreboard.innerText = 'Dots Popped: 0';
            document.body.appendChild(scoreboard);

            function updateScoreboard() {
                scoreboard.innerText = 'Dots Popped: ' + score;
                scoreboard.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    scoreboard.style.transform = 'scale(1)';
                }, 100);
            }

            function tick() {
                if (!isActive) return;

                ctx.clearRect(0, 0, width, height);

                dots.forEach(dot => {
                    if (dot.popped && Date.now() - dot.popTime > 4000) {
                        dot.popped = false;
                        dot.vx = (Math.random() - 0.5) * 2;
                        dot.vy = (Math.random() - 0.5) * 2;
                    }

                    const dx = dot.x - mouse.x;
                    const dy = dot.y - mouse.y;
                    const dist = Math.sqrt(dx * dx + dy * dy);

                    if (dist < 100) {
                        if (!dot.popped) {
                            if (dist < 8) {
                                popDot(dot);
                                return;
                            }
                            
                            const force = (100 - dist) / 100;
                            const angle = Math.atan2(dy, dx);
                            dot.vx += Math.cos(angle) * force * 0.5;
                            dot.vy += Math.sin(angle) * force * 0.5;
                            dot.radius = 2 + (1 - dist / 100) * 4;
                            dot.color = `rgba(0, 0, 0, ${0.12 + (1 - dist / 100) * 0.6})`;
                        }
                    } else {
                        if (!dot.popped) {
                            dot.radius = 2;
                            dot.color = 'rgba(0, 0, 0, 0.12)';
                        }
                    }

                    const homeDx = dot.ox - dot.x;
                    const homeDy = dot.oy - dot.y;
                    dot.vx += homeDx * 0.05;
                    dot.vy += homeDy * 0.05;

                    dot.vx *= 0.85;
                    dot.vy *= 0.85;
                    dot.x += dot.vx;
                    dot.y += dot.vy;

                    if (!dot.popped) {
                        ctx.beginPath();
                        ctx.arc(dot.x, dot.y, dot.radius, 0, Math.PI * 2);
                        ctx.fillStyle = dot.color;
                        ctx.fill();
                    }
                });

                sparks.forEach((spark, index) => {
                    spark.x += spark.vx;
                    spark.y += spark.vy;
                    spark.vy += 0.05;
                    spark.alpha -= 0.02;

                    if (spark.alpha <= 0) {
                        sparks.splice(index, 1);
                    } else {
                        ctx.save();
                        ctx.globalAlpha = spark.alpha;
                        ctx.beginPath();
                        ctx.arc(spark.x, spark.y, spark.size, 0, Math.PI * 2);
                        ctx.fillStyle = spark.color;
                        ctx.fill();
                        ctx.restore();
                    }
                });

                animationFrameId = requestAnimationFrame(tick);
            }

            function toggleGamifiedGrid(enable) {
                isActive = enable;
                if (enable) {
                    canvas.style.display = 'block';
                    scoreboard.style.display = 'block';
                    resize();
                    window.addEventListener('resize', resize);
                    window.addEventListener('mousemove', onMouseMove);
                    window.addEventListener('click', onClick);
                    if (!animationFrameId) {
                        animationFrameId = requestAnimationFrame(tick);
                    }
                } else {
                    canvas.style.display = 'none';
                    scoreboard.style.display = 'none';
                    window.removeEventListener('resize', resize);
                    window.removeEventListener('mousemove', onMouseMove);
                    window.removeEventListener('click', onClick);
                    if (animationFrameId) {
                        cancelAnimationFrame(animationFrameId);
                        animationFrameId = null;
                    }
                    ctx.clearRect(0, 0, width, height);
                }
            }

            function onMouseMove(e) {
                mouse.x = e.clientX;
                mouse.y = e.clientY;
            }

            function onClick(e) {
                // Ignore clicks on forms/buttons to allow regular submit events
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON' || e.target.tagName === 'A' || e.target.closest('.spotify-card')) {
                    return;
                }
                triggerShockwave(e.clientX, e.clientY);
            }

            window.toggleGamifiedGrid = toggleGamifiedGrid;

            // Auto-init if already inverted on load
            if (document.documentElement.classList.contains('inverted-bw')) {
                toggleGamifiedGrid(true);
            }
        })();
    </script>

    <!-- Real-time Phone Number Input Sanitizer -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const phoneInputs = document.querySelectorAll('input[type="tel"], input[name="phone"], input[name="emergency_number"]');

            phoneInputs.forEach(function (input) {
                // Real-time input/paste filtering
                input.addEventListener('input', function () {
                    let val = this.value;
                    const startsWithPlus = val.startsWith('+');

                    // Allow only digits, spaces, hyphens, and parentheses
                    let sanitized = val.replace(/[^0-9\s\-()]/g, '');

                    // Preserve leading + if present, but remove all other + symbols
                    if (startsWithPlus) {
                        sanitized = '+' + sanitized.replace(/\+/g, '');
                    } else {
                        sanitized = sanitized.replace(/\+/g, '');
                    }

                    if (this.value !== sanitized) {
                        this.value = sanitized;
                    }
                });

                // Prevent invalid keystrokes immediately
                input.addEventListener('keypress', function (e) {
                    // Allow control keys (backspace, enter, arrow keys, Ctrl shortcuts)
                    if (e.ctrlKey || e.metaKey || e.altKey || e.key.length > 1) {
                        return;
                    }

                    // Allow '+' only at the first position
                    if (e.key === '+') {
                        if (this.selectionStart !== 0 || this.value.includes('+')) {
                            e.preventDefault();
                        }
                        return;
                    }

                    // Allow digits, spaces, hyphens, and parentheses
                    if (!/[0-9\s\-()]/.test(e.key)) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>

    <!-- Real-time / Blur Email Input Validation -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const emailInputs = document.querySelectorAll('input[type="email"]');
            const emailRegex = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/;

            emailInputs.forEach(function (input) {
                const parent = input.parentElement;
                let feedback = parent.querySelector('.invalid-feedback');

                // If no .invalid-feedback container exists yet, create one dynamically
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    parent.appendChild(feedback);
                }

                function validateEmailField(forceValidation) {
                    const val = input.value.trim();

                    if (val === '') {
                        if (input.hasAttribute('required') && forceValidation) {
                            input.classList.add('is-invalid');
                            feedback.textContent = 'Email address is required.';
                            feedback.style.display = 'block';
                            return false;
                        } else {
                            input.classList.remove('is-invalid');
                            feedback.textContent = '';
                            feedback.style.display = '';
                            return true;
                        }
                    }

                    if (!emailRegex.test(val)) {
                        input.classList.add('is-invalid');
                        feedback.textContent = 'Please enter a valid email address (e.g. name@company.com).';
                        feedback.style.display = 'block';
                        return false;
                    } else {
                        input.classList.remove('is-invalid');
                        feedback.textContent = '';
                        feedback.style.display = '';
                        return true;
                    }
                }

                // Trigger validation as soon as user presses/clicks out of the field
                input.addEventListener('blur', function () {
                    input.dataset.hasBlurred = 'true';
                    validateEmailField(true);
                });

                // Clear error dynamically as user types valid input
                input.addEventListener('input', function () {
                    if (input.classList.contains('is-invalid') || input.dataset.hasBlurred === 'true') {
                        validateEmailField(false);
                    }
                });

                // Validate before form submission
                const form = input.closest('form');
                if (form) {
                    form.addEventListener('submit', function (e) {
                        input.dataset.hasBlurred = 'true';
                        if (!validateEmailField(true)) {
                            e.preventDefault();
                            input.focus();
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8"/>
        <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
        <title>{{ config('app.name', 'Check Giá') }} - The Financial Sentinel</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&amp;family=Space+Grotesk:wght@500&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
        <script id="tailwind-config">
            tailwind.config = {
                darkMode: "class",
                theme: {
                    extend: {
                        colors: {
                            "on-secondary-fixed-variant": "#8d1600",
                            "inverse-primary": "#b4c5ff",
                            "on-secondary-fixed": "#3e0500",
                            "background": "#fcf9f5",
                            "surface-dim": "#dcdad6",
                            "surface-container-highest": "#e5e2de",
                            "primary": "#004bca",
                            "surface": "#fcf9f5",
                            "outline": "#737687",
                            "on-tertiary-fixed": "#002113",
                            "tertiary-fixed": "#6ffbbe",
                            "surface-variant": "#e5e2de",
                            "on-secondary-container": "#fffbff",
                            "on-surface-variant": "#424656",
                            "on-primary": "#ffffff",
                            "on-primary-fixed": "#00174b",
                            "error-container": "#ffdad6",
                            "on-background": "#1c1c1a",
                            "on-tertiary-container": "#ccffe2",
                            "secondary-container": "#e12a00",
                            "primary-fixed-dim": "#b4c5ff",
                            "on-error": "#ffffff",
                            "on-primary-container": "#f1f2ff",
                            "on-surface": "#1c1c1a",
                            "surface-container": "#f0ede9",
                            "surface-bright": "#fcf9f5",
                            "surface-container-low": "#f6f3ef",
                            "on-primary-fixed-variant": "#003ea8",
                            "surface-tint": "#0052dc",
                            "inverse-surface": "#31302e",
                            "surface-container-high": "#eae8e4",
                            "tertiary-container": "#007f57",
                            "inverse-on-surface": "#f3f0ec",
                            "secondary": "#b41f00",
                            "secondary-fixed": "#ffdad3",
                            "primary-container": "#0061ff",
                            "primary-fixed": "#dbe1ff",
                            "error": "#ba1a1a",
                            "on-error-container": "#93000a",
                            "on-tertiary": "#ffffff",
                            "outline-variant": "#c2c6d9",
                            "tertiary-fixed-dim": "#4edea3",
                            "on-tertiary-fixed-variant": "#005236",
                            "surface-container-lowest": "#ffffff",
                            "on-secondary": "#ffffff",
                            "tertiary": "#006443",
                            "secondary-fixed-dim": "#ffb4a4",
                        },
                        borderRadius: {
                            DEFAULT: "0.125rem",
                            lg: "0.25rem",
                            xl: "0.5rem",
                            full: "0.75rem",
                        },
                        fontFamily: {
                            headline: ["Inter"],
                            body: ["Inter"],
                            label: ["Space Grotesk"],
                        },
                    },
                },
            }
        </script>
        <style>
            .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24}
            .hero-gradient{background:linear-gradient(135deg,#004bca 0%,#0061ff 100%)}
            .glass-nav{background-color:rgba(252,249,245,.8);backdrop-filter:blur(24px)}
        </style>
    </head>
    <body class="bg-surface text-on-surface font-body selection:bg-primary/20">
        @php($loginUrl = route('login'))
        <nav class="fixed top-0 w-full z-50 glass-nav shadow-sm">
            <div class="flex justify-between items-center px-4 sm:px-8 py-4 max-w-7xl mx-auto">
                <a class="text-2xl font-bold tracking-tight text-[#1c1c1a] font-headline" href="{{ route('home') }}">Check Giá</a>
                <div class="hidden md:flex items-center gap-8">
                    <a class="text-[#1c1c1a] opacity-70 hover:opacity-100 transition-opacity duration-200 font-label text-sm uppercase tracking-wider" href="#features">Tính năng</a>
                    <a class="text-blue-700 font-semibold border-b-2 border-blue-700 font-label text-sm uppercase tracking-wider" href="#pricing">Bảng giá</a>
                    <a class="text-[#1c1c1a] opacity-70 hover:opacity-100 transition-opacity duration-200 font-label text-sm uppercase tracking-wider" href="#faq">FAQ</a>
                </div>
                <div class="flex items-center gap-3 sm:gap-4">
                    <a class="px-4 py-2 text-sm font-label text-[#1c1c1a] opacity-70 hover:opacity-100 transition-all duration-150 active:scale-95 hidden sm:inline-flex" href="{{ $loginUrl }}">Login</a>
                    <a class="hero-gradient text-on-primary px-5 sm:px-6 py-2 rounded-lg text-sm font-label font-bold transition-all duration-150 active:scale-95" href="{{ $loginUrl }}">Bắt đầu ngay</a>
                    <button aria-controls="mobileNav" aria-expanded="false" class="md:hidden w-10 h-10 inline-flex items-center justify-center rounded-lg bg-surface-container-low hover:bg-surface-container-high transition-colors" id="mobileNavBtn" type="button">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                </div>
            </div>
            <div class="hidden md:hidden px-4 pb-4" id="mobileNav">
                <div class="bg-surface-container-low rounded-xl p-4 flex flex-col gap-3">
                    <a class="font-label text-sm uppercase tracking-wider text-[#1c1c1a] opacity-80 hover:opacity-100" href="#features">Tính năng</a>
                    <a class="font-label text-sm uppercase tracking-wider text-[#1c1c1a] opacity-80 hover:opacity-100" href="#pricing">Bảng giá</a>
                    <a class="font-label text-sm uppercase tracking-wider text-[#1c1c1a] opacity-80 hover:opacity-100" href="#faq">FAQ</a>
                    <a class="font-label text-sm uppercase tracking-wider text-blue-700 font-bold" href="{{ $loginUrl }}">Login</a>
                </div>
            </div>
        </nav>
        <main class="pt-24">
            <section class="max-w-7xl mx-auto px-4 sm:px-8 py-16 lg:py-32 grid lg:grid-cols-12 gap-12 items-center">
                <div class="lg:col-span-7">
                    <div class="inline-flex items-center gap-2 bg-tertiary-container/10 px-3 py-1 rounded-full mb-6">
                        <span class="w-2 h-2 rounded-full bg-tertiary animate-pulse"></span>
                        <span class="text-tertiary font-label text-xs font-bold uppercase tracking-widest">Real-time Intelligence</span>
                    </div>
                    <h1 class="text-4xl sm:text-5xl lg:text-7xl font-bold font-headline leading-tight tracking-tight mb-8">
                        Check Giá - Biết giá ngay khi <span class="text-primary italic">đối thủ vừa thay đổi</span>
                    </h1>
                    <p class="text-base sm:text-lg text-on-surface-variant max-w-xl mb-10 leading-relaxed">
                        Đừng để mất khách hàng chỉ vì cập nhật giá chậm. Hệ thống Sentinel tự động quét, so sánh và cảnh báo biến động thị trường 24/7.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <a class="hero-gradient text-on-primary px-7 sm:px-8 py-4 rounded-lg text-base font-label font-bold flex items-center gap-3 transition-all duration-150 active:scale-95" href="{{ $loginUrl }}">
                            Bắt đầu ngay
                            <span class="material-symbols-outlined text-xl">arrow_forward</span>
                        </a>
                        <a class="bg-surface-container-high px-7 sm:px-8 py-4 rounded-lg text-base font-label font-bold flex items-center gap-3 transition-all duration-150 hover:bg-surface-container-highest" href="{{ route('demo') }}">
                            Xem Demo
                            <span class="material-symbols-outlined text-xl">play_circle</span>
                        </a>
                    </div>
                </div>
                <div class="lg:col-span-5 relative">
                    <div class="bg-surface-container-low rounded-xl p-6 shadow-2xl relative z-10 overflow-hidden">
                        <div class="flex justify-between items-end mb-8">
                            <div>
                                <span class="text-xs font-label text-on-surface-variant uppercase tracking-widest">Market Index</span>
                                <div class="text-4xl font-headline font-bold">$2,840.50</div>
                            </div>
                            <div class="bg-tertiary-container px-3 py-1 rounded-full flex items-center gap-1">
                                <span class="material-symbols-outlined text-on-tertiary-fixed text-sm">trending_down</span>
                                <span class="text-on-tertiary-fixed font-label text-xs font-bold">-12.4%</span>
                            </div>
                        </div>
                        <div class="h-48 w-full bg-surface-container-high rounded-lg relative overflow-hidden">
                            <div class="absolute inset-0 flex items-end">
                                <svg class="w-full h-full" viewBox="0 0 400 150">
                                    <path d="M0,120 Q50,110 100,130 T200,90 T300,110 T400,60" fill="none" stroke="#004bca" stroke-width="3"></path>
                                    <path d="M0,120 Q50,110 100,130 T200,90 T300,110 T400,60 V150 H0 Z" fill="url(#chartGradient)"></path>
                                    <defs>
                                        <linearGradient id="chartGradient" x1="0" x2="0" y1="0" y2="1">
                                            <stop offset="0%" stop-color="#004bca" stop-opacity="0.1"></stop>
                                            <stop offset="100%" stop-color="#004bca" stop-opacity="0"></stop>
                                        </linearGradient>
                                    </defs>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-6 space-y-3">
                            <div class="flex justify-between items-center py-2 border-b border-outline-variant/10">
                                <span class="text-sm font-label text-on-surface-variant">Đối thủ A</span>
                                <span class="font-bold text-secondary">Giảm 5%</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-outline-variant/10">
                                <span class="text-sm font-label text-on-surface-variant">Đối thủ B</span>
                                <span class="font-bold">Giữ nguyên</span>
                            </div>
                        </div>
                    </div>
                    <div class="absolute -top-6 -right-6 w-32 h-32 bg-primary/10 rounded-full blur-3xl"></div>
                    <div class="absolute -bottom-10 -left-10 w-48 h-48 bg-tertiary/10 rounded-full blur-3xl"></div>
                </div>
            </section>
            <section class="bg-surface-container-low py-20 sm:py-24" id="features">
                <div class="max-w-7xl mx-auto px-4 sm:px-8">
                    <div class="text-center mb-16 sm:mb-20">
                        <span class="font-label text-xs font-bold text-secondary uppercase tracking-[0.2em] mb-4 block">Nỗi đau thị trường</span>
                        <h2 class="text-3xl sm:text-4xl font-headline font-bold max-w-2xl mx-auto leading-tight">Tại sao việc check giá thủ công đang giết chết lợi nhuận của bạn?</h2>
                    </div>
                    <div class="grid md:grid-cols-3 gap-8 sm:gap-12">
                        <div class="p-8 bg-surface rounded-xl">
                            <span class="material-symbols-outlined text-secondary text-4xl mb-6">timer_off</span>
                            <h3 class="text-xl font-headline font-semibold mb-4 text-on-surface">Tốn quá nhiều thời gian</h3>
                            <p class="text-on-surface-variant leading-relaxed">Dành hàng giờ mỗi ngày để F5 các trang web đối thủ thay vì tập trung vào chiến lược bán hàng.</p>
                        </div>
                        <div class="p-8 bg-surface rounded-xl">
                            <span class="material-symbols-outlined text-secondary text-4xl mb-6">person_remove</span>
                            <h3 class="text-xl font-headline font-semibold mb-4 text-on-surface">Mất khách hàng tức thì</h3>
                            <p class="text-on-surface-variant leading-relaxed">Chỉ cần chậm 30 phút điều chỉnh giá, khách hàng đã chuyển sang mua của đối thủ cạnh tranh.</p>
                        </div>
                        <div class="p-8 bg-surface rounded-xl">
                            <span class="material-symbols-outlined text-secondary text-4xl mb-6">query_stats</span>
                            <h3 class="text-xl font-headline font-semibold mb-4 text-on-surface">Thiếu hụt dữ liệu lịch sử</h3>
                            <p class="text-on-surface-variant leading-relaxed">Không nắm bắt được quy luật tăng giảm giá của đối thủ để đưa ra kịch bản ứng phó tối ưu.</p>
                        </div>
                    </div>
                </div>
            </section>
            <section class="py-20 sm:py-24">
                <div class="max-w-7xl mx-auto px-4 sm:px-8 grid lg:grid-cols-2 gap-14 lg:gap-20 items-center">
                    <div>
                        <h2 class="text-3xl sm:text-4xl font-headline font-bold mb-8 leading-tight">Theo dõi toàn bộ thị trường trên 1 màn hình duy nhất</h2>
                        <p class="text-on-surface-variant text-base sm:text-lg mb-10 leading-relaxed">Không còn tab chồng tab. Check Giá hợp nhất toàn bộ dữ liệu từ các sàn TMĐT và website lớn nhất về một dashboard trực quan, giúp bạn ra quyết định trong tích tắc.</p>
                        <ul class="space-y-6">
                            <li class="flex gap-4 items-start">
                                <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0 mt-1">
                                    <span class="material-symbols-outlined text-primary text-sm" style="font-variation-settings:'FILL' 1;">check</span>
                                </div>
                                <span class="font-medium">Quét hơn 50+ website và sàn TMĐT hàng đầu Việt Nam.</span>
                            </li>
                            <li class="flex gap-4 items-start">
                                <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0 mt-1">
                                    <span class="material-symbols-outlined text-primary text-sm" style="font-variation-settings:'FILL' 1;">check</span>
                                </div>
                                <span class="font-medium">Tự động nhận diện thay đổi từng phút.</span>
                            </li>
                            <li class="flex gap-4 items-start">
                                <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0 mt-1">
                                    <span class="material-symbols-outlined text-primary text-sm" style="font-variation-settings:'FILL' 1;">check</span>
                                </div>
                                <span class="font-medium">Giao diện sạch sẽ, tối ưu cho phân tích nhanh.</span>
                            </li>
                        </ul>
                    </div>
                    <div class="bg-surface-container-highest rounded-2xl p-4 shadow-xl border border-outline-variant/10">
                        <img alt="Financial Dashboard" class="rounded-xl w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCLzcvHHC2q3NRLLsLL1jPKixz5PHEoXqFrfJKvWvycoU91PGkG-EuhXH3or6tH1uqldTP7qHapkF2g-T4lTrCXgY4S3jBarPwuM4V61K2yFul4ueWzBqGS1mPipSWcHDiE9vfgyWMUn8IQrHsHKVYW9pFF-FV28VwNBu9HJReFRpZEK5-VVbbOzHR3BRjwx1XwZdOZ7NSgDZtXgfRl1z1R7PPo3tjVpkUS6THoxAhqLYgya3lajHlFRv-HIj3grGRllBGP459zPQg9"/>
                    </div>
                </div>
            </section>
            <section class="py-20 sm:py-24 bg-surface-container-low">
                <div class="max-w-7xl mx-auto px-4 sm:px-8">
                    <div class="mb-14 sm:mb-16">
                        <h2 class="text-3xl font-headline font-bold mb-4">Tính năng chuyên biệt cho Merchant</h2>
                        <div class="w-20 h-1.5 bg-primary"></div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-6">
                        <div class="md:col-span-3 bg-surface p-8 rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-6">
                                <span class="material-symbols-outlined text-primary">compare_arrows</span>
                            </div>
                            <h3 class="text-xl font-headline font-bold mb-3">So sánh giá đa nền tảng</h3>
                            <p class="text-on-surface-variant text-sm">Hiển thị song song giá của cùng một sản phẩm trên các sàn TMĐT và website riêng của đối thủ.</p>
                        </div>
                        <div class="md:col-span-3 bg-surface p-8 rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="w-12 h-12 bg-secondary/10 rounded-lg flex items-center justify-center mb-6">
                                <span class="material-symbols-outlined text-secondary">notifications_active</span>
                            </div>
                            <h3 class="text-xl font-headline font-bold mb-3">Cảnh báo theo Rule</h3>
                            <p class="text-on-surface-variant text-sm">Nhận thông báo Zalo/Telegram ngay khi giá đối thủ thấp hơn giá của bạn một khoảng định trước.</p>
                        </div>
                        <div class="md:col-span-2 bg-surface p-8 rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="w-12 h-12 bg-tertiary/10 rounded-lg flex items-center justify-center mb-6">
                                <span class="material-symbols-outlined text-tertiary">timeline</span>
                            </div>
                            <h3 class="text-lg font-headline font-bold mb-3">Lịch sử &amp; Biểu đồ</h3>
                            <p class="text-on-surface-variant text-sm">Truy xuất lịch sử giá lên tới 365 ngày để phân tích xu hướng mùa vụ.</p>
                        </div>
                        <div class="md:col-span-2 bg-surface p-8 rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="w-12 h-12 bg-on-surface-variant/10 rounded-lg flex items-center justify-center mb-6">
                                <span class="material-symbols-outlined text-on-surface-variant">description</span>
                            </div>
                            <h3 class="text-lg font-headline font-bold mb-3">Xuất Excel báo cáo</h3>
                            <p class="text-on-surface-variant text-sm">Một click để tải về báo cáo so sánh giá chi tiết phục vụ cuộc họp chiến lược.</p>
                        </div>
                        <div class="md:col-span-2 bg-surface p-8 rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="w-12 h-12 bg-primary-fixed-dim/20 rounded-lg flex items-center justify-center mb-6">
                                <span class="material-symbols-outlined text-on-primary-fixed-variant">filter_alt</span>
                            </div>
                            <h3 class="text-lg font-headline font-bold mb-3">Phân tích lọc thông minh</h3>
                            <p class="text-on-surface-variant text-sm">Lọc theo mã SKU, thương hiệu hoặc phân khúc giá để thu hẹp phạm vi theo dõi.</p>
                        </div>
                    </div>
                </div>
            </section>
            <section class="py-20 sm:py-24 bg-surface" id="pricing">
                <div class="max-w-7xl mx-auto px-4 sm:px-8 text-center">
                    <span class="font-label text-xs font-bold text-primary uppercase tracking-[0.2em] mb-4 block">Bảng giá dịch vụ</span>
                    <h2 class="text-3xl sm:text-4xl font-headline font-bold mb-12 sm:mb-16">Lựa chọn gói phù hợp với doanh nghiệp</h2>
                    <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                        <div class="bg-surface-container-low p-10 rounded-2xl border border-outline-variant/10 flex flex-col items-center">
                            <h3 class="text-xl font-headline font-bold mb-4">Gói Tháng</h3>
                            <div class="flex items-baseline gap-1 mb-8">
                                <span class="text-4xl font-headline font-bold">200,000</span>
                                <span class="text-on-surface-variant font-label">VNĐ / tháng</span>
                            </div>
                            <ul class="space-y-4 mb-10 text-sm text-left w-full">
                                <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-lg">check_circle</span> Theo dõi 50 sản phẩm</li>
                                <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-lg">check_circle</span> Cập nhật mỗi 5 phút</li>
                                <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-lg">check_circle</span> Cảnh báo Telegram</li>
                            </ul>
                            <a class="w-full py-4 px-6 rounded-lg border border-primary text-primary font-bold font-label hover:bg-primary/5 transition-colors active:scale-95" href="{{ $loginUrl }}">Đăng ký gói tháng</a>
                        </div>
                        <div class="bg-surface border-2 border-primary p-10 rounded-2xl relative flex flex-col items-center shadow-xl">
                            <div class="absolute -top-4 bg-primary text-on-primary px-4 py-1 rounded-full text-xs font-bold font-label uppercase">Tiết kiệm 50%</div>
                            <h3 class="text-xl font-headline font-bold mb-4">Gói Năm</h3>
                            <div class="flex flex-col items-center mb-8">
                                <div class="flex items-baseline gap-1">
                                    <span class="text-4xl font-headline font-bold text-primary">100,000</span>
                                    <span class="text-on-surface-variant font-label">VNĐ / tháng</span>
                                </div>
                                <p class="text-xs text-on-surface-variant mt-2">Thanh toán 1,200,000 VNĐ / năm</p>
                            </div>
                            <ul class="space-y-4 mb-10 text-sm text-left w-full">
                                <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-lg">check_circle</span> Theo dõi 200 sản phẩm</li>
                                <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-lg">check_circle</span> Cập nhật mỗi 5 phút</li>
                                <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-lg">check_circle</span> Cảnh báo Zalo &amp; Telegram</li>
                                <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-lg">check_circle</span> Xuất báo cáo Excel không giới hạn</li>
                            </ul>
                            <a class="w-full py-4 px-6 rounded-lg hero-gradient text-on-primary font-bold font-label transition-all duration-150 active:scale-95 shadow-lg" href="{{ $loginUrl }}">Đăng ký ngay</a>
                        </div>
                    </div>
                </div>
            </section>
            <section class="py-20 sm:py-24 bg-surface-container-low" id="faq">
                <div class="max-w-4xl mx-auto px-4 sm:px-8">
                    <h2 class="text-3xl font-headline font-bold text-center mb-12">Câu hỏi thường gặp</h2>
                    <div class="space-y-4">
                        <details class="bg-surface rounded-xl p-6">
                            <summary class="cursor-pointer font-headline font-semibold">Check Giá cập nhật nhanh tới mức nào?</summary>
                            <p class="mt-3 text-on-surface-variant">Bạn có thể tuỳ chỉnh chu kỳ cập nhật từ 5 phút tới 1 ngày tuỳ nhu cầu và hạn mức hệ thống.</p>
                        </details>
                        <details class="bg-surface rounded-xl p-6">
                            <summary class="cursor-pointer font-headline font-semibold">Có hỗ trợ xuất Excel và lọc theo nhóm không?</summary>
                            <p class="mt-3 text-on-surface-variant">Có. Bạn có thể xuất Excel toàn bộ hoặc theo nhóm sản phẩm, đồng thời lọc/sắp xếp ngay trong dashboard.</p>
                        </details>
                        <details class="bg-surface rounded-xl p-6">
                            <summary class="cursor-pointer font-headline font-semibold">Tôi có thể dùng tài khoản con để chỉ xem không?</summary>
                            <p class="mt-3 text-on-surface-variant">Có. Bạn có thể tạo tài khoản con (viewer) để xem dữ liệu mà không sửa cấu hình.</p>
                        </details>
                    </div>
                </div>
            </section>
            <section class="max-w-7xl mx-auto px-4 sm:px-8 py-16 sm:py-20">
                <div class="hero-gradient rounded-3xl p-10 sm:p-12 lg:p-20 text-center relative overflow-hidden">
                    <div class="relative z-10">
                        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-headline font-bold text-on-primary mb-8 max-w-3xl mx-auto">Sẵn sàng để làm chủ thị trường với dữ liệu chính xác?</h2>
                        <p class="text-on-primary/80 text-base sm:text-lg mb-10 sm:mb-12 max-w-xl mx-auto">Bắt đầu theo dõi đối thủ ngay hôm nay. Nắm bắt biến động thị trường trong tầm tay.</p>
                        <div class="flex flex-col sm:flex-row justify-center gap-4">
                            <a class="bg-surface text-primary px-10 py-5 rounded-lg text-lg font-label font-bold transition-all duration-150 active:scale-95" href="{{ $loginUrl }}">Bắt đầu ngay</a>
                            <a class="bg-primary-container/20 text-on-primary border border-on-primary/30 px-10 py-5 rounded-lg text-lg font-label font-bold transition-all duration-150 hover:bg-primary-container/40" href="mailto:support@checkgia.id.vn">Liên hệ tư vấn</a>
                        </div>
                    </div>
                    <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2 blur-3xl"></div>
                    <div class="absolute bottom-0 left-0 w-96 h-96 bg-black/10 rounded-full translate-y-1/2 -translate-x-1/2 blur-3xl"></div>
                </div>
            </section>
        </main>
        <footer class="bg-surface-container py-16 mt-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-8 grid grid-cols-1 md:grid-cols-4 gap-12">
                <div class="md:col-span-1">
                    <div class="text-xl font-bold text-[#1c1c1a] font-headline mb-6">Check Giá</div>
                    <p class="text-sm text-on-surface-variant leading-relaxed">Nền tảng trí tuệ giá cả cho các nhà bán lẻ và thương hiệu TMĐT tại Việt Nam.</p>
                </div>
                <div>
                    <h4 class="font-label text-xs font-bold uppercase tracking-widest text-[#1c1c1a] mb-6">Product</h4>
                    <ul class="space-y-4">
                        <li><a class="text-on-surface/60 hover:text-blue-700 transition-colors text-sm font-label" href="#features">Price Tracking</a></li>
                        <li><a class="text-on-surface/60 hover:text-blue-700 transition-colors text-sm font-label" href="#pricing">Pricing</a></li>
                        <li><a class="text-on-surface/60 hover:text-blue-700 transition-colors text-sm font-label" href="{{ $loginUrl }}">Login</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-label text-xs font-bold uppercase tracking-widest text-[#1c1c1a] mb-6">Company</h4>
                    <ul class="space-y-4">
                        <li><a class="text-on-surface/60 hover:text-blue-700 transition-colors text-sm font-label" href="#">Terms of Service</a></li>
                        <li><a class="text-on-surface/60 hover:text-blue-700 transition-colors text-sm font-label" href="#">Privacy Policy</a></li>
                        <li><a class="text-on-surface/60 hover:text-blue-700 transition-colors text-sm font-label" href="mailto:support@checkgia.id.vn">Contact Support</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-label text-xs font-bold uppercase tracking-widest text-[#1c1c1a] mb-6">Newsletter</h4>
                    <div class="flex gap-2">
                        <input class="bg-surface px-4 py-2 rounded-lg border-none text-sm w-full focus:ring-2 focus:ring-primary" placeholder="Email của bạn" type="email"/>
                        <button class="bg-primary text-on-primary px-4 py-2 rounded-lg" type="button">
                            <span class="material-symbols-outlined text-sm">send</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="max-w-7xl mx-auto px-4 sm:px-8 mt-16 pt-8 border-t border-outline-variant/10 text-center text-xs text-on-surface/40 font-label">
                © {{ now()->year }} Check Giá. The Financial Sentinel.
            </div>
        </footer>
        <script>
            (function () {
                const btn = document.getElementById('mobileNavBtn');
                const nav = document.getElementById('mobileNav');
                if (!btn || !nav) return;
                function closeNav() {
                    nav.classList.add('hidden');
                    btn.setAttribute('aria-expanded', 'false');
                }
                function toggleNav() {
                    const open = nav.classList.contains('hidden');
                    if (open) {
                        nav.classList.remove('hidden');
                        btn.setAttribute('aria-expanded', 'true');
                    } else {
                        closeNav();
                    }
                }
                btn.addEventListener('click', toggleNav);
                nav.addEventListener('click', (e) => {
                    const a = e.target.closest('a');
                    if (a) closeNav();
                });
                window.addEventListener('resize', () => {
                    if (window.innerWidth >= 768) closeNav();
                });
            })();
        </script>
    </body>
</html>


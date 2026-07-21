<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Tracklio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        html {
            scroll-behavior: smooth;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-weight: 400;
            line-height: 1.6;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .container-custom {
            width: 100%;
            max-width: 1280px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 1rem;
            padding-right: 1rem;
        }
        @media (min-width: 640px) {
            .container-custom {
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }
        }
        @media (min-width: 1024px) {
            .container-custom {
                padding-left: 2rem;
                padding-right: 2rem;
            }
        }
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased">

    <!-- Navigation -->
    <nav class="fixed top-0 w-full bg-white/90 backdrop-blur-md border-b border-slate-200/80 z-50 transition-all duration-300">
        <div class="container-custom">
            <div class="flex justify-between items-center h-16 md:h-20">
                <a href="/" class="flex items-center gap-3 group">
                    <img
                        src="{{ asset('assets/images/tracklio.png') }}"
                        alt="Tracklio Logo"
                        class="h-8 w-8 md:h-10 md:w-10 object-contain transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-6"
                        loading="lazy"
                    >
                    <span class="text-xl md:text-2xl font-black text-indigo-600 tracking-tight transition-colors duration-300 group-hover:text-purple-600">
                        Tracklio
                    </span>
                </a>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="/#features" class="text-sm font-semibold text-slate-600 hover:text-indigo-600 transition-colors">Features</a>
                    <a href="/#why-tracklio" class="text-sm font-semibold text-slate-600 hover:text-indigo-600 transition-colors">Why Tracklio</a>
                    <a href="/#how-it-works" class="text-sm font-semibold text-slate-600 hover:text-indigo-600 transition-colors">How it works</a>
                    <a href="/#pricing" class="text-sm font-semibold text-slate-600 hover:text-indigo-600 transition-colors">Pricing</a>
                    <a href="{{ route('login') }}" class="text-sm font-semibold text-slate-600 hover:text-indigo-600 transition-colors">
                        Sign in
                    </a>
                    <a href="{{ route('register.page') }}" class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-full font-semibold text-sm hover:shadow-lg transition-all">
                        Start Free Trial
                    </a>
                </div>
                <div class="md:hidden">
                    <button type="button" class="text-slate-600 hover:text-slate-900 p-2 rounded-lg" id="mobile-menu-button">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="md:hidden hidden py-4 border-t border-slate-100" id="mobile-menu">
                <div class="flex flex-col space-y-4">
                    <a href="/#features" class="text-sm font-medium text-slate-600 hover:text-indigo-600 transition-colors py-2">Features</a>
                    <a href="/#why-tracklio" class="text-sm font-medium text-slate-600 hover:text-indigo-600 transition-colors py-2">Why Tracklio</a>
                    <a href="/#how-it-works" class="text-sm font-medium text-slate-600 hover:text-indigo-600 transition-colors py-2">How it works</a>
                    <a href="/#pricing" class="text-sm font-medium text-slate-600 hover:text-indigo-600 transition-colors py-2">Pricing</a>
                    <a href="{{ route('login') }}" class="text-sm font-medium text-slate-600 hover:text-indigo-600 transition-colors py-2">
                        Sign in
                    </a>
                    <a href="{{ route('register.page') }}" class="px-4 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-full font-medium text-sm text-center">
                        Start Free Trial
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Privacy Policy Content -->
    <section class="pt-28 md:pt-36 pb-16 bg-white">
        <div class="container-custom">
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 md:p-10 lg:p-12">
                    <h1 class="text-3xl md:text-4xl lg:text-5xl font-black text-slate-900 mb-6 tracking-tight">Privacy Policy</h1>

                    <p class="text-slate-600 mb-6">
                        At Tracklio, we value your privacy and are committed to protecting your personal information.
                        This Privacy Policy explains how we collect, use, and safeguard the information you provide while using our platform.
                    </p>

                    <hr class="my-8 border-slate-200">

                    <h2 class="text-2xl font-bold text-slate-900 mb-4">Information Collection</h2>
                    <p class="text-slate-600 mb-4">We collect information to enhance our services, which may include:</p>

                    <p class="text-slate-600 mb-3">
                        <strong class="text-slate-900">Personal Information:</strong>
                        Your name, contact details, job title, and affiliations are gathered through registrations,
                        newsletter subscriptions, and service usage.
                    </p>
                    <p class="text-slate-600 mb-3">
                        <strong class="text-slate-900">Financial Information:</strong>
                        For transactions, we collect financial data securely without retention on our servers.
                    </p>
                    <p class="text-slate-600 mb-6">
                        <strong class="text-slate-900">Technical Information:</strong>
                        We automatically collect technical details including IP addresses, browser specifications,
                        and device information to optimize website functionality.
                    </p>

                    <hr class="my-8 border-slate-200">

                    <h2 class="text-2xl font-bold text-slate-900 mb-4">Purpose of Information Use</h2>
                    <p class="text-slate-600 mb-4">We use your information for:</p>

                    <ul class="space-y-3 mb-6">
                        <li class="flex items-start gap-3 text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500 mt-1 text-sm"></i>
                            <span><strong class="text-slate-900">Service Fulfillment:</strong> Personal information is used to respond to inquiries, provide customer support, and fulfill service requests.</span>
                        </li>
                        <li class="flex items-start gap-3 text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500 mt-1 text-sm"></i>
                            <span><strong class="text-slate-900">Marketing and Promotions:</strong> With your explicit consent, we may send marketing communications, promotional content, and industry-related updates.</span>
                        </li>
                        <li class="flex items-start gap-3 text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500 mt-1 text-sm"></i>
                            <span><strong class="text-slate-900">Analytics and Enhancement:</strong> Non-personal information is analyzed to understand user behavior, preferences, and trends, allowing continuous improvement of our platform.</span>
                        </li>
                    </ul>

                    <hr class="my-8 border-slate-200">

                    <h2 class="text-2xl font-bold text-slate-900 mb-4">Information Sharing and Disclosure</h2>
                    <p class="text-slate-600 mb-4">We may share your information in the following situations:</p>

                    <ul class="space-y-3 mb-6">
                        <li class="flex items-start gap-3 text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500 mt-1 text-sm"></i>
                            <span><strong class="text-slate-900">Third-Party Service Providers:</strong> Collaboration with trusted providers to enhance service delivery and functionality.</span>
                        </li>
                        <li class="flex items-start gap-3 text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500 mt-1 text-sm"></i>
                            <span><strong class="text-slate-900">Legal Compliance:</strong> Information may be disclosed to comply with applicable laws, regulations, or legal procedures.</span>
                        </li>
                        <li class="flex items-start gap-3 text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500 mt-1 text-sm"></i>
                            <span><strong class="text-slate-900">Business Transfers:</strong> In mergers, acquisitions, or asset sales, your information may be transferred to the acquiring entity.</span>
                        </li>
                    </ul>

                    <hr class="my-8 border-slate-200">

                    <h2 class="text-2xl font-bold text-slate-900 mb-4">Cookies and Tracking Technologies</h2>
                    <p class="text-slate-600 mb-4">We use cookies and similar technologies:</p>

                    <ul class="space-y-3 mb-6">
                        <li class="flex items-start gap-3 text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500 mt-1 text-sm"></i>
                            <span><strong class="text-slate-900">Cookies:</strong> These help gather insights into browsing behavior, preferences, and session data for a more personalized user experience.</span>
                        </li>
                        <li class="flex items-start gap-3 text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500 mt-1 text-sm"></i>
                            <span><strong class="text-slate-900">Opt-Out:</strong> You can manage cookie preferences through browser settings, though disabling cookies may impact certain website functionalities.</span>
                        </li>
                    </ul>

                    <hr class="my-8 border-slate-200">

                    <h2 class="text-2xl font-bold text-slate-900 mb-4">Data Security</h2>
                    <p class="text-slate-600 mb-4">We implement industry-standard security measures to protect your information.</p>

                    <ul class="space-y-3 mb-6">
                        <li class="flex items-start gap-3 text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500 mt-1 text-sm"></i>
                            <span><strong class="text-slate-900">Security Protocols:</strong> Measures are in place to prevent unauthorized access, disclosure, alteration, and destruction of information.</span>
                        </li>
                        <li class="flex items-start gap-3 text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500 mt-1 text-sm"></i>
                            <span><strong class="text-slate-900">Encryption:</strong> Sensitive information is encrypted where appropriate for secure transmission and storage.</span>
                        </li>
                    </ul>

                    <hr class="my-8 border-slate-200">

                    <h2 class="text-2xl font-bold text-slate-900 mb-4">Your Rights</h2>
                    <p class="text-slate-600 mb-4">You have rights regarding your personal information:</p>

                    <ul class="space-y-3 mb-6">
                        <li class="flex items-start gap-3 text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500 mt-1 text-sm"></i>
                            <span><strong class="text-slate-900">Access and Correction:</strong> Contact us to access and correct your personal information.</span>
                        </li>
                        <li class="flex items-start gap-3 text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500 mt-1 text-sm"></i>
                            <span><strong class="text-slate-900">Opt-Out:</strong> You may unsubscribe from marketing communications at any time.</span>
                        </li>
                    </ul>

                    <hr class="my-8 border-slate-200">

                    <h2 class="text-2xl font-bold text-slate-900 mb-4">Children's Privacy</h2>
                    <p class="text-slate-600 mb-6">
                        Our services are not intended for individuals under the age of 16.
                        We do not knowingly collect or process personal information from minors
                        without parental consent.
                    </p>

                    <hr class="my-8 border-slate-200">

                    <h2 class="text-2xl font-bold text-slate-900 mb-4">Changes to the Privacy Policy</h2>
                    <p class="text-slate-600 mb-6">
                        We reserve the right to update this Privacy Policy periodically.
                        Material changes will be communicated through our website.
                    </p>

                    <hr class="my-8 border-slate-200">

                    <h2 class="text-2xl font-bold text-slate-900 mb-4">Contact Information</h2>
                    <p class="text-slate-600 mb-2">
                        For inquiries or concerns about this Privacy Policy, please contact us at:
                    </p>
                    <p class="text-slate-900 font-semibold">
                        <i class="fas fa-envelope text-indigo-500 mr-2"></i> support@tracklio.in
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-300 pt-16 pb-10">
        <div class="container-custom">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16">
                <div>
                    <div class="flex items-center gap-3 mb-6">
                        <img
                            src="{{ asset('assets/images/tracklio.png') }}"
                            alt="Tracklio Logo"
                            class="h-10 w-10 object-contain"
                        >
                        <span class="text-2xl font-black text-white">
                            Tracklio
                        </span>
                    </div>
                    <p class="text-slate-400 leading-relaxed mb-6 text-sm">
                        5th Floor, Grand Emporio, Shiv Habitat B-Block, Motera Stadium Rd, opp. S Mall, Motera, Ahmedabad, Gujarat 380005
                    </p>
                </div>
                <div>
                    <h4 class="font-bold text-white mb-6">Product</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="/#features" class="hover:text-white transition-colors">Features</a></li>
                        <li><a href="/#pricing" class="hover:text-white transition-colors">Pricing</a></li>
                        <li><a href="/#how-it-works" class="hover:text-white transition-colors">How it works</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-white mb-6">Company</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="#" class="hover:text-white transition-colors">About Us</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Careers</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-white mb-6">Legal</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="/privacy-policy" class="hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="/terms-and-conditions" class="hover:text-white transition-colors">Terms of Service</a></li>
                        <li><a href="/data-deletion" class="hover:text-white transition-colors">Data Deletion</a></li>
                    </ul>
                </div>
            </div>
            <div class="pt-8 border-t border-slate-800 text-center">
                <p class="text-sm text-slate-500">
                    © 2024 Tracklio Technologies Inc. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script>
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
            });
        }

        document.addEventListener('click', function(event) {
            if (mobileMenuButton && mobileMenu) {
                if (!mobileMenuButton.contains(event.target) && !mobileMenu.contains(event.target)) {
                    mobileMenu.classList.add('hidden');
                }
            }
        });

        function checkScroll() {
            const elements = document.querySelectorAll('.animate-on-scroll');
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                if (elementTop < windowHeight - 100) {
                    element.classList.add('visible');
                }
            });
        }

        checkScroll();
        window.addEventListener('scroll', checkScroll);

        const navbar = document.querySelector('nav');
        window.addEventListener('scroll', function() {
            if (window.scrollY > 100) {
                navbar.classList.add('shadow-md');
            } else {
                navbar.classList.remove('shadow-md');
            }
        });
    </script>

</body>
</html>
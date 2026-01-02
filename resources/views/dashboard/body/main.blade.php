<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Nova POS APP</title>

        <!-- Favicon -->
        <link rel="shortcut icon" href="{{ asset('assets/images/favicon.ico') }}"/>
        <link rel="stylesheet" href="{{ asset('assets/css/backend-plugin.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/backend.css?v=1.0.0') }}">

        <link rel="stylesheet" href="{{ asset('assets/vendor/line-awesome/dist/line-awesome/css/line-awesome.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/remixicon/fonts/remixicon.css') }}">

        @if (request()->route()->getName() !== 'dashboard')
        <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}">
        @endif

        <style>
            .iq-top-navbar .navbar {
                min-height: 64px;
                padding-top: 8px;
                padding-bottom: 8px;
            }

            .iq-sidebar {
                padding-top: 64px;
            }

            .content-page {
               /* padding-top: 85px;*/
            }

            @media (max-width: 991px) {
                .iq-sidebar {
                    padding-top: 90px;
                }

                .content-page {
                    padding-top: 40px;
                }
            }
        </style>

        @yield('specificpagestyles')
    </head>
<body>
    <!-- loader Start -->
    {{-- <div id="loading">
        <div id="loading-center"></div>
    </div> --}}
    <!-- loader END -->

    <!-- Wrapper Start -->
    <div class="wrapper">
        @include('dashboard.body.sidebar')

        @include('dashboard.body.navbar')

        <div class="content-page">
            @yield('container')
        </div>
    </div>
    <!-- Wrapper End-->

    @include('dashboard.body.footer')

    <!-- Backend Bundle JavaScript -->
    <script src="{{ asset('assets/js/backend-bundle.min.js') }}"></script>
    <script src="https://kit.fontawesome.com/4c897dc313.js" crossorigin="anonymous"></script>

    @yield('specificpagescripts')

    <!-- App JavaScript -->
    <script src="{{ asset('assets/js/app.js') }}"></script>
    
    <!-- Fix for stuck modal backdrops -->
    <script>
        // Remove any stuck modal backdrops on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Remove any modal backdrops that shouldn't be there
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(function(backdrop) {
                backdrop.remove();
            });
            
            // Remove modal-open class from body if no modals are open
            if (!document.querySelector('.modal.show')) {
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
        });
        
        // Also check periodically for stuck backdrops
        setInterval(function() {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            const openModals = document.querySelectorAll('.modal.show');
            
            if (openModals.length === 0 && backdrops.length > 0) {
                backdrops.forEach(function(backdrop) {
                    backdrop.remove();
                });
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
        }, 1000);
    </script>
</body>
</html>

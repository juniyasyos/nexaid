<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signing out...</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            color: #374151;
        }
        .container {
            text-align: center;
        }
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border-left-color: #3b82f6;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .iframes {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <h2>Signing out...</h2>
        <p>Please wait while we log you out of all applications securely.</p>
    </div>

    <div class="iframes">
        @foreach($logoutUris as $uri)
            <iframe src="{{ $uri }}" onload="iframeLoaded()"></iframe>
        @endforeach
    </div>

    <script>
        const totalIframes = {{ count($logoutUris) }};
        let loadedIframes = 0;
        let redirected = false;
        
        function redirectToLogin() {
            if (redirected) return;
            redirected = true;
            window.location.replace("{{ $redirectUrl }}");
        }

        function iframeLoaded() {
            loadedIframes++;
            if (loadedIframes >= totalIframes) {
                // Add a small delay to ensure cookies are cleared before redirect
                setTimeout(redirectToLogin, 500);
            }
        }

        // Fallback timeout: force redirect after 3 seconds even if some apps fail to load
        setTimeout(redirectToLogin, 3000);

        // If there are no iframes at all
        if (totalIframes === 0) {
            redirectToLogin();
        }
    </script>
</body>
</html>

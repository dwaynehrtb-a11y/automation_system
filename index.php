<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NU Academic System</title>
    <style>
        /* Embedded CSS to avoid path issues */
        :root {
            --primary: #1e40af;
            --primary-dark: #1e3a8a;
            --white: #ffffff;
            --gray-400: #9ca3af;
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --radius-xl: 1rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .splash-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.5s ease-in;
        }

        .splash-container {
            text-align: center;
            color: var(--white);
            max-width: 500px;
            padding: 2rem;
        }

        .splash-logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 2rem;
            border-radius: 50%;
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-xl);
            animation: pulse 2s infinite;
            font-size: 3rem;
        }

        .splash-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .splash-subtitle {
            font-size: 1.125rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            font-weight: 300;
        }

        .loading-bar {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-xl);
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .loading-progress {
            height: 100%;
            background: var(--white);
            border-radius: var(--radius-xl);
            animation: loading 3s ease-in-out;
        }

        .loading-text {
            font-size: 0.875rem;
            opacity: 0.8;
            animation: fadeInOut 1.5s infinite;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes loading {
            0% { width: 0%; }
            70% { width: 90%; }
            100% { width: 100%; }
        }

        @keyframes fadeInOut {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        .splash-wrapper.fade-out {
            animation: fadeOut 0.5s ease-out forwards;
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; visibility: hidden; }
        }
    </style>
</head>
<body>
    <div class="splash-wrapper" id="splashScreen">
        <div class="splash-container">
            <div class="splash-logo">
                <img src="assets/images/nu_logo.png" alt="NU Logo" style="width: 100px; height: 100px; border-radius: 50%;">
            </div>
            <h1 class="splash-title">NU Academic System</h1>
            <p class="splash-subtitle">National University Lipa</p>
            <div class="loading-bar">
                <div class="loading-progress"></div>
            </div>
            <p class="loading-text">Initializing system...</p>
        </div>
    </div>

    <script>
        // Test PHP functionality
        <?php
        echo "console.log('PHP is working correctly');";
        ?>

        // Redirect to login page after splash screen animation
        setTimeout(() => {
            const splash = document.getElementById('splashScreen');
            splash.classList.add('fade-out');

            setTimeout(() => {
                window.location.href = 'auth/login.php';
            }, 500);
        }, 3000); // 3 seconds splash screen
    </script>
</body>
</html>

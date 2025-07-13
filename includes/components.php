<!-- FAVICON -->
<link rel="apple-touch-icon" sizes="57x57" href="assets/img/favicon/apple-icon-57x57.png">
<link rel="apple-touch-icon" sizes="60x60" href="assets/img/favicon/apple-icon-60x60.png">
<link rel="apple-touch-icon" sizes="72x72" href="assets/img/favicon/apple-icon-72x72.png">
<link rel="apple-touch-icon" sizes="76x76" href="assets/img/favicon/apple-icon-76x76.png">
<link rel="apple-touch-icon" sizes="114x114" href="assets/img/favicon/apple-icon-114x114.png">
<link rel="apple-touch-icon" sizes="120x120" href="assets/img/favicon/apple-icon-120x120.png">
<link rel="apple-touch-icon" sizes="144x144" href="assets/img/favicon/apple-icon-144x144.png">
<link rel="apple-touch-icon" sizes="152x152" href="assets/img/favicon/apple-icon-152x152.png">
<link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicon/apple-icon-180x180.png">
<link rel="icon" type="image/png" sizes="192x192"  href="assets/img/favicon/android-icon-192x192.png">
<link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="96x96" href="assets/img/favicon/favicon-96x96.png">
<link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon/favicon-16x16.png">
<link rel="manifest" href="assets/img/favicon/manifest.json">
<meta name="msapplication-TileColor" content="#ffffff">
<meta name="msapplication-TileImage" content="assets/img/favicon/ms-icon-144x144.png">
<meta name="theme-color" content="#ffffff">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    body {
        font-family: 'Inter', sans-serif;
    }

    /* iOS-Inspired Design System */
    :root {
        --ios-background: #fff;
        --ios-card-background: #fff;
        --ios-text-primary: #1a1a1a;
        --ios-text-secondary: #8e8e93;
        --ios-accent-blue: #007aff;
        --ios-accent-green: #34c759;
        --ios-accent-red: #ff3b30;
        --ios-accent-orange: #ff9500;
        --ios-accent-purple: #5856d6;
    }
    body {
        background-color: var(--ios-background);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        color: var(--ios-text-primary);
    }
    .dashboard-container {
        background: var(--ios-card-background);
        backdrop-filter: blur(15px);
        border-radius: 20px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .menu-item {
        transition: all 0.3s ease;
        border-radius: 15px;
        position: relative;
    }
    .menu-item:hover {
        background-color: rgba(255,255,255,0.5);
        transform: scale(1.02);
    }
    .icon-circle {
        transition: all 0.3s ease;
        background-color: rgba(0,0,0,0.05);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .top-bar {
        background: var(--ios-card-background);
        backdrop-filter: blur(10px);
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .stat-card {
        background: rgba(255,255,255,0.7);
        border-radius: 15px;
        backdrop-filter: blur(10px);
        border-left: 4px solid;
        transition: transform 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-5px);
    }

    body {
        background: linear-gradient(135deg,rgb(23, 20, 113) 0%,rgb(63, 116, 239) 100%);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        color:rgb(15, 54, 139); /* Changed text color to white for better contrast */
        min-height: 100vh;
        background-attachment: fixed;
    }
    
    .min-h-screen {
        background: transparent;
    }
    
    .dashboard-container {
        background: var(--ios-card-background);
        backdrop-filter: blur(15px);
    }
</style>
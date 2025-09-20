<?php
// Get the directory of the current script
$currentDir = __DIR__;
$parentDir = dirname($currentDir);
$configPath = $parentDir . '/config.php';

// Calculate base path for navigation links
$scriptPath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
$pathParts = array_filter(explode('/', $scriptPath));
$depth = count($pathParts);
$basePath = str_repeat('../', $depth);
if ($basePath === '') $basePath = './';

require_once $configPath;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>

    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Critical JavaScript for sidebar functionality -->
    <script>
        // Sidebar functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const closeMenu = document.getElementById('closeMenu');

            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.add('open');
                });
            }

            if (closeMenu && sidebar) {
                closeMenu.addEventListener('click', function() {
                    sidebar.classList.remove('open');
                });
            }

            // Sidebar submenu toggles
            const submenuToggles = document.querySelectorAll('.sidebar-item-content');
            submenuToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const submenu = this.nextElementSibling;
                    const chevron = this.querySelector('.chevron-icon');

                    if (submenu && submenu.classList.contains('sidebar-submenu')) {
                        submenu.classList.toggle('hidden');
                        if (chevron) {
                            chevron.classList.toggle('rotate-180');
                        }
                    }
                });
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    if (sidebar && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                        sidebar.classList.remove('open');
                    }
                }
            });
        });
    </script>

    <!-- Inline critical styles for immediate rendering -->
    <style>
        /* Critical sidebar styles */
        .sidebar {
            width: 256px;
            background-color: #1e293b;
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 40;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 16px;
            border-bottom: 1px solid #374151;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 8px 0;
        }

        .sidebar-item {
            margin: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .sidebar-item:hover {
            background-color: #374151;
        }

        .sidebar-item.active {
            background-color: #1f49d4;
        }

        .sidebar-item a,
        .sidebar-item .sidebar-item-content {
            display: flex;
            align-items: center;
            padding: 12px;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .sidebar-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .main-content {
            margin-left: 256px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .top-bar {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .content-area {
            flex: 1;
            padding: 24px;
            background-color: #f8fafc;
            overflow-y: auto;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                width: 280px;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f6ff',
                            100: '#e0edff',
                            200: '#c9e0ff',
                            300: '#a8ceff',
                            400: '#86b2ff',
                            500: '#5e8eff',
                            600: '#3d6bff',
                            700: '#2d5af1',
                            800: '#1f49d4',
                            900: '#1d3fab',
                        },
                        gray: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body>
<script>
        // Initialize feather icons
        feather.replace();
        
        // Initialize AOS animations
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });

        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const closeMenu = document.getElementById('closeMenu');
        const sidebar = document.getElementById('sidebar');

        if (menuToggle && closeMenu && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.add('sidebar-open');
            });

            closeMenu.addEventListener('click', () => {
                sidebar.classList.remove('sidebar-open');
            });
        }

        // Toggle submenus
        document.querySelectorAll('nav li > div').forEach(item => {
            item.addEventListener('click', () => {
                const submenu = item.nextElementSibling;
                const chevron = item.querySelector('i[data-feather="chevron-down"]');
                
                if (submenu.classList.contains('hidden')) {
                    submenu.classList.remove('hidden');
                    chevron.classList.add('rotate-180');
                } else {
                    submenu.classList.add('hidden');
                    chevron.classList.remove('rotate-180');
                }
            });
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (sidebar && !sidebar.contains(e.target) && e.target !== menuToggle && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('sidebar-open');
            }
        });
    </script>
</body>
</html>
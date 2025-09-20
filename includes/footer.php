    <!-- JavaScript -->
    <?php
    // Calculate base path for navigation links
    $scriptPath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
    $pathParts = array_filter(explode('/', $scriptPath));
    $depth = count($pathParts);
    $basePath = str_repeat('../', $depth);
    if ($basePath === '') $basePath = './';
    ?>
    <script src="<?php echo $basePath; ?>assets/js/main.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>

    <script>
        // Initialize Feather icons if available
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // Set active navigation item
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const navLinks = document.querySelectorAll('.sidebar-item a');

            navLinks.forEach(link => {
                if (link.getAttribute('href') === currentPath.split('/').pop()) {
                    link.parentElement.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
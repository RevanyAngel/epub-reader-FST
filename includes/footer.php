    </main>
    
    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo t('app_name'); ?> - FST</p>
    </footer>
    
    <script src="assets/js/script.js"></script>
    <script>
        const themeToggle = document.getElementById('theme-toggle');
        const htmlElement = document.documentElement;
        const themeIcon = themeToggle.querySelector('i');

        // Fungsi untuk update icon
        function updateIcon(theme) {
            if (theme === 'dark') {
                themeIcon.classList.replace('fa-moon', 'fa-sun');
            } else {
                themeIcon.classList.replace('fa-sun', 'fa-moon');
            }
        }

        // Set icon saat pertama kali load
        updateIcon(htmlElement.getAttribute('data-theme'));

        themeToggle.addEventListener('click', () => {
            let currentTheme = htmlElement.getAttribute('data-theme');
            let newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            // Terapkan tema
            htmlElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Update icon
            updateIcon(newTheme);
        });
    </script>
</body>
</html>
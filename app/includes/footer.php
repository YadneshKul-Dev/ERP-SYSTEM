            </div>
            
            <footer class="main-footer">
                <p>&copy; <?= date('Y') ?> <?= APP_NAME ?> v<?= APP_VERSION ?>. All rights reserved. | <a href="/modules/backup/index.php">System Admin</a></p>
            </footer>
        </main>
    </div>
    
    <script src="/assets/js/script.js"></script>
    <script src="/assets/js/modules.js"></script>
    <script>
    (function() {
        var theme = localStorage.getItem('erp-theme') || '';
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        if (window.updateToggleIcon) updateToggleIcon();
    })();
    </script>
</body>
</html>

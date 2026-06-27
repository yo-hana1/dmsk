            </main> <!-- Close content-body -->

            <!-- Main Footer -->
            <footer class="main-footer no-print">
                <strong>Copyright &copy; 2026 PT. Wijaya Kusuma Perdana.</strong> All rights reserved.
            </footer>
        </div> <!-- Close main-content -->
    </div> <!-- Close wrapper -->

    <!-- Global Javascript for Sidebar, Modals, and UI helper -->
    <script>
        // Toggle Sidebar collapsed state on desktop
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('collapsed');
            } else {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            }
        }

        // Toggle Sidebar on mobile
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }

        // Helper to show modal
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
            }
        }

        // Helper to close modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
            }
        }

        // Handle escape key to close modals
        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const openModals = document.querySelectorAll('.modal.show');
                openModals.forEach(modal => modal.classList.remove('show'));
                
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebar-overlay');
                if (sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                }
            }
        });
    </script>
</body>
</html>

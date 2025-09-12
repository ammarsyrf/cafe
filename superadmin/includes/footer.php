        </main>
    </div>
</body>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const links = document.querySelectorAll('.sidebar-link');
    const pageTitle = document.getElementById('page-title');
    // Dapatkan nama file saat ini, contoh: "dashboard.php"
    const currentFile = window.location.pathname.split("/").pop();

    links.forEach(link => {
        // Dapatkan href dari link, contoh: "dashboard.php"
        const linkFile = link.getAttribute('href');
        
        // Jika nama file link sama dengan nama file saat ini
        if (linkFile === currentFile) {
            link.classList.add('active');
            // Ganti judul halaman sesuai dengan menu yang aktif
            // .trim() untuk menghapus spasi ekstra
            pageTitle.innerText = link.innerText.trim();
        }
    });
});
</script>
</html>

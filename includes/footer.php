</main>
<footer class="footer pt-5 pb-4">
    <div class="container text-center text-muted">
        <p class="mb-2">UMU Rubaga Varsity Ball Voting System</p>
        <small>Developed by  <a href="https://github.com/Aijuka-Josbert" target="_blank" rel="noopener noreferrer">Lunatic</a> && <a href="tel:+256708854302">Mufasa</a></small>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php
$mainJsUrl = 'assets/js/main.js';
if (isset($config) && function_exists('asset_url_versioned')) {
    $mainJsUrl = asset_url_versioned('assets/js/main.js', $config);
}
?>
<script src="<?php echo h($mainJsUrl); ?>"></script>
</body>
</html>

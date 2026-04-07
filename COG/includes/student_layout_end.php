<!-- End .cog-main -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Session timeout -->
<?php include __DIR__ . '/session_modal.php'; ?>
<script>
    window.SESSION_TIMEOUT_SECS = <?= SESSION_TIMEOUT ?>;
</script>
<script src="../assets/js/session-timeout.js"></script>

<!-- Timeout progress bar animation -->
<script>
(function () {
    const TOTAL = window.SESSION_TIMEOUT_SECS;
    const bar   = document.getElementById('timeoutBar');
    let rem     = <?= Session::getRemainingTime() ?>;

    function tick() {
        rem = Math.max(0, rem - 1);
        const pct = (rem / TOTAL) * 100;
        bar.style.width      = pct + '%';
        bar.style.background = rem < 120 ? '#dc3545' : (rem < 300 ? '#ffc107' : '#800000');
    }
    tick(); // immediate paint
    setInterval(tick, 1000);
})();
</script>

<!-- COGBot chatbot widget -->
<?php include __DIR__ . '/chatbot.php'; ?>

<!-- Auto-close flash alerts -->
<script>
    setTimeout(function () {
        document.querySelectorAll('.alert-dismissible').forEach(function (el) {
            try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch (e) {}
        });
    }, 5000);
</script>
</body>
</html>
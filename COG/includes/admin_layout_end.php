<!-- end .main-content -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Session timeout -->
<?php include __DIR__ . '/session_modal.php'; ?>
<script>window.SESSION_TIMEOUT_SECS = <?= SESSION_TIMEOUT ?>;</script>
<script src="../assets/js/session-timeout.js"></script>

<!-- Timeout progress bar -->
<script>
(function () {
    const TOTAL = window.SESSION_TIMEOUT_SECS;
    const bar   = document.getElementById('timeoutBar');
    if (!bar) return;
    let rem = <?= Session::getRemainingTime() ?>;
    function tick() {
        rem = Math.max(0, rem - 1);
        bar.style.width      = ((rem / TOTAL) * 100) + '%';
        bar.style.background = rem < 120 ? '#dc3545' : (rem < 300 ? '#ffc107' : '#800000');
    }
    tick();
    setInterval(tick, 1000);
})();
</script>

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
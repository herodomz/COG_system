<!-- includes/session_modal.php  –  Insert just before </body> on authenticated pages -->
<div class="modal fade" id="sessionWarningModal" tabindex="-1" aria-labelledby="sessionWarningTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-warning text-dark border-0 rounded-top-4">
                <h5 class="modal-title fw-bold" id="sessionWarningTitle">
                    <i class="bi bi-clock-history me-2"></i>Session About to Expire
                </h5>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-hourglass-split display-3 text-warning mb-3 d-block"></i>
                <p class="fs-5 mb-1">Your session will expire in</p>
                <h2 class="fw-bold text-danger"><span id="sessionCountdown">120</span> seconds</h2>
                <p class="text-muted mt-2 small">Click <strong>Stay Logged In</strong> to continue your session.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pb-4">
                <button id="stayLoggedInBtn" class="btn btn-success px-5 rounded-pill">
                    <i class="bi bi-check-circle me-2"></i>Stay Logged In
                </button>
                <a href="/logout.php" class="btn btn-outline-danger px-4 rounded-pill">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout Now
                </a>
            </div>
        </div>
    </div>
</div>
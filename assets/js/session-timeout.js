/**
 * assets/js/session-timeout.js
 * Shows a warning modal 2 min before session expiry and auto-logs out at 0.
 * Depends on Bootstrap 5 Modal API being available.
 */
(function () {
    'use strict';

    const TIMEOUT_MS = (window.SESSION_TIMEOUT_SECS || 1800) * 1000;
    const WARNING_MS = 2 * 60 * 1000;   // show warning 2 min before expiry
    const PING_MS    = 5 * 60 * 1000;   // ping server every 5 min

    let warningTimer, logoutTimer, pingTimer;
    let lastActivity  = Date.now();
    let warningShown  = false;
    let countdownIv   = null;

    // ── Reset timers on activity ──────────────────────────────
    function resetTimers() {
        clearTimeout(warningTimer);
        clearTimeout(logoutTimer);
        clearInterval(countdownIv);
        warningShown = false;
        hideModal();

        warningTimer = setTimeout(showWarning, TIMEOUT_MS - WARNING_MS);
        logoutTimer  = setTimeout(doLogout,    TIMEOUT_MS);
    }

    // ── Show Bootstrap warning modal ──────────────────────────
    function showWarning() {
        warningShown = true;
        const modalEl = document.getElementById('sessionWarningModal');
        if (!modalEl || typeof bootstrap === 'undefined') {
            // Fallback: native confirm
            if (confirm('Your session is about to expire. Stay logged in?')) {
                pingServer();
                resetTimers();
            }
            return;
        }
        let bsModal = bootstrap.Modal.getInstance(modalEl);
        if (!bsModal) bsModal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
        bsModal.show();

        let secs = Math.floor(WARNING_MS / 1000);
        const el = document.getElementById('sessionCountdown');
        if (el) el.textContent = secs;

        countdownIv = setInterval(() => {
            secs = Math.max(0, secs - 1);
            if (el) el.textContent = secs;
            if (secs <= 0) clearInterval(countdownIv);
        }, 1000);
    }

    function hideModal() {
        const modalEl = document.getElementById('sessionWarningModal');
        if (!modalEl) return;
        const bsModal = bootstrap.Modal.getInstance(modalEl);
        if (bsModal) bsModal.hide();
    }

    function doLogout() {
        window.location.href = '/logout.php?timeout=1';
    }

    // ── Ping server to keep session alive ─────────────────────
    function pingServer() {
        fetch('/ping.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(r => r.json())
        .then(d => { if (d.status === 'expired') doLogout(); })
        .catch(() => {});
    }

    // ── User activity → reset timers (throttled to every 30 s) ─
    function onActivity() {
        const now = Date.now();
        if (now - lastActivity > 30_000) {
            lastActivity = now;
            if (!warningShown) resetTimers();
        }
    }

    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(ev =>
        document.addEventListener(ev, onActivity, { passive: true })
    );

    // ── "Stay Logged In" button ───────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('stayLoggedInBtn');
        if (btn) {
            btn.addEventListener('click', () => {
                pingServer();
                resetTimers();
            });
        }
    });

    // ── Boot ──────────────────────────────────────────────────
    pingTimer = setInterval(pingServer, PING_MS);
    resetTimers();
})();
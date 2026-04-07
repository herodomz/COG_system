<!-- includes/chatbot.php  –  Floating COGBot widget (include before </body> on student pages) -->
<style>
/* ── Floating toggle button ──────────────────────────────── */
#cogChatBtn {
    position:fixed; bottom:28px; right:28px;
    width:58px; height:58px;
    background:linear-gradient(135deg,#800000,#660000);
    color:#fff; border:none; border-radius:50%;
    font-size:24px; cursor:pointer;
    box-shadow:0 6px 20px rgba(128,0,0,.45);
    z-index:1060;
    transition:transform .2s, box-shadow .2s;
    display:flex; align-items:center; justify-content:center;
}
#cogChatBtn:hover { transform:scale(1.1); box-shadow:0 8px 28px rgba(128,0,0,.6); }
#cogChatBtn .chat-notif-badge {
    position:absolute; top:-4px; right:-4px;
    background:#dc3545; color:#fff; border-radius:50%;
    width:20px; height:20px; font-size:11px; font-weight:700;
    display:none; align-items:center; justify-content:center;
}

/* ── Chat window ─────────────────────────────────────────── */
#cogChatWindow {
    position:fixed; bottom:100px; right:28px;
    width:360px; max-height:530px;
    background:#fff; border-radius:20px;
    box-shadow:0 12px 40px rgba(0,0,0,.2);
    display:flex; flex-direction:column;
    z-index:1059; overflow:hidden;
    opacity:0; transform:scale(.92) translateY(12px);
    pointer-events:none;
    transition:opacity .25s, transform .25s;
}
#cogChatWindow.cog-open {
    opacity:1; transform:scale(1) translateY(0);
    pointer-events:all;
}

/* header */
#cogChatHeader {
    background:linear-gradient(135deg,#800000,#660000);
    color:#fff; padding:14px 18px;
    display:flex; align-items:center; gap:10px; flex-shrink:0;
}
#cogChatHeader .bot-avatar {
    width:36px; height:36px; background:rgba(255,255,255,.2);
    border-radius:50%; display:flex; align-items:center; justify-content:center;
    font-size:18px;
}
#cogChatHeader .bot-info .bot-name  { font-weight:700; font-size:15px; line-height:1.2; }
#cogChatHeader .bot-info .bot-sub   { font-size:11px; opacity:.85; }
.cog-close-btn {
    margin-left:auto; background:none; border:none; color:#fff;
    font-size:20px; cursor:pointer; opacity:.8; line-height:1; padding:0;
}
.cog-close-btn:hover { opacity:1; }

/* messages */
#cogChatMsgs {
    flex:1; overflow-y:auto; padding:14px 16px;
    display:flex; flex-direction:column; gap:10px;
    background:#f8f9fa;
}
.cog-msg {
    max-width:84%; padding:10px 14px; border-radius:16px;
    font-size:14px; line-height:1.5; word-break:break-word;
}
.cog-msg.bot {
    background:#fff; color:#333;
    border:1px solid #e9ecef; align-self:flex-start;
    border-bottom-left-radius:4px;
}
.cog-msg.user {
    background:linear-gradient(135deg,#800000,#660000);
    color:#fff; align-self:flex-end;
    border-bottom-right-radius:4px;
}
.cog-msg.typing { color:#999; font-style:italic; }
.cog-msg .cog-time { font-size:10px; opacity:.55; margin-top:5px; display:block; }

/* quick replies */
#cogQuickReplies {
    display:flex; flex-wrap:wrap; gap:6px;
    padding:6px 16px 10px; background:#f8f9fa;
}
.cog-qr {
    background:#fff; border:1px solid #dee2e6;
    border-radius:20px; padding:5px 12px;
    font-size:12px; cursor:pointer;
    color:#800000; transition:all .2s;
}
.cog-qr:hover { background:#800000; color:#fff; border-color:#800000; }

/* input area */
#cogChatInputRow {
    display:flex; gap:8px; padding:12px 14px;
    background:#fff; border-top:1px solid #e9ecef; flex-shrink:0;
}
#cogChatInput {
    flex:1; border:1px solid #dee2e6; border-radius:22px;
    padding:9px 16px; font-size:14px; outline:none;
    height:40px; line-height:1.4; resize:none;
    transition:border-color .2s;
}
#cogChatInput:focus { border-color:#800000; }
#cogChatSend {
    width:40px; height:40px; flex-shrink:0;
    background:linear-gradient(135deg,#800000,#660000);
    border:none; border-radius:50%; color:#fff;
    font-size:16px; cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    transition:transform .2s;
}
#cogChatSend:hover:not(:disabled) { transform:scale(1.1); }
#cogChatSend:disabled { opacity:.45; cursor:not-allowed; }

@media(max-width:420px) {
    #cogChatWindow { width:calc(100vw - 16px); right:8px; }
}
</style>

<!-- Toggle button -->
<button id="cogChatBtn" aria-label="Open COGBot chat" title="Chat with COGBot">
    <i class="bi bi-chat-dots-fill"></i>
    <span class="chat-notif-badge" id="cogNotifBadge"></span>
</button>

<!-- Chat window -->
<div id="cogChatWindow" role="dialog" aria-label="COGBot Assistant">
    <div id="cogChatHeader">
        <div class="bot-avatar"><i class="bi bi-robot"></i></div>
        <div class="bot-info">
            <div class="bot-name">COGBot</div>
            <div class="bot-sub">● Online – OLSHCO Assistant</div>
        </div>
        <button class="cog-close-btn" id="cogCloseBtn" aria-label="Close chat">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div id="cogChatMsgs" aria-live="polite"></div>

    <div id="cogQuickReplies">
        <button class="cog-qr">How to request COG?</button>
        <button class="cog-qr">Check request status</button>
        <button class="cog-qr">Payment process</button>
        <button class="cog-qr">Processing time</button>
        <button class="cog-qr">Requirements to claim</button>
    </div>

    <div id="cogChatInputRow">
        <input id="cogChatInput" type="text"
               placeholder="Type your question…" maxlength="400" autocomplete="off"
               aria-label="Chat message">
        <button id="cogChatSend" aria-label="Send message">
            <i class="bi bi-send-fill"></i>
        </button>
    </div>
</div>

<script>
(function () {
    'use strict';

    /* ── DOM refs ── */
    const toggleBtn = document.getElementById('cogChatBtn');
    const chatWin   = document.getElementById('cogChatWindow');
    const closeBtn  = document.getElementById('cogCloseBtn');
    const input     = document.getElementById('cogChatInput');
    const sendBtn   = document.getElementById('cogChatSend');
    const msgBox    = document.getElementById('cogChatMsgs');
    const qrDiv     = document.getElementById('cogQuickReplies');
    const badge     = document.getElementById('cogNotifBadge');

    let history     = [];
    let isOpen      = false;
    let hasGreeted  = false;

    /* ── Helpers ── */
    function timestamp() {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function escHtml(s) {
        return String(s)
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/\n/g,'<br>');
    }

    function appendMsg(text, role) {
        const div = document.createElement('div');
        div.className = 'cog-msg ' + role;
        div.innerHTML = escHtml(text) + '<span class="cog-time">' + timestamp() + '</span>';
        msgBox.appendChild(div);
        msgBox.scrollTop = msgBox.scrollHeight;
        return div;
    }

    /* ── Toggle open/close ── */
    function openChat() {
        isOpen = true;
        chatWin.classList.add('cog-open');
        badge.style.display = 'none';
        if (!hasGreeted) {
            hasGreeted = true;
            appendMsg(
                '👋 Hi there! I\'m COGBot, your OLSHCO assistant.\n'
              + 'I can help you with COG requests, payments, and more. '
              + 'How can I help you today?',
                'bot'
            );
        }
        setTimeout(() => input.focus(), 260);
    }

    function closeChat() {
        isOpen = false;
        chatWin.classList.remove('cog-open');
    }

    toggleBtn.addEventListener('click', () => isOpen ? closeChat() : openChat());
    closeBtn.addEventListener('click', closeChat);

    /* Close on Esc key */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && isOpen) closeChat();
    });

    /* ── Send message ── */
    async function sendMessage(text) {
        text = text.trim();
        if (!text || sendBtn.disabled) return;

        appendMsg(text, 'user');
        history.push({ role: 'user', content: text });

        // Hide quick replies after first message
        qrDiv.style.display = 'none';

        input.value      = '';
        sendBtn.disabled = true;

        const typingEl = appendMsg('COGBot is typing…', 'bot typing');

        try {
            const res = await fetch('/chatbot_api.php', {
                method:  'POST',
                headers: {
                    'Content-Type':    'application/json',
                    'X-Requested-With':'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message: text, history }),
            });

            const data = await res.json();
            typingEl.remove();

            if (data.reply) {
                appendMsg(data.reply, 'bot');
                history.push({ role: 'assistant', content: data.reply });
                // Keep history manageable
                if (history.length > 20) history = history.slice(-20);
            } else {
                appendMsg(data.error || 'Something went wrong. Please try again.', 'bot');
            }
        } catch (err) {
            typingEl.remove();
            appendMsg('Connection error. Please check your internet and try again.', 'bot');
        }

        sendBtn.disabled = false;
        input.focus();
    }

    /* ── Event bindings ── */
    sendBtn.addEventListener('click', () => sendMessage(input.value));

    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage(input.value);
        }
    });

    // Quick-reply buttons
    qrDiv.querySelectorAll('.cog-qr').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!isOpen) openChat();
            sendMessage(btn.textContent);
        });
    });

    // Show badge when chat is closed and a new message arrives
    // (called from sendMessage if chat is closed)

})();
</script>
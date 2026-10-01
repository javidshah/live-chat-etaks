jQuery(document).ready(function($) {
    let sessionId = localStorage.getItem('slc_session_id');
    if (!sessionId) {
        sessionId = 'sess_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
        localStorage.setItem('slc_session_id', sessionId);
    }

    function escapeHTML(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function formatTime(dateStr) {
        // Parse time cleanly
        let d = dateStr ? new Date(dateStr.replace(' ', 'T') + '+04:00') : new Date();
        if (isNaN(d.getTime())) {
            d = new Date();
        }
        return d.toLocaleDateString('az-AZ', {day:'2-digit', month:'2-digit', year:'numeric', timeZone:'Asia/Baku'}) +
               ' ' +
               d.toLocaleTimeString('az-AZ', {hour:'2-digit', minute:'2-digit', timeZone:'Asia/Baku'});
    }

    function closeSpeedDial() {
        $('#slc-speed-dial').removeClass('slc-speed-dial-open').addClass('slc-speed-dial-hidden').attr('aria-hidden', 'true');
        $('#slc-chat-fab').removeClass('slc-fab-active');
    }

    function openSpeedDial() {
        $('#slc-speed-dial').removeClass('slc-speed-dial-hidden').addClass('slc-speed-dial-open').attr('aria-hidden', 'false');
        $('#slc-chat-fab').addClass('slc-fab-active');
    }

    function toggleSpeedDial() {
        if ($('#slc-speed-dial').hasClass('slc-speed-dial-open')) {
            closeSpeedDial();
        } else {
            openSpeedDial();
        }
    }

    function openChatWindow() {
        $('#slc-chat-window').addClass('slc-visible');
        $('#slc-widget-wrap').hide();
        fetchMessages();
        $('#slc-message-input').focus();
    }

    function closeChatWindow() {
        $('#slc-chat-window').removeClass('slc-visible');
        $('#slc-widget-wrap').show();
        closeSpeedDial();
    }

    // Main FAB click handler
    $('#slc-chat-fab').on('click keypress', function(e) {
        if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
        
        let hasSpeedDial = $('#slc-speed-dial').length > 0;
        if (hasSpeedDial) {
            toggleSpeedDial();
        } else if (slc_ajax.enable_live_chat === '1') {
            openChatWindow();
        } else if (slc_ajax.enable_whatsapp === '1' && slc_ajax.whatsapp_number) {
            let phone = (slc_ajax.whatsapp_number || '').replace(/[^0-9]/g, '');
            let waUrl = 'https://api.whatsapp.com/send?phone=' + phone;
            if (slc_ajax.whatsapp_msg) waUrl += '&text=' + encodeURIComponent(slc_ajax.whatsapp_msg);
            window.open(waUrl, '_blank');
        }
    });

    // Speed Dial item click - Live Chat
    $('#slc-dial-chat').on('click keypress', function(e) {
        if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
        closeSpeedDial();
        openChatWindow();
    });

    // Speed Dial item click - WhatsApp
    $('#slc-dial-wa').on('click', function() {
        closeSpeedDial();
    });

    // Close chat window
    $('#slc-chat-close').on('click keypress', function(e) {
        if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
        closeChatWindow();
    });

    // Close speed dial when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#slc-widget-wrap').length) {
            closeSpeedDial();
        }
    });

    function fetchMessages() {
        if (typeof slc_ajax === 'undefined') return;

        $.post(slc_ajax.ajax_url, {
            action: 'slc_get_messages',
            session_id: sessionId,
            nonce: slc_ajax.nonce
        }, function(response) {
            if (response.success && Array.isArray(response.data)) {
                let welcomeMsg = slc_ajax.welcome_msg || 'Hello! How can we help you today?';
                let brandName  = slc_ajax.brand_name || 'Support';
                let youLabel   = (slc_ajax.i18n && slc_ajax.i18n.you) ? slc_ajax.i18n.you : 'You';

                let html = '<div class="slc-msg-wrapper admin">' +
                    '<div class="slc-msg admin">' + escapeHTML(welcomeMsg) + '</div>' +
                    '<div class="slc-msg-time">' + escapeHTML(brandName) + '</div>' +
                '</div>';
                
                response.data.forEach(function(msg) {
                    let label = (msg.sender === 'admin') ? brandName : youLabel;
                    let time  = msg.time ? formatTime(msg.time) : '';
                    let senderClass = (msg.sender === 'admin') ? 'admin' : 'user';

                    html += '<div class="slc-msg-wrapper ' + senderClass + '">' +
                        '<div class="slc-msg ' + senderClass + '">' + escapeHTML(msg.message) + '</div>' +
                        '<div class="slc-msg-time">' + escapeHTML(label) + ' · ' + escapeHTML(time) + '</div>' +
                    '</div>';
                });

                $('#slc-messages').html(html);
                $('#slc-typing-indicator').hide();
                scrollToBottom();
            }
        });
    }

    function scrollToBottom() {
        let chatBody = $('#slc-chat-body');
        if (chatBody.length) {
            chatBody.scrollTop(chatBody[0].scrollHeight);
        }
    }

    function showTyping() {
        $('#slc-typing-indicator').show();
        scrollToBottom();
        setTimeout(function() {
            fetchMessages();
        }, 1500);
    }

    $('#slc-send-btn').on('click', function() {
        let input = $('#slc-message-input');
        let msg = input.val().trim();
        if (!msg) return;

        input.val('');

        let youLabel = (slc_ajax.i18n && slc_ajax.i18n.you) ? slc_ajax.i18n.you : 'You';
        let now = new Date();
        let timeStr = formatTime(now.toISOString());

        $('#slc-messages').append(
            '<div class="slc-msg-wrapper user">' +
                '<div class="slc-msg user">' + escapeHTML(msg) + '</div>' +
                '<div class="slc-msg-time">' + escapeHTML(youLabel) + ' · ' + escapeHTML(timeStr) + '</div>' +
            '</div>'
        );
        scrollToBottom();

        $.post(slc_ajax.ajax_url, {
            action: 'slc_send_message',
            session_id: sessionId,
            message: msg,
            nonce: slc_ajax.nonce
        }, function(response) {
            if (response.success) {
                showTyping();
            }
        });
    });

    $('#slc-message-input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#slc-send-btn').click();
        }
    });

    // Poll for new messages every 5 seconds if chat is open
    setInterval(function() {
        if ($('#slc-chat-window').hasClass('slc-visible')) {
            fetchMessages();
        }
    }, 5000);
});

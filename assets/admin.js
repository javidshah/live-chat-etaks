jQuery(document).ready(function($) {
    let currentSession = null;
    let isCurrentSessionArchived = 0;
    const today = (typeof slc_admin_data !== 'undefined' && slc_admin_data.today) ? slc_admin_data.today : '';
    const i18n = (typeof slc_admin_data !== 'undefined' && slc_admin_data.i18n) ? slc_admin_data.i18n : {};

    function escapeHTML(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Format date for display
    function formatTime(dateStr) {
        if (!dateStr) return '';
        let d = new Date(dateStr.replace(' ', 'T') + '+04:00');
        if (isNaN(d.getTime())) {
            d = new Date();
        }
        return d.toLocaleDateString(undefined, {day:'2-digit', month:'2-digit', year:'numeric'})
            + ' ' + d.toLocaleTimeString(undefined, {hour:'2-digit', minute:'2-digit'});
    }

    // Tab switching (Active / Archived)
    $('.slc-tab').on('click', function() {
        $('.slc-tab').removeClass('active');
        $(this).addClass('active');
        $('.slc-sessions-ul').hide();
        let target = $(this).data('target');
        $('#' + target).show();

        if (target === 'archived-list') {
            $('#slc-filter-bar').hide();
            $('#slc-search-bar').show();
            $('#slc-search-input').val('').trigger('input');
        } else {
            $('#slc-search-bar').hide();
            $('#slc-filter-bar').show();
            applyDateFilter();
        }
    });

    // Date filter (active sessions)
    function applyDateFilter() {
        let filterDate = $('#slc-date-filter').val();
        $('#active-list li').each(function() {
            if ($(this).hasClass('no-chats')) return;
            let sessionDate = $(this).data('date');
            if (!filterDate || sessionDate === filterDate) {
                $(this).removeClass('slc-hidden');
            } else {
                $(this).addClass('slc-hidden');
            }
        });

        let visible = $('#active-list li:not(.slc-hidden):not(.no-chats)').length;
        $('#active-list .slc-no-filter-results').remove();
        if (visible === 0 && filterDate) {
            let msg = i18n.no_chats_date || 'No conversations found on this date.';
            $('#active-list').append('<li class="no-chats slc-no-filter-results"><div class="slc-empty-desc">' + escapeHTML(msg) + '</div></li>');
        }
    }

    $('#slc-date-filter').on('change', applyDateFilter);
    applyDateFilter();

    $('#slc-clear-filter').on('click', function() {
        $('#slc-date-filter').val('');
        applyDateFilter();
    });

    // Archive search
    $('#slc-search-input').on('input', function() {
        let q = $(this).val().toLowerCase();
        $('#archived-list li:not(.no-chats)').each(function() {
            let text = $(this).text().toLowerCase();
            $(this).toggleClass('slc-hidden', q !== '' && text.indexOf(q) === -1);
        });
    });

    // Select session
    $('.slc-sessions-ul').on('click', 'li:not(.no-chats):not(.slc-hidden)', function() {
        $('.slc-sessions-ul li').removeClass('active');
        $(this).addClass('active');
        currentSession = String($(this).data('session'));
        isCurrentSessionArchived = $(this).data('archived');
        $('#slc-active-session').val(currentSession);

        // Show active chat area and hide empty placeholder
        $('#slc-no-session-placeholder').hide();
        $('#slc-active-session-pane').show();

        let isToday = ($(this).data('date') === today);
        let prefix = isToday ? (i18n.today_prefix || 'Today · User: ') : (i18n.user_prefix || 'User: ');
        let shortId = currentSession.substring(0, 8);
        $('#slc-current-session-title').text(prefix + shortId);
        $('.slc-header-user-avatar').text(shortId.substring(0, 2).toUpperCase());

        if (parseInt(isCurrentSessionArchived, 10) === 0) {
            $('#slc-archive-btn').show();
        } else {
            $('#slc-archive-btn').hide();
        }
        fetchAdminMessages();
    });

    // Archive conversation
    $('#slc-archive-btn').on('click', function() {
        if (!currentSession) return;
        let btn = $(this);
        btn.prop('disabled', true).text(i18n.please_wait || 'Please wait...');

        $.post(slc_admin_data.ajax_url, {
            action: 'slc_archive_session',
            session_id: currentSession,
            nonce: slc_admin_data.nonce
        }, function(r) {
            if (r.success) {
                location.reload();
            } else {
                alert(r.data && r.data.message ? r.data.message : 'Error archiving.');
                btn.prop('disabled', false).text(i18n.archive || 'Archive');
            }
        });
    });

    // Fetch messages for active session
    function fetchAdminMessages() {
        if (!currentSession) return;
        $.get(slc_admin_data.ajax_url, {
            action: 'slc_get_messages',
            session_id: currentSession,
            nonce: slc_admin_data.nonce
        }, function(r) {
            let messages = null;
            if (r.success) {
                if (Array.isArray(r.data)) {
                    messages = r.data;
                } else if (r.data && Array.isArray(r.data.messages)) {
                    messages = r.data.messages;
                }
            }

            if (messages) {
                let html = '';
                let hasAdminReply = false;
                messages.forEach(function(m) {
                    let isAdmin = (m.sender === 'admin' || parseInt(m.is_admin, 10) === 1);
                    if (isAdmin) hasAdminReply = true;
                    let cls = isAdmin ? 'admin' : 'user';
                    let t = formatTime(m.time);
                    html += '<div class="slc-msg-wrapper ' + cls + '">' +
                        '<div class="slc-msg ' + cls + '">' + escapeHTML(m.message) + '</div>' +
                        '<div class="slc-msg-time">' + t + '</div>' +
                    '</div>';
                });

                if (messages.length > 0 && !hasAdminReply) {
                    let waitingMsg = i18n.awaiting_reply || 'Awaiting reply...';
                    html += '<div class="slc-msg-wrapper admin">' +
                        '<div class="slc-dots"><span></span><span></span><span></span></div>' +
                        '<span>' + escapeHTML(waitingMsg) + '</span>' +
                    '</div>';
                }

                $('#slc-admin-messages').html(html);
                scrollToBottom();
            }
        });
    }

    function scrollToBottom() {
        let b = $('#slc-admin-messages');
        if (b.length) {
            b.scrollTop(b[0].scrollHeight);
        }
    }

    // Send reply
    $('#slc-admin-send').on('click', function() {
        if (!currentSession) {
            alert(i18n.select_session_alert || 'Please select a conversation first.');
            return;
        }
        let input = $('#slc-admin-input');
        let msg = input.val().trim();
        if (!msg) return;

        input.val('');
        $.post(slc_admin_data.ajax_url, {
            action: 'slc_send_message',
            session_id: currentSession,
            message: msg,
            is_admin: 1,
            nonce: slc_admin_data.nonce
        }, function(r) {
            if (r.success) {
                fetchAdminMessages();
                if (parseInt(isCurrentSessionArchived, 10) === 1) {
                    location.reload();
                }
            } else {
                alert(r.data && r.data.message ? r.data.message : (i18n.error_sending || 'Error sending message.'));
            }
        });
    });

    $('#slc-admin-input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#slc-admin-send').click();
        }
    });

    // Auto-refresh messages every 5s
    setInterval(function() {
        if (currentSession) {
            fetchAdminMessages();
        }
    }, 5000);

    // ─── Settings Page Handlers ───
    if (typeof slc_settings_data !== 'undefined') {
        const settingsI18n = slc_settings_data.i18n || {};

        function applyLiveColor(color) {
            if (!color) return;
            color = $.trim(color);
            if (color.charAt(0) !== '#') {
                color = '#' + color;
            }
            if (/^#([0-9A-Fa-f]{3}){1,2}$/.test(color)) {
                var hex6 = color;
                if (color.length === 4) {
                    hex6 = '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3];
                }
                $('#slc_color_picker').val(hex6);
                $('#slc_fab_preview_wrap').attr('style', 'background-color:' + hex6 + ' !important;');
                document.documentElement.style.setProperty('--slc-primary', hex6);
            }
        }

        // Color picker sync
        $('#slc_color_picker').on('input change', function() {
            var val = $(this).val();
            $('#slc_primary_color').val(val);
            $('#slc_fab_preview_wrap').attr('style', 'background-color:' + val + ' !important;');
            document.documentElement.style.setProperty('--slc-primary', val);
        });

        // Primary color text field sync
        $('#slc_primary_color').on('input keyup change paste propertychange', function() {
            var val = $(this).val();
            applyLiveColor(val);
        });

        // Color presets
        $('.slc-preset-btn').on('click', function(e) {
            e.preventDefault();
            var color = $(this).data('color');
            $('#slc_primary_color').val(color);
            applyLiveColor(color);
        });

        // Live Chat Speed Dial Button Color handlers
        function applyLiveDialColor(color) {
            if (!color) return;
            color = $.trim(color);
            if (color.charAt(0) !== '#') {
                color = '#' + color;
            }
            if (/^#([0-9A-Fa-f]{3}){1,2}$/.test(color)) {
                var hex6 = color;
                if (color.length === 4) {
                    hex6 = '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3];
                }
                $('#slc_chat_btn_color_picker').val(hex6);
                $('#slc_dial_preview_wrap').attr('style', 'background-color:' + hex6 + ' !important;');
                document.documentElement.style.setProperty('--slc-chat-dial-color', hex6);
            }
        }

        $('#slc_chat_btn_color_picker').on('input change', function() {
            var val = $(this).val();
            $('#slc_chat_btn_color').val(val);
            $('#slc_dial_preview_wrap').attr('style', 'background-color:' + val + ' !important;');
            document.documentElement.style.setProperty('--slc-chat-dial-color', val);
        });

        $('#slc_chat_btn_color').on('input keyup change paste propertychange', function() {
            var val = $(this).val();
            applyLiveDialColor(val);
        });

        $('.slc-dial-preset-btn').on('click', function(e) {
            e.preventDefault();
            var color = $(this).data('color');
            $('#slc_chat_btn_color').val(color);
            applyLiveDialColor(color);
        });

        // WP Media Uploader
        var mediaFrame;
        $('.slc-media-upload-btn').on('click', function(e) {
            e.preventDefault();
            var targetInput = $(this).data('target-input');
            var targetPreview = $(this).data('target-preview');

            mediaFrame = wp.media({
                title: settingsI18n.select_image || 'Select or Upload Image',
                button: { text: settingsI18n.use_image || 'Use this image' },
                multiple: false
            });

            mediaFrame.on('select', function() {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                $(targetInput).val(attachment.url);
                if (targetPreview) {
                    $(targetPreview).attr('src', attachment.url);
                }
            });

            mediaFrame.open();
        });

        // Reset to default button
        $('.slc-reset-btn').on('click', function() {
            var targetInput = $(this).data('target-input');
            var targetPreview = $(this).data('target-preview');
            var defUrl = $(this).data('default');
            $(targetInput).val(defUrl);
            if (targetPreview) {
                $(targetPreview).attr('src', defUrl);
            }
        });

        // Live input URL preview change
        $('#slc_chat_icon').on('input', function() {
            var url = $(this).val();
            $('#slc_icon_preview, #slc_dial_icon_preview').attr('src', url);
        });
        $('#slc_profile_image').on('input', function() {
            $('#slc_avatar_preview').attr('src', $(this).val());
        });

        // WhatsApp toggle visibility
        $('#slc_enable_whatsapp').on('change', function() {
            if ($(this).is(':checked')) {
                $('#slc_wa_fields').slideDown(150);
            } else {
                $('#slc_wa_fields').slideUp(150);
            }
        });

        // Language presets loader
        var langPresets = slc_settings_data.lang_presets || {};

        function applyLangPreset(code) {
            var targetCode = (code === 'auto') ? (slc_settings_data.site_locale || 'en_US') : code;
            if (!langPresets[targetCode]) {
                targetCode = 'en_US';
            }
            if (langPresets[targetCode]) {
                var p = langPresets[targetCode];
                $('#slc_chat_title').val(p.chat_title);
                $('#slc_chat_subtitle').val(p.chat_subtitle);
                $('#slc_welcome_message').val(p.welcome_message);
                $('#slc_auto_reply_message').val(p.auto_reply_message);
            }
        }

        $('#slc_load_lang_defaults').on('click', function() {
            var selected = $('#slc_chat_language').val();
            applyLangPreset(selected);
        });

        // Database Repair AJAX Action
        $('#slc_repair_db_btn').on('click', function() {
            var btn = $(this);
            var statusSpan = $('#slc_repair_status');
            btn.prop('disabled', true).text(settingsI18n.working || 'Working...');
            statusSpan.css('color', '#64748b').text(settingsI18n.recreating_db || 'Recreating database table...');

            $.post(slc_settings_data.ajax_url, {
                action: 'slc_repair_database',
                nonce: slc_settings_data.nonce
            }, function(r) {
                btn.prop('disabled', false).text(settingsI18n.repair_btn || 'Repair Database Table');
                if (r.success) {
                    statusSpan.css('color', '#166534').text(r.data && r.data.message ? r.data.message : (settingsI18n.repair_success || 'Database table repaired successfully!'));
                    setTimeout(function() {
                        location.reload();
                    }, 1200);
                } else {
                    statusSpan.css('color', '#991b1b').text(r.data && r.data.message ? r.data.message : (settingsI18n.repair_failed || 'Failed to repair table.'));
                }
            }).fail(function() {
                btn.prop('disabled', false).text(settingsI18n.repair_btn || 'Repair Database Table');
                statusSpan.css('color', '#991b1b').text(settingsI18n.repair_failed || 'Server communication error.');
            });
        });
    }
});

<?php
/**
 * Plugin Name: Live Chat - etaks
 * Plugin URI: https://etaks.az/
 * Description: Fast, modern, and customizable live support chat plugin for WordPress.
 * Version: 1.6.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: etaks.az | Javid Shahmuradov
 * Author URI: https://etaks.az
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: live-chat-etaks
 * Domain Path: /languages
 *
 * @package LiveChatEtaks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SLC_VERSION' ) ) {
    define( 'SLC_VERSION', '1.6.0' );
}
if ( ! defined( 'SLC_PLUGIN_DIR' ) ) {
    define( 'SLC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SLC_PLUGIN_URL' ) ) {
    define( 'SLC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Main Live_Chat_Etaks Class.
 */
class Live_Chat_Etaks {

    /**
     * Bootstrap the plugin.
     */
    public static function init() {
        // Handle activation & upgrade
        register_activation_hook( __FILE__, array( __CLASS__, 'activate_plugin' ) );
        add_action( 'plugins_loaded', array( __CLASS__, 'check_db_upgrade' ) );
        add_action( 'plugins_loaded', array( __CLASS__, 'migrate_legacy_settings' ) );
        add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
        add_action( 'admin_init', array( __CLASS__, 'check_legacy_conflict' ) );
        add_action( 'admin_init', array( __CLASS__, 'migrate_legacy_settings' ) );

        // Internationalization
        add_filter( 'plugin_locale', array( __CLASS__, 'custom_plugin_locale' ), 10, 2 );
        add_action( 'init', array( __CLASS__, 'load_textdomain' ) );

        // Enqueues & frontend widget
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
        add_action( 'wp_footer', array( __CLASS__, 'render_frontend_widget' ) );

        // Admin menu
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );

        // AJAX endpoints
        add_action( 'wp_ajax_slc_send_message', array( __CLASS__, 'ajax_send_message' ) );
        add_action( 'wp_ajax_nopriv_slc_send_message', array( __CLASS__, 'ajax_send_message' ) );
        add_action( 'wp_ajax_slc_get_messages', array( __CLASS__, 'ajax_get_messages' ) );
        add_action( 'wp_ajax_nopriv_slc_get_messages', array( __CLASS__, 'ajax_get_messages' ) );
        add_action( 'wp_ajax_slc_archive_session', array( __CLASS__, 'ajax_archive_session' ) );
        add_action( 'wp_ajax_slc_repair_database', array( __CLASS__, 'ajax_repair_database' ) );
    }

    /**
     * Check for conflicting legacy plugins safely and notify administrator.
     */
    public static function check_legacy_conflict() {
        if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            if ( is_plugin_active( 'caspian-live-chat/caspian-live-chat.php' ) ) {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Please deactivate the legacy Caspian Live Chat plugin to prevent conflicts with Live Chat - etaks.', 'live-chat-etaks' ) . '</p></div>';
                } );
            }
        }
    }

    /**
     * Clean and migrate any legacy settings (purging Caspian URLs, emojis, and old colors).
     */
    public static function migrate_legacy_settings() {
        $settings = get_option( 'slc_chat_settings', null );
        if ( ! is_array( $settings ) ) {
            return;
        }
        $updated = false;

        // Replace Caspian icon URLs with modern etaks assets
        if ( empty( $settings['chat_icon'] ) || false !== stripos( (string) $settings['chat_icon'], 'caspian' ) ) {
            $settings['chat_icon'] = SLC_PLUGIN_URL . 'assets/chat-icon.png';
            $updated = true;
        }
        if ( empty( $settings['profile_image'] ) || false !== stripos( (string) $settings['profile_image'], 'caspian' ) ) {
            $settings['profile_image'] = SLC_PLUGIN_URL . 'assets/chat-avatar.png';
            $updated = true;
        }
        // Clean legacy navy blue if present
        if ( isset( $settings['primary_color'] ) && '#0A2952' === strtoupper( (string) $settings['primary_color'] ) ) {
            $settings['primary_color'] = '#cc0000';
            $updated = true;
        }
        // Strip emojis
        $emoji_pattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
        if ( isset( $settings['welcome_message'] ) ) {
            $cleaned = trim( preg_replace( $emoji_pattern, '', (string) $settings['welcome_message'] ) );
            if ( $cleaned !== $settings['welcome_message'] ) {
                $settings['welcome_message'] = $cleaned;
                $updated = true;
            }
        }
        if ( isset( $settings['auto_reply_message'] ) ) {
            $cleaned = trim( preg_replace( $emoji_pattern, '', (string) $settings['auto_reply_message'] ) );
            if ( $cleaned !== $settings['auto_reply_message'] ) {
                $settings['auto_reply_message'] = $cleaned;
                $updated = true;
            }
        }

        if ( $updated ) {
            update_option( 'slc_chat_settings', $settings );
        }
    }

    /**
     * Activation hook callback.
     */
    public static function activate_plugin() {
        self::migrate_legacy_settings();
        self::create_db_table();
        update_option( 'slc_db_version', SLC_VERSION );
    }

    /**
     * Get active plugin locale based on settings or site default.
     */
    public static function get_active_locale() {
        $chosen_lang = self::get_setting( 'chat_language', 'auto' );
        if ( ! empty( $chosen_lang ) && 'auto' !== $chosen_lang ) {
            return $chosen_lang;
        }
        if ( function_exists( 'determine_locale' ) ) {
            return determine_locale();
        }
        return get_locale();
    }

    /**
     * Language filter to support custom widget language from settings.
     */
    public static function custom_plugin_locale( $locale, $domain ) {
        if ( 'live-chat-etaks' === $domain ) {
            $chosen_lang = self::get_setting( 'chat_language', 'auto' );
            if ( ! empty( $chosen_lang ) && 'auto' !== $chosen_lang ) {
                return $chosen_lang;
            }
        }
        return $locale;
    }

    /**
     * Load plugin textdomain with support for custom setting and both short/long codes.
     */
    public static function load_textdomain() {
        $locale = self::get_active_locale();

        $candidates = array( $locale );
        if ( strpos( $locale, '_' ) !== false ) {
            $candidates[] = substr( $locale, 0, strpos( $locale, '_' ) );
        } else {
            $map = array(
                'az' => 'az_AZ',
                'tr' => 'tr_TR',
                'ru' => 'ru_RU',
                'uz' => 'uz_UZ',
                'es' => 'es_ES',
                'fr' => 'fr_FR',
                'pt' => 'pt_PT',
                'ja' => 'ja_JP',
                'ar' => 'ar_AR',
                'zh' => 'zh_CN',
            );
            if ( isset( $map[ $locale ] ) ) {
                $candidates[] = $map[ $locale ];
            }
        }

        unload_textdomain( 'live-chat-etaks' );

        $loaded = false;
        foreach ( $candidates as $cand ) {
            $mofile = SLC_PLUGIN_DIR . 'languages/live-chat-etaks-' . $cand . '.mo';
            if ( file_exists( $mofile ) ) {
                $loaded = load_textdomain( 'live-chat-etaks', $mofile );
                if ( $loaded ) {
                    break;
                }
            }
        }

        if ( ! $loaded ) {
            load_textdomain( 'live-chat-etaks', SLC_PLUGIN_DIR . 'languages/live-chat-etaks-' . determine_locale() . '.mo' );
        }
    }

    /**
     * Get validated database table name.
     *
     * @return string
     */
     public static function get_table_name() {
         global $wpdb;
         return $wpdb->prefix . 'slc_messages';
     }

    /**
     * Database creation and upgrade.
     */
    public static function create_db_table() {
        global $wpdb;
        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            message text NOT NULL,
            sender varchar(20) NOT NULL,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            is_archived tinyint(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY is_archived (is_archived),
            KEY time (time)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Check if DB needs upgrade.
     */
    public static function check_db_upgrade() {
        if ( get_option( 'slc_db_version' ) !== SLC_VERSION ) {
            self::create_db_table();
            update_option( 'slc_db_version', SLC_VERSION );
        }
    }

    /**
     * Supported Languages.
     */
    public static function get_supported_languages() {
        return array(
            'auto'  => __( 'Auto (Site Language)', 'live-chat-etaks' ),
            'en_US' => 'English',
            'az_AZ' => 'Azərbaycan dili',
            'tr_TR' => 'Türkçe',
            'ru_RU' => 'Русский',
            'uz_UZ' => 'Oʻzbekcha',
            'es_ES' => 'Español',
            'fr_FR' => 'Français',
            'pt_PT' => 'Português',
            'ja'    => '日本語',
            'zh_CN' => '简体中文',
            'ar'    => 'العربية (RTL)',
        );
    }

    /**
     * Default language strings for each language.
     */
    public static function get_language_presets() {
        return array(
            'en_US' => array(
                'chat_title'         => 'Live Support',
                'chat_subtitle'      => 'Online Support',
                'welcome_message'    => 'Hello! How can we help you today?',
                'auto_reply_message' => 'Thank you for reaching out! Please provide your name and contact details (email or phone number), and our team will get back to you shortly.',
                'placeholder'        => 'Type your message...',
            ),
            'az_AZ' => array(
                'chat_title'         => 'Canlı Dəstək',
                'chat_subtitle'      => 'Onlayn dəstək',
                'welcome_message'    => 'Salam, necə yardımçı ola bilərik?',
                'auto_reply_message' => 'Zəhmət olmasa, adınızı və əlaqə vasitənizi (nömrə və ya email) qeyd edin, tezliklə sizə geri dönüş edəcəyik.',
                'placeholder'        => 'Mesajınızı yazın...',
            ),
            'tr_TR' => array(
                'chat_title'         => 'Canlı Destek',
                'chat_subtitle'      => 'Çevrimiçi Destek',
                'welcome_message'    => 'Merhaba! Size nasıl yardımcı olabiliriz?',
                'auto_reply_message' => 'Bizimle iletişime geçtiğiniz için teşekkür ederiz! Lütfen adınızı ve iletişim bilgilerinizi (e-posta veya telefon) belirtin, en kısa sürede size dönüş yapacağız.',
                'placeholder'        => 'Mesajınızı yazın...',
            ),
            'ru_RU' => array(
                'chat_title'         => 'Онлайн-поддержка',
                'chat_subtitle'      => 'Служба поддержки онлайн',
                'welcome_message'    => 'Здравствуйте! Чем мы можем вам помочь?',
                'auto_reply_message' => 'Спасибо за обращение! Пожалуйста, укажите ваше имя и контакты (email или телефон), и мы свяжемся с вами в ближайшее время.',
                'placeholder'        => 'Введите сообщение...',
            ),
            'uz_UZ' => array(
                'chat_title'         => 'Jonli muloqot',
                'chat_subtitle'      => 'Onlayn yordam',
                'welcome_message'    => 'Assalomu alaykum! Sizga qanday yordam bera olamiz?',
                'auto_reply_message' => 'Murojaatingiz uchun tashakkur! Iltimos, ismingiz va aloqa ma\'lumotlaringizni (elektron pochta yoki telefon) qoldiring, tez orada siz bilan bog\'lanamiz.',
                'placeholder'        => 'Xabaringizni yozing...',
            ),
            'es_ES' => array(
                'chat_title'         => 'Soporte en Vivo',
                'chat_subtitle'      => 'Soporte en línea',
                'welcome_message'    => '¡Hola! ¿En qué podemos ayudarte hoy?',
                'auto_reply_message' => '¡Gracias por contactarnos! Por favor, indica tu nombre y datos de contacto (correo o teléfono), y nuestro equipo te responderá en breve.',
                'placeholder'        => 'Escribe tu mensaje...',
            ),
            'fr_FR' => array(
                'chat_title'         => 'Support en Direct',
                'chat_subtitle'      => 'Support en ligne',
                'welcome_message'    => 'Bonjour ! Comment pouvons-nous vous aider aujourd\'hui ?',
                'auto_reply_message' => 'Merci de nous avoir contactés ! Veuillez indiquer votre nom et vos coordonnées (e-mail ou téléphone), et notre équipe vous répondra rapidement.',
                'placeholder'        => 'Écrivez votre message...',
            ),
            'pt_PT' => array(
                'chat_title'         => 'Suporte ao Vivo',
                'chat_subtitle'      => 'Suporte online',
                'welcome_message'    => 'Olá! Como podemos ajudar hoje?',
                'auto_reply_message' => 'Obrigado pelo contacto! Por favor, indique o seu nome e dados de contacto (e-mail ou telefone), e a nossa equipa responderá em breve.',
                'placeholder'        => 'Digite a sua mensagem...',
            ),
            'ja' => array(
                'chat_title'         => 'ライブサポート',
                'chat_subtitle'      => 'オンラインサポート',
                'welcome_message'    => 'こんにちは！どのようなご用件でしょうか？',
                'auto_reply_message' => 'お問い合わせありがとうございます。お名前とご連絡先（メールまたはお電話番号）をお知らせください。折り返しご連絡いたします。',
                'placeholder'        => 'メッセージを入力...',
            ),
            'zh_CN' => array(
                'chat_title'         => '在线客服',
                'chat_subtitle'      => '在线支持',
                'welcome_message'    => '您好！请问有什么可以帮助您的？',
                'auto_reply_message' => '感谢您的咨询！请留下您的姓名和联系方式（邮箱或电话），我们将尽快与您取得联系。',
                'placeholder'        => '输入消息...',
            ),
            'ar' => array(
                'chat_title'         => 'الدعم المباشر',
                'chat_subtitle'      => 'الدعم متصل الآن',
                'welcome_message'    => 'مرحباً! كيف يمكننا مساعدتك اليوم؟',
                'auto_reply_message' => 'شكراً لتواصلك معنا! يرجى ترك اسمك وبيانات الاتصال الخاصة بك (البريد الإلكتروني أو الهاتف)، وسيقوم فريقنا بالرد عليك قريباً.',
                'placeholder'        => 'اكتب رسالتك هنا...',
            ),
        );
    }

    /**
     * Default settings array.
     */
    public static function get_default_settings() {
        $lang = self::get_setting( 'chat_language', 'auto' );
        $presets = self::get_language_presets();
        $preset = ( 'auto' !== $lang && isset( $presets[ $lang ] ) ) ? $presets[ $lang ] : $presets['en_US'];

        return array(
            'chat_language'       => 'auto',
            'primary_color'       => '#cc0000',
            'live_chat_btn_color' => '#7c3aed',
            'enable_whatsapp'     => '1',
            'whatsapp_number'     => '',
            'whatsapp_msg'        => '',
            'enable_live_chat'    => '1',
            'chat_icon'           => SLC_PLUGIN_URL . 'assets/chat-icon.png',
            'profile_image'       => SLC_PLUGIN_URL . 'assets/chat-avatar.png',
            'chat_title'          => $preset['chat_title'],
            'chat_subtitle'       => $preset['chat_subtitle'],
            'welcome_message'     => $preset['welcome_message'],
            'auto_reply_message'  => $preset['auto_reply_message'],
        );
    }

    /**
     * Retrieve an option setting with runtime sanitization.
     */
    public static function get_setting( $key, $default = null ) {
        $settings = get_option( 'slc_chat_settings', array() );
        $val = isset( $settings[ $key ] ) ? $settings[ $key ] : null;

        // Auto-sanitize Caspian references and fallback to etaks assets
        if ( 'chat_icon' === $key ) {
            if ( empty( $val ) || false !== stripos( (string) $val, 'caspian' ) ) {
                return SLC_PLUGIN_URL . 'assets/chat-icon.png';
            }
            return $val;
        }

        if ( 'profile_image' === $key ) {
            if ( empty( $val ) || false !== stripos( (string) $val, 'caspian' ) ) {
                return SLC_PLUGIN_URL . 'assets/chat-avatar.png';
            }
            return $val;
        }

        if ( 'primary_color' === $key ) {
            if ( empty( $val ) || '#0A2952' === strtoupper( (string) $val ) ) {
                return '#cc0000';
            }
            return $val;
        }

        if ( ( 'welcome_message' === $key || 'auto_reply_message' === $key ) && ! empty( $val ) ) {
            $emoji_pattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
            $val = trim( preg_replace( $emoji_pattern, '', (string) $val ) );
            return $val;
        }

        if ( null !== $val && '' !== $val ) {
            return $val;
        }
        if ( null !== $default ) {
            return $default;
        }
        $defaults = self::get_default_settings();
        return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
    }

    public static function adjust_brightness( $hex, $steps ) {
        $hex = str_replace( '#', '', (string) $hex );
        if ( strlen( $hex ) === 3 ) {
            $hex = str_repeat( substr( $hex, 0, 1 ), 2 ) . str_repeat( substr( $hex, 1, 1 ), 2 ) . str_repeat( substr( $hex, 2, 1 ), 2 );
        }
        if ( strlen( $hex ) !== 6 ) {
            return '#000000';
        }
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );

        $r = max( 0, min( 255, $r + $steps ) );
        $g = max( 0, min( 255, $g + $steps ) );
        $b = max( 0, min( 255, $b + $steps ) );

        return '#' . sprintf( '%02x%02x%02x', $r, $g, $b );
    }

    public static function hex2rgba( $hex, $opacity = 1 ) {
        $hex = str_replace( '#', '', (string) $hex );
        if ( strlen( $hex ) === 3 ) {
            $hex = str_repeat( substr( $hex, 0, 1 ), 2 ) . str_repeat( substr( $hex, 1, 1 ), 2 ) . str_repeat( substr( $hex, 2, 1 ), 2 );
        }
        if ( strlen( $hex ) !== 6 ) {
            return 'rgba(0, 0, 0, ' . floatval( $opacity ) . ')';
        }
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . floatval( $opacity ) . ')';
    }

    /**
     * Frontend script & styles enqueuing.
     */
    public static function enqueue_frontend() {
        wp_enqueue_style( 'slc-style', SLC_PLUGIN_URL . 'assets/chat.css', array(), SLC_VERSION );

        $primary            = sanitize_hex_color( self::get_setting( 'primary_color', '#cc0000' ) ) ?: '#cc0000';
        $primary_dark       = self::adjust_brightness( $primary, -35 );
        $primary_rgba       = self::hex2rgba( $primary, 0.4 );
        $primary_light_rgba = self::hex2rgba( $primary, 0.35 );

        $chat_btn_color     = sanitize_hex_color( self::get_setting( 'live_chat_btn_color', '#7c3aed' ) ) ?: '#7c3aed';
        $chat_btn_dark      = self::adjust_brightness( $chat_btn_color, -35 );

        $inline_css = ":root {
            --slc-primary: {$primary};
            --slc-primary-dark: {$primary_dark};
            --slc-primary-rgba: {$primary_rgba};
            --slc-primary-light-rgba: {$primary_light_rgba};
            --slc-chat-dial-color: {$chat_btn_color};
            --slc-chat-dial-dark: {$chat_btn_dark};
        }";
        wp_add_inline_style( 'slc-style', $inline_css );

        wp_enqueue_script( 'slc-script', SLC_PLUGIN_URL . 'assets/chat.js', array( 'jquery' ), SLC_VERSION, true );
        wp_localize_script( 'slc-script', 'slc_ajax', array(
            'ajax_url'         => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'slc_frontend_nonce' ),
            'welcome_msg'      => self::get_setting( 'welcome_message' ),
            'brand_name'       => self::get_setting( 'chat_title' ),
            'brand_subtitle'   => self::get_setting( 'chat_subtitle' ),
            'enable_whatsapp'  => self::get_setting( 'enable_whatsapp', '1' ),
            'whatsapp_number'  => self::get_setting( 'whatsapp_number', '' ),
            'whatsapp_msg'     => self::get_setting( 'whatsapp_msg', '' ),
            'enable_live_chat' => self::get_setting( 'enable_live_chat', '1' ),
            'i18n'             => array(
                'you'     => __( 'You', 'live-chat-etaks' ),
                'support' => __( 'Support', 'live-chat-etaks' ),
            ),
        ) );
    }

    /**
     * Admin scripts & styles enqueuing.
     */
    public static function enqueue_admin( $hook ) {
        if ( strpos( $hook, 'slc-dashboard' ) === false && strpos( $hook, 'slc-settings' ) === false ) {
            return;
        }

        wp_enqueue_style( 'slc-admin-style', SLC_PLUGIN_URL . 'assets/admin.css', array( 'dashicons' ), SLC_VERSION );

        $primary      = sanitize_hex_color( self::get_setting( 'primary_color', '#cc0000' ) ) ?: '#cc0000';
        $primary_dark = self::adjust_brightness( $primary, -35 );
        $primary_rgba = self::hex2rgba( $primary, 0.15 );

        $inline_css = ":root {
            --slc-primary: {$primary};
            --slc-primary-dark: {$primary_dark};
            --slc-primary-rgba: {$primary_rgba};
        }";
        wp_add_inline_style( 'slc-admin-style', $inline_css );

        if ( strpos( $hook, 'slc-dashboard' ) !== false ) {
            wp_enqueue_script( 'slc-admin-script', SLC_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), SLC_VERSION, true );
            wp_localize_script( 'slc-admin-script', 'slc_admin_data', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'slc_admin_nonce' ),
                'today'    => current_time( 'Y-m-d' ),
                'i18n'     => array(
                    'today_prefix'         => __( 'Today · User: ', 'live-chat-etaks' ),
                    'user_prefix'          => __( 'User: ', 'live-chat-etaks' ),
                    'support'              => __( 'Support', 'live-chat-etaks' ),
                    'user'                 => __( 'User', 'live-chat-etaks' ),
                    'please_wait'          => __( 'Please wait...', 'live-chat-etaks' ),
                    'select_session_alert' => __( 'Please select a conversation first.', 'live-chat-etaks' ),
                    'awaiting_reply'       => __( 'Awaiting reply...', 'live-chat-etaks' ),
                    'no_chats_date'        => __( 'No conversations found on this date.', 'live-chat-etaks' ),
                    'archive'              => __( 'Archive', 'live-chat-etaks' ),
                    'error_sending'        => __( 'Error sending message.', 'live-chat-etaks' ),
                ),
            ) );
        }

        if ( strpos( $hook, 'slc-settings' ) !== false ) {
            wp_enqueue_media();
            wp_enqueue_script( 'slc-admin-settings', SLC_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), SLC_VERSION, true );
            wp_localize_script( 'slc-admin-settings', 'slc_settings_data', array(
                'ajax_url'     => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'slc_admin_nonce' ),
                'site_locale'  => self::get_active_locale(),
                'lang_presets' => self::get_language_presets(),
                'i18n'         => array(
                    'select_image'   => __( 'Select or Upload Image', 'live-chat-etaks' ),
                    'use_image'      => __( 'Use this image', 'live-chat-etaks' ),
                    'working'        => __( 'Working...', 'live-chat-etaks' ),
                    'recreating_db'  => __( 'Recreating database table...', 'live-chat-etaks' ),
                    'repair_btn'     => __( 'Repair Database Table', 'live-chat-etaks' ),
                    'repair_success' => __( 'Database table repaired and verified successfully!', 'live-chat-etaks' ),
                    'repair_failed'  => __( 'Failed to repair table.', 'live-chat-etaks' ),
                ),
            ) );
        }
    }

    /**
     * Render frontend chat floating widget.
     */
    public static function render_frontend_widget() {
        $chat_icon        = self::get_setting( 'chat_icon' );
        $profile_image    = self::get_setting( 'profile_image' );
        $chat_title       = self::get_setting( 'chat_title' );
        $chat_subtitle    = self::get_setting( 'chat_subtitle' );
        $chosen_lang      = self::get_setting( 'chat_language', 'auto' );
        $enable_whatsapp  = self::get_setting( 'enable_whatsapp', '1' );
        $whatsapp_number  = self::get_setting( 'whatsapp_number', '' );
        $whatsapp_msg     = self::get_setting( 'whatsapp_msg', '' );
        $enable_live_chat   = self::get_setting( 'enable_live_chat', '1' );
        $chat_btn_color     = sanitize_hex_color( self::get_setting( 'live_chat_btn_color', '#7c3aed' ) ) ?: '#7c3aed';

        $is_wa_enabled    = ( '1' === (string) $enable_whatsapp && ! empty( $whatsapp_number ) );
        $is_chat_enabled  = ( '1' === (string) $enable_live_chat );

        // If neither channel is enabled, do not render widget
        if ( ! $is_wa_enabled && ! $is_chat_enabled ) {
            return;
        }

        $is_multi_channel = ( $is_wa_enabled && $is_chat_enabled );

        $clean_phone = preg_replace( '/[^0-9]/', '', (string) $whatsapp_number );
        $wa_url      = 'https://api.whatsapp.com/send?phone=' . rawurlencode( $clean_phone );
        if ( ! empty( $whatsapp_msg ) ) {
            $wa_url .= '&text=' . rawurlencode( $whatsapp_msg );
        }

        $is_rtl    = ( 'ar' === $chosen_lang || ( 'auto' === $chosen_lang && is_rtl() ) );
        $rtl_class = $is_rtl ? ' slc-rtl' : '';
        ?>
        <!-- Floating Contact Launcher & Speed Dial -->
        <div id="slc-widget-wrap" class="<?php echo esc_attr( $rtl_class ); ?>">
            <?php if ( $is_multi_channel ) : ?>
            <!-- Multi-channel Speed Dial Stack -->
            <div id="slc-speed-dial" class="slc-speed-dial-hidden" aria-hidden="true">
                <!-- Channel 1: WhatsApp -->
                <div class="slc-dial-row">
                    <span class="slc-dial-label"><?php esc_html_e( 'WhatsApp', 'live-chat-etaks' ); ?></span>
                    <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener noreferrer" class="slc-dial-btn slc-dial-wa" id="slc-dial-wa" aria-label="<?php echo esc_attr__( 'Chat on WhatsApp', 'live-chat-etaks' ); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="28" height="28" fill="#ffffff" aria-hidden="true">
                            <path d="M16.002 2C8.28 2 2.016 8.264 2.016 15.986c0 2.58.685 5.023 1.884 7.152L2 30l7.086-1.855a13.916 13.916 0 0 0 6.916 1.84h.006c7.72 0 13.986-6.264 13.986-13.987A13.93 13.93 0 0 0 16.002 2zm8.172 19.805c-.34.957-1.7 1.83-2.35 1.95-.61.11-1.39.16-2.25-.12a19.5 19.5 0 0 1-5.63-3.13 20.4 20.4 0 0 1-4.75-4.8c-.89-1.2-1.34-2.58-1.34-3.95 0-1.57.81-2.35 1.1-2.64.29-.29.64-.36.85-.36.22 0 .44 0 .63.01.2.01.47-.08.73.55.27.63.92 2.25 1 2.41.08.16.14.36.03.57-.1.21-.16.34-.32.53-.16.19-.34.42-.48.57-.16.16-.33.34-.14.67.19.33.85 1.4 1.82 2.26 1.25 1.11 2.3 1.46 2.63 1.62.33.16.52.14.71-.08.2-.22.84-.98 1.07-1.32.22-.34.45-.28.75-.17.31.11 1.96.92 2.3 1.09.34.16.56.25.64.39.09.13.09.77-.25 1.73z"/>
                        </svg>
                    </a>
                </div>

                <!-- Channel 2: etaks Live Chat -->
                <div class="slc-dial-row">
                    <span class="slc-dial-label"><?php echo esc_html( $chat_title ); ?></span>
                    <button type="button" class="slc-dial-btn slc-dial-chat" id="slc-dial-chat" style="background-color: <?php echo esc_attr( $chat_btn_color ); ?>;" aria-label="<?php echo esc_attr__( 'Open Live Chat', 'live-chat-etaks' ); ?>">
                        <img src="<?php echo esc_url( $chat_icon ); ?>" alt="<?php echo esc_attr( $chat_title ); ?>">
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Main Floating Launcher Button (FAB) -->
            <div id="slc-chat-fab" class="<?php echo $is_multi_channel ? 'slc-multi-fab' : ''; ?>" role="button" aria-label="<?php echo esc_attr__( 'Open Contact Menu', 'live-chat-etaks' ); ?>" tabindex="0">
                <div class="slc-fab-icon-holder">
                    <img src="<?php echo esc_url( $chat_icon ); ?>" alt="<?php echo esc_attr( $chat_title ); ?>" class="slc-fab-main-icon">
                    <span class="slc-fab-close-x" aria-hidden="true">&times;</span>
                </div>
            </div>
        </div>

        <?php if ( $is_chat_enabled ) : ?>
        <!-- Interactive Live Chat Window -->
        <div id="slc-chat-window" class="<?php echo esc_attr( $rtl_class ); ?>" role="dialog" aria-labelledby="slc-header-name">
            <div id="slc-chat-header">
                <div id="slc-header-avatar">
                    <img src="<?php echo esc_url( $profile_image ); ?>" alt="<?php echo esc_attr( $chat_title ); ?>" class="slc-avatar-img">
                </div>
                <div id="slc-header-info">
                    <div id="slc-header-name"><?php echo esc_html( $chat_title ); ?></div>
                    <div id="slc-header-status"><?php echo esc_html( $chat_subtitle ); ?></div>
                </div>
                <button id="slc-chat-close" type="button" aria-label="<?php echo esc_attr__( 'Close Live Chat', 'live-chat-etaks' ); ?>">&times;</button>
            </div>
            <div id="slc-chat-body">
                <div id="slc-messages" role="log" aria-live="polite"></div>
                <div id="slc-typing-indicator" style="display:none;">
                    <div class="slc-dots"><span></span><span></span><span></span></div>
                    <span><?php echo esc_html( $chat_title ); ?> <?php esc_html_e( 'is typing...', 'live-chat-etaks' ); ?></span>
                </div>
            </div>
            <div id="slc-chat-footer">
                <input type="text" id="slc-message-input" placeholder="<?php echo esc_attr__( 'Type your message...', 'live-chat-etaks' ); ?>" aria-label="<?php echo esc_attr__( 'Message', 'live-chat-etaks' ); ?>">
                <button id="slc-send-btn" type="button" aria-label="<?php echo esc_attr__( 'Send Message', 'live-chat-etaks' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="18px" height="18px" aria-hidden="true"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
            </div>
        </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Admin menu registration.
     */
    /**
     * Admin menu registration.
     */
    public static function register_admin_menu() {
        self::load_textdomain();

        add_menu_page(
            __( 'Live Chat', 'live-chat-etaks' ),
            __( 'Live Chat', 'live-chat-etaks' ),
            'manage_options',
            'slc-dashboard',
            array( __CLASS__, 'render_dashboard_page' ),
            'dashicons-format-chat',
            6
        );

        add_submenu_page(
            'slc-dashboard',
            __( 'Conversations', 'live-chat-etaks' ),
            __( 'Conversations', 'live-chat-etaks' ),
            'manage_options',
            'slc-dashboard',
            array( __CLASS__, 'render_dashboard_page' )
        );

        add_submenu_page(
            'slc-dashboard',
            __( 'Settings', 'live-chat-etaks' ),
            __( 'Settings', 'live-chat-etaks' ),
            'manage_options',
            'slc-settings',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    /**
     * Admin navigation tabs with modern header.
     */
    public static function render_admin_tabs( $active_tab = 'dashboard', $right_action = '' ) {
        ?>
        <div class="slc-top-bar">
            <h1 class="wp-heading-inline"><?php echo ( 'dashboard' === $active_tab ) ? esc_html__( 'Live Chat Dashboard', 'live-chat-etaks' ) : esc_html__( 'Live Chat Settings', 'live-chat-etaks' ); ?></h1>
            <span class="slc-version-pill">v<?php echo esc_html( SLC_VERSION ); ?></span>
        </div>
        <hr class="wp-header-end">

        <div class="slc-nav-row">
            <nav class="slc-nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=slc-dashboard' ) ); ?>" class="slc-nav-tab <?php echo 'dashboard' === $active_tab ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-format-chat"></span> <?php esc_html_e( 'Conversations', 'live-chat-etaks' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=slc-settings' ) ); ?>" class="slc-nav-tab <?php echo 'settings' === $active_tab ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Settings', 'live-chat-etaks' ); ?>
                </a>
            </nav>
            <?php if ( ! empty( $right_action ) ) : ?>
                <div class="slc-nav-actions">
                    <?php echo wp_kses_post( $right_action ); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Admin Live Chat Dashboard.
     */
    public static function render_dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'live-chat-etaks' ) );
        }

        self::load_textdomain();

        global $wpdb;
        $table_name = self::get_table_name();
        $today      = current_time( 'Y-m-d' );

        // Active sessions query
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $active_sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m1.session_id, m1.time as last_time, m1.message as last_msg
                 FROM %i m1
                 INNER JOIN (
                     SELECT session_id, MAX(id) as max_id
                     FROM %i
                     WHERE is_archived = 0
                     GROUP BY session_id
                 ) m2 ON m1.id = m2.max_id
                 ORDER BY m1.time DESC",
                $table_name,
                $table_name
            )
        );

        // Archived sessions query
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $archived_sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m1.session_id, m1.time as last_time, m1.message as last_msg
                 FROM %i m1
                 INNER JOIN (
                     SELECT session_id, MAX(id) as max_id
                     FROM %i
                     WHERE is_archived = 1
                     GROUP BY session_id
                 ) m2 ON m1.id = m2.max_id
                 WHERE m1.session_id NOT IN (
                     SELECT DISTINCT session_id FROM %i WHERE is_archived = 0
                 )
                 ORDER BY m1.time DESC",
                $table_name,
                $table_name,
                $table_name
            )
        );
        ?>
        <div class="wrap slc-admin-wrap">
            <?php self::render_admin_tabs( 'dashboard' ); ?>

            <div class="slc-admin-container">
                <div class="slc-session-list">

                    <!-- Sub-tabs -->
                    <div class="slc-session-tabs">
                        <button type="button" class="slc-tab active" data-target="active-list"><?php esc_html_e( 'Active', 'live-chat-etaks' ); ?></button>
                        <button type="button" class="slc-tab" data-target="archived-list"><?php esc_html_e( 'Archived', 'live-chat-etaks' ); ?></button>
                    </div>

                    <!-- Date Filter (active tab) -->
                    <div id="slc-filter-bar" class="slc-filter-bar">
                        <label for="slc-date-filter"><?php esc_html_e( 'Date:', 'live-chat-etaks' ); ?></label>
                        <input type="date" id="slc-date-filter" value="<?php echo esc_attr( $today ); ?>">
                        <button type="button" id="slc-clear-filter" title="<?php echo esc_attr__( 'All Dates', 'live-chat-etaks' ); ?>">&times;</button>
                    </div>

                    <!-- Search bar (archive tab) -->
                    <div id="slc-search-bar" class="slc-filter-bar" style="display:none;">
                        <input type="text" id="slc-search-input" placeholder="<?php echo esc_attr__( 'Search conversations...', 'live-chat-etaks' ); ?>">
                    </div>

                    <!-- Active sessions list -->
                    <ul id="active-list" class="slc-sessions-ul">
                        <?php if ( ! empty( $active_sessions ) ) : foreach ( $active_sessions as $row ) :
                            $row_date     = substr( $row->last_time, 0, 10 );
                            $is_today     = ( $row_date === $today );
                            $time_display = $is_today
                                ? wp_date( 'H:i', strtotime( $row->last_time ) )
                                : wp_date( 'd.m.Y', strtotime( $row->last_time ) );
                            $snippet      = mb_substr( stripslashes( $row->last_msg ), 0, 32 ) . '...';
                        ?>
                            <li data-session="<?php echo esc_attr( $row->session_id ); ?>"
                                data-archived="0"
                                data-date="<?php echo esc_attr( $row_date ); ?>">
                                <div class="slc-session-top">
                                    <span class="slc-session-name">
                                        <?php if ( $is_today ) : ?><span class="slc-today-dot"></span><?php endif; ?>
                                        <?php echo esc_html__( 'User: ', 'live-chat-etaks' ) . esc_html( substr( $row->session_id, 0, 8 ) ); ?>
                                    </span>
                                    <span class="slc-session-time <?php echo $is_today ? 'today' : ''; ?>">
                                        <?php echo $is_today ? esc_html__( 'Today', 'live-chat-etaks' ) . ' ' . esc_html( $time_display ) : esc_html( $time_display ); ?>
                                    </span>
                                </div>
                                <div class="slc-session-snippet"><?php echo esc_html( $snippet ); ?></div>
                            </li>
                        <?php endforeach; else : ?>
                            <li class="no-chats">
                                <svg class="slc-empty-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                                <div class="slc-empty-title"><?php esc_html_e( 'No active conversations', 'live-chat-etaks' ); ?></div>
                                <div class="slc-empty-desc"><?php esc_html_e( 'New customer messages will appear here in real-time.', 'live-chat-etaks' ); ?></div>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <!-- Archived sessions list -->
                    <ul id="archived-list" class="slc-sessions-ul" style="display:none;">
                        <?php if ( ! empty( $archived_sessions ) ) : foreach ( $archived_sessions as $row ) :
                            $row_date     = substr( $row->last_time, 0, 10 );
                            $is_today     = ( $row_date === $today );
                            $time_display = $is_today
                                ? wp_date( 'H:i', strtotime( $row->last_time ) )
                                : wp_date( 'd.m.Y', strtotime( $row->last_time ) );
                            $snippet      = mb_substr( stripslashes( $row->last_msg ), 0, 32 ) . '...';
                        ?>
                            <li data-session="<?php echo esc_attr( $row->session_id ); ?>"
                                data-archived="1"
                                data-date="<?php echo esc_attr( $row_date ); ?>">
                                <div class="slc-session-top">
                                    <span class="slc-session-name"><?php echo esc_html__( 'User: ', 'live-chat-etaks' ) . esc_html( substr( $row->session_id, 0, 8 ) ); ?></span>
                                    <span class="slc-session-time <?php echo $is_today ? 'today' : ''; ?>">
                                        <?php echo $is_today ? esc_html__( 'Today', 'live-chat-etaks' ) . ' ' . esc_html( $time_display ) : esc_html( $time_display ); ?>
                                    </span>
                                </div>
                                <div class="slc-session-snippet"><?php echo esc_html( $snippet ); ?></div>
                            </li>
                        <?php endforeach; else : ?>
                            <li class="no-chats">
                                <svg class="slc-empty-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
                                <div class="slc-empty-title"><?php esc_html_e( 'No archived conversations', 'live-chat-etaks' ); ?></div>
                                <div class="slc-empty-desc"><?php esc_html_e( 'Archived conversations will appear here.', 'live-chat-etaks' ); ?></div>
                            </li>
                        <?php endif; ?>
                    </ul>

                </div><!-- /session-list -->

                <div class="slc-chat-area">
                    <!-- Clean Empty Placeholder -->
                    <div id="slc-no-session-placeholder" class="slc-chat-empty-state">
                        <div class="slc-empty-illustration">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
                        </div>
                        <h3><?php esc_html_e( 'Select a conversation', 'live-chat-etaks' ); ?></h3>
                        <p><?php esc_html_e( 'Choose an active or archived conversation from the sidebar to view chat history and reply.', 'live-chat-etaks' ); ?></p>
                    </div>

                    <!-- Active Session Pane -->
                    <div id="slc-active-session-pane" style="display:none;">
                        <div class="slc-chat-header-actions">
                            <div class="slc-chat-header-left">
                                <div class="slc-header-user-avatar">US</div>
                                <h3 id="slc-current-session-title"></h3>
                            </div>
                            <button type="button" id="slc-archive-btn" class="button" style="display:none;"><?php esc_html_e( 'Archive', 'live-chat-etaks' ); ?></button>
                        </div>
                        <div id="slc-admin-messages"></div>
                        <div class="slc-admin-input-area">
                            <input type="hidden" id="slc-active-session" value="">
                            <input type="text" id="slc-admin-input" placeholder="<?php echo esc_attr__( 'Type your reply...', 'live-chat-etaks' ); ?>">
                            <button type="button" id="slc-admin-send" class="button button-primary">
                                <span><?php esc_html_e( 'Send', 'live-chat-etaks' ); ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * System status & diagnostic data.
     */
    public static function get_system_diagnostics() {
        global $wpdb;
        $table_name = self::get_table_name();

        // Check DB table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name );
        $msg_count = 0;
        if ( $table_exists ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $msg_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", $table_name ) );
        }

        // Check Assets
        $assets_ok = file_exists( SLC_PLUGIN_DIR . 'assets/chat.css' )
                  && file_exists( SLC_PLUGIN_DIR . 'assets/chat.js' )
                  && file_exists( SLC_PLUGIN_DIR . 'assets/chat-icon.png' )
                  && file_exists( SLC_PLUGIN_DIR . 'assets/chat-avatar.png' );

        // Version checks
        $php_ok = version_compare( PHP_VERSION, '7.4', '>=' );
        $wp_ok  = version_compare( $GLOBALS['wp_version'], '5.8', '>=' );

        return array(
            'table_name'   => $table_name,
            'table_exists' => $table_exists,
            'msg_count'    => $msg_count,
            'assets_ok'    => $assets_ok,
            'php_ok'       => $php_ok,
            'php_version'  => PHP_VERSION,
            'wp_ok'        => $wp_ok,
            'wp_version'   => $GLOBALS['wp_version'],
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
        );
    }

    /**
     * Admin Settings Page.
     */
    /**
     * Admin Settings Page.
     */
    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'live-chat-etaks' ) );
        }

        self::load_textdomain();

        $default_icon   = SLC_PLUGIN_URL . 'assets/chat-icon.png';
        $default_avatar = SLC_PLUGIN_URL . 'assets/chat-avatar.png';

        // Handle form submission
        $message = '';
        if ( isset( $_POST['slc_save_settings'] ) && check_admin_referer( 'slc_save_settings_nonce', 'slc_settings_nonce' ) ) {
            $submitted_icon   = isset( $_POST['chat_icon'] ) ? esc_url_raw( wp_unslash( $_POST['chat_icon'] ) ) : $default_icon;
            $submitted_avatar = isset( $_POST['profile_image'] ) ? esc_url_raw( wp_unslash( $_POST['profile_image'] ) ) : $default_avatar;

            // Purge Caspian references from form submission
            if ( empty( $submitted_icon ) || false !== stripos( $submitted_icon, 'caspian' ) ) {
                $submitted_icon = $default_icon;
            }
            if ( empty( $submitted_avatar ) || false !== stripos( $submitted_avatar, 'caspian' ) ) {
                $submitted_avatar = $default_avatar;
            }

            $emoji_pattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
            $raw_welcome   = isset( $_POST['welcome_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['welcome_message'] ) ) : '';
            $raw_reply     = isset( $_POST['auto_reply_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['auto_reply_message'] ) ) : '';

            $clean_settings = array(
                'chat_language'       => isset( $_POST['chat_language'] ) ? sanitize_text_field( wp_unslash( $_POST['chat_language'] ) ) : 'auto',
                'primary_color'       => isset( $_POST['primary_color'] ) ? ( sanitize_hex_color( wp_unslash( $_POST['primary_color'] ) ) ?: '#cc0000' ) : '#cc0000',
                'live_chat_btn_color' => isset( $_POST['live_chat_btn_color'] ) ? ( sanitize_hex_color( wp_unslash( $_POST['live_chat_btn_color'] ) ) ?: '#7c3aed' ) : '#7c3aed',
                'enable_whatsapp'     => isset( $_POST['enable_whatsapp'] ) ? '1' : '0',
                'whatsapp_number'     => isset( $_POST['whatsapp_number'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp_number'] ) ) : '',
                'whatsapp_msg'        => isset( $_POST['whatsapp_msg'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp_msg'] ) ) : '',
                'enable_live_chat'    => isset( $_POST['enable_live_chat'] ) ? '1' : '0',
                'chat_icon'           => $submitted_icon,
                'profile_image'       => $submitted_avatar,
                'chat_title'          => isset( $_POST['chat_title'] ) ? sanitize_text_field( wp_unslash( $_POST['chat_title'] ) ) : '',
                'chat_subtitle'       => isset( $_POST['chat_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['chat_subtitle'] ) ) : '',
                'welcome_message'     => trim( preg_replace( $emoji_pattern, '', $raw_welcome ) ),
                'auto_reply_message'  => trim( preg_replace( $emoji_pattern, '', $raw_reply ) ),
            );
            update_option( 'slc_chat_settings', $clean_settings );
            self::load_textdomain();
            $message = __( 'Settings saved successfully!', 'live-chat-etaks' );
        }

        $chat_language      = self::get_setting( 'chat_language', 'auto' );
        $primary_color      = self::get_setting( 'primary_color', '#cc0000' );
        $chat_btn_color     = self::get_setting( 'live_chat_btn_color', '#7c3aed' );
        $enable_whatsapp    = self::get_setting( 'enable_whatsapp', '1' );
        $whatsapp_number    = self::get_setting( 'whatsapp_number', '' );
        $whatsapp_msg       = self::get_setting( 'whatsapp_msg', '' );
        $enable_live_chat   = self::get_setting( 'enable_live_chat', '1' );
        $chat_icon          = self::get_setting( 'chat_icon', $default_icon );
        $profile_image      = self::get_setting( 'profile_image', $default_avatar );
        $chat_title         = self::get_setting( 'chat_title' );
        $chat_subtitle      = self::get_setting( 'chat_subtitle' );
        $welcome_message    = self::get_setting( 'welcome_message' );
        $auto_reply_message = self::get_setting( 'auto_reply_message' );
        $languages          = self::get_supported_languages();
        $diagnostics        = self::get_system_diagnostics();
        ?>
        <div class="wrap slc-admin-wrap">
            <form id="slc_settings_form" method="post" action="">
                <?php
                wp_nonce_field( 'slc_save_settings_nonce', 'slc_settings_nonce' );

                $save_button = '<button type="submit" name="slc_save_settings" class="button button-primary slc-header-save-btn">' . esc_html__( 'Save Settings', 'live-chat-etaks' ) . '</button>';
                self::render_admin_tabs( 'settings', $save_button );
                ?>

                <?php if ( ! empty( $message ) ) : ?>
                    <div class="notice notice-success is-dismissible" style="margin-top:10px;">
                        <p><strong><?php echo esc_html( $message ); ?></strong></p>
                    </div>
                <?php endif; ?>

                <div class="slc-settings-grid">

                    <!-- CARD 1: Colors & Theme + System Health & Diagnostics -->
                    <div class="slc-settings-card">
                        <div class="slc-card-header">
                            <h3><?php esc_html_e( 'Colors & Theme', 'live-chat-etaks' ); ?></h3>
                        </div>

                        <div class="slc-setting-field">
                            <label class="slc-field-title" for="slc_primary_color"><?php esc_html_e( 'Launcher Button Color (Main FAB):', 'live-chat-etaks' ); ?></label>
                            <div class="slc-color-row">
                                <input type="color" id="slc_color_picker" value="<?php echo esc_attr( $primary_color ); ?>">
                                <input type="text" name="primary_color" id="slc_primary_color" value="<?php echo esc_attr( $primary_color ); ?>" maxlength="7">
                            </div>
                            <span class="description"><?php esc_html_e( 'Used for the main floating trigger button, pulsating ring, header, and submit button.', 'live-chat-etaks' ); ?></span>

                            <div class="slc-color-presets">
                                <span style="font-size:11px;color:#64748b;font-weight:600;margin-right:2px;"><?php esc_html_e( 'Presets:', 'live-chat-etaks' ); ?></span>
                                <button type="button" class="slc-preset-btn" style="background:#cc0000;" data-color="#cc0000" title="<?php echo esc_attr__( 'Classic Red', 'live-chat-etaks' ); ?>"></button>
                                <button type="button" class="slc-preset-btn" style="background:#25D366;" data-color="#25D366" title="<?php echo esc_attr__( 'WhatsApp Green', 'live-chat-etaks' ); ?>"></button>
                                <button type="button" class="slc-preset-btn" style="background:#1877F2;" data-color="#1877F2" title="<?php echo esc_attr__( 'Facebook Blue', 'live-chat-etaks' ); ?>"></button>
                                <button type="button" class="slc-preset-btn" style="background:#222222;" data-color="#222222" title="<?php echo esc_attr__( 'Dark / Minimalist', 'live-chat-etaks' ); ?>"></button>
                                <button type="button" class="slc-preset-btn" style="background:#7c3aed;" data-color="#7c3aed" title="<?php echo esc_attr__( 'Purple', 'live-chat-etaks' ); ?>"></button>
                                <button type="button" class="slc-preset-btn" style="background:#0284c7;" data-color="#0284c7" title="<?php echo esc_attr__( 'Sky Blue', 'live-chat-etaks' ); ?>"></button>
                            </div>
                        </div>

                        <!-- Live Chat Speed Dial Button Color -->
                        <div class="slc-setting-field" style="margin-top:16px;">
                            <label class="slc-field-title" for="slc_chat_btn_color"><?php esc_html_e( 'Live Chat Button Color (Speed Dial Circle):', 'live-chat-etaks' ); ?></label>
                            <div class="slc-color-row">
                                <input type="color" id="slc_chat_btn_color_picker" value="<?php echo esc_attr( $chat_btn_color ); ?>">
                                <input type="text" name="live_chat_btn_color" id="slc_chat_btn_color" value="<?php echo esc_attr( $chat_btn_color ); ?>" maxlength="7">
                            </div>
                            <span class="description"><?php esc_html_e( 'Background color of the Live Chat circular button inside the popup speed-dial stack.', 'live-chat-etaks' ); ?></span>

                            <div class="slc-color-presets">
                                <span style="font-size:11px;color:#64748b;font-weight:600;margin-right:2px;"><?php esc_html_e( 'Presets:', 'live-chat-etaks' ); ?></span>
                                <button type="button" class="slc-preset-btn slc-dial-preset-btn" style="background:#7c3aed;" data-color="#7c3aed" title="<?php echo esc_attr__( 'Purple (Default)', 'live-chat-etaks' ); ?>"></button>
                                <button type="button" class="slc-preset-btn slc-dial-preset-btn" style="background:#cc0000;" data-color="#cc0000" title="<?php echo esc_attr__( 'Red', 'live-chat-etaks' ); ?>"></button>
                                <button type="button" class="slc-preset-btn slc-dial-preset-btn" style="background:#1877F2;" data-color="#1877F2" title="<?php echo esc_attr__( 'Blue', 'live-chat-etaks' ); ?>"></button>
                                <button type="button" class="slc-preset-btn slc-dial-preset-btn" style="background:#0284c7;" data-color="#0284c7" title="<?php echo esc_attr__( 'Sky Blue', 'live-chat-etaks' ); ?>"></button>
                                <button type="button" class="slc-preset-btn slc-dial-preset-btn" style="background:#10b981;" data-color="#10b981" title="<?php echo esc_attr__( 'Emerald', 'live-chat-etaks' ); ?>"></button>
                                <button type="button" class="slc-preset-btn slc-dial-preset-btn" style="background:#222222;" data-color="#222222" title="<?php echo esc_attr__( 'Dark', 'live-chat-etaks' ); ?>"></button>
                            </div>
                        </div>

                        <!-- System Health & Diagnostics placed into the empty area of Card 1 -->
                        <hr class="slc-card-divider">

                        <div class="slc-card-header slc-card-header-sub">
                            <h3><?php esc_html_e( 'System Health & Diagnostics', 'live-chat-etaks' ); ?></h3>
                        </div>
                        <p class="description" style="margin: 0 0 10px; font-size: 11.5px;">
                            <?php esc_html_e( 'Verify database table integrity, server compatibility, and resolve potential issues.', 'live-chat-etaks' ); ?>
                        </p>

                        <div class="slc-diagnostics-box">
                            <div class="slc-diag-row">
                                <strong><?php esc_html_e( 'Database Table:', 'live-chat-etaks' ); ?></strong>
                                <?php if ( $diagnostics['table_exists'] ) : ?>
                                    <span class="slc-diag-badge-ok">&#10003; <?php echo esc_html( $diagnostics['table_name'] ); ?> (<?php echo intval( $diagnostics['msg_count'] ); ?>)</span>
                                <?php else : ?>
                                    <span class="slc-diag-badge-err">&#10007; <?php esc_html_e( 'Table missing', 'live-chat-etaks' ); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="slc-diag-row">
                                <strong><?php esc_html_e( 'PHP Version:', 'live-chat-etaks' ); ?></strong>
                                <?php if ( $diagnostics['php_ok'] ) : ?>
                                    <span class="slc-diag-badge-ok">&#10003; <?php echo esc_html( $diagnostics['php_version'] ); ?> (&ge; 7.4)</span>
                                <?php else : ?>
                                    <span class="slc-diag-badge-err"><?php echo esc_html( $diagnostics['php_version'] ); ?> (&ge; 7.4)</span>
                                <?php endif; ?>
                            </div>

                            <div class="slc-diag-row">
                                <strong><?php esc_html_e( 'WordPress Version:', 'live-chat-etaks' ); ?></strong>
                                <?php if ( $diagnostics['wp_ok'] ) : ?>
                                    <span class="slc-diag-badge-ok">&#10003; <?php echo esc_html( $diagnostics['wp_version'] ); ?> (&ge; 5.8)</span>
                                <?php else : ?>
                                    <span class="slc-diag-badge-err"><?php echo esc_html( $diagnostics['wp_version'] ); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="slc-diag-row">
                                <strong><?php esc_html_e( 'Asset Files:', 'live-chat-etaks' ); ?></strong>
                                <?php if ( $diagnostics['assets_ok'] ) : ?>
                                    <span class="slc-diag-badge-ok">&#10003; <?php esc_html_e( 'Loaded', 'live-chat-etaks' ); ?></span>
                                <?php else : ?>
                                    <span class="slc-diag-badge-err">&#10007; <?php esc_html_e( 'Missing', 'live-chat-etaks' ); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <button type="button" id="slc_repair_db_btn" class="button" style="font-size:12px;height:28px;line-height:26px;">
                                <?php esc_html_e( 'Repair Database Table', 'live-chat-etaks' ); ?>
                            </button>
                            <span id="slc_repair_status" style="font-size:12px;font-weight:600;"></span>
                        </div>
                    </div>

                    <!-- CARD 2: Channels & Icons -->
                    <div class="slc-settings-card">
                        <div class="slc-card-header">
                            <h3><?php esc_html_e( 'Channels & Icons', 'live-chat-etaks' ); ?></h3>
                        </div>

                        <!-- 1. WhatsApp Channel Section -->
                        <div class="slc-setting-field">
                            <label class="slc-field-title" for="slc_enable_whatsapp" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" name="enable_whatsapp" id="slc_enable_whatsapp" value="1" <?php checked( $enable_whatsapp, '1' ); ?>>
                                <span><strong><?php esc_html_e( 'Enable WhatsApp Channel', 'live-chat-etaks' ); ?></strong></span>
                            </label>
                            <span class="description"><?php esc_html_e( 'Shows WhatsApp button when floating contact menu is clicked.', 'live-chat-etaks' ); ?></span>
                        </div>

                        <div id="slc_wa_fields" style="<?php echo ( '1' === (string) $enable_whatsapp ) ? '' : 'display:none;'; ?>">
                            <div class="slc-setting-field" style="margin-bottom:8px;">
                                <label class="slc-field-title" for="slc_whatsapp_number"><?php esc_html_e( 'WhatsApp Phone Number:', 'live-chat-etaks' ); ?></label>
                                <input type="text" name="whatsapp_number" id="slc_whatsapp_number" value="<?php echo esc_attr( $whatsapp_number ); ?>" placeholder="+994509933398">
                                <span class="description"><?php esc_html_e( 'Example: +994509933398 (include country code).', 'live-chat-etaks' ); ?></span>
                            </div>

                            <div class="slc-setting-field" style="margin-bottom:8px;">
                                <label class="slc-field-title" for="slc_whatsapp_msg"><?php esc_html_e( 'WhatsApp Pre-filled Text (Optional):', 'live-chat-etaks' ); ?></label>
                                <input type="text" name="whatsapp_msg" id="slc_whatsapp_msg" value="<?php echo esc_attr( $whatsapp_msg ); ?>" placeholder="<?php echo esc_attr__( 'Hello! I would like more information.', 'live-chat-etaks' ); ?>">
                            </div>
                        </div>

                        <hr class="slc-card-divider">

                        <!-- 2. etaks Live Chat Channel Section -->
                        <div class="slc-setting-field">
                            <label class="slc-field-title" for="slc_enable_live_chat" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" name="enable_live_chat" id="slc_enable_live_chat" value="1" <?php checked( $enable_live_chat, '1' ); ?>>
                                <span><strong><?php esc_html_e( 'Enable Live Chat (etaks) Channel', 'live-chat-etaks' ); ?></strong></span>
                            </label>
                            <span class="description"><?php esc_html_e( 'Shows on-site interactive live chat box when clicked.', 'live-chat-etaks' ); ?></span>
                        </div>

                        <!-- Floating Chat Icon (FAB) -->
                        <div class="slc-setting-field">
                            <label class="slc-field-title" for="slc_chat_icon"><?php esc_html_e( 'Floating Chat Button Icon (FAB & Speed Dial):', 'live-chat-etaks' ); ?></label>
                            <div class="slc-image-upload-wrap">
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <div class="slc-preview-circle" id="slc_fab_preview_wrap" style="background-color:<?php echo esc_attr( $primary_color ); ?>;" title="<?php echo esc_attr__( 'Main Launcher FAB Preview', 'live-chat-etaks' ); ?>">
                                        <img id="slc_icon_preview" src="<?php echo esc_url( $chat_icon ); ?>" alt="<?php echo esc_attr__( 'FAB preview', 'live-chat-etaks' ); ?>">
                                    </div>
                                    <div class="slc-preview-circle" id="slc_dial_preview_wrap" style="background-color:<?php echo esc_attr( $chat_btn_color ); ?>;" title="<?php echo esc_attr__( 'Speed Dial Live Chat Preview', 'live-chat-etaks' ); ?>">
                                        <img id="slc_dial_icon_preview" src="<?php echo esc_url( $chat_icon ); ?>" alt="<?php echo esc_attr__( 'Speed dial preview', 'live-chat-etaks' ); ?>">
                                    </div>
                                </div>
                                <div class="slc-upload-actions">
                                    <input type="text" name="chat_icon" id="slc_chat_icon" value="<?php echo esc_attr( $chat_icon ); ?>" placeholder="https://...">
                                    <div class="slc-upload-actions-btns">
                                        <button type="button" class="button slc-media-upload-btn" data-target-input="#slc_chat_icon" data-target-preview="#slc_icon_preview,#slc_dial_icon_preview"><?php esc_html_e( 'Select / Upload Image', 'live-chat-etaks' ); ?></button>
                                        <button type="button" class="button slc-reset-btn" data-target-input="#slc_chat_icon" data-target-preview="#slc_icon_preview,#slc_dial_icon_preview" data-default="<?php echo esc_attr( $default_icon ); ?>"><?php esc_html_e( 'Reset to Default', 'live-chat-etaks' ); ?></button>
                                    </div>
                                </div>
                            </div>
                            <span class="description"><?php esc_html_e( 'Icon displayed inside the floating action button and speed dial. Previews show both Main FAB (left) and Speed Dial (right). Transparent PNG recommended.', 'live-chat-etaks' ); ?></span>
                        </div>

                        <hr class="slc-card-divider">

                        <!-- Header Avatar -->
                        <div class="slc-setting-field">
                            <label class="slc-field-title" for="slc_profile_image"><?php esc_html_e( 'Chat Header Profile Avatar:', 'live-chat-etaks' ); ?></label>
                            <div class="slc-image-upload-wrap">
                                <div class="slc-preview-circle avatar-preview">
                                    <img id="slc_avatar_preview" src="<?php echo esc_url( $profile_image ); ?>" alt="<?php echo esc_attr__( 'Avatar preview', 'live-chat-etaks' ); ?>">
                                </div>
                                <div class="slc-upload-actions">
                                    <input type="text" name="profile_image" id="slc_profile_image" value="<?php echo esc_attr( $profile_image ); ?>" placeholder="https://...">
                                    <div class="slc-upload-actions-btns">
                                        <button type="button" class="button slc-media-upload-btn" data-target-input="#slc_profile_image" data-target-preview="#slc_avatar_preview"><?php esc_html_e( 'Select / Upload Image', 'live-chat-etaks' ); ?></button>
                                        <button type="button" class="button slc-reset-btn" data-target-input="#slc_profile_image" data-target-preview="#slc_avatar_preview" data-default="<?php echo esc_attr( $default_avatar ); ?>"><?php esc_html_e( 'Reset to Default', 'live-chat-etaks' ); ?></button>
                                    </div>
                                </div>
                            </div>
                            <span class="description"><?php esc_html_e( 'Avatar image displayed in the header next to the support title.', 'live-chat-etaks' ); ?></span>
                        </div>
                    </div>

                    <!-- CARD 3: Texts & Automated Messages -->
                    <div class="slc-settings-card">
                        <div class="slc-card-header">
                            <h3><?php esc_html_e( 'Texts & Messages', 'live-chat-etaks' ); ?></h3>
                        </div>

                        <!-- Language Switcher Field -->
                        <div class="slc-setting-field">
                            <label class="slc-field-title" for="slc_chat_language"><?php esc_html_e( 'Plugin & Widget Language:', 'live-chat-etaks' ); ?></label>
                            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                <select name="chat_language" id="slc_chat_language" style="min-width:150px;flex:1;">
                                    <?php foreach ( $languages as $code => $label ) : ?>
                                        <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $chat_language, $code ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" id="slc_load_lang_defaults" class="button" style="font-size:11.5px;padding:1px 8px;height:28px;line-height:26px;" title="<?php echo esc_attr__( 'Auto-fill title, subtitle, welcome, and reply messages in the selected language.', 'live-chat-etaks' ); ?>">
                                    <?php esc_html_e( 'Load Language Defaults', 'live-chat-etaks' ); ?>
                                </button>
                            </div>
                            <span class="description"><?php esc_html_e( 'Sets the language for entire plugin and visitor widget (11 languages + Arabic RTL).', 'live-chat-etaks' ); ?></span>
                        </div>

                        <div class="slc-setting-field">
                            <label class="slc-field-title" for="slc_chat_title"><?php esc_html_e( 'Chat Title:', 'live-chat-etaks' ); ?></label>
                            <input type="text" name="chat_title" id="slc_chat_title" value="<?php echo esc_attr( $chat_title ); ?>">
                        </div>

                        <div class="slc-setting-field">
                            <label class="slc-field-title" for="slc_chat_subtitle"><?php esc_html_e( 'Subtitle / Online Status:', 'live-chat-etaks' ); ?></label>
                            <input type="text" name="chat_subtitle" id="slc_chat_subtitle" value="<?php echo esc_attr( $chat_subtitle ); ?>">
                        </div>

                        <div class="slc-setting-field">
                            <label class="slc-field-title" for="slc_welcome_message"><?php esc_html_e( 'Welcome Message (on first open):', 'live-chat-etaks' ); ?></label>
                            <textarea name="welcome_message" id="slc_welcome_message" rows="2"><?php echo esc_textarea( $welcome_message ); ?></textarea>
                        </div>

                        <div class="slc-setting-field">
                            <label class="slc-field-title" for="slc_auto_reply_message"><?php esc_html_e( "Auto-Reply (sent immediately after visitor's first message):", 'live-chat-etaks' ); ?></label>
                            <textarea name="auto_reply_message" id="slc_auto_reply_message" rows="2"><?php echo esc_textarea( $auto_reply_message ); ?></textarea>
                            <span class="description"><?php esc_html_e( 'Sent automatically once a new visitor sends their first message to collect contact information.', 'live-chat-etaks' ); ?></span>
                        </div>
                    </div>

                </div>
            </form>
        </div>
        <?php
    }

    /**
     * AJAX endpoint to repair database table.
     */
    public static function ajax_repair_database() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized.', 'live-chat-etaks' ) ), 403 );
        }
        check_ajax_referer( 'slc_admin_nonce', 'nonce' );

        self::create_db_table();
        update_option( 'slc_db_version', SLC_VERSION );

        global $wpdb;
        $table_name = self::get_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists = ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name );

        if ( $exists ) {
            wp_send_json_success( array( 'message' => esc_html__( 'Database table repaired and verified successfully!', 'live-chat-etaks' ) ) );
        } else {
            wp_send_json_error( array( 'message' => esc_html__( 'Failed to repair table.', 'live-chat-etaks' ) ), 500 );
        }
    }

    /**
     * AJAX: Send message.
     */
    public static function ajax_send_message() {
        $raw_is_admin = isset( $_POST['is_admin'] ) ? sanitize_text_field( wp_unslash( $_POST['is_admin'] ) ) : '0';
        $is_admin_action = ( '1' === $raw_is_admin );

        if ( $is_admin_action ) {
            check_ajax_referer( 'slc_admin_nonce', 'nonce' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized.', 'live-chat-etaks' ) ), 403 );
            }
            $sender = 'admin';
        } else {
            check_ajax_referer( 'slc_frontend_nonce', 'nonce' );
            $sender = 'user';
        }

        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
        $message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

        if ( empty( $session_id ) || '' === trim( $message ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid message parameters.', 'live-chat-etaks' ) ), 400 );
        }

        global $wpdb;
        $table_name = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'session_id'  => $session_id,
                'message'     => $message,
                'sender'      => $sender,
                'time'        => current_time( 'mysql' ),
                'is_archived' => 0,
            ),
            array( '%s', '%s', '%s', '%s', '%d' )
        );

        if ( ! $inserted ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Failed to save message.', 'live-chat-etaks' ) ), 500 );
        }

        // Auto-reply logic for first user message
        if ( 'user' === $sender ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE session_id = %s AND sender = 'user'",
                $table_name,
                $session_id
            ) );

            if ( 1 === $count ) {
                $default_auto_reply = __( 'Thank you for reaching out! Please provide your name and contact details (email or phone number), and our team will get back to you shortly.', 'live-chat-etaks' );
                $auto_reply = self::get_setting( 'auto_reply_message', $default_auto_reply );

                if ( ! empty( $auto_reply ) ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->insert(
                        $table_name,
                        array(
                            'session_id'  => $session_id,
                            'message'     => $auto_reply,
                            'sender'      => 'admin',
                            'time'        => current_time( 'mysql' ),
                            'is_archived' => 0,
                        ),
                        array( '%s', '%s', '%s', '%s', '%d' )
                    );
                }
            }
        }

        wp_send_json_success();
    }

    /**
     * AJAX: Get messages.
     */
    public static function ajax_get_messages() {
        $nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';

        $is_valid_user  = wp_verify_nonce( $nonce, 'slc_frontend_nonce' );
        $is_valid_admin = ( current_user_can( 'manage_options' ) && wp_verify_nonce( $nonce, 'slc_admin_nonce' ) );

        if ( ! $is_valid_user && ! $is_valid_admin ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid security token.', 'live-chat-etaks' ) ), 403 );
        }

        $session_id = isset( $_REQUEST['session_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['session_id'] ) ) : '';
        if ( empty( $session_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Session ID required.', 'live-chat-etaks' ) ), 400 );
        }

        global $wpdb;
        $table_name = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $messages = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, session_id, message, sender, time FROM %i WHERE session_id = %s ORDER BY time ASC, id ASC",
            $table_name,
            $session_id
        ) );

        if ( ! is_array( $messages ) ) {
            $messages = array();
        }

        foreach ( $messages as &$msg ) {
            $msg->message = stripslashes( $msg->message );
        }

        wp_send_json_success( $messages );
    }

    /**
     * AJAX: Archive session.
     */
    public static function ajax_archive_session() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized.', 'live-chat-etaks' ) ), 403 );
        }

        check_ajax_referer( 'slc_admin_nonce', 'nonce' );

        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
        if ( empty( $session_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Session ID required.', 'live-chat-etaks' ) ), 400 );
        }

        global $wpdb;
        $table_name = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            $table_name,
            array( 'is_archived' => 1 ),
            array( 'session_id' => $session_id ),
            array( '%d' ),
            array( '%s' )
        );

        if ( false === $updated ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Database error.', 'live-chat-etaks' ) ), 500 );
        }

        wp_send_json_success();
    }
}

// ── Backwards-Compatible Global Helper Wrappers ──────────────
if ( ! function_exists( 'slc_get_setting' ) ) {
    function slc_get_setting( $key, $default = null ) {
        return Live_Chat_Etaks::get_setting( $key, $default );
    }
}
if ( ! function_exists( 'slc_create_db_table' ) ) {
    function slc_create_db_table() {
        Live_Chat_Etaks::create_db_table();
    }
}

// Initialize plugin
Live_Chat_Etaks::init();

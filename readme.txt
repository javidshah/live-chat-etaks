=== Live Chat - etaks ===
Contributors: javidshahmuradov
Donate link: https://etaks.az/
Tags: live chat, chat widget, support chat, customer service, messaging
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast, lightweight, and customizable live support chat widget for WordPress. Connect with site visitors in real time.

== Description ==

**Live Chat - etaks** is a modern, lightweight, and privacy-friendly live chat plugin for WordPress. Connect directly with your website visitors, answer presale questions, and provide instant customer support without paying expensive monthly subscription fees to third-party services.

All chat messages and conversations are stored securely inside your own WordPress database. There are no external tracking scripts, no third-party cloud dependencies, and zero performance bloat.

### Key Features

* **Sleek Floating Chat Widget:** Eye-catching floating action button with customizable pulsating animation and mobile-optimized sliding chat window.
* **Real-Time Customer Conversations:** Seamless back-and-forth messaging with dynamic typing indicators and optimistic message sending.
* **Integrated Admin Chat Dashboard:** Intuitive WordPress admin panel to review live inquiries, reply in real time, and archive resolved conversations.
* **Date Filters & Search:** Filter conversations by date or search historical chat sessions in seconds.
* **Automated Welcome & Auto-Reply:** Greet visitors automatically when they open the chat and request their contact details upon their first message.
* **Complete Visual Customization:** Easily adjust brand primary colors with live preview, upload custom widget icons and agent avatars via the WordPress Media Uploader.
* **11 Languages Built-in & RTL Support:** Switch between English, Azerbaijani, Turkish, Russian, Uzbek, Spanish, French, Portuguese, Japanese, Chinese, and Arabic (with native right-to-left layout) directly from settings.
* **System Health & Diagnostics:** Built-in table inspector with a one-click database table repair tool.
### Third-Party Services

This plugin provides an optional WhatsApp contact channel allowing website visitors to initiate a direct chat conversation via WhatsApp. When a visitor clicks the WhatsApp button, they are directed to `https://api.whatsapp.com/` to open WhatsApp Web or the WhatsApp application. No personal data or visitor records are automatically transmitted to WhatsApp.

* WhatsApp Service: https://www.whatsapp.com/
* WhatsApp Terms of Service: https://www.whatsapp.com/legal/terms-of-service
* WhatsApp Privacy Policy: https://www.whatsapp.com/legal/privacy-policy

== Installation ==

1. Upload the `live-chat-etaks` folder to your `/wp-content/plugins/` directory, or install the plugin directly through the WordPress plugins dashboard.
2. Activate the plugin via the **Plugins** screen in WordPress.
3. Go to **Live Chat** > **Settings** in your WordPress admin menu to configure your brand colors, widget title, and automated greeting messages.
4. The floating chat widget will now appear automatically for your website visitors!

== Frequently Asked Questions ==

= Does Live Chat - etaks require any external service or monthly fees? =
No. Live Chat - etaks runs 100% locally on your WordPress installation. There are no subscription fees, third-party accounts, or monthly charges.

= Where are chat conversations saved? =
All conversation records and messages are saved directly in a dedicated table inside your WordPress database.

= Can I customize the chat colors and images? =
Yes! You can choose your brand color, select from built-in color presets with instant live preview, and upload your custom widget icon and support avatar using the WordPress Media Library.

= Is the chat widget mobile-friendly? =
Yes. The chat window and floating button adapt dynamically to smartphones, tablets, and desktop displays.

= How do I clean up data when deleting the plugin? =
If you uninstall the plugin from the WordPress Plugins screen, all plugin database tables and options are safely deleted automatically via `uninstall.php`.

== Changelog ==

= 1.6.0 =
* Complete WordPress.org Plugin Directory compliance.
* Standardized text domain and plugin slug to live-chat-etaks.
* Fixed real-time admin conversation display and response management.
* Enhanced database repair diagnostics and added third-party disclosure for WhatsApp integration.
* Added full security escaping and capability validation across all endpoints.

= 1.4.1 =
* Removed top banner header section for a clean, distraction-free WordPress admin interface.
* Removed redundant card icons for clean, modern card headers.
* Added instant live color update to the floating action button preview circle when adjusting color picker or presets.
* Added official WordPress.org banners (772x250, 1544x500), icons (128x128, 256x256), and screenshots.
* Reordered readme sections (Description, Installation, FAQ, Changelog, Screenshots) matching the WordPress plugin directory modal tabs.

= 1.4.0 =
* Complete compliance with WordPress.org Plugin Directory guidelines.
* Modern clean UI view with empty state illustrations, segmented navigation tabs, and polished settings cards.
* Added 11 built-in languages covering both visitor widget and entire admin dashboard (English, Azerbaijani, Turkish, Russian, Uzbek, Spanish, French, Portuguese, Japanese, Chinese, and Arabic RTL).
* Added System Health & Diagnostics with one-click database table repair tool.
* Added live color preview on the floating action button and primary branding controls.
* Added official WordPress.org banners, icons, and screenshot assets.
* Automatic migration to new etaks globe logo assets and legacy setting sanitization.
* Added nonce validation and user capability checks on all AJAX endpoints.
* Fixed XSS vulnerabilities by implementing strict escaping across frontend and admin scripts.
* Upgraded database schema with indexing and clean `uninstall.php` handler.

= 1.0.0 =
* Initial release.

== Screenshots ==

1. Floating chat button and customer conversation window on website frontend.
2. Admin conversation manager with date filtering, active/archived tabs, and live responses.
3. Customization settings page for branding colors, 11 languages, automated responses, and system health diagnostics.

== Upgrade Notice ==

= 1.6.0 =
Official directory release with standardized text domain, conversation management bugfix, and full security compliance.

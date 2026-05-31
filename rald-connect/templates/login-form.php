<?php
/**
 * RALD Login Form template.
 * Include via: include RALD_CONNECT_PLUGIN_DIR . 'templates/login-form.php';
 * Or use shortcode: [rald_login_form]
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$redirect_to = esc_url( $_GET['redirect_to'] ?? wp_login_url() );
?>
<div class="rald-auth-form rald-login-form" data-rald-component="login">
    <div class="rald-auth-logo">
        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="40" height="40" rx="8" fill="#080D14"/>
            <path d="M8 12h10c4.4 0 8 3.6 8 8s-3.6 8-8 8H8V12z" fill="none" stroke="#00FF88" stroke-width="2"/>
            <circle cx="18" cy="20" r="3" fill="#00FF88"/>
            <line x1="24" y1="28" x2="32" y2="28" stroke="#00FF88" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="rald-auth-brand">RALD</span>
    </div>

    <h2 class="rald-auth-title"><?php esc_html_e( 'Sign in', 'rald-connect' ); ?></h2>

    <div class="rald-auth-messages" id="rald-login-messages" aria-live="polite"></div>

    <form id="rald-login-form" class="rald-form" novalidate>
        <?php wp_nonce_field( 'rald_login', '_rald_nonce' ); ?>
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">

        <div class="rald-form-group">
            <label for="rald-email"><?php esc_html_e( 'Email', 'rald-connect' ); ?></label>
            <input
                type="email"
                id="rald-email"
                name="email"
                class="rald-input"
                placeholder="<?php esc_attr_e( 'you@example.com', 'rald-connect' ); ?>"
                autocomplete="email"
                required
            >
        </div>

        <div class="rald-form-group">
            <label for="rald-password"><?php esc_html_e( 'Password', 'rald-connect' ); ?></label>
            <input
                type="password"
                id="rald-password"
                name="password"
                class="rald-input"
                placeholder="••••••••"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit" class="rald-btn rald-btn-primary" id="rald-login-btn">
            <span class="rald-btn-label"><?php esc_html_e( 'Sign in', 'rald-connect' ); ?></span>
            <span class="rald-btn-spinner" hidden></span>
        </button>
    </form>

    <?php if ( get_option( 'users_can_register' ) || get_option( 'rald_connect_register_page_id' ) ) : ?>
    <p class="rald-auth-footer">
        <?php esc_html_e( "Don't have an account?", 'rald-connect' ); ?>
        <a href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'Create one', 'rald-connect' ); ?></a>
    </p>
    <?php endif; ?>
</div>

<script>
(function() {
    const form    = document.getElementById('rald-login-form');
    const msgs    = document.getElementById('rald-login-messages');
    const btn     = document.getElementById('rald-login-btn');
    const label   = btn.querySelector('.rald-btn-label');
    const spinner = btn.querySelector('.rald-btn-spinner');

    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        msgs.innerHTML = '';
        btn.disabled = true;
        label.hidden = true;
        spinner.hidden = false;

        const email    = form.querySelector('[name="email"]').value.trim();
        const password = form.querySelector('[name="password"]').value;
        const redirect = form.querySelector('[name="redirect_to"]').value;
        const nonce    = form.querySelector('[name="_rald_nonce"]').value;

        try {
            const res = await fetch('<?php echo esc_url( rest_url("rald-connect/v1/auth/login") ); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce("wp_rest") ); ?>'
                },
                body: JSON.stringify({ email, password })
            });
            const data = await res.json();
            if (data.success) {
                window.location.href = redirect || '<?php echo esc_js( admin_url() ); ?>';
            } else {
                showError(data.error || '<?php echo esc_js( __("Login failed. Please try again.", "rald-connect") ); ?>');
            }
        } catch (err) {
            showError('<?php echo esc_js( __("Network error. Please try again.", "rald-connect") ); ?>');
        } finally {
            btn.disabled = false;
            label.hidden = false;
            spinner.hidden = true;
        }
    });

    function showError(msg) {
        msgs.innerHTML = '<div class="rald-alert rald-alert-error">' + escHtml(msg) + '</div>';
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
})();
</script>

<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$redirect_to = esc_url( $_GET['redirect_to'] ?? home_url( '/' ) );
?>
<div class="rald-auth-form rald-register-form" data-rald-component="register">
    <div class="rald-auth-logo">
        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="40" height="40" rx="8" fill="#080D14"/>
            <path d="M8 12h10c4.4 0 8 3.6 8 8s-3.6 8-8 8H8V12z" fill="none" stroke="#00FF88" stroke-width="2"/>
            <circle cx="18" cy="20" r="3" fill="#00FF88"/>
        </svg>
        <span class="rald-auth-brand">RALD</span>
    </div>

    <h2 class="rald-auth-title"><?php esc_html_e( 'Create account', 'rald-connect' ); ?></h2>
    <div class="rald-auth-messages" id="rald-register-messages" aria-live="polite"></div>

    <form id="rald-register-form" class="rald-form" novalidate>
        <?php wp_nonce_field( 'rald_register', '_rald_nonce' ); ?>
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">

        <div class="rald-form-group">
            <label for="rald-reg-name"><?php esc_html_e( 'Full name', 'rald-connect' ); ?></label>
            <input type="text" id="rald-reg-name" name="name" class="rald-input"
                   placeholder="<?php esc_attr_e( 'Your name', 'rald-connect' ); ?>"
                   autocomplete="name">
        </div>

        <div class="rald-form-group">
            <label for="rald-reg-email"><?php esc_html_e( 'Email', 'rald-connect' ); ?></label>
            <input type="email" id="rald-reg-email" name="email" class="rald-input"
                   placeholder="<?php esc_attr_e( 'you@example.com', 'rald-connect' ); ?>"
                   autocomplete="email" required>
        </div>

        <div class="rald-form-group">
            <label for="rald-reg-password"><?php esc_html_e( 'Password', 'rald-connect' ); ?></label>
            <input type="password" id="rald-reg-password" name="password" class="rald-input"
                   placeholder="<?php esc_attr_e( 'Min. 8 characters', 'rald-connect' ); ?>"
                   autocomplete="new-password" required minlength="8">
        </div>

        <button type="submit" class="rald-btn rald-btn-primary" id="rald-register-btn">
            <span class="rald-btn-label"><?php esc_html_e( 'Create account', 'rald-connect' ); ?></span>
            <span class="rald-btn-spinner" hidden></span>
        </button>
    </form>

    <p class="rald-auth-footer">
        <?php esc_html_e( 'Already have an account?', 'rald-connect' ); ?>
        <a href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'Sign in', 'rald-connect' ); ?></a>
    </p>
</div>

<script>
(function() {
    const form    = document.getElementById('rald-register-form');
    const msgs    = document.getElementById('rald-register-messages');
    const btn     = document.getElementById('rald-register-btn');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        msgs.innerHTML = '';
        btn.disabled = true;

        const email    = form.querySelector('[name="email"]').value.trim();
        const password = form.querySelector('[name="password"]').value;
        const name     = form.querySelector('[name="name"]').value.trim();
        const redirect = form.querySelector('[name="redirect_to"]').value;

        try {
            const res = await fetch('<?php echo esc_url( rest_url("rald-connect/v1/auth/register") ); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce("wp_rest") ); ?>'
                },
                body: JSON.stringify({ email, password, name })
            });
            const data = await res.json();
            if (data.success) {
                window.location.href = redirect || '<?php echo esc_js( home_url("/") ); ?>';
            } else {
                msgs.innerHTML = '<div class="rald-alert rald-alert-error">' + (data.error || 'Registration failed.') + '</div>';
            }
        } catch (err) {
            msgs.innerHTML = '<div class="rald-alert rald-alert-error">Network error. Please try again.</div>';
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>

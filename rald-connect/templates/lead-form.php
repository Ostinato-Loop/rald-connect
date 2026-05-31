<?php
/**
 * RALD Connect — Lead Form Template
 *
 * Variables: $type, $form_id, $nonce, $title, $btn_label
 *
 * @package RaldConnect
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="rald-form-wrap" id="<?php echo esc_attr( $form_id ); ?>">
    <h3 class="rald-form-title"><?php echo esc_html( $title ); ?></h3>
    <form class="rald-lead-form" data-form-type="<?php echo esc_attr( $type ); ?>" novalidate>
        <?php wp_nonce_field( 'rald_lead_nonce', 'rald_nonce_field' ); ?>
        <input type="hidden" name="form_type" value="<?php echo esc_attr( $type ); ?>">
        <input type="hidden" name="nonce"     value="<?php echo esc_attr( $nonce ); ?>">

        <div class="rald-field">
            <label><?php esc_html_e( 'Full Name', 'rald-connect' ); ?> <span class="req">*</span></label>
            <input type="text" name="name" required placeholder="<?php esc_attr_e( 'Your name', 'rald-connect' ); ?>">
        </div>
        <div class="rald-field">
            <label><?php esc_html_e( 'Email', 'rald-connect' ); ?> <span class="req">*</span></label>
            <input type="email" name="email" required placeholder="<?php esc_attr_e( 'you@example.com', 'rald-connect' ); ?>">
        </div>
        <div class="rald-field">
            <label><?php esc_html_e( 'Phone', 'rald-connect' ); ?></label>
            <input type="tel" name="phone" placeholder="<?php esc_attr_e( '+234...', 'rald-connect' ); ?>">
        </div>
        <?php if ( in_array( $type, [ 'contact', 'quote', 'inquiry' ], true ) ) : ?>
        <div class="rald-field">
            <label><?php esc_html_e( 'Message', 'rald-connect' ); ?></label>
            <textarea name="message" rows="4" placeholder="<?php esc_attr_e( 'How can we help?', 'rald-connect' ); ?>"></textarea>
        </div>
        <?php endif; ?>

        <div class="rald-form-status" style="display:none"></div>

        <button type="submit" class="rald-btn"><?php echo esc_html( $btn_label ); ?></button>
    </form>
</div>

<script>
(function(){
    var form = document.getElementById(<?php echo wp_json_encode( $form_id ); ?>);
    if (!form) return;
    var el = form.querySelector('.rald-lead-form');
    var status = form.querySelector('.rald-form-status');
    var btn = form.querySelector('button[type=submit]');
    el.addEventListener('submit', function(e) {
        e.preventDefault();
        var fd = new FormData(el);
        var data = {};
        fd.forEach(function(v,k){ data[k]=v; });
        btn.disabled = true;
        btn.textContent = (typeof raldLeads !== 'undefined') ? raldLeads.labels.sending : 'Sending…';
        status.style.display = 'none';
        fetch((typeof raldLeads !== 'undefined') ? raldLeads.url : '/wp-json/rald-connect/v1/leads', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-WP-Nonce': data.nonce||''},
            body: JSON.stringify(data)
        })
        .then(function(r){ return r.json(); })
        .then(function(r){
            status.style.display = 'block';
            if (r.success) {
                status.style.color = '#2ECFA3';
                status.textContent = r.message || ((typeof raldLeads !== 'undefined') ? raldLeads.labels.success : 'Thank you!');
                el.reset();
            } else {
                status.style.color = '#FF6B63';
                status.textContent = r.error || ((typeof raldLeads !== 'undefined') ? raldLeads.labels.error : 'Error. Try again.');
            }
            btn.disabled = false;
            btn.textContent = <?php echo wp_json_encode( $btn_label ); ?>;
        })
        .catch(function(){
            status.style.display = 'block';
            status.style.color = '#FF6B63';
            status.textContent = (typeof raldLeads !== 'undefined') ? raldLeads.labels.error : 'Error. Try again.';
            btn.disabled = false;
            btn.textContent = <?php echo wp_json_encode( $btn_label ); ?>;
        });
    });
})();
</script>

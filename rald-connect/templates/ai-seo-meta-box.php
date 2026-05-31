<?php
/**
 * RALD Connect — AI SEO Meta Box Template
 *
 * Variables: $post, $title, $desc, $schema, $nonce
 *
 * @package RaldConnect
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<input type="hidden" name="rald_seo_nonce" value="<?php echo esc_attr( $nonce ); ?>">
<div id="rald-ai-seo-box" style="font-family:sans-serif">
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
        <?php foreach ( [ 'title' => 'Generate Title', 'description' => 'Generate Description', 'schema' => 'Generate Schema', 'faqs' => 'Generate FAQs' ] as $type => $label ) : ?>
        <button type="button" class="rald-ai-btn button button-secondary"
            data-type="<?php echo esc_attr( $type ); ?>"
            data-post-id="<?php echo esc_attr( $post->ID ); ?>">
            <?php echo esc_html( $label ); ?>
        </button>
        <?php endforeach; ?>
        <span class="rald-ai-spinner" style="display:none;padding:4px 8px;color:#888;font-size:12px">Generating…</span>
    </div>
    <div id="rald-ai-status" style="display:none;padding:8px 12px;border-radius:6px;margin-bottom:12px;font-size:13px"></div>
    <p>
        <label style="font-weight:600;font-size:13px"><?php esc_html_e( 'SEO Title', 'rald-connect' ); ?></label><br>
        <input id="rald_seo_title_input" type="text" name="rald_seo_title" value="<?php echo $title; ?>" style="width:100%;margin-top:4px" class="widefat">
        <span id="rald_seo_title_count" style="font-size:11px;color:#888"></span>
    </p>
    <p>
        <label style="font-weight:600;font-size:13px"><?php esc_html_e( 'Meta Description', 'rald-connect' ); ?></label><br>
        <textarea id="rald_seo_desc_input" name="rald_seo_description" rows="3" style="width:100%;margin-top:4px" class="widefat"><?php echo $desc; ?></textarea>
        <span id="rald_seo_desc_count" style="font-size:11px;color:#888"></span>
    </p>
    <p>
        <label style="font-weight:600;font-size:13px"><?php esc_html_e( 'JSON-LD Schema', 'rald-connect' ); ?></label><br>
        <textarea id="rald_seo_schema_input" name="rald_seo_schema" rows="6" style="width:100%;margin-top:4px;font-family:monospace;font-size:12px" class="widefat"><?php echo $schema; ?></textarea>
    </p>
</div>
<script>
(function(){
    var nonce = (typeof raldAiSeo !== 'undefined') ? raldAiSeo.nonce : '';
    var url   = (typeof raldAiSeo !== 'undefined') ? raldAiSeo.url   : '';
    var postId= (typeof raldAiSeo !== 'undefined') ? raldAiSeo.postId: '';

    function getContent() {
        var c = document.getElementById('content');
        return c ? c.value.replace(/<[^>]+>/g,'').slice(0,2000) : '';
    }

    function setStatus(msg, ok) {
        var s = document.getElementById('rald-ai-status');
        s.style.display = 'block';
        s.style.background = ok ? 'rgba(46,207,163,.1)' : 'rgba(255,59,48,.1)';
        s.style.color = ok ? '#2ECFA3' : '#FF6B63';
        s.textContent = msg;
        setTimeout(function(){ s.style.display='none'; }, 4000);
    }

    document.querySelectorAll('.rald-ai-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var type = btn.dataset.type;
            var spinner = document.querySelector('.rald-ai-spinner');
            spinner.style.display = 'inline';
            btn.disabled = true;
            fetch(url, {
                method:'POST',
                headers:{'Content-Type':'application/json','X-WP-Nonce': nonce},
                body: JSON.stringify({ type: type, content: getContent(), nonce: nonce })
            })
            .then(function(r){ return r.json(); })
            .then(function(data){
                spinner.style.display = 'none';
                btn.disabled = false;
                if (data.error) { setStatus(data.error, false); return; }
                if (type === 'title' && data.title) {
                    document.getElementById('rald_seo_title_input').value = data.title;
                    setStatus('Title generated!', true);
                } else if (type === 'description' && data.description) {
                    document.getElementById('rald_seo_desc_input').value = data.description;
                    setStatus('Description generated!', true);
                } else if (type === 'schema' && data.schema) {
                    document.getElementById('rald_seo_schema_input').value = typeof data.schema === 'string' ? data.schema : JSON.stringify(data.schema, null, 2);
                    setStatus('Schema generated!', true);
                } else if (type === 'faqs' && data.faqs) {
                    setStatus('FAQs generated! Copy from below.', true);
                    var s = document.getElementById('rald_seo_schema_input');
                    s.value = JSON.stringify(data.faqs, null, 2);
                } else {
                    setStatus('Generated! Check fields above.', true);
                }
            })
            .catch(function(){
                spinner.style.display = 'none';
                btn.disabled = false;
                setStatus('AI service error. Check API key.', false);
            });
        });
    });

    // Character counters
    function counter(inputId, countId, max) {
        var el = document.getElementById(inputId);
        var ct = document.getElementById(countId);
        if (!el || !ct) return;
        function update() {
            var len = el.value.length;
            ct.textContent = len + ' / ' + max + (len > max ? ' (too long)' : '');
            ct.style.color = len > max ? '#FF6B63' : '#888';
        }
        el.addEventListener('input', update);
        update();
    }
    counter('rald_seo_title_input', 'rald_seo_title_count', 60);
    counter('rald_seo_desc_input',  'rald_seo_desc_count',  160);
})();
</script>

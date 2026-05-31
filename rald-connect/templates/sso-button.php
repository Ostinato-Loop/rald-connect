<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <rect width="16" height="16" rx="3" fill="#080D14"/>
        <circle cx="8" cy="8" r="3" fill="none" stroke="#00FF88" stroke-width="1.5"/>
        <path d="M12 12l2 2" stroke="#00FF88" stroke-width="1.5" stroke-linecap="round"/>
    </svg>
    <?php echo esc_html( $atts['label'] ); ?>
</a>

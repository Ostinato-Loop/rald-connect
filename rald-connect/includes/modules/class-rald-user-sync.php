<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Syncs a RALD user object into a WordPress WP_User.
 * RALD is always the source of truth. WP user is a shadow/proxy.
 */
class RALD_User_Sync {

    /**
     * Find or create a WP_User for the given RALD user.
     * Returns WP_User on success, WP_Error on failure.
     *
     * @param array  $rald_user   User array from auth.rald.cloud
     * @param string $token       RALD JWT token
     * @return WP_User|WP_Error
     */
    public static function sync( array $rald_user, string $token ) {
        $email   = sanitize_email( $rald_user['email'] ?? '' );
        $rald_id = sanitize_text_field( $rald_user['rald_id'] ?? '' );
        $name    = sanitize_text_field( $rald_user['name']    ?? '' );
        $role    = sanitize_text_field( $rald_user['role']    ?? 'user' );

        if ( ! $email ) {
            return new WP_Error( 'rald_sync_no_email', __( 'RALD user has no email address.', 'rald-connect' ) );
        }

        // Try to find existing WP user by email
        $wp_user = get_user_by( 'email', $email );

        if ( $wp_user ) {
            self::update_meta( $wp_user->ID, $rald_user, $token );
            return $wp_user;
        }

        // Create new shadow WP user
        $username = $rald_id ?: 'rald_' . substr( md5( $email ), 0, 8 );

        // Ensure username is unique
        if ( username_exists( $username ) ) {
            $username .= '_' . substr( md5( uniqid() ), 0, 4 );
        }

        $wp_role = self::map_role( $role );

        $user_id = wp_insert_user( [
            'user_login'   => $username,
            'user_email'   => $email,
            'display_name' => $name ?: $email,
            'first_name'   => self::extract_first_name( $name ),
            'last_name'    => self::extract_last_name( $name ),
            'user_pass'    => wp_generate_password( 32, true, true ), // Random — RALD is auth source
            'role'         => $wp_role,
        ] );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        self::update_meta( $user_id, $rald_user, $token );
        return get_user_by( 'ID', $user_id );
    }

    /**
     * Update user meta from fresh RALD user data.
     */
    public static function update_meta( int $wp_user_id, array $rald_user, string $token ): void {
        RALD_Token_Store::save( $wp_user_id, $token, $rald_user );

        update_user_meta( $wp_user_id, '_rald_id',       sanitize_text_field( $rald_user['rald_id'] ?? '' ) );
        update_user_meta( $wp_user_id, '_rald_role',     sanitize_text_field( $rald_user['role']    ?? 'user' ) );
        update_user_meta( $wp_user_id, '_rald_verified', ! empty( $rald_user['email_verified'] ) ? '1' : '0' );

        // Keep WP display name in sync
        wp_update_user( [
            'ID'           => $wp_user_id,
            'display_name' => sanitize_text_field( $rald_user['name'] ?? '' ),
        ] );
    }

    /**
     * Map RALD role → WordPress role.
     */
    private static function map_role( string $rald_role ): string {
        $map = apply_filters( 'rald_connect_role_map', [
            'admin'    => 'administrator',
            'operator' => 'editor',
            'merchant' => 'author',
            'user'     => 'subscriber',
        ] );

        return $map[ $rald_role ] ?? 'subscriber';
    }

    private static function extract_first_name( string $name ): string {
        $parts = explode( ' ', trim( $name ), 2 );
        return $parts[0] ?? '';
    }

    private static function extract_last_name( string $name ): string {
        $parts = explode( ' ', trim( $name ), 2 );
        return $parts[1] ?? '';
    }
}

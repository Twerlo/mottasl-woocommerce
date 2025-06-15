<?php
/**
 * Utility helper functions.
 *
 * @since      1.0.0
 * @package    Mottasl_WooCommerce
 * @subpackage Mottasl_WooCommerce/Utils
 * @author     Your Name <your-email@example.com>
 */

namespace Mottasl\WooCommerce\Utils;

defined( 'ABSPATH' ) || exit;

class Helper {

    /**
     * Get the store's base URL.
     *
     * @since  1.0.0
     * @return string The store URL.
     */
    public static function get_store_url(): string {
        return home_url();
    }

    /**
     * Get plugin settings.
     *
     * @since 1.0.0
     * @param string|null $key Specific setting key to retrieve. If null, returns all settings.
     * @param mixed $default Default value if the setting is not found.
     * @return mixed The setting value or all settings.
     */
    public static function get_setting( ?string $key = null, $default = null ) {
        $options = get_option( \Mottasl\WooCommerce\Constants::SETTINGS_OPTION_KEY, [] );

        if ( $key !== null ) {
            return $options[ $key ] ?? $default;
        }

        return $options;
    }

    /**
     * Sanitize a string for use as a filename.
     *
     * @param string $filename The filename to sanitize.
     * @return string The sanitized filename.
     */
    public static function sanitize_filename(string $filename): string {
        // Remove anything which isn't a word, whitespace, number or any of the following caracters -_~,;:[]().
        $filename = preg_replace( '([^\w\s\d\-_~,;:\[\]\(\).])', '', $filename );
        // Remove any runs of periods (thanks falstro!)
        $filename = preg_replace( '([\.]{2,})', '', $filename );
        // Replace whitespace with underscores
        $filename = preg_replace( '/\s+/', '_', $filename );
        // Remove leading/trailing underscores
        $filename = trim( $filename, '_' );
        // Lowercase
        $filename = strtolower( $filename );
        return $filename;
    }

    /**
     * Get the path to the directory for storing generated invoice PDFs.
     * Creates the directory if it doesn't exist.
     *
     * @since 1.0.0
     * @return string|false Path to the directory, or false on failure.
     */
    public static function get_invoice_storage_path(): string|false {
        $upload_dir = wp_upload_dir();
        // Use a constant if defined, otherwise a default directory name
        $invoice_dir_name = defined('\Mottasl\WooCommerce\Constants::PDF_STORAGE_DIRECTORY') ? \Mottasl\WooCommerce\Constants::PDF_STORAGE_DIRECTORY : 'mottasl-invoices';
        $invoice_path = trailingslashit( $upload_dir['basedir'] ) . $invoice_dir_name;

        if ( ! file_exists( $invoice_path ) ) {
            if ( ! wp_mkdir_p( $invoice_path ) ) {
                error_log( "Mottasl WC: Unable to create invoice directory: " . $invoice_path );
                return false;
            }
            // Add a .htaccess file to prevent direct browsing if possible, and an index.html
            self::secure_directory($invoice_path);
        }
        return $invoice_path;
    }

    /**
     * Secures a directory by adding an .htaccess and index.html file.
     *
     * @param string $directory_path Path to the directory.
     */
    private static function secure_directory(string $directory_path): void {
        // Add .htaccess to prevent directory listing and direct PHP execution (if server allows)
        $htaccess_content = "Options -Indexes\n";
        $htaccess_content .= "<FilesMatch \"\.(php|phtml|php[3-7]|phps)$\">\n";
        $htaccess_content .= "    Order Allow,Deny\n";
        $htaccess_content .= "    Deny from all\n";
        $htaccess_content .= "</FilesMatch>\n";

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
        @file_put_contents( trailingslashit( $directory_path ) . '.htaccess', $htaccess_content );

        // Add a blank index.html to prevent directory listing if .htaccess is not processed
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
        @file_put_contents( trailingslashit( $directory_path ) . 'index.html', '<!-- Silence is golden. -->' );
    }

    // Add more helper functions as needed (e.g., formatting data, sanitization)
}
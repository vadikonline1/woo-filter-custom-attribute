<?php
/**
 * Plugin Name: FAC - Filter Custom Attribute
 * Description: Un plugin de filtrare WooCommerce după taxonomii/atribute personalizate.
 * Version: 1.0.1
 * Author: Steel..xD
 * Plugin URI: https://github.com/vadikonline1/woo-filter-custom-attribute/
 * Author URI: https://github.com/vadikonline1/woo-filter-custom-attribute/
 * GitHub Plugin URI: https://github.com/vadikonline1/woo-filter-custom-attribute/
 */


if ( ! defined( 'ABSPATH' ) ) exit;

define( 'FAC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'FAC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
add_action('admin_init', function() {
    // Only run in admin area
    if (!is_admin()) return;
    
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    $required_plugin = 'github-plugin-manager/github-plugin-manager.php';
    $current_plugin = plugin_basename(__FILE__);
    
    // If current plugin is active but required plugin is not
    if (is_plugin_active($current_plugin) && !is_plugin_active($required_plugin)) {
        // Deactivate current plugin
        deactivate_plugins($current_plugin);
        
        // Show admin notice
        add_action('admin_notices', function() {
            $plugin_name = get_plugin_data(__FILE__)['Name'] ?? 'This plugin';
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php echo esc_html($plugin_name); ?></strong> has been deactivated.
                    <br>
                    This plugin requires <strong>GitHub Plugin Manager</strong> to function properly.
                </p>
                <p>
                    <strong>How to fix:</strong>
                    <ol style="margin-left: 20px;">
                        <li>Download <a href="https://github.com/vadikonline1/github-plugin-manager" target="_blank">GitHub Plugin Manager from GitHub</a></li>
                        <li>Go to WordPress Admin → Plugins → Add New → Upload Plugin</li>
                        <li>Upload the downloaded ZIP file and activate it</li>
                        <li>Reactivate <?php echo esc_html($plugin_name); ?></li>
                    </ol>
                </p>
                <p>
                    <a href="https://github.com/vadikonline1/github-plugin-manager/archive/refs/heads/main.zip" 
                       class="button button-primary"
                       style="margin-right: 10px;">
                        ⬇️ Download Plugin (ZIP)
                    </a>
                    <a href="<?php echo admin_url('plugin-install.php?tab=upload'); ?>" 
                       class="button">
                        📤 Upload to WordPress
                    </a>
                </p>
            </div>
            <?php
        });
    }
});

// Prevent activation without required plugin
register_activation_hook(__FILE__, function() {
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    if (!is_plugin_active('github-plugin-manager/github-plugin-manager.php')) {
        $plugin_name = get_plugin_data(__FILE__)['Name'] ?? 'This plugin';
        
        // Create a user-friendly error message
        $error_message = '
        <div style="max-width: 700px; margin: 50px auto; padding: 30px; background: #fff; border: 2px solid #d63638; border-radius: 5px;">
            <h2 style="color: #d63638; margin-top: 0;">
                <span style="font-size: 24px;">⚠️</span> Missing Required Plugin
            </h2>
            
            <p><strong>' . esc_html($plugin_name) . '</strong> cannot be activated because it requires another plugin to be installed first.</p>
            
            <div style="background: #f0f6fc; padding: 20px; border-radius: 4px; margin: 20px 0;">
                <h3 style="margin-top: 0;">Required Plugin: GitHub Plugin Manager</h3>
                <p>This plugin manages GitHub repositories directly from your WordPress dashboard.</p>
            </div>
            
            <h3>Installation Steps:</h3>
            <ol>
                <li><strong>Download:</strong> Get the plugin from <a href="https://github.com/vadikonline1/github-plugin-manager" target="_blank">GitHub</a></li>
                <li><strong>Upload:</strong> Go to <a href="' . admin_url('plugin-install.php?tab=upload') . '">Plugins → Add New → Upload Plugin</a></li>
                <li><strong>Activate:</strong> Activate the GitHub Plugin Manager</li>
                <li><strong>Return:</strong> Come back and activate ' . esc_html($plugin_name) . '</li>
            </ol>
            
            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #ddd;">
                <a href="https://github.com/vadikonline1/github-plugin-manager/archive/refs/heads/main.zip" 
                   class="button button-primary button-large"
                   style="margin-right: 10px;">
                    Download ZIP File
                </a>
                <a href="' . admin_url('plugins.php') . '" class="button button-large">
                    Return to Plugins
                </a>
            </div>
            
            <p style="margin-top: 20px; color: #666; font-size: 13px;">
                <strong>Note:</strong> All plugins that require GitHub Plugin Manager will be deactivated until it is installed.
            </p>
        </div>';
        
        // Stop activation with the error message
        wp_die($error_message, 'Missing Required Plugin', 200);
    }
});

require_once FAC_PLUGIN_PATH . 'includes/class-fac-widget.php';
require_once FAC_PLUGIN_PATH . 'includes/class-fac-admin-settings.php';
require_once FAC_PLUGIN_PATH . 'includes/class-fac-shortcode.php';
// Înregistrăm widget-ul
function fac_register_widget() {
    register_widget( 'FAC_Filter_Widget' );
}
add_action( 'widgets_init', 'fac_register_widget' );

// Încărcare CSS
function fac_enqueue_assets() {
    if ( is_shop() || is_product_category() || is_product_taxonomy() || is_page() ) {
        wp_enqueue_style( 'fac-style', FAC_PLUGIN_URL . 'assets/css/fac-style.css', [], null );
    }
}
add_action( 'wp_enqueue_scripts', 'fac_enqueue_assets' );

// Aplică filtrele din URL la query-ul principal
function fac_apply_filters_to_query( $query ) {
    // ✅ Exclude request-urile de preview
    if ( isset( $_GET['preview'] ) && $_GET['preview'] === 'true' ) {
        return;
    }
    
    if ( ! is_admin() && $query->is_main_query() && ( is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy() ) ) {
        
        $tax_query = $query->get( 'tax_query' ) ?: [];
        
        // Procesează toți parametrii care încep cu 'filter_'
        foreach ( $_GET as $key => $value ) {
            if ( strpos( $key, 'filter_' ) === 0 && ! empty( $value ) ) {
                $taxonomy = str_replace( 'filter_', '', $key );
                
                if ( taxonomy_exists( $taxonomy ) ) {
                    // ✅ Asigură-te că valoarea este string
                    $terms = is_string( $value ) ? explode( ',', sanitize_text_field( $value ) ) : [];
                    
                    $valid_terms = [];
                    
                    // Verifică dacă termenii există
                    foreach ( $terms as $term_slug ) {
                        $term = get_term_by( 'slug', $term_slug, $taxonomy );
                        if ( $term && ! is_wp_error( $term ) ) {
                            $valid_terms[] = $term_slug;
                        }
                    }
                    
                    if ( ! empty( $valid_terms ) ) {
                        $tax_query[] = [
                            'taxonomy' => $taxonomy,
                            'field'    => 'slug',
                            'terms'    => $valid_terms,
                            'operator' => 'IN'
                        ];
                    }
                }
            }
        }
        
        if ( count( $tax_query ) > 1 ) {
            $tax_query['relation'] = 'AND';
        }
        
        if ( ! empty( $tax_query ) ) {
            $query->set( 'tax_query', $tax_query );
        }
    }
}
add_action( 'pre_get_posts', 'fac_apply_filters_to_query', 20 );

// Adaugă filtrele FAC în widget-ul "Filtre active" WooCommerce
function fac_add_to_woocommerce_active_filters( $filter_list ) {
    foreach ( $_GET as $key => $value ) {
        if ( strpos( $key, 'filter_' ) === 0 && ! empty( $value ) ) {
            $taxonomy = str_replace( 'filter_', '', $key );
            
            if ( taxonomy_exists( $taxonomy ) ) {
                $terms = is_string( $value ) ? explode( ',', $value ) : [];
                
                foreach ( $terms as $term_slug ) {
                    $term = get_term_by( 'slug', $term_slug, $taxonomy );
                    if ( $term && ! is_wp_error( $term ) ) {
                        
                        // Creează URL-ul fără acest filtru
                        $current_filters = $terms;
                        $key_to_remove = array_search( $term_slug, $current_filters );
                        if ( $key_to_remove !== false ) {
                            unset( $current_filters[ $key_to_remove ] );
                        }
                        
                        $remove_url = add_query_arg( 
                            [ 'filter_' . $taxonomy => ! empty( $current_filters ) ? implode( ',', $current_filters ) : null ],
                            remove_query_arg( [ 'filter_' . $taxonomy, 'paged' ] )
                        );
                        
                        // Adaugă la lista de filtre active WooCommerce
                        $filter_list[] = [
                            'name' => $term->name,
                            'remove_url' => $remove_url
                        ];
                    }
                }
            }
        }
    }
    
    return $filter_list;
}
add_filter( 'woocommerce_layered_nav_filters', 'fac_add_to_woocommerce_active_filters' );

new FAC_Shortcode();

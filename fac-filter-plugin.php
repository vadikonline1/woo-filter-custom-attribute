<?php
/**
 * Plugin Name: FAC - Filter Custom Attribute
 * Description: Un plugin de filtrare WooCommerce după taxonomii/atribute personalizate.
 * Version: 1.0
 * Author: Steel..xD
 * Plugin URI: https://github.com/vadikonline1/woo-filter-custom-attribute/
 * Author URI: https://github.com/vadikonline1/woo-filter-custom-attribute/
 * GitHub Plugin URI: https://github.com/vadikonline1/woo-filter-custom-attribute/
 */


if ( ! defined( 'ABSPATH' ) ) exit;

define( 'FAC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'FAC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

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
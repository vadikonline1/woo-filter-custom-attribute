<?php
/**
 * Plugin Name: FAC - Filter Custom Attribute
 * Description: Un plugin de filtrare WooCommerce după taxonomii/atribute personalizate.
 * Version: 1.0
 * Author: Steel..xD
 * Plugin URI: https://github.com/vadikonline1/
 * Author URI: https://github.com/vadikonline1/
 * GitHub Plugin URI: https://github.com/vadikonline1/
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'FAC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'FAC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once FAC_PLUGIN_PATH . 'includes/class-fac-widget.php';
require_once FAC_PLUGIN_PATH . 'includes/class-fac-admin-settings.php';

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'fac_settings_link');
function fac_settings_link($links) {
    if (!current_user_can('manage_options')) {
        return $links;
    }
    
    $settings_link = '<a href="' . admin_url('/wp-admin/admin.php?page=fac-settings') . '">' . __('Setări', 'fac-settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Înregistrăm widget-ul
function fac_register_widget() {
    register_widget( 'FAC_Filter_Widget' );
}
add_action( 'widgets_init', 'fac_register_widget' );

// Încărcare CSS
function fac_enqueue_assets() {
    if ( is_shop() || is_product_category() || is_product_taxonomy() ) {
        wp_enqueue_style( 'fac-style', FAC_PLUGIN_URL . 'assets/css/fac-style.css');
    }
}
add_action( 'wp_enqueue_scripts', 'fac_enqueue_assets' );

// Adaugă filtrele active în lista WooCommerce
function fac_add_to_active_filters( $active_filters ) {
    foreach ( $_GET as $key => $value ) {
        if ( strpos( $key, 'filter_' ) === 0 && ! empty( $value ) ) {
            $taxonomy = str_replace( 'filter_', '', $key );
            
            if ( taxonomy_exists( $taxonomy ) ) {
                $terms = explode( ',', $value );
                
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
                            [ 'filter_' . $taxonomy => implode( ',', $current_filters ) ],
                            remove_query_arg( [ 'filter_' . $taxonomy, 'paged' ] )
                        );
                        
                        $active_filters[] = [
                            'name' => $term->name,
                            'remove_url' => $remove_url
                        ];
                    }
                }
            }
        }
    }
    
    return $active_filters;
}
add_filter( 'woocommerce_layered_nav_filters', 'fac_add_to_active_filters' );

// Adaugă link-ul "Clear all" pentru toate filtrele FAC
function fac_add_clear_all_filters() {
    $has_fac_filters = false;
    $fac_filter_params = [];
    
    // Identifică toți parametrii FAC
    foreach ( $_GET as $key => $value ) {
        if ( strpos( $key, 'filter_' ) === 0 && ! empty( $value ) ) {
            $has_fac_filters = true;
            $fac_filter_params[] = $key;
        }
    }
    
    if ( $has_fac_filters ) {
        $clear_url = remove_query_arg( $fac_filter_params );
        echo '<div class="fac-clear-all" style="margin: 15px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">';
        echo '<a href="' . esc_url( $clear_url ) . '" class="button" style="background: #dc3545; color: white; border: none; padding: 8px 16px; text-decoration: none; border-radius: 4px; display: inline-block;">';
        echo 'Șterge toate filtrele FAC';
        echo '</a>';
        echo '</div>';
    }
}
add_action( 'woocommerce_before_shop_loop', 'fac_add_clear_all_filters' );

// Adaugă stiluri pentru buton
function fac_add_clear_button_styles() {
    if ( is_shop() || is_product_category() || is_product_taxonomy() ) {
        echo '
        <style>
        .fac-clear-all .button:hover {
            background: #c82333 !important;
            color: white !important;
        }
        .woocommerce .fac-clear-all {
            border: 1px solid #e5e5e5;
        }
        </style>
        ';
    }
}
add_action( 'wp_head', 'fac_add_clear_button_styles' );


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

// Aplică filtrele din URL la query-ul principal
function fac_apply_filters_to_query( $query ) {
    if ( ! is_admin() && $query->is_main_query() && ( is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy() ) ) {
        
        $tax_query = $query->get( 'tax_query' ) ?: [];
        
        // Procesează toți parametrii care încep cu 'filter_'
        foreach ( $_GET as $key => $value ) {
            if ( strpos( $key, 'filter_' ) === 0 && ! empty( $value ) ) {
                $taxonomy = str_replace( 'filter_', '', $key );
                
                if ( taxonomy_exists( $taxonomy ) ) {
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

// Asigură-te că stilurile WooCommerce sunt încărcate pentru widget-ul de filtre active
function fac_enqueue_woocommerce_styles() {
    if ( is_shop() || is_product_category() || is_product_taxonomy() ) {
        if ( function_exists( 'wc_enqueue_js' ) ) {
            wc_enqueue_js( "
                jQuery(document).ready(function($) {
                    // Asigură-te că widget-ul de filtre active este afișat corect
                    if ($('.woocommerce-widget-layered-nav-list').length) {
                        $('.woocommerce-widget-layered-nav-list').show();
                    }
                });
            " );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'fac_enqueue_woocommerce_styles' );
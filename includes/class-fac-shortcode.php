<?php

class FAC_Shortcode {

    private $scripts_loaded = false;

    public function __construct() {
        add_shortcode( 'fac-menu', [ $this, 'render_fac_menu' ] );
        add_action( 'wp_footer', [ $this, 'add_dropdown_scripts' ] );
    }

    public function render_fac_menu( $atts ) {
        $atts = shortcode_atts( [
            'fac-position'  => 'orizontal',
            'fac-filter-id' => '',
            'fac-class'     => '',
            'count'         => '1'
        ], $atts, 'fac-menu' );

        $filter_ids = array_map( 'trim', explode( ',', $atts['fac-filter-id'] ) );
        
        // CORECTARE: Verificare corectă pentru toate ID-urile, inclusiv 0
        $filter_ids = array_filter( $filter_ids, function( $id ) {
            return $id !== '';
        });
        
        if ( empty( $filter_ids ) ) {
            return '<p>Nu există filtre configurate pentru acest meniu.</p>';
        }

        $saved_filters = FAC_Admin_Settings::get_saved_filters();
        $available_filters = [];

        foreach ( $filter_ids as $filter_id ) {
            // CORECTARE: Verificare explicită pentru toate index-uri, inclusiv 0
            if ( isset( $saved_filters[ $filter_id ] ) ) {
                $available_filters[ $filter_id ] = $saved_filters[ $filter_id ];
            } else {
                // Debug: afișează ce ID-uri nu sunt găsite
                error_log( "FAC Filter: ID {$filter_id} nu a fost găsit în filtrele salvate." );
            }
        }

        if ( empty( $available_filters ) ) {
            return '<p>Nu există filtre configurate pentru acest meniu. ID-uri solicitate: ' . esc_html( $atts['fac-filter-id'] ) . '</p>';
        }

        // Marchează că scripturile trebuie încărcate
        $this->scripts_loaded = true;

        $position_class = ( $atts['fac-position'] === 'vertical' ) ? 'fac-menu-vertical' : 'fac-menu-horizontal';
        $custom_class = ! empty( $atts['fac-class'] ) ? sanitize_html_class( $atts['fac-class'] ) : '';
        $show_count = $atts['count'] === '1'; // Convertim în boolean

        ob_start();
        ?>
        <div class="fac-menu-container <?php echo esc_attr( $position_class ); ?> <?php echo esc_attr( $custom_class ); ?>">
            <form method="get" action="<?php echo esc_url( $this->get_shop_url() ); ?>" class="fac-menu-form" id="fac-menu-form">
                <?php 
                foreach ( $available_filters as $filter_id => $filter ) {
                    $this->render_filter_field( $filter_id, $filter, $show_count );
                }
                ?>
                
                <div class="fac-menu-submit">
                    <button type="submit" class="fac-menu-btn">
                        <span class="dashicons dashicons-search"></span>
                        Caută
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_filter_field( $filter_id, $filter, $show_count = true ) {
        $terms = $this->get_filter_terms( $filter['taxonomy'] );
        
        if ( empty( $terms ) ) {
            error_log( "FAC Filter: Nu s-au găsit termeni pentru taxonomia: " . $filter['taxonomy'] );
            return;
        }
        ?>
        <div class="fac-menu-field">
            <?php if ( $filter['type'] === 'multiselect' ) : ?>
                <!-- Dropdown custom cu checkbox-uri pentru multiple select -->
                <div class="fac-custom-dropdown" data-taxonomy="<?php echo esc_attr( $filter['taxonomy'] ); ?>">
                    <div class="fac-dropdown-toggle">
                        <span class="fac-dropdown-placeholder" style="text-align: center;"><?php echo esc_html( $filter['label'] ); ?></span>
                        <span class="fac-dropdown-arrow">▾</span>
                    </div>
                    <div class="fac-dropdown-content">
                        <div class="fac-checkbox-group">
                            <?php foreach ( $terms as $term ) : ?>
                                <div class="fac-checkbox-item">
                                    <input type="checkbox" 
                                           id="fac-<?php echo esc_attr( $filter['taxonomy'] ); ?>-<?php echo esc_attr( $term->slug ); ?>" 
                                           value="<?php echo esc_attr( $term->slug ); ?>"
                                           class="fac-multiselect-checkbox">
                                    <label for="fac-<?php echo esc_attr( $filter['taxonomy'] ); ?>-<?php echo esc_attr( $term->slug ); ?>">
                                        <?php echo esc_html( $term->name ); ?>
                                        <?php if ( $show_count && $term->count > 0 ) : ?>
                                            <span class="fac-checkbox-count">(<?php echo $term->count; ?>)</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="fac-dropdown-actions">
                            <button type="button" class="fac-select-all">Selectează tot</button>
                            <button type="button" class="fac-clear-all">Șterge selecția</button>
                        </div>
                    </div>
                </div>
            <?php else : // select simplu sau checkbox ?>
                <select name="filter_<?php echo esc_attr( $filter['taxonomy'] ); ?>" 
                        id="fac-filter-<?php echo esc_attr( $filter_id ); ?>" style="background-color: rgb(255 255 255 / 70%);">
                    <option value="" style="text-align: center;"><?php echo esc_html( $filter['label'] ); ?></option>
                    <?php foreach ( $terms as $term ) : ?>
                        <option value="<?php echo esc_attr( $term->slug ); ?>">
                            <?php echo esc_html( $term->name ); ?>
                            <?php if ( $show_count && $term->count > 0 ) : ?>
                                (<?php echo $term->count; ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
        <?php
    }

    public function add_dropdown_scripts() {
        // Încarcă scripturile doar dacă shortcode-ul a fost folosit
        if ( ! $this->scripts_loaded ) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('FAC Dropdown Script loaded'); // Debug line
            
            // Toggle dropdown
            $('.fac-dropdown-toggle').on('click', function(e) {
                e.stopPropagation();
                console.log('Dropdown toggle clicked'); // Debug line
                
                var $dropdown = $(this).closest('.fac-custom-dropdown');
                var $content = $dropdown.find('.fac-dropdown-content');
                
                // Închide toate celelalte dropdown-uri
                $('.fac-dropdown-content').not($content).hide();
                
                // Toggle dropdown-ul curent
                $content.toggle();
                console.log('Dropdown visibility:', $content.is(':visible')); // Debug line
            });

            // Închide dropdown când se face click în afara
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.fac-custom-dropdown').length) {
                    $('.fac-dropdown-content').hide();
                }
            });

            // Gestionare checkbox-uri
            $('.fac-multiselect-checkbox').on('change', function() {
                console.log('Checkbox changed'); // Debug line
                
                var $dropdown = $(this).closest('.fac-custom-dropdown');
                var $toggle = $dropdown.find('.fac-dropdown-toggle');
                var selectedLabels = [];
                
                $dropdown.find('.fac-multiselect-checkbox:checked').each(function() {
                    var label = $(this).closest('.fac-checkbox-item').find('label').text().split(' (')[0];
                    selectedLabels.push(label);
                });
                
                if (selectedLabels.length > 0) {
                    var displayText = selectedLabels.length === 1 ? selectedLabels[0] : selectedLabels.length + ' selectate';
                    $toggle.find('.fac-dropdown-placeholder').text(displayText);
                } else {
                    $toggle.find('.fac-dropdown-placeholder').text('-- ' + $toggle.find('.fac-dropdown-placeholder').data('original') + ' --');
                }
            });

            // Salvează textul original al placeholder-ului
            $('.fac-dropdown-placeholder').each(function() {
                var originalText = $(this).text().replace(/^--\s*|\s*--$/g, '');
                $(this).data('original', originalText);
            });

            // Selectează tot
            $('.fac-select-all').on('click', function(e) {
                e.preventDefault();
                console.log('Select all clicked'); // Debug line
                
                var $dropdown = $(this).closest('.fac-custom-dropdown');
                $dropdown.find('.fac-multiselect-checkbox').prop('checked', true).trigger('change');
            });

            // Șterge selecția
            $('.fac-clear-all').on('click', function(e) {
                e.preventDefault();
                console.log('Clear all clicked'); // Debug line
                
                var $dropdown = $(this).closest('.fac-custom-dropdown');
                $dropdown.find('.fac-multiselect-checkbox').prop('checked', false).trigger('change');
            });

            // Procesare formular
            $('#fac-menu-form').on('submit', function(e) {
                console.log('Form submitted'); // Debug line
                
                // Pentru dropdown-urile custom cu checkbox-uri
                $(this).find('.fac-custom-dropdown').each(function() {
                    var taxonomy = $(this).data('taxonomy');
                    var checkedValues = [];
                    
                    $(this).find('.fac-multiselect-checkbox:checked').each(function() {
                        checkedValues.push($(this).val());
                    });
                    
                    if (checkedValues.length > 0) {
                        // Crează un input hidden cu valorile ca string
                        var hiddenInput = $('<input type="hidden">')
                            .attr('name', 'filter_' + taxonomy)
                            .attr('value', checkedValues.join(','));
                        $(this).after(hiddenInput);
                    }
                });

                // Curăță toate select-urile goale înainte de submit
                $(this).find('select').each(function() {
                    if ($(this).val() === '' || $(this).val() === null) {
                        $(this).prop('disabled', true);
                    }
                });
            });
        });
        </script>

        <style>
        .fac-menu-container {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            background: rgb(250 250 250 / 20%);
        }

        .fac-menu-horizontal .fac-menu-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .fac-menu-vertical .fac-menu-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .fac-menu-field {
            flex: 1;
            min-width: 200px;
        }

        .fac-menu-vertical .fac-menu-field {
            width: 100%;
        }

        .fac-menu-field select,
        .fac-menu-field input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background: white;
        }

        .fac-menu-field select:focus,
        .fac-menu-field input[type="text"]:focus {
            border-color: #007cba;
            outline: none;
            box-shadow: 0 0 0 1px #007cba;
        }

        /* Stiluri pentru dropdown custom */
        .fac-custom-dropdown {
            position: relative;
            width: 100%;
        }

        .fac-dropdown-toggle {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .fac-dropdown-toggle:hover {
            border-color: #007cba;
            background: #f8f9fa;
        }

        .fac-dropdown-arrow {
            color: #666;
            font-size: 12px;
            transition: transform 0.3s ease;
        }

        .fac-custom-dropdown.active .fac-dropdown-arrow {
            transform: rotate(180deg);
        }

        .fac-dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 1000;
            margin-top: 5px;
            max-height: 300px;
            overflow: hidden;
        }

        .fac-checkbox-group {
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
        }

        .fac-checkbox-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            padding: 5px 0;
        }

        .fac-checkbox-item:last-child {
            margin-bottom: 0;
        }

        .fac-checkbox-item input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.1);
            cursor: pointer;
        }

        .fac-checkbox-item label {
            cursor: pointer;
            font-size: 14px;
            color: #333;
            margin: 0;
            flex: 1;
        }

        .fac-checkbox-item:hover {
            background: #f8f9fa;
            border-radius: 3px;
            padding-left: 5px;
            padding-right: 5px;
        }

        .fac-checkbox-count {
            color: #666;
            font-size: 12px;
            margin-left: 5px;
        }

        .fac-dropdown-actions {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-top: 1px solid #eee;
            background: #f8f9fa;
        }

        .fac-dropdown-actions button {
            background: none;
            border: 1px solid #ddd;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            color: #555;
            transition: all 0.3s ease;
        }

        .fac-dropdown-actions button:hover {
            background: #007cba;
            color: white;
            border-color: #007cba;
        }

        .fac-menu-submit {
            flex-shrink: 0;
        }

        .fac-menu-horizontal .fac-menu-submit {
            margin-top: 0;
        }

        .fac-menu-vertical .fac-menu-submit {
            margin-top: 10px;
        }

        .fac-menu-btn .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }

        /* Scrollbar personalizat */
        .fac-checkbox-group::-webkit-scrollbar {
            width: 6px;
        }

        .fac-checkbox-group::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .fac-checkbox-group::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .fac-checkbox-group::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .fac-menu-horizontal .fac-menu-form {
                flex-direction: column;
            }
            
            .fac-menu-horizontal .fac-menu-field {
                min-width: 100%;
            }

            .fac-dropdown-content {
                position: fixed;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%);
                width: 90vw;
                max-width: 400px;
                max-height: 70vh;
            }
        }
			
		button.fac-menu-btn {
			color: var(--secondary);
		}
			
        </style>
        <?php
    }

    private function get_filter_terms( $taxonomy ) {
        if ( ! taxonomy_exists( $taxonomy ) ) {
            error_log( "FAC Filter: Taxonomia {$taxonomy} nu există." );
            return [];
        }

        $terms = get_terms( [
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC'
        ] );

        if ( is_wp_error( $terms ) ) {
            error_log( "FAC Filter: Eroare la obținerea termenilor pentru {$taxonomy}: " . $terms->get_error_message() );
            return [];
        }

        return $terms;
    }

    private function get_shop_url() {
        // Obține URL-ul paginii de shop WooCommerce
        $shop_page_id = wc_get_page_id( 'shop' );
        if ( $shop_page_id ) {
            return get_permalink( $shop_page_id );
        }
        
        // Fallback la homepage dacă nu există pagină de shop
        return home_url( '/' );
    }
}

new FAC_Shortcode();
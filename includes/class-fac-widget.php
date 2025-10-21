<?php

class FAC_Filter_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'fac_filter_widget',
            'FAC - Filter Custom Attribute',
            [ 'description' => 'Filtru pentru taxonomii WooCommerce configurate în setările FAC' ]
        );
    }

    public function widget( $args, $instance ) {
        $filter_id = $instance['filter_id'] ?? '';
        $title = apply_filters( 'widget_title', $instance['title'] ?? '' );

        // Verificare corectă pentru filter_id (inclusiv 0)
        if ( $filter_id === '' || $filter_id === '-' ) {
            return;
        }

        $saved_filters = FAC_Admin_Settings::get_saved_filters();
        
        // Verificare corectă pentru index (inclusiv 0)
        if ( ! isset( $saved_filters[ $filter_id ] ) ) {
            return;
        }

        $filter = $saved_filters[ $filter_id ];
        $taxonomy = $filter['taxonomy'];
        $type = $filter['type'];

        if ( ! taxonomy_exists( $taxonomy ) ) {
            echo '<!-- FAC Filter: Taxonomia ' . esc_html( $taxonomy ) . ' nu există -->';
            return;
        }

        // Obține termenii cu counter
        $terms = $this->get_terms_with_count( $taxonomy );
        
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return;
        }

        echo $args['before_widget'];

        if ( ! empty( $title ) ) {
            echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        }

        // Obține termenii curenti din URL
        $current_terms = $this->get_current_filter_terms( $taxonomy );
        $has_active_filters = ! empty( $current_terms );

        echo '<div class="fac-filter fac-filter-' . esc_attr( $type ) . '" data-taxonomy="' . esc_attr( $taxonomy ) . '">';
        
        switch ( $type ) {
            case 'checkbox':
                $this->render_checkbox_filter( $taxonomy, $terms, $current_terms, $has_active_filters );
                break;

            case 'select':
                $this->render_select_filter( $taxonomy, $terms, $current_terms, $has_active_filters, false );
                break;

            case 'multiselect':
                $this->render_select_filter( $taxonomy, $terms, $current_terms, $has_active_filters, true );
                break;
        }

        echo '</div>';
        echo $args['after_widget'];
    }

    /**
     * Obține termenii curenti din URL pentru o taxonomie
     */
    private function get_current_filter_terms( $taxonomy ) {
        $param_name = 'filter_' . $taxonomy;
        
        if ( ! isset( $_GET[ $param_name ] ) || empty( $_GET[ $param_name ] ) ) {
            return [];
        }

        $filter_value = $_GET[ $param_name ];

        // CORECTARE: WooCommerce nu suportă array-uri, folosim doar string cu virgulă
        if ( is_string( $filter_value ) ) {
            return explode( ',', sanitize_text_field( $filter_value ) );
        }

        return [];
    }

    private function render_checkbox_filter( $taxonomy, $terms, $current_terms, $has_active_filters ) {
        $clear_url = $this->get_clear_filter_url( $taxonomy );
        
        echo '<form method="get" action="" class="fac-filter-form" id="fac-form-' . esc_attr( $taxonomy ) . '">';
        
        // Păstrează toți parametrii existenți (except filtrele curente și paginarea)
        foreach ( $_GET as $key => $value ) {
            if ( $key !== 'filter_' . $taxonomy && $key !== 'paged' ) {
                if ( is_array( $value ) ) {
                    foreach ( $value as $val ) {
                        echo '<input type="hidden" name="' . esc_attr( $key ) . '[]" value="' . esc_attr( $val ) . '">';
                    }
                } else {
                    echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
                }
            }
        }

        // Input hidden care va conține toate termenii selectate (ca string cu virgulă)
        echo '<input type="hidden" name="filter_' . esc_attr( $taxonomy ) . '" id="fac-hidden-' . esc_attr( $taxonomy ) . '" value="' . esc_attr( implode( ',', $current_terms ) ) . '">';

        foreach ( $terms as $term ) {
            $checked = in_array( $term->slug, $current_terms ) ? 'checked' : '';
            $disabled = ( $term->count === 0 ) ? 'disabled' : '';
            
            echo '<label class="fac-filter-label ' . esc_attr( $term->slug ) . $disabled . '">';
            echo '<input type="checkbox" value="' . esc_attr( $term->slug ) . '" ' . $checked . ' ' . $disabled . ' class="fac-checkbox">';
            echo '<span class="fac-filter-text">';
            echo '<span class="fac-filter-name">' . esc_html( $term->name ) . '</span>';
            echo '<span class="fac-filter-count">' . $term->count . '</span>';
            echo '</span>';
            echo '</label>';
        }

        echo '<div class="fac-filter-buttons">';
        echo '<button type="submit" class="fac-filter-btn fac-apply-btn">Aplică Filtre</button>';
        
        if ( $has_active_filters ) {
            echo '<a href="' . esc_url( $clear_url ) . '" class="fac-filter-btn fac-clear-btn">Șterge Filtru</a>';
        }
        echo '</div>';
        
        echo '</form>';

        // JavaScript pentru a gestiona checkbox-urile și a construi string-ul cu virgulă
        echo '
        <script>
        jQuery(document).ready(function($) {
            var form = $("#fac-form-' . esc_attr( $taxonomy ) . '");
            var hiddenInput = $("#fac-hidden-' . esc_attr( $taxonomy ) . '");
            var checkboxes = form.find(".fac-checkbox");
            
            checkboxes.on("change", function() {
                var selectedValues = [];
                checkboxes.each(function() {
                    if ($(this).is(":checked") && !$(this).is(":disabled")) {
                        selectedValues.push($(this).val());
                    }
                });
                hiddenInput.val(selectedValues.join(","));
            });
            
            // Previne submit-ul dacă nu sunt schimbări
            form.on("submit", function(e) {
                var currentValue = hiddenInput.val();
                var originalValue = "' . esc_attr( implode( ',', $current_terms ) ) . '";
                if (currentValue === originalValue) {
                    e.preventDefault();
                }
            });
        });
        </script>
        ';
    }

    private function render_select_filter( $taxonomy, $terms, $current_terms, $has_active_filters, $multiple = false ) {
        $select_name = 'filter_' . $taxonomy;
        $current_value = implode( ',', $current_terms );
        $clear_url = $this->get_clear_filter_url( $taxonomy );
        
        echo '<form method="get" action="" class="fac-filter-form">';
        
        // Păstrează toți parametrii existenți
        foreach ( $_GET as $key => $value ) {
            if ( $key !== 'filter_' . $taxonomy && $key !== 'paged' ) {
                if ( is_array( $value ) ) {
                    foreach ( $value as $val ) {
                        echo '<input type="hidden" name="' . esc_attr( $key ) . '[]" value="' . esc_attr( $val ) . '">';
                    }
                } else {
                    echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
                }
            }
        }

        if ( $multiple ) {
            echo '<select name="' . esc_attr( $select_name ) . '" multiple class="fac-multiselect" onchange="this.form.submit()">';
            foreach ( $terms as $term ) {
                $selected = in_array( $term->slug, $current_terms ) ? 'selected' : '';
                $disabled = ( $term->count === 0 ) ? 'disabled' : '';
                
                echo '<option value="' . esc_attr( $term->slug ) . '" ' . $selected . ' ' . $disabled . '>';
                echo esc_html( $term->name ) . ' (' . $term->count . ')';
                echo '</option>';
            }
            echo '</select>';
            
            echo '<div class="fac-filter-buttons">';
            echo '<button type="submit" class="fac-filter-btn fac-apply-btn">Aplică</button>';
            
            if ( $has_active_filters ) {
                echo '<a href="' . esc_url( $clear_url ) . '" class="fac-filter-btn fac-clear-btn">Șterge Filtru</a>';
            }
            echo '</div>';
        } else {
            echo '<select name="' . esc_attr( $select_name ) . '" class="fac-select" onchange="this.form.submit()">';
            echo '<option value="">-- Alege --</option>';
            foreach ( $terms as $term ) {
                $selected = in_array( $term->slug, $current_terms ) ? 'selected' : '';
                $disabled = ( $term->count === 0 ) ? 'disabled' : '';
                
                echo '<option value="' . esc_attr( $term->slug ) . '" ' . $selected . ' ' . $disabled . '>';
                echo esc_html( $term->name ) . ' (' . $term->count . ')';
                echo '</option>';
            }
            echo '</select>';
            
            // Pentru select simplu, afișăm butonul de ștergere doar dacă există filtru activ
            if ( $has_active_filters ) {
                echo '<div class="fac-filter-buttons">';
                echo '<a href="' . esc_url( $clear_url ) . '" class="fac-filter-btn fac-clear-btn" style="margin-top: 10px;">Șterge Filtru</a>';
                echo '</div>';
            }
        }

        echo '</form>';
    }

    /**
     * Obține URL-ul pentru ștergerea filtrului curent
     */
    private function get_clear_filter_url( $taxonomy ) {
        return remove_query_arg( [ 'filter_' . $taxonomy, 'paged' ] );
    }

    private function get_terms_with_count( $taxonomy ) {
        return get_terms( [
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC'
        ] );
    }

    public function form( $instance ) {
        $title = $instance['title'] ?? '';
        $filter_id = $instance['filter_id'] ?? '';
        $saved_filters = FAC_Admin_Settings::get_saved_filters();
        ?>
        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>">Titlu widget:</label>
            <input type="text" id="<?php echo $this->get_field_id( 'title' ); ?>"
                   name="<?php echo $this->get_field_name( 'title' ); ?>"
                   value="<?php echo esc_attr( $title ); ?>" class="widefat"
                   placeholder="ex: Filtrează după Mărime">
        </p>
        
        <p>
            <label for="<?php echo $this->get_field_id( 'filter_id' ); ?>">Selectează Filtru:</label>
            <select id="<?php echo $this->get_field_id( 'filter_id' ); ?>"
                    name="<?php echo $this->get_field_name( 'filter_id' ); ?>"
                    class="widefat">
                <option value="-">-- Alege un filtru --</option>
                <?php foreach ( $saved_filters as $index => $filter ) : ?>
                    <option value="<?php echo esc_attr( $index ); ?>" <?php selected( $filter_id, (string) $index ); ?>>
                        <?php echo esc_html( $filter['label'] . ' (' . $filter['taxonomy'] . ')' ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        
        <?php if ( empty( $saved_filters ) ) : ?>
            <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0;">
                <strong>Nu există filtre configurate.</strong><br>
                <a href="<?php echo admin_url( 'admin.php?page=fac-settings' ); ?>" target="_blank">
                    → Configurează filtrele în setările FAC
                </a>
            </div>
        <?php else : ?>
            <p style="font-size: 12px; color: #666; font-style: italic;">
                Filtrele sunt configurate în <a href="<?php echo admin_url( 'admin.php?page=fac-settings' ); ?>" target="_blank">FAC Settings</a>
            </p>
        <?php endif; ?>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        $instance = [];
        $instance['title'] = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
        $instance['filter_id'] = ( $new_instance['filter_id'] !== '-' ) ? sanitize_text_field( $new_instance['filter_id'] ) : '';
        return $instance;
    }
}

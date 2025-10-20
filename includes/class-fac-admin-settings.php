<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class FAC_Filters_List_Table extends WP_List_Table {

    private $filters_data = [];

    public function __construct() {
        parent::__construct( [
            'singular' => 'filtru',
            'plural'   => 'filtre',
            'ajax'     => false
        ] );

        $this->filters_data = get_option( 'fac_saved_filters', [] );
    }

    public function get_columns() {
        return [
            'cb'        => '<input type="checkbox" />',
            'label'     => 'Label',
            'taxonomy'  => 'Taxonomie',
            'type'      => 'Tip afișare',
            'actions'   => 'Acțiuni'
        ];
    }

    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="filters[]" value="%s" />',
            $item['index']
        );
    }

    public function column_label( $item ) {
        $delete_url = wp_nonce_url(
            add_query_arg( [
                'action' => 'fac_delete_filter',
                'index'  => $item['index']
            ], admin_url( 'admin-post.php' ) ),
            'fac_delete_filter_' . $item['index']
        );

        $title = '<strong>' . esc_html( $item['label'] ) . '</strong>';
        
        $actions = [
            'edit'   => sprintf( '<a href="#" class="edit-filter" data-index="%s" data-label="%s" data-taxonomy="%s" data-type="%s">Editare</a>', 
                $item['index'],
                esc_attr( $item['label'] ),
                esc_attr( $item['taxonomy'] ),
                esc_attr( $item['type'] )
            ),
            'delete' => sprintf( '<a href="%s" class="submitdelete">Șterge</a>', $delete_url )
        ];

        return $title . $this->row_actions( $actions );
    }

    public function column_taxonomy( $item ) {
        // Verifică dacă taxonomia există
        $taxonomy_exists = taxonomy_exists( $item['taxonomy'] );
        $status = $taxonomy_exists ? 
            '<span style="color: #46b450;">✓ Există</span>' : 
            '<span style="color: #dc3232;">✗ Custom</span>';
        
        return '<code>' . esc_html( $item['taxonomy'] ) . '</code> ' . $status;
    }

    public function column_type( $item ) {
        $types = [
            'checkbox'    => 'Checkbox',
            'select'      => 'Dropdown Single',
            'multiselect' => 'Dropdown Multiple'
        ];

        return $types[ $item['type'] ] ?? $item['type'];
    }

    public function column_actions( $item ) {
        return sprintf(
            '<button type="button" class="button button-small edit-filter" data-index="%s" data-label="%s" data-taxonomy="%s" data-type="%s">Editare</button>',
            $item['index'],
            esc_attr( $item['label'] ),
            esc_attr( $item['taxonomy'] ),
            esc_attr( $item['type'] )
        );
    }

    public function get_bulk_actions() {
        return [
            'delete' => 'Șterge selecția'
        ];
    }

    public function process_bulk_action() {
        if ( 'delete' === $this->current_action() ) {
            $filters_to_delete = $_POST['filters'] ?? [];
            
            if ( ! empty( $filters_to_delete ) ) {
                $saved_filters = get_option( 'fac_saved_filters', [] );
                
                foreach ( $filters_to_delete as $index ) {
                    if ( isset( $saved_filters[ $index ] ) ) {
                        unset( $saved_filters[ $index ] );
                    }
                }
                
                $saved_filters = array_values( $saved_filters );
                update_option( 'fac_saved_filters', $saved_filters );
                
                wp_redirect( add_query_arg( 'message', 'bulk_deleted', wp_get_referer() ) );
                exit;
            }
        }
    }

    public function prepare_items() {
        $this->process_bulk_action();

        $data = [];
        foreach ( $this->filters_data as $index => $filter ) {
            $data[] = array_merge( $filter, [ 'index' => $index ] );
        }

        $this->_column_headers = [ $this->get_columns(), [], [] ];
        $this->items = $data;
    }

    public function no_items() {
        echo 'Nu există filtre configurate.';
    }
}

class FAC_Admin_Settings {

    private $filters_option = 'fac_saved_filters';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_post_fac_save_filter', [ $this, 'save_filter' ] );
        add_action( 'admin_post_fac_edit_filter', [ $this, 'edit_filter' ] );
        add_action( 'admin_post_fac_delete_filter', [ $this, 'delete_filter' ] );
        add_action( 'admin_notices', [ $this, 'admin_notices' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( 'woocommerce_page_fac-settings' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'wp-util' );
        
        // Inline scripts pentru modal-uri
        add_action( 'admin_footer', [ $this, 'admin_footer_scripts' ] );
    }

    public function admin_footer_scripts() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Modal pentru adăugare filtru
            $('.fac-add-filter-btn').on('click', function(e) {
                e.preventDefault();
                $('#fac-add-modal').show();
            });

            // Modal pentru editare filtru - cu pre-populare datelor
            $(document).on('click', '.edit-filter', function(e) {
                e.preventDefault();
                
                var index = $(this).data('index');
                var label = $(this).data('label');
                var taxonomy = $(this).data('taxonomy');
                var type = $(this).data('type');
                
                // Populează formularul cu datele existente
                $('#fac-edit-index').val(index);
                $('#edit_filter_label').val(label);
                $('#edit_filter_type').val(type);
                
                // Verifică dacă taxonomia există în select
                var $taxonomySelect = $('#edit_filter_taxonomy');
                var $customTaxonomy = $('#edit_custom_taxonomy');
                
                if ($taxonomySelect.find('option[value="' + taxonomy + '"]').length > 0) {
                    // Taxonomia există în select
                    $taxonomySelect.val(taxonomy);
                    $customTaxonomy.val('');
                    $('#edit_taxonomy_type').val('existing');
                    $('#edit_existing_taxonomy_section').show();
                    $('#edit_custom_taxonomy_section').hide();
                } else {
                    // Taxonomia este custom
                    $taxonomySelect.val('custom');
                    $customTaxonomy.val(taxonomy);
                    $('#edit_taxonomy_type').val('custom');
                    $('#edit_existing_taxonomy_section').hide();
                    $('#edit_custom_taxonomy_section').show();
                }
                
                // Afișează modal-ul
                $('#fac-edit-modal').show();
            });

            // Închide modal-uri
            $('.fac-modal-close, .fac-modal-cancel').on('click', function(e) {
                e.preventDefault();
                $(this).closest('.fac-modal').hide();
            });

            // Instrucțiuni modal
            $('.fac-instructions-btn').on('click', function(e) {
                e.preventDefault();
                $('#fac-instructions-modal').show();
            });

            // Toggle între taxonomie existentă și custom - ADD
            $('#taxonomy_type').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#existing_taxonomy_section').hide();
                    $('#custom_taxonomy_section').show();
                } else {
                    $('#existing_taxonomy_section').show();
                    $('#custom_taxonomy_section').hide();
                }
            });

            // Toggle între taxonomie existentă și custom - EDIT
            $('#edit_taxonomy_type').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#edit_existing_taxonomy_section').hide();
                    $('#edit_custom_taxonomy_section').show();
                } else {
                    $('#edit_existing_taxonomy_section').show();
                    $('#edit_custom_taxonomy_section').hide();
                }
            });

            // Reset formular adăugare la închidere
            $('#fac-add-modal .fac-modal-close, #fac-add-modal .fac-modal-cancel').on('click', function() {
                $('#fac-add-modal form')[0].reset();
                $('#existing_taxonomy_section').show();
                $('#custom_taxonomy_section').hide();
            });

            // Validare formular - ADD
            $('#fac-add-modal form').on('submit', function(e) {
                var taxonomyType = $('#taxonomy_type').val();
                var taxonomyValue = '';
                
                if (taxonomyType === 'existing') {
                    taxonomyValue = $('#filter_taxonomy').val();
                } else {
                    taxonomyValue = $('#custom_taxonomy').val().trim();
                }
                
                if (!taxonomyValue) {
                    e.preventDefault();
                    alert('Vă rugăm să selectați sau să introduceți o taxonomie.');
                    return false;
                }
            });

            // Validare formular - EDIT
            $('#fac-edit-modal form').on('submit', function(e) {
                var taxonomyType = $('#edit_taxonomy_type').val();
                var taxonomyValue = '';
                
                if (taxonomyType === 'existing') {
                    taxonomyValue = $('#edit_filter_taxonomy').val();
                } else {
                    taxonomyValue = $('#edit_custom_taxonomy').val().trim();
                }
                
                if (!taxonomyValue) {
                    e.preventDefault();
                    alert('Vă rugăm să selectați sau să introduceți o taxonomie.');
                    return false;
                }
            });
        });
        </script>
        <?php
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            'FAC - Settings',
            'FAC - Settings',
            'manage_options',
            'fac-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function render_settings_page() {
        $filters_table = new FAC_Filters_List_Table();
        $filters_table->prepare_items();
        $taxonomies = $this->get_available_taxonomies();
        ?>
        <div class="wrap">
            <h1>FAC - Filter Settings</h1>

            <?php $this->display_admin_notices(); ?>

            <div style="margin: 20px 0;">
                <button type="button" class="button button-primary fac-add-filter-btn">
                    + Adaugă Filtru Nou
                </button>
                <button type="button" class="button fac-instructions-btn" style="margin-left: 10px;">
                    📋 Instrucțiuni
                </button>
            </div>

            <!-- Formular pentru acțiuni în masă -->
            <form method="post">
                <?php
                $filters_table->display();
                ?>
            </form>

            <!-- Modal pentru adăugare filtru -->
            <div id="fac-add-modal" class="fac-modal" style="display: none;">
                <div class="fac-modal-content">
                    <div class="fac-modal-header">
                        <h2>Adaugă Filtru Nou</h2>
                        <span class="fac-modal-close">&times;</span>
                    </div>
                    <div class="fac-modal-body">
                        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                            <?php wp_nonce_field( 'fac_save_filter', 'fac_nonce' ); ?>
                            <input type="hidden" name="action" value="fac_save_filter">
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="filter_label">Label afișat:</label></th>
                                    <td>
                                        <input type="text" id="filter_label" name="filter_label" class="regular-text" required>
                                        <p class="description">Numele care va apărea în widget</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="taxonomy_type">Tip taxonomie:</label></th>
                                    <td>
                                        <select id="taxonomy_type" name="taxonomy_type" class="regular-text" required>
                                            <option value="existing">Selectează din taxonomiile existente</option>
                                            <option value="custom">Introdu taxonomie custom</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="existing_taxonomy_section">
                                    <th scope="row"><label for="filter_taxonomy">Taxonomie existentă:</label></th>
                                    <td>
                                        <select id="filter_taxonomy" name="filter_taxonomy" class="regular-text">
                                            <option value="">-- Alege taxonomia --</option>
                                            <?php foreach ( $taxonomies as $taxonomy ) : ?>
                                                <option value="<?php echo esc_attr( $taxonomy->name ); ?>">
                                                    <?php echo esc_html( $taxonomy->label . ' (' . $taxonomy->name . ')' ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">Alege dintre taxonomiile WooCommerce existente</p>
                                    </td>
                                </tr>
                                <tr id="custom_taxonomy_section" style="display: none;">
                                    <th scope="row"><label for="custom_taxonomy">Taxonomie custom:</label></th>
                                    <td>
                                        <input type="text" id="custom_taxonomy" name="custom_taxonomy" class="regular-text" placeholder="ex: custom_taxonomy">
                                        <p class="description">Introdu slug-ul taxonomiei custom</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="filter_type">Tip afișare:</label></th>
                                    <td>
                                        <select id="filter_type" name="filter_type" class="regular-text" required>
                                            <option value="checkbox">Checkbox</option>
                                            <option value="select">Dropdown Single</option>
                                            <option value="multiselect">Dropdown Multiple</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="fac-modal-footer">
                                <button type="submit" class="button button-primary">Salvează</button>
                                <button type="button" class="button fac-modal-cancel">Anulează</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal pentru editare filtru -->
            <div id="fac-edit-modal" class="fac-modal" style="display: none;">
                <div class="fac-modal-content">
                    <div class="fac-modal-header">
                        <h2>Editare Filtru</h2>
                        <span class="fac-modal-close">&times;</span>
                    </div>
                    <div class="fac-modal-body">
                        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                            <?php wp_nonce_field( 'fac_edit_filter', 'fac_edit_nonce' ); ?>
                            <input type="hidden" name="action" value="fac_edit_filter">
                            <input type="hidden" name="filter_index" id="fac-edit-index" value="">
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="edit_filter_label">Label afișat:</label></th>
                                    <td>
                                        <input type="text" id="edit_filter_label" name="filter_label" class="regular-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="edit_taxonomy_type">Tip taxonomie:</label></th>
                                    <td>
                                        <select id="edit_taxonomy_type" name="taxonomy_type" class="regular-text" required>
                                            <option value="existing">Selectează din taxonomiile existente</option>
                                            <option value="custom">Introdu taxonomie custom</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="edit_existing_taxonomy_section">
                                    <th scope="row"><label for="edit_filter_taxonomy">Taxonomie existentă:</label></th>
                                    <td>
                                        <select id="edit_filter_taxonomy" name="filter_taxonomy" class="regular-text">
                                            <option value="">-- Alege taxonomia --</option>
                                            <?php foreach ( $taxonomies as $taxonomy ) : ?>
                                                <option value="<?php echo esc_attr( $taxonomy->name ); ?>">
                                                    <?php echo esc_html( $taxonomy->label . ' (' . $taxonomy->name . ')' ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="edit_custom_taxonomy_section" style="display: none;">
                                    <th scope="row"><label for="edit_custom_taxonomy">Taxonomie custom:</label></th>
                                    <td>
                                        <input type="text" id="edit_custom_taxonomy" name="custom_taxonomy" class="regular-text" placeholder="ex: custom_taxonomy">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="edit_filter_type">Tip afișare:</label></th>
                                    <td>
                                        <select id="edit_filter_type" name="filter_type" class="regular-text" required>
                                            <option value="checkbox">Checkbox</option>
                                            <option value="select">Dropdown Single</option>
                                            <option value="multiselect">Dropdown Multiple</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="fac-modal-footer">
                                <button type="submit" class="button button-primary">Actualizează</button>
                                <button type="button" class="button fac-modal-cancel">Anulează</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal pentru instrucțiuni -->
            <div id="fac-instructions-modal" class="fac-modal" style="display: none;">
                <div class="fac-modal-content">
                    <div class="fac-modal-header">
                        <h2>Instrucțiuni FAC Filter</h2>
                        <span class="fac-modal-close">&times;</span>
                    </div>
                    <div class="fac-modal-body">
                        <ol>
                            <li><strong>Configurează filtrele</strong> în această pagină folosind butonul "Adaugă Filtru Nou"</li>
                            <li><strong>Tip taxonomie:</strong> Poți alege dintre taxonomiile existente sau introduce una custom</li>
                            <li><strong>Taxonomii custom:</strong> Introdu slug-ul taxonomiei tale custom (ex: custom_size, location, etc.)</li>
                            <li><strong>Mergi la Apariție → Widgets</strong> și adaugă widget-ul "FAC - Filter" în sidebar-ul shop-ului</li>
                            <li><strong>În widget</strong>, selectează filtru din lista celor configurate aici</li>
                            <li><strong>Filtrele active</strong> vor apărea automat în secțiunea "Filtre active" WooCommerce</li>
                        </ol>
                    </div>
                    <div class="fac-modal-footer">
                        <button type="button" class="button button-primary fac-modal-close">Am înțeles</button>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .fac-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .fac-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            width: 600px;
            max-width: 90%;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .fac-modal-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .fac-modal-header h2 {
            margin: 0;
            font-size: 1.3em;
        }

        .fac-modal-close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .fac-modal-close:hover {
            color: #000;
        }

        .fac-modal-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .fac-modal-footer {
            padding: 20px;
            border-top: 1px solid #e5e5e5;
            background: #f8f9fa;
            text-align: right;
        }

        .fac-modal-footer .button {
            margin-left: 10px;
        }

        .wrap h1 {
            margin-bottom: 20px;
        }

        /* Stiluri pentru rândurile tabelului */
        .wp-list-table .column-label { width: 25%; }
        .wp-list-table .column-taxonomy { width: 25%; }
        .wp-list-table .column-type { width: 20%; }
        .wp-list-table .column-actions { width: 15%; }
        .wp-list-table .column-cb { width: 5%; }

        /* Stiluri pentru status taxonomii */
        .taxonomy-status {
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 5px;
        }
        .taxonomy-exists {
            background: #46b450;
            color: white;
        }
        .taxonomy-custom {
            background: #dc3232;
            color: white;
        }
        </style>
        <?php
    }

    private function display_admin_notices() {
        if ( isset( $_GET['message'] ) ) {
            $messages = [
                'saved'        => 'Filtru salvat cu succes!',
                'updated'      => 'Filtru actualizat cu succes!',
                'deleted'      => 'Filtru șters cu succes!',
                'bulk_deleted' => 'Filtrele selectate au fost șterse!'
            ];

            if ( isset( $messages[ $_GET['message'] ] ) ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . $messages[ $_GET['message'] ] . '</p></div>';
            }
        }
    }

    public function save_filter() {
        if ( ! wp_verify_nonce( $_POST['fac_nonce'], 'fac_save_filter' ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permisiune refuzată.' );
        }

        $saved_filters = get_option( $this->filters_option, [] );
        
        // Determină valoarea taxonomiei
        $taxonomy_type = sanitize_text_field( $_POST['taxonomy_type'] );
        $taxonomy_value = '';
        
        if ( $taxonomy_type === 'existing' ) {
            $taxonomy_value = sanitize_text_field( $_POST['filter_taxonomy'] );
        } else {
            $taxonomy_value = sanitize_text_field( $_POST['custom_taxonomy'] );
        }
        
        if ( empty( $taxonomy_value ) ) {
            wp_die( 'Taxonomia este obligatorie.' );
        }

        $new_filter = [
            'label'    => sanitize_text_field( $_POST['filter_label'] ),
            'taxonomy' => $taxonomy_value,
            'type'     => sanitize_text_field( $_POST['filter_type'] )
        ];

        $saved_filters[] = $new_filter;
        update_option( $this->filters_option, $saved_filters );

        wp_redirect( admin_url( 'admin.php?page=fac-settings&message=saved' ) );
        exit;
    }

    public function edit_filter() {
        $index = intval( $_POST['filter_index'] );
        
        if ( ! wp_verify_nonce( $_POST['fac_edit_nonce'], 'fac_edit_filter' ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permisiune refuzată.' );
        }

        $saved_filters = get_option( $this->filters_option, [] );

        // Determină valoarea taxonomiei
        $taxonomy_type = sanitize_text_field( $_POST['taxonomy_type'] );
        $taxonomy_value = '';
        
        if ( $taxonomy_type === 'existing' ) {
            $taxonomy_value = sanitize_text_field( $_POST['filter_taxonomy'] );
        } else {
            $taxonomy_value = sanitize_text_field( $_POST['custom_taxonomy'] );
        }
        
        if ( empty( $taxonomy_value ) ) {
            wp_die( 'Taxonomia este obligatorie.' );
        }

        if ( isset( $saved_filters[ $index ] ) ) {
            $saved_filters[ $index ] = [
                'label'    => sanitize_text_field( $_POST['filter_label'] ),
                'taxonomy' => $taxonomy_value,
                'type'     => sanitize_text_field( $_POST['filter_type'] )
            ];

            update_option( $this->filters_option, $saved_filters );
        }

        wp_redirect( admin_url( 'admin.php?page=fac-settings&message=updated' ) );
        exit;
    }

    public function delete_filter() {
        $index = intval( $_GET['index'] );
        
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'fac_delete_filter_' . $index ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permisiune refuzată.' );
        }

        $saved_filters = get_option( $this->filters_option, [] );

        if ( isset( $saved_filters[ $index ] ) ) {
            unset( $saved_filters[ $index ] );
            $saved_filters = array_values( $saved_filters );
            update_option( $this->filters_option, $saved_filters );
        }

        wp_redirect( admin_url( 'admin.php?page=fac-settings&message=deleted' ) );
        exit;
    }

    public function admin_notices() {
        // Folosim display_admin_notices() în loc de aceasta
    }

    private function get_available_taxonomies() {
        $taxonomies = get_taxonomies( [
            'object_type' => [ 'product' ]
        ], 'objects' );

        return $taxonomies;
    }

    public static function get_saved_filters() {
        return get_option( 'fac_saved_filters', [] );
    }
}

new FAC_Admin_Settings();

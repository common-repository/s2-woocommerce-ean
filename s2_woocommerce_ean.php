<?php
/*
    Plugin Name: s2 Woocommerce EAN
    Plugin URI:
    Description: Add EAN/GTIN field to woocommerce products, must have for google Merchant shop
    Version: 0.1.0
    Author: Sebas2
    Author URI: http://s2.sebas2.nl
    License: GNU GPL
    Text Domain: s2-woocommerce-ean
*/
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Start the plugin
add_action(
    'plugins_loaded',
    [ s2_woocommerce_ean::get_instance(), 'plugin_setup' ]
);
  
class s2_woocommerce_ean {

    # Singleton
    protected static $instance = NULL;

    private $options;

    # Leave empty
    public function __construct() {}

    # Singleton
    public static function get_instance()
    {
        NULL === self::$instance and self::$instance = new self;
        return self::$instance;
    }
    
    # Start our action hooks
    public function plugin_setup()
    {
        add_action( 'init',                                                     array( $this, 's2_woocommerce_textdomain' ) );

        add_action( 'woocommerce_product_options_inventory_product_data',       array( $this, 's2_woocommerce_ean_field'),  1, 1 );
        add_action( 'woocommerce_process_product_meta',                         array( $this, 's2_woocommerce_save_ean_field'),  12, 2 );
        
        // update since 0.0.3
        add_action( 'woocommerce_variation_options',                            array( $this, 's2_woocommerce_ean_field_variation'),  10, 3 );
        add_action( 'woocommerce_save_product_variation',                       array( $this, 's2_woocommerce_ean_field_save_variation'),  10, 2 );
        add_filter( 'woocommerce_available_variation',                          array( $this, 's2_woocommerce_ean_field_add_custom_field_variation_data'),  10, 2 ); 
 
        add_filter( 'manage_edit-product_columns',                              array( $this, 's2_woocommerce_admin_column'),  1, 1 );
        add_action( 'manage_product_posts_custom_column',                       array( $this, 's2_woocommerce_admin_column_data'), 33, 10 );
        add_action( 'admin_head',                                               array( $this, 's2_woocommerce_admin_column_width'), 33, 10 );
        add_filter( 'manage_edit-product_sortable_columns',                     array( $this, 's2_woocommerce_admin_set_sortable_columns'), 33, 10 ); 
        add_action( 'plugins_loaded',                                           array( $this, 's2_woocommerce_check_for_woocommerce') );
        add_action( 'admin_notices',                                            array( $this, 's2_woocommerce_admin_notice') );
        // grab options (if set)    
        $this->options = get_option( 's2_woocommerce_ean' );
    }

    public function s2_woocommerce_textdomain (){

        load_plugin_textdomain( 's2-woocommerce-ean', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 

    }

    public function s2_woocommerce_admin_notice() {
        if (!defined('WC_VERSION')) {
            ?>    
            <div class="notice notice-warning">
                <p><?php _e('(S2_woocommerce_EAN) we need Woocommerce to be installed.', 's2-woocommerce-ean') ?></p>
            </div>
            <?php
        }
    }

    public function s2_woocommerce_check_for_woocommerce() {
        
        if (!defined('WC_VERSION')) {
            // no woocommerce :(
            do_action( 'admin_notices' );
        } else {
            // var_dump("WooCommerce installed in version", WC_VERSION);
        }
    }

    /**
     * 
     */
    public function s2_woocommerce_ean_field_variation( $loop, $variation_data, $variation ) {
        
        woocommerce_wp_text_input( array(
            'id' => 's2_woocommerce_ean_field[' . $loop . ']',
            'name' => 's2_woocommerce_ean_field[' . $loop . ']',
            'class' => 'short s2_woocommerce_ean_field',
            'label' => __( 'EAN/gTIN field', 's2-woocommerce-ean' ),
            'desc_tip'      => true,
            'description'   => __( 'Gebruik dit veld voor de EAN code van een product.', 's2-woocommerce-ean' ),
            'value' => get_post_meta( $variation->ID, 's2_woocommerce_ean_field', true ),
            'wrapper_class' => 'form-row form-row-full',
        ));
        
    }

    /**
     * woocommerce_save_product_variation
     */
    public function s2_woocommerce_ean_field_save_variation( $variation_id, $i ) {
        $s2_woocommerce_ean_field = $_POST['s2_woocommerce_ean_field'][$i];
        if ( isset( $s2_woocommerce_ean_field ) ) update_post_meta( $variation_id, 's2_woocommerce_ean_field', sanitize_text_field( $s2_woocommerce_ean_field ) );
    }

    
    /**
     * s2_woocommerce_ean_field_add_custom_field_variation_data
     */    
    function s2_woocommerce_ean_field_add_custom_field_variation_data( $variations ) {
        $variations['s2_woocommerce_ean_field'] = '<div class="woocommerce_custom_field">EAN Field: <span>' . get_post_meta( $variations[ 'variation_id' ], 's2_woocommerce_ean_field', true ) . '</span></div>';
        return $variations;
    }


    /**
     * 
     */
    function s2_woocommerce_ean_field() {
        global $woocommerce, $post;

        $args = array(
            'id'            => 's2_woocommerce_ean_field',
            'label'         => __( 'EAN', 's2-woocommerce-ean' ),
            'class'			=> 's2_woocommerce-ean-field',
            'desc_tip'      => true,
            'description'   => __( 'Gebruik dit veld voor de EAN code van een product.', 's2-woocommerce-ean' ),
        );
        woocommerce_wp_text_input( $args );
    }

    /**
     * Save out EAN field as Meta data
     */
    function s2_woocommerce_save_ean_field( $post_id ) {

        $product    = wc_get_product( $post_id );
        $txt        = isset( $_POST['s2_woocommerce_ean_field'] ) ? sanitize_text_field($_POST['s2_woocommerce_ean_field']): '';
        $product->update_meta_data( 's2_woocommerce_ean_field', $txt );
        $product->save();

    }
    
    /**
     * add column in admin products table
     */
    function s2_woocommerce_admin_column( $columns ) {
        
        $new_columns = (is_array($columns)) ? $columns : array();
        $new_columns['s2_woocommerce_ean_field'] = __( 'EAN', 's2-woocommerce-ean');
 
        return $new_columns;
    }

    /**
     * Create the EAN column
     */
    function s2_woocommerce_admin_column_data( $colname ) {
        
        global $post;
        
        if( $colname == 's2_woocommerce_ean_field' ) {
            $eancode = get_post_meta($post->ID, 's2_woocommerce_ean_field', true);
            if( $eancode ){
                echo $eancode; 
            } else {
                echo '-';
            }
            
        }
    }

    function s2_woocommerce_admin_column_width() {
        echo '<style type="text/css">';
        echo 'table.wp-list-table .column-s2_woocommerce_ean_field { width: 120px; text-align: left!important;padding: 5px;}';
        echo '</style>';
    }

    /**
     * Make our column sortable
     */
    function s2_woocommerce_admin_set_sortable_columns( $columns ) {
        $columns['s2_woocommerce_ean_field'] = 's2_woocommerce_ean_field';
        return $columns;
    }

 

} 
<?php
/**
 * Plugin Name: MIMO Woocommerce Order Tracking
 * Plugin URI:  -
 * Description: MIMO Woocommerce Order Tracking
 * Version:     0.0.1
 * Author:      Surakrai Nookong
 * Author URI:  https://www.facebook.com/surakraisam
 * Donate link: -
 * License:     GPLv2
 * Text Domain: mwot
 * Domain Path: /languages
 *
 * @link -
 *
 * @package Dmcr
 * @version 0.0.1
 */

/**
 * Copyright (c) 2016 Surakrai Nookong (email : surakraisam@gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */


if ( ! defined( 'ABSPATH' ) ) {
  exit;
}


class MIMO_Woocommerce_Order_Tracking {


  const VERSION = '0.0.1';

  private static $instance;

  protected $url = '';
  protected $path = '';
  protected $basename = '';

  public $provider_list = array();

  public function __construct() {

    $this->provider_list = get_option( 'mimo_provider_list' );
    $this->basename = dirname( plugin_basename( __FILE__ ) );
    $this->url      = plugin_dir_url( __FILE__ );
    $this->path     = plugin_dir_path( __FILE__ );

    add_action( 'plugins_loaded', array( $this, 'add_hooks' ) );

    load_plugin_textdomain( 'mwot', false,  $this->basename . '/languages/' );

    register_activation_hook( __FILE__, array( $this, 'plugin_activate') );
    register_deactivation_hook( __FILE__, array( $this, 'plugin_deactivate') );

  }

  public static function get_instance() {
    if ( ! isset( self::$instance ) ) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function add_hooks() {

    add_action( 'add_meta_boxes', array( $this, 'adding_meta_boxes' ), 10, 2 );
    add_action( 'save_post', array( $this, 'save_meta_boxes' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'register_script' ) );
    add_action( 'wp_ajax_send_tracking', array( $this, 'send_tracking' ) );
    add_action( 'wp_ajax_add_provider', array( $this, 'add_provider' ) );
    add_action( 'wp_ajax_update_provider', array( $this, 'update_provider' ) );
    add_action( 'wp_ajax_delete_provider', array( $this, 'delete_provider' ) );
    add_action( 'wp_ajax_update_order_provider', array( $this, 'update_order_provider' ) );
    add_action( 'woocommerce_email_order_meta', array( $this, 'add_order_email_shipment_tracking' ), 10, 2 );
    add_action( 'woocommerce_settings_tabs_mimo_mwot_settings_tab', array( $this, 'settings_tab' ) );
    add_action( 'woocommerce_update_options_mimo_mwot_settings_tab', array( $this, 'update_settings' ) );
    add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );

    if ( !function_exists( 'WC' ) ) {
      add_action( 'admin_notices', array( $this, 'admin_notice_error' ) );
    }

  }

  public function  plugin_activate() {

    if( empty( $this->provider_list  ) ){

      delete_option( 'mimo_provider_list' );

      $list = array(
        array( 'provider_name' => 'Thailand post', 'tracking_url' => 'http://emsbot.com/#/?s=' , 'add_tracking_url' => 1 ),
        array( 'provider_name' => 'Kerry express', 'tracking_url' => 'https://th.kerryexpress.com/en/track/?track=' , 'add_tracking_url' => 1 ),
      );
      add_option( 'mimo_provider_list', $list );

    }

  }
  public function plugin_deactivate() {}

  public function register_script() {

    wp_enqueue_style( 'mimo-style', $this->url . 'css/style.css', array() );

    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_enqueue_script( 'jquery-ui-sortable' );

    wp_enqueue_script( 'mimo-functions', $this->url . 'js/functions.js', array('jquery'), false, true );
    wp_localize_script( 'mimo-functions', 'MIMO', array(
      'ajaxurl'          => admin_url( 'admin-ajax.php' ),
      'provider_name'    => __( 'Provider name', 'mwot' ),
      'tracking_url'     => __( 'Tracking URL', 'mwot' ),
      'add_tracking_url' => __( 'Add a tracking number to the URL', 'mwot' ),
      'delete'           => __( 'Delete', 'mwot' ),
      'close'            => __( 'Close', 'mwot' ),
      'update'           => __( 'Update', 'mwot' ),
    ));

  }

  public function send_tracking() {

    check_ajax_referer( 'mimo_shipment_tracking_data', 'mimo_shipment_tracking_nonce' );

    $return = array();
    $errors = false;

    $post_id = sanitize_text_field( $_POST["mimo_order_ID"] );
    $provider = sanitize_text_field( $_POST["mimo_tracking_provider"] );
    $tracking_number  = sanitize_text_field( $_POST["mimo_tracking_number"] );
    $date_shipped  = sanitize_text_field( $_POST["mimo_date_shipped"] );

    update_post_meta( $post_id, 'mimo_tracking_provider', $provider );
    update_post_meta( $post_id, 'mimo_tracking_number', $tracking_number );
    update_post_meta( $post_id, 'mimo_date_shipped', $date_shipped );

    if ( $provider == '' ){
      $errors = true;
      $return['msg'] .= __( 'Please select a provider', 'mwot' ) .  "\n";
    }
    if ( $tracking_number == '' ){
      $errors = true;
      $return['msg'] .= __( 'Please enter a tracking number', 'mwot' ) .  "\n";
    }
    if ( $date_shipped == '' ){
      $errors = true;
      $return['msg'] .= __( 'Please enter a date', 'mwot' ) .  "\n";
    }

    if ( $errors == false ) {

      $order = new WC_Order( $post_id );
      $order->update_status('completed');
      $return['tracking_link'] = $this->tracking_link( $post_id );

    }

    $return['errors'] = $errors;

    wp_send_json( $return );

  }


  public function add_provider() {

    $order = $_POST["list_item"];

    $provider_name = sanitize_text_field( $_POST["provider_name"] );
    $tracking_url = sanitize_text_field( $_POST["tracking_url"] );

    end($order);
    $key = key($order);

    $this->provider_list[$key]['provider_name'] = '';
    $this->provider_list[$key]['tracking_url'] = '';
    $this->provider_list[$key]['add_tracking_url'] = 0;

    update_option( 'mimo_provider_list', $this->provider_list );

    die( (string)$key );

  }

  public function delete_provider() {

    $key = sanitize_text_field( $_POST["key"] );

    unset( $this->provider_list[$key] );

    update_option( 'mimo_provider_list', $this->provider_list );

    die();

  }

  public function update_provider() {

    $provider_name = sanitize_text_field( $_POST["provider_name"] );
    $tracking_url = sanitize_text_field( $_POST["tracking_url"] );
    $add_tracking_url = sanitize_text_field( $_POST["add_tracking_url"] );

    $key = sanitize_text_field( $_POST["key"] );

    $this->provider_list[$key]['provider_name'] = $provider_name;
    $this->provider_list[$key]['tracking_url'] = $tracking_url;
    $this->provider_list[$key]['add_tracking_url'] = $add_tracking_url;

    update_option( 'mimo_provider_list', $this->provider_list );

    die();

  }

  public function update_order_provider() {

    $order = $_POST["list_item"];
    $new_list = array();

    foreach ( $order as $key ) {

      if ( isset( $this->provider_list[$key] ) ) {

        $new_list[$key]['provider_name'] = $this->provider_list[$key]['provider_name'];
        $new_list[$key]['tracking_url'] = $this->provider_list[$key]['tracking_url'];
        $new_list[$key]['add_tracking_url'] = $this->provider_list[$key]['add_tracking_url'];
      }

    }

    update_option( 'mimo_provider_list', $new_list );

    die();

  }

  public function admin_notice_error() {

    $class = 'notice notice-error';
    $message = __( 'MIMO Woocommerce Order Tracking is enabled but not effective. It requires WooCommerce in order to work.', 'mwot' );

    printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );

  }

  public function save_meta_boxes( $post_id ) {

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    if ( isset( $_POST[ 'mimo_shipment_tracking_nonce' ] ) && wp_verify_nonce( $_POST[ 'mimo_shipment_tracking_nonce' ], 'mimo_shipment_tracking_data' ) ) return;

    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['mimo_tracking_provider'] ) )
      update_post_meta( $post_id, 'mimo_tracking_provider', sanitize_text_field( $_POST['mimo_tracking_provider'] ) );

    if ( isset( $_POST['mimo_tracking_number'] ) )
      update_post_meta( $post_id, 'mimo_tracking_number', sanitize_text_field( $_POST['mimo_tracking_number'] ) );

    if ( isset( $_POST['mimo_date_shipped'] ) )
      update_post_meta( $post_id, 'mimo_date_shipped', sanitize_text_field( $_POST['mimo_date_shipped'] ) );

  }

  public function adding_meta_boxes( $post_type, $post ) {

    add_meta_box(
      'mimo-shipment-tracking',
      __( 'Shipment Tracking', 'mwot' ),
      array( $this, 'meta_boxes_callback' ),
      'shop_order',
      'side',
      'high'
    );

  }
  public function get_settings() {

    $settings = array(
      'description' => array(
        'name' => __( 'Description', '' ),
        'type' => 'textarea',
        'desc' => __( '' ),
        'id'   => 'mimo_email_description'
      ),
    );

    return $settings;

  }

  public function add_settings_tab( $settings_tabs ) {

    $settings_tabs['mimo_mwot_settings_tab'] = __( 'Order Tracking', 'mwot' );
    return $settings_tabs;

  }

  public function update_settings() {

    woocommerce_update_options( $this->get_settings() );

  }

  public function validate( $order_id ) {

    if ( get_post_meta( $order_id, 'mimo_tracking_provider', true ) == ''
      || get_post_meta( $order_id, 'mimo_tracking_number', true ) == ''
      || get_post_meta( $order_id, 'mimo_date_shipped', true ) == '' ){
      return false;
    }else{
      return true;
    }

  }

  public function tracking_link( $order_id ) {

    $stored_meta = get_post_meta( $order_id );

    if ( $this->validate( $order_id ) == true ) {

      return sprintf(
        '<a href="%s%s" target="_blank">%s</a>',
        $this->provider_list[$stored_meta['mimo_tracking_provider'][0]]['tracking_url'],
        ( $this->provider_list[ $stored_meta['mimo_tracking_provider'][0] ]['add_tracking_url'] == 1 ? $stored_meta['mimo_tracking_number'][0] : "" ),
        __( 'Track', 'mwot' )
      );

    }

  }

  public function meta_boxes_callback( $post ) {

    wp_nonce_field( 'mimo_shipment_tracking_data', 'mimo_shipment_tracking_nonce' );

    $stored_meta = get_post_meta( $post->ID );

    ?>

    <p>
      <label for="mimo_tracking_provider" class="input-text"><strong><?php _e( 'Provider', 'mwot' )?> :</strong></label> <br>
      <select name="mimo_tracking_provider" id="mimo_tracking_provider" class="widefat mimo-field">
        <?php if ( $this->provider_list ) :
        foreach ( $this->provider_list as $key => $value ) : ?>
          <option value="<?php echo $key ?>"  <?php selected( isset ( $stored_meta['mimo_tracking_provider'] ) ?  $stored_meta['mimo_tracking_provider'][0] : '', $key ); ?>><?php echo $this->provider_list[$key]['provider_name'] ?></option>
        <?php endforeach;
        endif ?>
      </select>
    </p>

    <p>
      <label for="mimo_tracking_number"><strong><?php _e( 'Tracking number', 'mwot' )?> :</strong></label>
      <input type="text" class="widefat mimo-field" name="mimo_tracking_number" id="mimo_tracking_number" value="<?php if ( isset ( $stored_meta['mimo_tracking_number'] ) ) echo $stored_meta['mimo_tracking_number'][0]; ?>" />
    </p>

    <p>
      <label for="mimo_date_shipped"><strong><?php _e( 'Date shipped', 'mwot' )?> :</strong></label>
      <input type="text" class="widefat mimo-field" name="mimo_date_shipped" id="mimo_date_shipped" value="<?php if ( isset ( $stored_meta['mimo_date_shipped'] ) ) echo $stored_meta['mimo_date_shipped'][0]; ?>" />
    </p>

    <input type="hidden" class="mimo-field" name="mimo_order_ID" value="<?php echo $post->ID ?>" />

    <div class="control-actions">
      <div class="alignleft">
        <?php echo $this->tracking_link( $post->ID ); ?>
      </div>
      <div class="alignright">
        <button class="button button-primary right " id="save_send">
          <?php
            if (  $this->validate( $post->ID ) == true ) {
              _e( 'Save', 'mwot' );
            }else{
              _e( 'Save and Send', 'mwot' );
            }
          ?>
        </button>
        <span class="spinner"></span>
      </div>
      <br class="clear">
    </div>

    <?php
  }

  public function settings_tab() { ?>

    <table class="form-table mimo-mwot-setting">

      <h2><?php _e('Order Tracking Settings', 'mwot') ?></h2><br/>

      <tbody>
        <tr valign="top" class="titledesc">
          <th scope="row"><?php _e('Provider', 'mwot') ?></th>
          <td>
            <div id="provider-sortable">
              <?php if ( $this->provider_list ) :
              foreach ( $this->provider_list as $key => $value ) : ?>

                <div id="list_item_<?php echo $key ?>" class="list_item">
                  <h3><?php echo ( $this->provider_list[$key]['provider_name'] ? $this->provider_list[$key]['provider_name'] : __( 'Provider name', 'mwot' )  ) ; ?></h3>
                  <div class="list-item-inner">

                    <label for="provider_name_<?php echo $key ?>">
                      <?php _e( 'Provider name', 'mwot' ) ?>
                      <input type="text" class="widefat provider-name" name="provider_name" id="provider_name_<?php echo $key ?>" value="<?php echo $this->provider_list[$key]['provider_name']; ?>">
                    </label>

                    <label for="tracking_url_<?php echo $key ?>">
                      <?php _e( 'Tracking URL', 'mwot' ) ?>
                      <input type="text" class="widefat" name="tracking_url" id="tracking_url_<?php echo $key ?>" value="<?php echo $this->provider_list[$key]['tracking_url']; ?>">
                    </label>

                    <label for="add_tracking_url_<?php echo $key ?>">
                      <input type="hidden" name="add_tracking_url" value="<?php echo $this->provider_list[$key]['add_tracking_url']; ?>">
                      <input type="checkbox" class="add_tracking_url"  id="add_tracking_url_<?php echo $key ?>" <?php checked( $this->provider_list[$key]['add_tracking_url'], 1); ?> >
                      <?php _e( 'Add a tracking number to the URL', 'mwot' ) ?>
                    </label>

                    <input type="hidden" name="key" value="<?php echo $key ?>">

                    <div class="control-actions">
                      <div class="alignleft">
                        <a class="widget-control-remove delete-provider" href="#"><?php _e( 'Delete', 'mwot' ) ?></a> |
                        <a class="close-provider" href="#"><?php _e( 'Close', 'mwot' ) ?></a>
                      </div>
                      <div class="alignright">
                        <input type="submit" class="button button-primary right update-provider" value="<?php _e( 'Update', 'mwot' ) ?>">
                        <span class="spinner"></span>
                      </div>
                      <br class="clear">
                    </div>

                  </div>
                </div>

              <?php endforeach;
              endif ?>

            </div>
            <div class="alignright">
              <button id="add-provider" class="button button-secondary right"> <?php _e( 'Add provider', 'mwot' ) ?> </button>
              <span class="spinner"></span>
            </div>

          </td>
        </tr>

        <tr valign="top">
          <th scope="row">
            <label for="mimo_email_description"><?php _e( 'Email description', 'mwot' ) ?></label>
          </th>
          <td class="forminp forminp-text">
            <textarea name="mimo_email_description" rows="8" id="mimo_email_description" class="widefat"><?php echo esc_textarea( get_option('mimo_email_description') ); ?></textarea>
          </td>
        </tr>

      </tbody>
    </table>

    <?php

  }

  public function add_order_email_shipment_tracking( $order, $sent_to_admin ) {

    $provider_id = get_post_meta( $order->id, 'mimo_tracking_provider', true );
    $tracking_number = get_post_meta( $order->id, 'mimo_tracking_number', true );
    $date_shipped = get_post_meta( $order->id, 'mimo_date_shipped', true );

    if ( ! $sent_to_admin && 'completed' == $order->status && $this->validate( $order->id ) == true ) { ?>

      <h2><?php _e( 'Shipping information', 'mwot' ) ?></h2>
      <p><?php echo wpautop( get_option('mimo_email_description') ); ?></p>
      <table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
        <thead>
          <tr>
            <th class="td" scope="col" style="text-align:left;"><?php _e( 'Provider', 'mwot'  ) ?></th>
            <th class="td" scope="col" style="text-align:left;"><?php _e( 'Tracking number', 'mwot'  ) ?></th>
            <th class="td" scope="col" style="text-align:left;"><?php _e( 'Date shipped', 'mwot'  ) ?></th>
            <th class="td" scope="col" style="text-align:left;"><?php _e( '#', 'mwot'  ) ?></th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="td"><?php echo $this->provider_list[$provider_id]['provider_name']; ?></td>
            <td class="td"><?php echo $tracking_number ?></td>
            <td class="td"><?php echo date_i18n( get_option( 'date_format' ), strtotime( $date_shipped ) ); ?></td>
            <td class="td"><?php echo $this->tracking_link( $order->id ); ?></td>
          </tr>
        </tbody>
      </table>

    <?php
    }

  }

}

MIMO_Woocommerce_Order_Tracking::get_instance();


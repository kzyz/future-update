<?php
/*
Plugin Name: Future Update
Plugin URI:
Description: Post/Page reservation update.
Version: 0.1
Author: Kazunori Yamazaki
Author URI:
License: GPL2
Text Domain: future-update
*/

/*  Copyright 2012 Kazunori Yamazaki (email : yamazaki@gluum.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class FutureUpdate
{

	var $version;
	var $key;
	var $text_domain;
	var $plugin_base_name;
	var $plugin_base_page;
	var $plugin_dir;

	/**
	 * the constructor
	 *
	 * @param none
	 * @return none
	 */
	function FutureUpdate()
	{
		global $post;

		$this->version = '0.1';
		$this->key = 'futureupdate';
		$this->text_domain = 'future-update';
		$this->plugin_base_name = plugin_basename( __FILE__ );
		$this->plugin_base_page = 'tools.php?page=' . $this->text_domain;
		$this->plugin_dir = get_option( 'siteurl' ) . '/wp-content/plugins/' . dirname( $this->plugin_base_name );
		load_plugin_textdomain( $this->text_domain, false, dirname( $this->plugin_base_name ) . '/languages' );

		register_activation_hook( __FILE__, array( &$this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );

		add_filter( 'manage_posts_columns', array( &$this, 'addColumns' ) );
		add_filter( 'manage_pages_columns', array( &$this, 'addColumns' ) );
		add_filter( 'the_content', array( &$this, 'getFutureContent' ) );

		add_action( 'manage_posts_custom_column', array( &$this, 'showValue' ) );
		add_action( 'manage_pages_custom_column', array( &$this, 'showValue' ) );
		add_action( 'save_post', array( &$this, 'updatePostMeta' ) );
		add_action( '_wp_put_post_revision', array( &$this, 'makeRevisionMeta' ) );
		add_action( 'wp_restore_post_revision', array( &$this, 'restoreRevisionMeta' ), 10, 2 );
		add_action( 'post_submitbox_misc_actions', array( &$this, 'submitbox' ) );
		add_action( 'admin_print_styles', array( &$this, 'addAdminCss' ), 30 );
		add_action( 'admin_print_scripts', array( &$this, 'addAdminScripts' ) );
	}

	/**
	 * activate
	 *
	 * @param none
	 * @return none
	 */
	function activate() {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_date, post_date_gmt FROM $wpdb->posts WHERE post_status = %s", 'publish' ) );
		foreach ( $results as $result ) {
			$post_id = $result->ID;
			update_post_meta( $post_id, 'fup_date', $result->post_date );
			update_post_meta( $post_id, 'fup_date_gmt', $result->post_date_gmt );
			wp_update_post( array( 'ID' => $post_id ) );
		}
	}

	/**
	 * deactivate
	 *
	 * @param none
	 * @return none
	 */
	function deactivate() {
		global $wpdb;

		$wpdb->get_results( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = %s OR meta_key = %s", 'fup_date', 'fup_date_gmt' ) );
	}

	/**
	 * addColumns
	 *
	 * @param $columns
	 * @return $columns
	 */
	function addColumns( $columns ) {
	  	$columns['fupdate'] = __( 'Future Update', $this -> text_domain );
	  	return $columns;
	}

	/**
	 * showValue
	 *
	 * @param $column_name
	 * @return none
	 */
	function showValue( $column_name ) {
		if ( $column_name == 'fupdate' ) {
			global $wpdb, $post, $count;

			$id = $post -> ID;

			$utc = get_post_meta( $id, 'fup_date', true );
			$gmt = get_post_meta( $id, 'fup_date_gmt', true );

			if ( empty( $utc ) ) {
				$t_time = $h_time = __( 'None' );
				$time_diff = 0;
			} else {
				$t_time = date_i18n( __( 'Y/m/d g:i:s A' ), strtotime( $utc ) );
				$m_time = $utc;
				$time = mysql2date( 'G', $gmt );

				$time_diff = time() - $time;

				if ( $time_diff > 0 && $time_diff < 24 * 60 * 60 )
					$h_time = sprintf( __( '%s ago' ), human_time_diff( $time ) );
				else
					$h_time = mysql2date( __( 'Y/m/d' ), $m_time );
			}

			echo '<abbr title="' . $t_time . '">' . $h_time . '</abbr>';
			/*
			echo '<br />';
			if ( 'publish' == $post -> post_status && !empty( $utc ) ) {
				if ( $time_diff > 0 )
					echo '<strong class="attention">' . __( 'Missed schedule' ). '</strong>';
				else
					_e( 'Scheduled' );
			}
			*/
		}
	}

	/**
	 * updatePostMeta
	 *
	 * @param $id
	 * @return none
	 */
	function updatePostMeta( $id ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $id;

		if ( array_key_exists( 'post_type', $_POST ) ) {
			if ( 'page' == $_POST['post_type'] ) {
				if ( !current_user_can( 'edit_page', $id ) )
					return $id;
			} else {
				if ( !current_user_can( 'edit_post', $id ) )
					return $id;
			}
		}

		if ( array_key_exists( 'fup_month', $_POST )
				&& array_key_exists( 'fup_day', $_POST )
				&& array_key_exists( 'fup_year', $_POST )
				&& array_key_exists( 'fup_hour', $_POST )
				&& array_key_exists( 'fup_minute', $_POST )
				&& array_key_exists( 'fup_second', $_POST ) ) {

			$month	= intval( $_POST['fup_month'] );
			$day	= intval( $_POST['fup_day'] );
			$year	= intval( $_POST['fup_year'] );
			$hour	= intval( $_POST['fup_hour'] );
			$minute	= intval( $_POST['fup_minute'] );
			$second	= intval( $_POST['fup_second'] );

			$utc = sprintf( "%04d-%02d-%02d %02d:%02d:%02d", $year, $month, $day, $hour, $minute, $second );
			$gmt = get_gmt_from_date( $utc );

			update_post_meta( $id, 'fup_date', $utc );
			update_post_meta( $id, 'fup_date_gmt', $gmt );
		}
	}

	/**
	 * makeRevisionMeta
	 *
	 * @param $revision_id
	 * @return none
	 */
	function makeRevisionMeta( $revision_id ) {
		global $wpdb;

		if ( $parent_id = wp_is_post_revision( $revision_id ) ) {
			$utc = get_post_meta( $parent_id, 'fup_date', true );
			$gmt = get_post_meta( $parent_id, 'fup_date_gmt', true );
			$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $revision_id, 'meta_key' => 'fup_date', 'meta_value' => $utc  ), array( '%d', '%s', '%s' ) );
			$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $revision_id, 'meta_key' => 'fup_date_gmt', 'meta_value' => $gmt  ), array( '%d', '%s', '%s' ) );
		}
	}

	/**
	 * restoreRevisionMeta
	 *
	 * @param $id
	 * @return none
	 */
	function restoreRevisionMeta( $post_id, $revision_id ) {
		$utc = get_post_meta( $revision_id, 'fup_date', true );
		$gmt = get_post_meta( $revision_id, 'fup_date_gmt', true );

		update_post_meta( $post_id, 'fup_date', $utc );
		update_post_meta( $post_id, 'fup_date_gmt', $gmt );
	}

	/**
	 * submitbox
	 *
	 * @param $id
	 * @return none
	 */
	function submitbox() {
		global $post, $action;

		$datef = __( 'M j, Y @ G:i' );
		$futureupdatets = strtotime( get_post_meta( $post -> ID, 'fup_date' , true ) );

		if ( empty( $futureupdatets ) ) {
			$fup_date = __( 'None' );
		} else {
			$fup_date = date_i18n( $datef, $futureupdatets );
		}

		echo '<div class="misc-pub-section curtime misc-pub-section-last" style="border-top:1px solid #EEE">';
		echo '	<span id="futureupdate_timestamp">' . __( 'Update on', $this -> text_domain ) . ': <b>' . $fup_date . '</b></span>';
		echo '	<a href="#edit_futureupdate_date" class="edit-futureupdate_date hide-if-no-js" tabindex="4">' . __( 'Edit' ) . '</a>';
		echo '	<div id="futureupdate_date_div" class="hide-if-js">';
		$this -> touchTime( ( $action == 'edit' ), 1, 4 );
		echo '	</div>';
		echo '</div>';
	}

	/**
	 * touchTime
	 *
	 * @param unknown_type $edit
	 * @param unknown_type $for_post
	 * @param unknown_type $tab_index
	 * @param unknown_type $multi
	 */
	function touchTime( $edit = 1, $for_post = 1, $tab_index = 0, $multi = 0 ) {
		global $wp_locale, $post, $comment;

		$fup_date = get_post_meta( $post->ID, 'fup_date', true );
		$fup_date_gmt = get_post_meta( $post->ID, 'fup_date_gmt', true );

		if ( $for_post )
			$edit = ! ( !$fup_date_gmt || '0000-00-00 00:00:00' == $fup_date_gmt ) ;

		$tab_index_attribute = '';
		if ( (int) $tab_index > 0 )
			$tab_index_attribute = " tabindex=\"$tab_index\"";

		// echo '<label for="timestamp" style="display: block;"><input type="checkbox" class="checkbox" name="edit_date" value="1" id="timestamp"'.$tab_index_attribute.' /> '.__( 'Edit timestamp' ).'</label><br />';

		$time_adj = current_time( 'timestamp' );
		$jj = ( $edit ) ? mysql2date( 'd', $fup_date, false ) : gmdate( 'd', $time_adj );
		$mm = ( $edit ) ? mysql2date( 'm', $fup_date, false ) : gmdate( 'm', $time_adj );
		$aa = ( $edit ) ? mysql2date( 'Y', $fup_date, false ) : gmdate( 'Y', $time_adj );
		$hh = ( $edit ) ? mysql2date( 'H', $fup_date, false ) : gmdate( 'H', $time_adj );
		$mn = ( $edit ) ? mysql2date( 'i', $fup_date, false ) : gmdate( 'i', $time_adj );
		$ss = ( $edit ) ? mysql2date( 's', $fup_date, false ) : gmdate( 's', $time_adj );

		$month = "<select " . ( $multi ? '' : 'id="fup_month" ' ) . "name=\"fup_month\"$tab_index_attribute>\n";
		for ( $i = 1; $i < 13; $i = $i +1 ) {
			$month .= "\t\t\t" . '<option value="' . zeroise($i, 2) . '"';
			if ( $i == $mm )
				$month .= ' selected="selected"';
			$month .= '>' . $wp_locale -> get_month_abbrev( $wp_locale -> get_month( $i ) ) . "</option>\n";
		}
		$month .= '</select>';

		$day = '<input type="text" ' . ( $multi ? '' : 'id="fup_day" ' ) . 'name="fup_day" value="' . $jj . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
		$year = '<input type="text" ' . ( $multi ? '' : 'id="fup_year" ' ) . 'name="fup_year" value="' . $aa . '" size="4" maxlength="4"' . $tab_index_attribute . ' autocomplete="off" />';
		$hour = '<input type="text" ' . ( $multi ? '' : 'id="fup_hour" ' ) . 'name="fup_hour" value="' . $hh . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
		$minute = '<input type="text" ' . ( $multi ? '' : 'id="fup_minute" ' ) . 'name="fup_minute" value="' . $mn . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';


		echo '<div class="futureupdate_date-wrap">';
		/* translators: 1: month input, 2: day input, 3: year input, 4: hour input, 5: minute input */
		printf(__('%1$s%2$s, %3$s @ %4$s : %5$s'), $month, $day, $year, $hour, $minute);

		echo '</div><input type="hidden" id="fup_second" name="fup_second" value="' . $ss . '" />';

		if ( $multi ) return;

		echo "\n";

		echo '<p>';
		echo '<a href="#edit_futureupdate_date" class="save-futureupdate_date hide-if-no-js button">' . __('OK') . '</a> ';
		echo '<a href="#edit_futureupdate_date" class="cancel-futureupdate_date hide-if-no-js">' . __('Cancel') . '</a>';
		echo '</p>';

	}

	/**
	 * addAdminCss
	 *
	 * @param none
	 * @return none
	 */
	function addAdminCss() {
		echo '<link rel="stylesheet" href="' . $this->plugin_dir . '/css/admin.css" type="text/css" media="all" />' . "\n";
	}

	/**
	 * addAdminScripts
	 *
	 * @param none
	 * @return none
	 */
	function addAdminScripts() {
		wp_enqueue_script( 'postfup', $this->plugin_dir . '/js/postfup.js', array( 'jquery-ui-core' ), false, true );
	}

	/**
	 * futureContent
	 *
	 * @param none
	 * @return none
	 */
	function getFutureContent( $content )
	{
		global $post;

		$now = time();
		$date = gmdate( "Y-m-d H:i:s" );
		$update = strtotime( get_post_meta( $post -> ID, 'fup_date_gmt' , true ) );
		if ( !$update || ( $update && $update <= $now ) )
			return $content;

		$args = array(
			'order' => 'DESC',
			'orderby' => 'ID',
			'post_parent' => $post->ID,
			'post_type' => 'revision',
			'post_status' => 'inherit',
			'meta_query' => array(
				array(
					'key' => 'fup_date_gmt',
					'value' => $date,
					'compare' => '<='
				)
			)
		);
		if ( $revisions = get_children( $args ) )
			foreach ( $revisions as $revision )
				return $revision->post_content;

		return __( 'No content.' );
	}

}

if ( class_exists( 'FutureUpdate' ) && function_exists( 'is_admin' ) ) {
	$future_update = new FutureUpdate();
}
?>

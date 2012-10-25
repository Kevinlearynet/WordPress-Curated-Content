<?php
/*
Plugin Name: Curated Content
Version: 0.1
Plugin URI: http://www.kevinleary.net/
Description: 
Author: Kevin Leary
Author URI: http://www.kevinleary.net
License: GPL2

Copyright 2012 Kevin Leary  (email : info@kevinleary.net)

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

// Load helper classes
include_once( plugin_dir_path( __FILE__ ) . 'classes/settings-helper.php' );
include_once( plugin_dir_path( __FILE__ ) . 'classes/google-reader-api.php' );

/**
 * Curated Content from RSS
 *
 * Take articles from a given set of Google Reader and create draft posts in WordPress 
 * that will redirect to the full article when clicked.
 */
class CuratedRSSPosts
{
	private $admin_notice_msg = ''; // Holds the current admin message to display
	private $admin_notice_class = 'updated'; // Holds the current admin message class
	private $updated = 0; // Track the number of posts created during "Get Curated" process
	private $post_type = 'curated';
	private $excerpt_size = 75;
	
	/**
	 * Add Actions & Filters
	 *
	 * Let's get this plugin back crack-a-lackin'
	 *
	 * @param $options An array of options for this object
	 */
	function __construct( $options = array() ) 
	{
		// Admin additions
		add_action( 'admin_print_styles', array($this, 'admin_css') );
		add_action( 'restrict_manage_posts', array($this, 'admin_buttons') );
		
		// Activate & de-activate actions
		register_activation_hook( __FILE__, array($this, 'activate') );
		register_deactivation_hook( __FILE__, array($this, 'deactivate') );
		
		// Register cron
		$this->schedule_cronjobs();
		
		// Post types & taxonomies
		add_action( 'init', array($this, 'register_post_type') );
		
		// Settings page
		add_action( 'admin_menu', array($this, 'settings') );
		
		// Permalink redirect (from Page Links To plugin by Mark J.)
		add_filter( 'post_link', array($this, 'link'), 20, 2 );
		add_filter( 'post_type_link', array($this, 'link',), 20, 2 );
		add_action( 'template_redirect', array($this, 'template_redirect') );
	}
	
	/**
	 * Plugin activation
	 *
	 * Reset the permalink rewrite rules after registering post types
	 */
	public function activate() {
	
		// Flush permalinks
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}

	/**
	 * Plugin de-activation
	 *
	 * Flush out permalink rewrite rules
	 */
	public function deactivate() {
	
		// Flush permalinks
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
		
		// Clear scheduled cronjob's
		wp_clear_scheduled_hook('get_curated');
	}
	
	/**
	 * Schedule cronjobs
	 *
	 * Schedule cron jobs that control getting new curated resources
	 */
	function schedule_cronjobs() {
		
		// Register cron
		$options = get_option('curated_options');
		$frequency = ( isset($options['frequency']) && !empty($options['frequency']) ) ? $options['frequency'] : 'daily';
		$scheduled = wp_get_schedule( 'get_curated' );
		
		// Update cron schedule if settings change
		if ( $scheduled != $frequency ) {
			wp_reschedule_event( current_time('timestamp'), $frequency, 'get_curated' );
		}
		
		// Register the scheduled event for the first time
		elseif ( !wp_next_scheduled( 'get_curated' ) ) {
			wp_schedule_event( current_time('timestamp'), $frequency, 'get_curated' );
		}
		
		// Add action that fires when cron is run
		add_action( 'get_curated', array($this, 'get_curated_posts') );
		
		// Force a curation update
		if ( isset($_GET['update']) && intval($_GET['update']) === 1 ) {
			$this->get_curated_posts();
		}
	}
	
	/**
	 * Returns post ids and meta values that have a given key
	 *
	 * @param string $key post meta key
	 * @return array an array of objects with post_id and meta_value properties
	 */
	private function meta_by_key( $key ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = %s", $key ) );
	}
	
	/**
	 * Returns all links for the current site
	 *
	 * @return array an array of links, keyed by post ID
	 */
	private function get_links() {
		global $wpdb, $blog_id;

		if ( !isset( $this->links[$blog_id] ) )
			$links_to = $this->meta_by_key( '_curated_redirect' );
		else
			return $this->links[$blog_id];

		if ( !$links_to ) {
			$this->links[$blog_id] = false;
			return false;
		}

		foreach ( (array) $links_to as $link )
			$this->links[$blog_id][$link->post_id] = $link->meta_value;

		return $this->links[$blog_id];
	}
	
	/**
	 * Update Permalink
	 *
	 * This will automatically redirect a curated article to the original source
	 * 
	 * @param string $link the URL for the post or page
	 * @param int|object $post Either a post ID or a post object
	 * @return string output URL
	 */
	public function link( $link, $post ) {
		$links = $this->get_links();

		// Really strange, but page_link gives us an ID and post_link gives us a post object
		$id = ( is_object( $post ) && $post->ID ) ? $post->ID : $post;

		if ( isset( $links[$id] ) && $links[$id] )
			$link = esc_url( $links[$id] );

		return $link;
	}
	
	/**
	 * Performs a redirect, if appropriate
	 */
	public function template_redirect() {
		if ( !is_single() && !is_page() )
			return;

		global $wp_query;

		$link = get_post_meta( $wp_query->post->ID, '_curated_redirect', true );

		if ( !$link )
			return;

		wp_redirect( $link, 301 );
		exit;
	}
	
	/**
	 * Performs a redirect, if appropriate
	 */
	public function register_post_type() {
		$args = array(
			'public' => false,
			'exclude_from_search' => true,
			'publicly_queryable' => true,
			'show_ui' => true,
			'show_in_nav_menus' => false,
			'show_in_menu' => true,
			'menu_position' => 20,
			'query_var' => true,
			'has_archive' => true,
			'label' => 'Curated',
			'menu_icon' => plugins_url( 'images/menu-icon.png' , __FILE__ ),
			'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields' )
		); 
		register_post_type( $this->post_type, $args );
	}
	
	/**
	 * Get Curated Posts
	 *
	 * Get a set of curated posts from Google Reader and create post objects for each
	 *
	 * @global
	 * @param $options An array of options for this object
	 * @return
	 * @staticvar
	 */
	public function get_curated_posts() {
		
		// Get Google Reader results
		$curated_feed = $this->google_reader_results( 50 );
		
		// Loop through all RSS items
		$this->updated = 0;
		foreach ( $curated_feed->items as $item )
			$this->create_post( $item );
		
		// Display admin notice
		if ( $this->updated != 0 ) {
			$this->admin_notice_class = 'updated';
			$this->admin_notice_msg = "<p>{$this->updated} curated post's have successfully been retrieved from Google Reader.</p>";
		}
		else {
			$this->admin_notice_class = 'error'; 
			$this->admin_notice_msg = '<p>No new curated articles have been found.</p>';
		}
		
		add_action('admin_notices', array($this, 'admin_notice'), 10, 2 );
	}

	/**
	 * Admin CSS
	 */
	public function admin_css() {
		$css_file = plugins_url( 'css/curated-posts.css' , __FILE__ );
		wp_enqueue_style('curated-posts-admin', $css_file, false, rand(0,5), 'screen');
	}
	
	/**
	 * Add Admin Button
	 *
	 * This add's a button to the WordPress admin "Posts" edit page
	 * to allow Administrators to get new curated posts
	 */
	public function admin_buttons() {
		global $typenow;
		
		// Only run for posts
		if ( $typenow == $this->post_type ) {
 
			// Create button HTML
			$update_nonce = wp_create_nonce('update_curated');
			
			// Debugging
			if ( isset($_GET['test']) && intval($_GET['test']) === 1 ) {
				echo '<a href="' . admin_url("edit.php?post_type={$this->post_type}&_wpnonce=$update_nonce&test=1") . '" class="button-primary button-curated">Import Curated</a>';
			}
			else {
				echo '<a href="' . admin_url("edit.php?post_type={$this->post_type}&_wpnonce=$update_nonce") . '" class="button-primary button-curated">Import Curated</a>';
			}
		};
	}
	
	/**
	 * Create Curated Category *(legacy and currently unused)
	 *
	 * Created a "Curated" category if it doesn't exist
	 */
	private function curated_term() 
	{
		// Check if the term already exists
		if ( term_exists('curated', 'category') ) {
			$curated_term = get_term_by('slug', 'curated', 'category');
		}
		
		// Get ID of existing "Curated" category
		else {
			$curated_term = wp_insert_term(
				'Curated', // the term 
				'category', // the taxonomy
				array(
					'description'=> 'Curated resources from various sources around the web.',
					'slug' => 'curated'
				)
			);
		}
		
		// Return error or term ID
		return ( is_wp_error($curated_term) ) ? $curated_term->get_error_message() : get_term_by('id', $curated_term->term_id, 'category');
	}
	
	/**
	 * Flatten Array
	 *
	 * From http://davidwalsh.name/flatten-nested-arrays-php
	 */
	public function flatten_array( $array, $return ) {
		for ( $x = 0; $x <= count($array); $x++ ) {
			if ( is_array($array[$x]) ) {
				$return = CuratedRSSPosts::flatten_array($array[$x],$return);
			}
			else {
				if ( $array[$x] ) {
					$return[] = $array[$x];
				}
			}
		}
		return $return;
	}
	
	/**
	 * stristr() with Arrays
	 *
	 * Searches an array $haystack for $needle. 
	 * Returns the value of the element which contains the first result.
	 */
	function stristr_array( $haystack, $needle ) {
		if ( !is_array( $haystack ) ) {
			return false;
		}
		foreach ( $haystack as $element ) {
			if ( strstr( $element, $needle ) ) {
				return $element;
			}
		}
	}
	
	/**
	 * Create Admin Notices
	 *
	 * Make it easy to add admin notices
	 *
	 * @param $message The message to display
	 * @param $class Optional class to control the style of the notice ("updated" or "error")
	 */
	public function admin_notice() {
		if ( $this->admin_notice_msg ) {
			$notice = '<div class="' . $this->admin_notice_class . '">';
       		$notice .= $this->admin_notice_msg;
    		$notice .= '</div>';
    		
    		echo $notice;
		}
	}
	
	/**
	 * Existing Post Titles
	 *
	 * Get a list of all existing post titles
	 */
	private function existing_post_titles() {
		global $wpdb;
		
		// SQL for the query
		$sql = $wpdb->prepare("SELECT post_title
		FROM {$wpdb->posts}
		WHERE post_type = '{$this->post_type}'
		AND post_status = 'publish'
		ORDER BY post_date DESC");
		
		// Get results from the query
		$post_titles = $wpdb->get_results( $sql, ARRAY_N );
		
		// Check for database return errors
		if ( is_wp_error( $post_titles ) )
			return $post_titles->get_error_message();
		
		// Flatten the array for searching
		return $this->flatten_array( $post_titles, array() );
	}
	
	/**
	 * RSS Feed Results
	 *
	 * Parse the Google Reader set in the admin using built-in WordPress Simplepie
	 *
	 * @param $size The number of items to retrieve from the Google Reader during a request
	 * @return array All SimplePie RSS items
	 */
	private function rss_results( $size = 20 ) {
		// SimplePie feed reader
		include_once(ABSPATH . WPINC . '/feed.php');
		
		// Get a SimplePie feed object from the specified feed source.
		$sources = array('http://www.dgaphotoshop.com/category/blog/feed', 'http://www.cameramanchronicles.com/feed');
		$rss = fetch_feed( $sources );
		
		// Checks that the object is created correctly 
		if ( is_wp_error( $rss ) )
			return $rss->get_error_message();
		
		// Figure out how many total items there are, but limit it to 5. 
		$maxitems = $rss->get_item_quantity($size); 
		
		// Build an array of all the items, starting with element 0 (first element).
		return $rss->get_items(0, $maxitems); 
	}
	
	/**
	 * Curated content from Google Reader
	 *
	 * Connect to Google Reader and snatch up all those starred items, then pull them into
	 * WordPress under a custom post type
	 *
	 * @param $size The number of items to retrieve from the Google Reader during a request
	 * @return array All SimplePie RSS items
	 */
	private function google_reader_results( $size = 50, $debug = false ) {
	
		global $base_options;
	
		// Google Reader API connect test
		$options = get_option('curated_options');
		$username = ( isset($options['username']) && !empty($options['username']) ) ? $options['username'] : null;
		$password = ( isset($options['password']) && !empty($options['password']) ) ? $base_options->decrypt($options['password']) : null;
		
		// Check for username and password
		if ( !$username || !$password )
			return 'Google Reader username and password have not been entered.';
			
		// Run Google Reader API processes
		try {
			
			// Get last time updated
			$last_update = get_option('curated_lastupdate');
			
			// Get starred items since the last updated time
			$gr_api = new GoogleReaderAPI( $username, $password );
			
			// Require the lastupdate option
			if ( isset($last_update) && !empty($last_update) )
				$gr_starred = $gr_api->get_starred( $size, 'o', $last_update );
			else
				$gr_starred = $gr_api->get_starred( $size );
			
			// Updated the lastupdate time
			$update_time = time();
			
			if ( isset($last_update) && !empty($last_update) )
				update_option( 'curated_lastupdate', $update_time );
			else
				add_option( 'curated_lastupdate', $update_time, ' ', 'no' );
		} 
		
		// Throw errors
		catch ( Exception $e ) {
			$error = $e->getMessage();
			
			return "Google Reader API Error: $error";
		}
		
		// For debugging
		if ( $debug ) {
			echo '<pre>';
			print_r($gr_starred);
			echo '</pre>';
			
			exit;
		}
	
		return $gr_starred;
	}
	
	/**
	 * Create Posts
	 *
	 * Create post object with the provided RSS data
	 *
	 * @global
	 * @param $item The Simplepie RSS item
	 * @return Post object or wp_error()
	 */
	private function create_post( $item = null ) {
		if ( !$item )
			return new WP_Error( 'curated-posts', __('Cannot create a post without an $item passed to create_post()') );
	
		// Sanitize data
		$title = apply_filters( 'single_post_title', $item->title );
		
		// **Content seams to come in 2 different nodes for various sources, some are in "summary" while others are in "content"
		if ( isset($item->content->content) && !empty($item->content->content) )
			$content = trim( $item->content->content );
		else
			$content = trim( $item->summary->content );
		
		// Allow IFRAME video content
		$allowed_tags = $GLOBALS['allowedposttags'];
		$allowed_tags["iframe"] = array(
			"src" => array(),
			"height" => array(),
			"width" => array()
		);
		
		// Allow flash embeds
		$allowed_tags["object"] = array(
			"height" => array(),
			"width" => array()
		);
		$allowed_tags["param"] = array(
			"name" => array(),
			"value" => array()
		);
		$allowed_tags["embed"] = array(
			"src" => array(),
			"type" => array(),
			"allowfullscreen" => array(),
			"allowscriptaccess" => array(),
			"height" => array(),
			"width" => array()
		);
		$content = wpautop( wp_kses( $content, $allowed_tags ) );
		$excerpt = wp_trim_words( trim( $content ), $this->excerpt_size );
		$date = date( 'Y-m-d H:i:s', $item->published );
		
		// Get a list of all existing post titles
		$existing_titles = $this->existing_post_titles();
		
		// Avoid duplicates
		if ( !in_array($title, array_values($existing_titles) ) ) {
		
			// Insert the post into the database
			$insert_post = wp_insert_post( array(
				'post_title' => $title,
				'post_content' => $content,
				'post_status' => 'publish',
				'post_author' => 1,
				'post_date' => $date,
				'post_excerpt' => $excerpt,
				'post_type' => $this->post_type,
	  			'comment_status' => 'closed',
			) );
			
			// Check for errors (no errors)
			if ( is_wp_error($insert_post) )
				return;
			
			// Save postdata
			if ( $insert_post != 0 && !empty($content) ) {
			
				// Check for images in content
				$doc = new DOMDocument();
			    @$doc->loadHTML( balanceTags( $content ) );
			    $images = $doc->getElementsByTagName('img');
				
				// If images are found, get the first real image
				if ( $images ) {
				
					// Skip images containing these URL's
					$skip = array('commindo-media-ressourcen.de', 'buysellads.com', 'feedburner.com');					
					
					foreach ( $images as $image ) {
						
						// Get src and avoid ads
						$src = $image->getAttribute('src');
						if ( $this->stristr_array($skip, $src) )
							continue;
							
						// Get src and avoid ads
						$size = getimagesize($src);
						if ( intval($size[0]) < 490 )
							continue;
							
						// Found an image, good to go!
						$feature_image = $src;
						break;
					}
				}
				
				// Update custom fields for storing article details
				$link = ( isset($item->canonical) && !empty($item->canonical[0]) ) ? $item->canonical[0] : $item->alternate[0];
				$link = $link->href;
				update_post_meta( $insert_post, '_curated_redirect', $link );
				update_post_meta( $insert_post, '_curated_author', esc_attr( $item->author ) );
				update_post_meta( $insert_post, '_curated_website', esc_attr( $item->origin->title ) );
				update_post_meta( $insert_post, '_curated_url', esc_url( $item->origin->htmlUrl ) );
				
				// Featured image
				if ( isset($feature_image) )
					update_post_meta( $insert_post, '_curated_image', $feature_image );
				
				$this->admin_notice_msg .= "<li class='success-item'>Curated post successfully added: <strong>$title</strong></li>";
			}
				
			$this->updated++;
		}
	}
	
	/**
	 * Theme Options
	 *
	 * Register and define the settings
	 */
	public function settings() {
	
		global $base_options;
		
		// Setup the page
		$base_options->setup( array(
			'page_title' => 'Curated Content Options',
			'menu_title' => 'Curated Content',
			'namespace'  =>	'curated'
		) );
		
		// Create a section group
		$base_options->add_section( 'General', 'test' );
		$base_options->add_field( 
			'Frequency', 
			'select', 
			'How often do you want to check for new curated content?', 
			array(
				'Daily' => 'daily',
				'Twice Daily' => 'twicedaily',
				'Hourly' => 'hourly',
			),
			'postform',
			'daily'
		);
		
		// Google reader options
		$base_options->add_section( 'Google Reader', 'This is required to get your starred items from Google.' );
		$base_options->add_field( 'Username', 'text', 'Usually an email address', array(), null, '', 20 );
		$base_options->add_field( 
			'Password', 
			'password', 
			'This is stored security in your WordPress database', 
			array(), 
			null, 
			'', 
			20
		);
	}
}

// Autoload the class
$curated_posts = new CuratedRSSPosts();
<?php
/**
 * Theme Options Class
 *
 * A modular class for building WordPress theme options pages
 *
 * PHP 5
 *
 * LICENSE: This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this file,
 * You can obtain one at http://mozilla.org/MPL/2.0/.
 *
 * @package    WordPress
 * @author     Kevin Leary <info@kevinleary.net>
 * @version    1.51
 * @see        add_options_page(), get_option(), do_settings_sections(), 
 *			   settings_fields(), register_setting(), add_settings_error()
 */
class BaseThemeOptions
{
	public $namespace;
	public $field_count = 0;
	public $upload_field = false;
	public $current_section = 'default';
	public $current_section_html;
	public $page_title;
	private $key = '-J5:2Yqd?Ri9wLjN';
	
	/**
	 * Setup The Options Page
	 * 
	 * Get user defined settings and connect to WP hooks
	 */
	public function setup( $args )
	{
		// Default args
		$defaults = array(
			'page_title' => 'Base Theme Options',
			'menu_title' => 'Theme Options',
			'namespace'  => 'base_theme'
		);
		
		// Merge args with defaults
		$args = wp_parse_args( $args, $defaults );
		
		// Define variables
		extract( $args, EXTR_SKIP );
		
		// Define namespace
		$this->namespace = $namespace;
		$this->page_title = $page_title;
		
		// Register settings DB entry
		register_setting(
			$this->namespace . '_options', // $option_group
			$this->namespace . '_options', // $option_name
			array($this, 'validate_options') // $sanitize_callback
		);
		
		// Add the menu
		$this->add_page( $args );
	}
	
	/**
	 * Add Form Section
	 *
	 * Divider long settings pages up using sections. A section
	 * is set and used until another section is set.
	 * 
	 * http://codex.wordpress.org/Function_Reference/add_settings_section
	 */
	public function add_section( $name = null, $html = null ) {
	
		// Check for required args
		if ( !$name )
			return new WP_Error('broke', __('The add_section() method requires a $name argument') );
	
		// If no HTML provided
		$this->current_section_html = ( $html ) ? wp_kses_post($html) : '';
	
		// Set the current section
		$section_id = $this->namespace . '_section_' . str_replace( ' ', '_', strtolower( trim($name) ) );
		
		// Create form section
		add_settings_section(
			$section_id, // $id
			$name, // $title
			array( $this, 'section_callback'), // $callback
			$this->namespace . '_options' // $page
		);
		
		// Set the current section
		$this->current_section = $section_id;
	}
	
	/**
	 * Section Callback
	 *
	 * Display the given HTML below the section title
	 */
	public function section_callback( $section ) {
		echo $this->current_section_html;
	}
	
	/**
	 * Encrypt and decrypt functions for secure password storage in database
	 * http://www.maxvergelli.com/2010/02/17/easy-to-use-and-strong-encryption-decryption-php-functions/
	 */
	public function encrypt( $input_string ) {
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$h_key = hash('sha256', $this->key, TRUE);
		return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $h_key, $input_string, MCRYPT_MODE_ECB, $iv));
	}
	
	public function decrypt( $encrypted_input_string ) {
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$h_key = hash('sha256', $this->key, TRUE);
		return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $h_key, base64_decode($encrypted_input_string), MCRYPT_MODE_ECB, $iv));
	}
	
	/**
	 * Add field
	 */
	public function add_field( $name = null, $type = 'text', $desc = null, $choices = array(), $class = null, $default = '', $size = null ) {
		
		// Check for required args
		if ( !$name )
			return new WP_Error('broke', __('The add_field() method requires a $name argument') );
		
		$options = array(
			'type' => $type, // The type of form field to create
			'id' => str_replace( ' ', '_', strtolower( trim($name) ) ) // The ID for the field: used for ID and NAME attributes
		);
		
		// Set description if it exists
		if ( $desc )
			$options['description'] = $desc;
			
		// Set class if it exists
		if ( $class )
			$options['class'] = $class;
			
		// Set default value if it exists
		if ( isset($default) )
			$options['default'] = $default;
			
		// If choices are set then pass the inputs
		if ( !empty($choices) )
			$options['choices'] = $choices;
			
		// If choices are set then pass the inputs
		if ( $size )
			$options['size'] = $size;
		
		// Create the field
		$field = add_settings_field(
			$this->namespace . '_' . str_replace( ' ', '_', strtolower( trim($name) ) ), // $id
			$name,	// $title
			array($this, 'setting_field'), // $callback
			$this->namespace . '_options', // $page
			$this->current_section, // $section
			$options
		);
		
		return $field;
	}
	
	
	/**
	 * Get Options Wrapper
	 *
	 * Get option values from the database given the set
	 * namespace.
	 */
	public function get_options() {
		return get_option( $this->namespace . '_options' );
	}

	/**
	 * Create Submenu Page
	 *
	 * Add a submenu for our option page under "Appearance"
	 *
	 * http://codex.wordpress.org/Function_Reference/add_submenu_page
	 */
	public function add_page( $args ) {
		add_submenu_page( 
			'options-general.php', // $parent_slug
			$args['page_title'], // $page_title
			$args['menu_title'], // $menu_title
			'manage_options', // $capability
			$this->namespace . '_options', // $menu_slug
			array($this, 'option_page') // $function
		);
	}
	
	/**
	 * Options page HTML
	 *
	 * Let's match the built-in WordPress settings page
	 * styleguide
	 */
	public function option_page() {
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php _e($this->page_title); ?></h2>
			<form action="options.php" method="post">
				<?php 
				settings_fields( $this->namespace . '_options' );
				do_settings_sections( esc_attr($_GET['page']) );
				?>
				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button-primary" value="Save Changes">
				</p>
			</form>
		</div>
		<?php
	}
	
	/**
	 * Form Field HTML
	 *
	 * Generate an HTML form field given the provided
	 * arguments.
	 */
	public function setting_field( $args ) {
		$this->field_count++;
		
		// Get arguments
		$defaults = array(
			'type' => 'text', // Default to text fields
			'id' => 'field_' . $this->field_count, // If not ID is given generate a unique one
			'rows' => 8, // The number of rows used for textareas and editors
			'required' => false // Not programmed yet
		);
		$args = wp_parse_args( $args, $defaults );
		extract( $args, EXTR_SKIP );
		
		// Get options from DB
		$options = $this->get_options();
		$class = ( isset($class) && !empty($class) ) ? ' class="' . $class . '"' : '';
		
		// Different cases for each form field type
		switch ( $type ) {
		
			// Single line text field
			case "text":
			
				// Sanitize
				$value = ( isset($options[$id]) && !empty($options[$id]) ) ? esc_attr($options[$id]) : $default;
				
				// HTML
				$field = '<input id="' . $this->namespace . '_' . $id . '" name="' . $this->namespace . '_options[' . $id . ']" type="text"' . $class . ' value="' . $value . '" />';
				break;
				
			// Single line password field
			case "password":
			
				// Sanitize
				$value = ( isset($options[$id]) && !empty($options[$id]) ) ? $this->decrypt( $options[$id] ) : '';
				
				// HTML
				$field = '<input id="' . $this->namespace . '_' . $id . '" name="' . $this->namespace . '_options[' . $id . ']" type="password"' . $class . ' size="20" value="' . $value . '" />';
				break;
			
			// Content block textarea
			case "textarea":
			
				// Sanitize
				$value = ( isset($options[$id]) && !empty($options[$id]) ) ? esc_textarea($options[$id]) : $default;
				
				// HTML
				$field = '<textarea rows="' . $rows . '" id="' . $this->namespace . '_' . $id . '" name="' . $this->namespace . '_options[' . $id . ']"' . $class . '>' . $value . '</textarea>';
				break;
			
			// Multiple choice radio buttons
			case "radio":
			case "checkbox":
			
				// Sanitize
				if ( !is_array($options[$id]) )
					$value = ( isset($options[$id]) && !empty($options[$id]) ) ? esc_attr($options[$id]) : $default;
				else
					$value = $options[$id];
					
				// HTML
				if ( isset($choices) && !empty($choices) ) {
					$field = '';
					$last_choice = end($choices);
					$multistorage = ( $type == 'checkbox' ) ? '[]' : '';
					foreach ( $choices as $label => $fieldval ) {
						// Get checked status, handle a special case for checkboxes with multiple values stored as array
						if ( is_array($value) )
							$checked = ( in_array($fieldval, $value) ) ? 'checked="checked"' : '';
						else	
							$checked = checked( $fieldval, $value, false );
							
						$field .= '<label>
							<input type="' . $type . '" name="' . $this->namespace . '_options[' . $id . ']' . $multistorage . '" ' . $class . 'value="' . $fieldval . '" ' . $checked . ' />
							<span>' . $label . '</span>
						</label>';
						
						if ( $fieldval != $last_choice )
							$field .= '<br />';
					}
				}
				break;
			
			// Dropdown select box
			case "select":
			
				// Sanitize
				$value = ( isset($options[$id]) && !empty($options[$id]) ) ? esc_attr($options[$id]) : $default;
				
				// HTML
				if ( isset($choices) && !empty($choices) ) {
					$field = '<select name="' . $this->namespace . '_options[' . $id . ']" id="' . $this->namespace . '_options[' . $id . ']"' . $class . '>';
					
					$field .= '<option value="">Choose one&hellip;</option>';
					foreach ( $choices as $label => $fieldval ) {
						$field .= '<option value="' . $fieldval . '" ' . selected( $value, $fieldval, false ) . '>' . $label . '</option>';
					}
					$field .= '</select>';
				}
				break;
				
			// Visual editor (TinyMCE)
			case "tinymce":
				// Sanitize
				$id = 'tinymce_' . $id;
				$value = ( isset($options[$id]) && !empty($options[$id]) ) ? $options[$id] : $default;
				
				// Add TinyMCE visual editor
				wp_editor( $value, $this->namespace . '_options[' . $id . ']', array(
					'textarea_rows' => $rows,
					'editor_class' => 'settings-tinymce'
				) );
				
				break;
				
			// Media upload button
			case "upload":	
			
				// Queue JS for uploader
				wp_enqueue_script('media-upload');
				wp_enqueue_script('thickbox');
				wp_enqueue_style('thickbox');
					
				// Sanitize
				$id = 'upload_' . $id;
				$value = ( isset($options[$id]) && !empty($options[$id]) ) ? esc_url($options[$id]) : $default;
				$field = '<p class="upload-field">
					<input name="' . $this->namespace . '_options[' . $id . ']' . '" id="' . $this->namespace . '_options[' . $id . ']' . '" type="text" value="' . $value . '" size="40" />
					<input type="button" class="button-secondary media-library-upload" rel="' . $this->namespace . '_options[' . $id . ']' . '" alt="Add Media" value="Add Media" />
					<input type="button" class="button-secondary media-library-remove" rel="' . $this->namespace . '_options[' . $id . ']' . '" value="Remove Media" />
				</p>';
				
				if ( !empty($value) )
					$field .= '<p><img src="' . $value . '" class="upload-image-preview" /></p>';
				
				break;
		}
		
		// Print the form field
		if ( isset($field) && !empty($field) ) 
			_e( $field );
		
		// Description
		if ( $description )
			_e( '<p class="description">' . $description . '</p>' );
	}
	
	/**
	 * Validate Options
	 *
	 * Validate user input (we want text only)
	 */
	public function validate_options( $input ) {
		
		// Encrypt Google password
		if ( $input['password'] != '' )
			$input['password'] = $this->encrypt( $input['password'] );
			
		return $input;
	}
}

// Autoload the class
$base_options = new BaseThemeOptions();  
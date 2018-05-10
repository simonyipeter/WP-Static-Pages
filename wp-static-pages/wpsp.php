<?php
/*
Plugin Name:  WP Static Pages
Description:  Generate Static HTML files from pages, so the these sites will be 10x faster than non-static. This plugin supports posts, categories and products also. The HTML files are updated each time the update or publish button is pressed. 
Author:       Peter Simonyi
Version:      0.9.3
Author URI:	  https://www.facebook.com/simonyi.peter
*/

$wpsp_register_string="<a href='https://wpsp.prs.hosting/termek/wp-static-pages-premium-plugin/' target='_blank'>Please, buy the premium plugin to use this feature!</a>";

function WPSP_pluginLinks($links) {
	$settings_link = '<a href="options-general.php?page=wpsp">Settings</a>'; 
  	array_unshift( $links, $settings_link ); 
  	return $links; 	
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'WPSP_pluginLinks');

$wpsp_home_path = $_SERVER["DOCUMENT_ROOT"].'/';
$wpsp_index_file_name= "index.html";

function wpsp_my_cron_schedules($schedules){
    if(!isset($schedules["1min"])){
        $schedules["1min"] = array(
            'interval' => 1*60,
            'display' => __('Once every minute'));
    }
	if(!isset($schedules["5min"])){
        $schedules["5min"] = array(
            'interval' => 5*60,
            'display' => __('Once every 5 minutes'));
    }
	if(!isset($schedules["10min"])){
        $schedules["10min"] = array(
            'interval' => 10*60,
            'display' => __('Once every 10 minutes'));
    }
    if(!isset($schedules["30min"])){
        $schedules["30min"] = array(
            'interval' => 30*60,
            'display' => __('Once every 30 minutes'));
    }
	if(!isset($schedules["60min"])){
        $schedules["60min"] = array(
            'interval' => 60*60,
            'display' => __('Once every 30 minutes'));
    }
    return $schedules;
}
add_filter('cron_schedules','wpsp_my_cron_schedules');


add_action('wpsp_plugin_cron_action', 'wpsp_plugin_cron_function');
function wpsp_cron_event($time) {
    if (wp_next_scheduled('wpsp_plugin_cron_action') == false) {         wp_schedule_event(time(), $time, 'wpsp_plugin_cron_action');    }
	else {
		wp_clear_scheduled_hook( 'wpsp_plugin_cron_action' );//error_log("WPSP: del Cronjob", 0);
		wp_schedule_event(time(), $time, 'wpsp_plugin_cron_action'); //error_log("WPSP: add Cronjob", 0);
	}
}


function wpsp_plugin_cron_function() {
	//Regenerate Everyhing
			wpsp_update_all_cat();
			wpsp_update_all_post();
			wpsp_update_home_page();
}

function wpsp_add_and_remove_additional_lines_from_htaccess($action)
{
	$doc_root=$_SERVER["DOCUMENT_ROOT"].'/';
	if($action=="add" and strpos(file_get_contents($doc_root.'.htaccess'),'BEGIN WPSP') == false){
		$file_data = '
#BEGIN WPSP
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]

#All Post redirect to index.php except /wp-admin/
RewriteCond %{REQUEST_METHOD} POST
RewriteCond %{REQUEST_URI} !^/wp-admin/
RewriteCond %{REQUEST_URI} !^/wp-login
RewriteRule . /index.php [L]

#Handle Ajax requests
#RewriteCond %{REQUEST_URI} ^/
RewriteCond %{QUERY_STRING} (wc-ajax|download)
RewriteRule . /index.php [L]
</IfModule>
#END WPSP

';
		$file_data .= file_get_contents($doc_root.'.htaccess');
		file_put_contents($doc_root.'.htaccess', $file_data);
		//error_log("WPSP: ".$_SERVER["DOCUMENT_ROOT"].'/'.'.htaccess', 0);
	}
	if($action=="remove"){
		$file_data = file_get_contents($doc_root.'.htaccess');
		$start=strpos($file_data,'#BEGIN WPSP');
		$stop=strpos($file_data,'#END WPSP');
		error_log("WPSP: ".$start.':'.$stop, 0);
		file_put_contents($doc_root.'.htaccess', substr_replace($file_data, '', $start, $stop+7));
	}
}

function wpsp_rrmdir($dir) {
       if (is_dir($dir)) { 
		 $objects = scandir($dir); 
		 foreach ($objects as $object) { 
		   if ($object != "." && $object != "..") { 
			 if (is_dir($dir."/".$object))
			 {wpsp_rrmdir($dir."/".$object);}
		   else {
 			    if(pathinfo($dir."/".$object, PATHINFO_EXTENSION)=="html"){unlink($dir."/".$object); }
			   	}
		   } 
		 }
		 rmdir($dir); 
	   } 
    }

function wpsp_deactivation() {
    // Delete index.html
	global $wpsp_home_path;
	global $wpsp_index_file_name;
    $file = $wpsp_home_path.$wpsp_index_file_name;
	unlink($file);
	//remove lines from .htaccess
	wpsp_add_and_remove_additional_lines_from_htaccess("remove");
    
}
register_deactivation_hook( __FILE__, 'wpsp_deactivation' );

function wpsp_activation() {
    // add lines to .htaccess file
	wpsp_add_and_remove_additional_lines_from_htaccess("add");
}
register_activation_hook( __FILE__, 'wpsp_activation' );

function wpsp_post_checkbox(){
        $html  = '<div id="major-publishing-actions" style="overflow:hidden">';
        $html .= '<div id="publishing-action">';
		$html .= '<input type="hidden" id="wpsp_is_static" name="wpsp_is_static" values="10"/>';
		$html .= 'Static Page: <input type="checkbox" id="wpsp_is_static" name="wpsp_is_static" ';
		if( get_post_meta( get_the_ID(), 'wpsp_is_static', true )=='1' ){ $html .= "value='1' checked='checked'"; }
		else {$html .= "value='10' "; }
		$html .= 'onclick="if(this.value==1){  this.value=10; } else{ this.value=1 }"/>';
		$html .= '</div>';
        $html .= '</div>';
        echo $html;
}
add_action( 'post_submitbox_misc_actions', 'wpsp_post_checkbox' );



function wpsp_save_state( $post_id ) {
	// Checks save status
	$is_autosave = wp_is_post_autosave( $post_id );
	$is_revision = wp_is_post_revision( $post_id );
	
	// Exits script depending on save status
	if ( $is_autosave || $is_revision ) {	return;	}
	
	// Checks for input and sanitizes/saves if needed
	if( isset( $_POST[ 'wpsp_is_static' ] ) ) {
		update_post_meta( $post_id, 'wpsp_is_static', sanitize_text_field($_POST[ 'wpsp_is_static' ])  );
	}
	
}
add_action('publish_post', 'wpsp_save_state');
add_action('save_post', 'wpsp_save_state' );


function wpsp_update () {
	global $wpsp_index_file_name;
	global $wpsp_home_path;
	$wpsp_id = get_the_ID();
	//$post_slug = get_post_field( 'post_name', $wpsp_id );
	$post_slug_rel = str_replace(site_url('/'),"",get_permalink($wpsp_id));
	$directory=$wpsp_home_path.$post_slug_rel;
	$post_slug_rel_arr = explode("/", $post_slug_rel);
	
	if( get_post_meta( $wpsp_id, 'wpsp_is_static', true )==='1' ){ 
			
			//If home page
			if($post_slug_rel==""){
				unlink($wpsp_home_path.$wpsp_index_file_name);
				//error_log("WPSP: ".$wpsp_home_path.$wpsp_index_file_name, 0);
				$raw_html = file_get_contents(site_url());
				//$raw_html = $wpsp_home_path.$wpsp_index_file_name;
			}
			
			//Not Home page
			else{
				//wpsp_rrmdir($directory);
				if(count($post_slug_rel_arr) < 3 ){ wpsp_rrmdir($wpsp_home_path.$post_slug_rel_arr[count($post_slug_rel_arr)-2]); }
				if( count($post_slug_rel_arr) == 3 ){ wpsp_rrmdir($wpsp_home_path.$post_slug_rel_arr[count($post_slug_rel_arr)-3].'/'.$post_slug_rel_arr[count($post_slug_rel_arr)-2]); }
				if( count($post_slug_rel_arr) == 4 ){ wpsp_rrmdir($wpsp_home_path.$post_slug_rel_arr[count($post_slug_rel_arr)-4].'/'.$post_slug_rel_arr[count($post_slug_rel_arr)-3].'/'.$post_slug_rel_arr[count($post_slug_rel_arr)-2]); }
				$raw_html = file_get_contents(get_permalink($wpsp_id ));
			}
			
			$temp_dirs=$wpsp_home_path;
			for($x = 0; $x < (count($post_slug_rel_arr)-1); $x++){
				$temp_dirs.=$post_slug_rel_arr[$x];
				if (!file_exists($temp_dirs)) {					mkdir($temp_dirs, 0755);			}
				$temp_dirs.='/';
				//error_log("WPSP: $x: ".$temp_dirs, 0);
			}
			
			
			
			$file = $directory.$wpsp_index_file_name;
			//error_log("WPSP: ".$file, 0);
			file_put_contents($file, $raw_html);
			
	}
	
	else {		
			//If home page
			if($post_slug_rel==""){
				unlink($wpsp_home_path.$wpsp_index_file_name);
			}
			//Not Home page
			else{
			 wpsp_rrmdir($directory);//		 error_log("WPSP: ".$directory, 0);
			}
		
			}
}
add_action('publish_post', 'wpsp_update');
add_action('save_post', 'wpsp_update');



function wpsp_category_fields( $tag ) {    //check for existing featured ID
    
	$html .= '<tr class="form-field"><th scope="row" valign="top"><label for="cat_Image_url">Static Page:</label></th><td>';
	$html .= '<input type="hidden" id="wpsp_cat_is_static" name="wpsp_cat_is_static" values="10"/>';
	//$html .= $_GET['tag_ID'];
	$html .= '<input type="checkbox" id="wpsp_cat_is_static" name="wpsp_cat_is_static" ';
	if( get_term_meta($tag->term_id, 'wpsp_cat_is_static', true)=='1' ){ $html .= "value='1' checked='checked'"; }
	else {$html .= "value='10' "; }
	$html .= 'onclick="if(this.value==1){  this.value=10; } else{ this.value=1 }"/>';
	$html .= '</td></tr>';
	echo $html;
}
//add extra fields to category edit form hook
add_action ( 'edit_category_form_fields', 'wpsp_category_fields');


// save extra category extra fields callback function
function wpsp_save_extra_category_fileds( $term_id ) {
    if ( isset( $_POST['wpsp_cat_is_static'] ) ) {		update_term_meta($term_id, 'wpsp_cat_is_static', sanitize_text_field($_POST['wpsp_cat_is_static']) );    }
	
	global $wpsp_index_file_name;
	global $wpsp_home_path;
	$current_cat = get_category($term_id);	$cat_slug=$current_cat->slug;
	$directory=$wpsp_home_path.$cat_slug;
	//update_term_meta($term_id, 'wpsp_cat_is_static', $directory); 
	if( get_term_meta( $term_id, 'wpsp_cat_is_static', true )==='1' ){ 
			
			wpsp_rrmdir($directory);
			//update_term_meta($term_id, 'wpsp_cat_is_static', $directory); 
			$raw_html = file_get_contents(home_url().'/'.$cat_slug.'/');
			
			if (!file_exists($directory)) {			mkdir($directory, 0755);			}
			
			$file = $directory.'/'.$wpsp_index_file_name;
			//$raw_html = $wpsp_home_path.$post_slug.'/'.$wpsp_index_file_name;
			file_put_contents($file, $raw_html);
			
	}
	else {			if($directory!=$wpsp_home_path){wpsp_rrmdir($directory);} }
	
}

// save extra category extra fields hook
add_action ( 'edited_category', 'wpsp_save_extra_category_fileds');


//Regenerate Home Page
function wpsp_update_home_page() {
			global $wpsp_home_path;
			$file = $wpsp_home_path."index.html";
			unlink($file);
			$raw_html = file_get_contents(home_url().'/');
			//$raw_html = $wpsp_home_path.$post_slug.'/'.$wpsp_index_file_name;
			file_put_contents($file, $raw_html);
			echo "Home Page Updated!";   
}


function wpsp_options_page_html()
{
    // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?= esc_html(get_admin_page_title()); ?></h1>
        
		
		<form action="options-general.php?page=wpsp" method="post">
			<?php wp_nonce_field('reg_hp'); ?>
            <input type="hidden" name="action" value="reg_hp" />
			<?php            // output save settings button
            submit_button('Re-Generate Home Page!');
			if ( isset( $_POST['action'] ) and wp_verify_nonce($_REQUEST['_wpnonce'], 'reg_hp' ) ) {	if($_POST['action']=="reg_hp"){	wpsp_update_home_page();	}}
	
            ?>
        </form>
		
		<h1>Only in <a href='https://wpsp.prs.hosting/termek/wp-static-pages-premium-plugin/' target='_blank'>Premium:</a></h1>
		<form action="options-general.php?page=wpsp" method="post">
			<p>Enable the static page option for all pages, posts and categories: <input type="checkbox" name="all_content" value="1" /><br/>
			Be careful to use this feature! Always check the results!</p>
			<?php wp_nonce_field('reg_cache'); ?>
			<input type="hidden" name="action" value="reg_cache" />
			<?php
            // output save settings button
            submit_button('Re-Generate All HTML');
			
			if ( isset( $_POST['action'] ) and wp_verify_nonce($_REQUEST['_wpnonce'], 'reg_cache' ) ) {	if($_POST['action']=="reg_cache"){
			
			//Regenerate Everyhing
			global $wpsp_register_string; echo $wpsp_register_string;
			
			}}
			?>
		</form>
		<form action="options-general.php?page=wpsp" method="post">
			
			<input type="hidden" name="action" value="del_cache" />
			<?php wp_nonce_field('del_cache'); ?>
			<?php
            // output save settings button
            submit_button('Delete All HTML');
			
			
			if ( isset( $_POST['action'] ) and wp_verify_nonce($_REQUEST['_wpnonce'], 'del_cache' ) ) {	if($_POST['action']=="del_cache"){
			
			//Remove Everyhing
			global $wpsp_register_string; echo $wpsp_register_string;
			
			
			
			}}
			?>
		</form>
		<form action="options-general.php?page=wpsp" method="post">
			<?php wp_nonce_field('ref_all_html'); ?>
			<?php
			//Init the variable		
			if ( get_option( 'wpsp_ref_time' ) == false ) {	add_option('wpsp_ref_time', '-');		} 
			
			//Update variable
			if ( isset( $_POST['ref_all_html'] ) and wp_verify_nonce($_REQUEST['_wpnonce'], 'ref_all_html' ) ) {		
			//wpsp_cron_event(sanitize_text_field($_POST['ref_all_html']));		update_option( 'wpsp_ref_time', sanitize_text_field($_POST['ref_all_html']) );
				global $wpsp_register_string;echo $wpsp_register_string;
			}
			
			?>
			<p>Re-Generate all static pages every: 
			<select name="ref_all_html" >
			  <option value="-" <?php if ( get_option( 'wpsp_ref_time' ) == false ) { echo 'selected';} ?>>-</option>
			  <option value="1min" <?php if(get_option( 'wpsp_ref_time' )==='1min'){ echo 'selected';} ?>>1</option>
			  <option value="5min" <?php if(get_option( 'wpsp_ref_time' )==='5min'){ echo 'selected';} ?>>5</option>
			  <option value="10min" <?php if(get_option( 'wpsp_ref_time' )==='10min'){ echo 'selected';} ?>>10</option>
			  <option value="30min" <?php if(get_option( 'wpsp_ref_time' )==='30min'){ echo 'selected';} ?>>30</option>
			  <option value="60min" <?php if(get_option( 'wpsp_ref_time' )==='60min'){ echo 'selected';} ?>>60</option>
			</select>
			minutes! </p>
			<?php
            // output save settings button
            submit_button('Save');
				
			?>
		</form>
		
		
    </div>
    <?php
}



function wpsp_options_page()
{
    add_submenu_page(
        'options-general.php',
        'WP Static Pages',
        'WP Static Pages',
        'manage_options',
        'wpsp',
        'wpsp_options_page_html'
    );
	
	//add_action( 'admin_init', 'register_wpsp_settings' );

}
add_action('admin_menu', 'wpsp_options_page');

?>
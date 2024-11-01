<?php

class FFWDModelInfo_ffwd {

  private $per_page = 20;
  private $facebook_sdk;
  private $app_id;
  private $app_secret;
  private $access_token;
	public  $ffwd_objects;
	public  $date_formats = array(
						"ago" => "2 days ago",
						"F j, Y, g:i a" => "March 10, 2015, 5:16 pm",
						"F j, Y" => "March 10, 2015",
						"l, F jS, Y" => "Tuesday, March 10th, 2015",
						"l, F jS, Y, g:i a" => "Tuesday, March 10th, 2015, 5:16 pm",
						"Y/m/d \a\\t g:i a" => "2015/03/10 at 12:50 AM",
						"Y/m/d" => " 2015/03/10",
                        "d/m/Y" => " 10/03/2015",
						"Y.m.d" => " 2015.03.10",
					);
  public $pages_list = array();

  public function __construct() {
    $user = get_current_user_id();
    $screen = get_current_screen();
    $option = $screen->get_option('per_page', 'option');

    $this->per_page = get_user_meta($user, $option, true);

    if ( empty ( $this->per_page) || $this->per_page < 1 ) {
      $this->per_page = $screen->get_option( 'per_page', 'default' );

    }

    $this->pages_list = get_option('ffwd_pages_list', array());
  }

  public function get_image_rows_data($gallery_id) {
    global $wpdb;
    if (!current_user_can('manage_options') && $wpdb->get_var("SELECT image_role FROM " . $wpdb->prefix . "bwg_option")) {
      $where = " WHERE author=" . get_current_user_id();
    }
    else {
      $where = " WHERE author>=0 ";
    }
    $where .= ((isset($_POST['search_value'])) ? $wpdb->prepare(' AND filename LIKE %s', '%' . sanitize_text_field(stripslashes($_POST['search_value'])) . '%' ) : '');
    $asc_or_desc = ((isset($_POST['asc_or_desc'])) ? sanitize_text_field(stripslashes($_POST['asc_or_desc'])) : 'asc');
    $asc_or_desc = ($asc_or_desc != 'asc') ? 'desc' : 'asc';
    $image_order_by = $wpdb->prepare(' ORDER BY ' . '%s' . ' ' . $asc_or_desc, ((isset($_POST['image_order_by']) && sanitize_text_field(stripslashes($_POST['image_order_by'])) != '') ? sanitize_text_field(stripslashes($_POST['image_order_by'])) : 'order'));
    if (isset($_POST['page_number']) && $_POST['page_number']) {
      $limit = ((int) sanitize_text_field($_POST['page_number']) - 1) * $this->per_page;
    }
    else {
      $limit = 0;
    }
    $row = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bwg_image " . $where . " AND gallery_id='" . $gallery_id . "' " . $image_order_by . " LIMIT " . $limit . ",".$this->per_page);
    return $row;
  }

  public function get_tag_rows_data($image_id) {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "terms AS table1 INNER JOIN " . $wpdb->prefix . "bwg_image_tag AS table2 ON table1.term_id=table2.tag_id WHERE table2.image_id='%d' ORDER BY table2.tag_id", $image_id));
    return $rows;
  }

	public function check_logged_in_user() {
		if(!class_exists('WDFacebookFeed'))
			require_once(WD_FFWD_DIR . '/framework/WDFacebookFeed.php');
		return WDFacebookFeed::check_logged_in_user();
  }

  public function get_facebook_data() {
    if(!class_exists('Facebook'))
	  include WD_FFWD_DIR . "/framework/facebook-sdk/facebook.php";
		global $wpdb;
		//get app_id and secret from facebook login response
		$row = $wpdb->get_row($wpdb->prepare('SELECT app_id, app_secret FROM ' . $wpdb->prefix . 'wd_fb_option WHERE id="%d"', 1));

		//$facebook_app_id = (isset($_REQUEST['appId'])) ? sanitize_text_field(stripslashes($_REQUEST['appId'])) : '';
		//$facebook_app_secret = (isset($_REQUEST['secret'])) ? sanitize_text_field(stripslashes($_REQUEST['secret'])) : '';

		$this->app_id = $row->app_id;
		$this->app_secret = $row->app_secret;
		$this->access_token = $row->user_access_token;

		$this->facebook_sdk = new Facebook(array(
			'appId'  => $this->app_id,
			'secret' => $this->app_secret,
			));
		if(isset($_POST['app_log_out'])) {
				//setcookie('fbs_'.$this->facebook_sdk->getAppId(), '', time()-100, '/', 'http://localhost/wordpress_rfe/');
				session_destroy();
			//although you need reload the page for loging out
		}
    if($this->facebook_sdk->getUser()) {
			try{
			}
			catch (FacebookApiException $e) {
				echo "<!--DEBUG: ".$e." :END-->";
				error_log($e);
			}
    }
  }

  public function log_in_log_out() {

		$this->get_facebook_data();
		$user = $this->facebook_sdk->getUser();


		if ($user) {
			try {
			$old_access_token = $this->access_token;
			// Proceed knowing you have a logged in user who's authenticated.
				$user_profile = $this->facebook_sdk->api('/me');
				$this->facebook_sdk->setExtendedAccessToken();
				$access_token = $this->facebook_sdk->getAccessToken();
			$this->access_token = $access_token;
			} catch (FacebookApiException $e) {
			echo '<div class="error"><p><strong>OAuth Error</strong>Error added to error_log: '.$e.'</p></div>';
			error_log($e);
			$user = null;
			}
		}
		// Login or logout url will be needed depending on current user state.
		$app_link_text = $app_link_url = null;
		if ($user && !isset($_POST['app_log_out'])) {
			$app_link_url = $this->facebook_sdk->getLogoutUrl(array('next' => admin_url() . 'admin.php?page=options_bwg'));
			$app_link_text = __("Logout of your app", 'facebook-albums');
		} else {
			$app_link_url = $this->facebook_sdk->getLoginUrl(array('scope' => 'user_photos,user_videos,read_stream,user_posts'));
				$app_link_text = __('Log into Facebook with your app', 'facebook-albums');
		} ?>
	  <!--<input type="hidden" name="facebookalbum[app_id]" value="<?php echo $this->app_id; ?>" />
	  <input type="hidden" name="facebookalbum[app_secret]" value="<?php echo $this->app_secret; ?>" />
	  <input type="hidden" name="facebookalbum[access_token]" value="<?php echo $this->access_token; ?>" /> -->
	  <?php if($user && !isset($_POST['app_log_out'])) : ?>
		<div style="float: right;"><span style="margin: 0 10px;"><?php echo $user_profile['name']; ?></span><img src="https://graph.facebook.com/<?php echo $user_profile['id']; ?>/picture?type=square" style="vertical-align: middle"/></div>
	    <ul style="margin:0px;list-style-type:none">
		  <li><a href="https://developers.facebook.com/apps/<?php echo $this->app_id; ?>" target="_blank"><?php _e("View your application's settings.", 'facebook-albums'); ?></a></li>
	      <input class="button-primary" type="submit" name="app_log_out" value="Log out from app" />
		</ul>
	  <?php else :  ?>
	    <a class="button button-primary" id="<?php echo WD_FB_PREFIX; ?>_login_button" href="<?php echo $app_link_url; ?>"><?php echo $app_link_text; ?></a>
	  <?php endif; ?>
	  <div style="clear: both;">&nbsp;</div>
	  <?php
	  /*<!-- <p><?php printf(__('Having issues once logged in? Try <a href="?page=facebook-album&amp;reset_application=%s">resetting application data.</a> <em>warning: removes App ID and App Secret</em>'), wp_create_nonce($current_user->data->user_email)); ?></p>
	  <p><strong>Notice!</strong> Your extended access token will only last about 2 months. So visit this page every month or so to keep the access token fresh.</p> -->*/
  }

  public function get_rows_data() {
    global $wpdb;
    $where = ((isset($_POST['search_value'])) ? $wpdb->prepare(' WHERE name LIKE %s', '%' . sanitize_text_field(stripslashes($_POST['search_value'])) . '%') : '');
    $asc_or_desc = ((isset($_POST['asc_or_desc'])) ? sanitize_text_field(stripslashes($_POST['asc_or_desc'])) : 'asc');
    $asc_or_desc = ($asc_or_desc != 'asc') ? 'desc' : 'asc';
    $order_by = $wpdb->prepare(' ORDER BY ' . '%s' . ' ' . $asc_or_desc, ((isset($_POST['order_by']) && sanitize_text_field(stripslashes($_POST['order_by'])) != '') ? sanitize_text_field(stripslashes($_POST['order_by'])) : 'order'));
    if (isset($_POST['page_number']) && $_POST['page_number']) {
      $limit = ((int) sanitize_text_field($_POST['page_number']) - 1) * $this->per_page;
    }
    else {
      $limit = 0;
    }
    $query = "SELECT * FROM " . $wpdb->prefix . "wd_fb_info " . $where . $order_by . " LIMIT " . $limit . ",".$this->per_page;
    $rows = $wpdb->get_results($query);
    return $rows;
  }

	public function del_ffwd_objects() {
    global $wpdb;
    $query = "SELECT * FROM " . $wpdb->prefix . "wd_fb_info WHERE `update_mode` <> 'no_update'";
    $rows = $wpdb->get_results($query);
		require_once(WD_FFWD_DIR . '/framework/WDFacebookFeed.php');
		WDFacebookFeed::prepare_to_delete($rows);
		$this->ffwd_objects = json_encode(WDFacebookFeed::$client_side_check, true);
  }

  public function get_row_data($id) {
    global $wpdb;
    if ($id != 0) {
      $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'wd_fb_info WHERE id="%d"', $id));
    }
    else {
      $row = new stdClass();
      $row->id = 0;
      $row->name = '';
      $row->page_access_token = '';
      $row->type = '';
      $row->content_type = 'timeline';
      $row->content = 'statuses,photos,albums,videos,links,events';
      $row->order = 0;
      $row->content_url = '';
      $row->timeline_type = 'posts';
      $row->limit = 10;
      $row->app_id = '';
      $row->app_secret = '';
      $row->exist_access = 1;
      $row->access_token = '';
	    $row->published = 1;
	    $row->update_mode = 'keep_old';
      $row->view_on_fb= 1;
      $row->post_text_length= 200;
      $row->post_date_format= 'ago';
      $row->fb_view_type= '';
       $row->date_timezone= '';
       $row->event_street= 1;
       $row->event_city= 1;
       $row->event_country= 1;
       $row->event_zip= 1;
       $row->event_map= 1;
       $row->event_date= 1;
       $row->event_date_format= 'F j; Y; g:i a';
       $row->event_desp_length= 200;
       $row->comments_replies= 1;
       $row->comments_filter= 'toplevel';
       $row->comments_order= 'chronological';
       $row->page_plugin_pos= 'bottom';
       $row->page_plugin_fans= 1;
       $row->page_plugin_cover= 1;
       $row->page_plugin_header= 0;
       $row->page_plugin_width= 380;
////////////////////////////////////////////

$row->popup_fullscreen= 0;
$row->popup_autoplay= 0;
$row->open_commentbox= 1;
$row->popup_width= 800;
$row->popup_height= 600;
$row->popup_effect= 'fade';
$row->popup_interval= 5;
$row->popup_enable_filmstrip= 0;
$row->popup_filmstrip_height= 70;
$row->popup_comments= 1;
$row->popup_likes= 1;
$row->popup_shares= 1;
$row->popup_author= 1;
$row->popup_name= 1;
$row->popup_place_name= 1;
$row->popup_message_desc= 1;
$row->popup_enable_ctrl_btn= 1;
$row->popup_enable_fullscreen= 1;
$row->popup_enable_info_btn= 1;
$row->popup_enable_facebook= 1;
$row->popup_enable_twitter= 1;
$row->popup_enable_google= 0;
$row->popup_enable_pinterest= 0;
$row->popup_enable_tumblr= 0;
/////////////////////////////////////
$row->image_max_columns= 5;
$row->thumb_width= 200;
$row->thumb_height= 150;
$row->thumb_comments= 1;
$row->thumb_likes= 1;
$row->thumb_name= 1;
$row->masonry_hor_ver= "vertical";
$row->blog_style_width= 700;
$row->blog_style_height= "";
$row->blog_style_view_type= 1;
$row->blog_style_comments= 1;
$row->blog_style_likes= 1;
$row->blog_style_message_desc= 1;
$row->blog_style_shares_butt= 1;
$row->blog_style_shares= 1;
$row->blog_style_author= 1;
$row->blog_style_name= 1;
$row->blog_style_place_name= 1;
$row->blog_style_facebook= 1;
$row->blog_style_twitter= 1;
$row->blog_style_google= 0;
$row->album_max_columns= 5;
$row->album_thumb_width= 200;
$row->album_thumb_height= 150;
$row->album_title= "show";
$row->fb_plugin= 0;
$row->fb_name= 0;
$row->pagination_type= 1;
$row->objects_per_page= 10;
$row->image_onclick_action= 'facebook';
$row->album_image_thumb_width= 200;
$row->album_image_thumb_height= 150;
$row->album_image_max_columns= 5;
$row->event_order= 0;
$row->upcoming_events= 0;
    }
    return $row;
  }

  public function page_nav() {
    global $wpdb;
    $where = ((isset($_POST['search_value']) && (sanitize_text_field(stripslashes($_POST['search_value'])) != '')) ? $wpdb->prepare(' WHERE name LIKE %s', '%' . sanitize_text_field(stripslashes($_POST['search_value'])) . '%' )  : '');
    $query = "SELECT COUNT(*) FROM " . $wpdb->prefix . "wd_fb_info " . $where;
    $total = $wpdb->get_var($query);
    $page_nav['total'] = $total;
    if (isset($_POST['page_number']) && $_POST['page_number']) {
      $limit = ((int) sanitize_text_field($_POST['page_number']) - 1) * $this->per_page;
    }
    else {
      $limit = 0;
    }
    $page_nav['limit'] = (int) ($limit / $this->per_page + 1);
    return $page_nav;
  }

  public function image_page_nav($gallery_id) {
    global $wpdb;
    if (!current_user_can('manage_options') && $wpdb->get_var("SELECT image_role FROM " . $wpdb->prefix . "bwg_option")) {
      $where = " AND author=" . get_current_user_id();
    }
    else {
      $where = " AND author>=0 ";
    }
    $where .= ((isset($_POST['search_value']) && (sanitize_text_field(stripslashes($_POST['search_value'])) != '')) ? $wpdb->prepare(' AND filename LIKE %s', '%' . sanitize_text_field(stripslashes($_POST['search_value'])) . '%' )  : '');
    $query = "SELECT COUNT(*) FROM " . $wpdb->prefix . "bwg_image WHERE gallery_id='" . $gallery_id . "' " . $where;
    $total = $wpdb->get_var($query);
    $page_nav['total'] = $total;
    if (isset($_POST['page_number']) && $_POST['page_number']) {
      $limit = ((int) sanitize_text_field($_POST['page_number']) - 1) * $this->per_page;
    }
    else {
      $limit = 0;
    }
    $page_nav['limit'] = (int) ($limit / $this->per_page + 1);
    return $page_nav;
  }

  public function get_option_row_data() {
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'wd_fb_option WHERE id="%d"', 1));
    return $row;
  }

  public function get_theme_rows_data() {
    global $wpdb;
    $query = "SELECT * FROM " . $wpdb->prefix . "wd_fb_theme ORDER BY name";
    $rows = $wpdb->get_results($query);
    return $rows;
  }

  public function per_page(){
    return $this->per_page;
  }
}

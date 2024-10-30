<?php
/*
Plugin Name:Mail to Users
Plugin URI:http://www.miraclewebsoft.com
Description:Mail to Users plugin used to notify blog users about new posts and pages. You can also send custom email. Well formatted email with nice email template.
Version:1.2
Author:sony7596, reachbaljit, miraclewebssoft
Author URI:http://www.miraclewebsoft.com
License:GPL2
License URI:https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!defined("MAIL2USERS_PLUGIN_VERSION_CURRENT")) define('MAIL2USERS_PLUGIN_VERSION_CURRENT', '1.1');
if (!defined("MAIL2USERS_PLUGIN_URL")) define('MAIL2USERS_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined("MAIL2USERS_PLUGIN_DIR")) define("MAIL2USERS_PLUGIN_DIR", plugin_dir_path(__FILE__));
if (!defined("MAIL2USERS_PLUGIN_NM")) define("MAIL2USERS_PLUGIN_NM", 'Mail to users');

Class Mail2Users
{
    public function __construct()
    {
        // Installation and uninstallation hooks
        register_activation_hook(__FILE__, array($this, 'mail2users_activate'));
        register_deactivation_hook(__FILE__, array($this, 'mail2users_deactivate'));
        add_action('admin_menu', array($this, 'mail2users_setup_admin_menu'));
        add_action("admin_init", array($this, 'mail2users_backend_plugin_js_scripts_filter_table'));
        add_action("admin_init", array($this, 'mail2users_backend_plugin_css_scripts_filter_table'));
        add_action('add_meta_boxes', array($this, 'mail2users_mail2user_register_meta_box'));
        add_action("save_post", array($this, 'mail2users_send_mail_on_save'), 10, 3);
        add_action('admin_init', array($this, 'mail2users_custom_url_handler'));
        add_action('admin_init', array($this, 'mail2users_settings_options'));

    }
    public function mail2users_setup_admin_menu()
    {
        add_menu_page(__('Mail to Users', 'm2u_menu_title'), 'Mail to users', 'activate_plugins', 'mail2users_handler',
            array($this, 'mail2users_admin_page'), MAIL2USERS_PLUGIN_URL . 'assets/image/icon.ico');
        add_submenu_page(null            // -> Set to null - will hide menu link
            , __('Thank You', 'm2u_sub_menu_title')// -> Page Title
            , 'Thanks'    // -> Title that would otherwise appear in the menu
            , 'administrator' // -> Capability level
            , 'thanks_handle'   // -> Still accessible via admin.php?page=menu_handle
            , array($this, 'mail2users_thanks') // -> To render the page
        );
        add_submenu_page('mail2users_handler'            // -> Set to null - will hide menu link
            , __('Unsubscribed Users', 'm2u_sub_menu_title_Unsubscribe')// -> Page Title
            , 'Unsubscribed Users'    // -> Title that would otherwise appear in the menu
            , 'administrator' // -> Capability level
            , 'Unsubscribed_handle'   // -> Still accessible via admin.php?page=menu_handle
            , array($this, 'mail2users_unsubscribed_list') // -> To render the page
        );
        add_submenu_page('mail2users_handler'            // -> Set to null - will hide menu link
            , __('Settings', 'm2u_sub_menu_title_Settings')// -> Page Title
            , 'Settings'    // -> Title that would otherwise appear in the menu
            , 'administrator' // -> Capability level
            , 'Settings_handle'   // -> Still accessible via admin.php?page=menu_handle
            , array($this, 'mail2users_settings') // -> To render the page
        );
    }
    public function mail2users_admin_page()
    {
        include(plugin_dir_path(__FILE__) . 'views/dashboard.php');
    }
    public function mail2users_thanks()
    {
        $to = [];
        $body = sanitize_text_field($_POST['mail2users-editor-id']);
        $subject = sanitize_text_field($_POST['mail2users-subject']);
        $attachment = stripslashes_deep($_POST['mail2users-attachment']);
        $cc = sanitize_email($_POST['mail2users-cc']);
        $from = sanitize_email($_POST['mail2users-from']);

        //send mail to group
        if (isset($_POST['mail2users_groups'])) {
            $to = $this->mail2users_send_user_group($_POST['mail2users_groups']);
        }
        //send mail to individuals
        if (isset($_POST['mail2users_indv'])) {
            $to = $this->mail2users_send_single_user($_POST['mail2users_indv']);

        }

        $is_sent = $this->mail2users_send_mail($to, $body, $subject, $from, $attachment, $cc, $subject);

        if ($is_sent) {
            echo '<div class="row">';
            echo '<div class="mail2users-compose-section">';
            if (isset($is_sent)) {
                echo '<h2>' . __('Mail sent successfully', 'm2u_mail_sent') . '</h2>';
            } else {
                echo '<h2>' . __('Mail not sent', 'm2u_mail_fail') . '</h2>';
            }
            echo '</div>';
        }
    }
    function mail2users_backend_plugin_js_scripts_filter_table()
    {
        wp_enqueue_script("jquery");
        wp_enqueue_script("mail2users.js", plugins_url("/assets/js/mail2users.js", __FILE__));
    }
    function mail2users_backend_plugin_css_scripts_filter_table()
    {
        wp_enqueue_style("view.css", plugins_url("/assets/css/view.css", __FILE__));
    }
    function mail2users_send_single_user($emails_array)
    {
        $to = [];
        $to_arr = explode(',', rtrim($emails_array, ','));

        return $to_arr;
    }
    function mail2users_send_user_group($emails_array)
    {

        $to = [];
        $groups = explode(',', rtrim($emails_array, ','));
        $emails = [];
        foreach ($groups as $group) {
            $emails[] = get_users(['blog_id' => $GLOBALS['blog_id'], 'role' => $group, 'fields' => ['user_email'],]);

        }
        foreach ($emails as $email) {
            if ($email == 1) {
                $to[] = $email->user_email;
            } else {
                foreach ($email as $single_email) {
                    array_push($to, $single_email->user_email);

                }

            }
        }

        return $to;
    }

    function mail2users_send_mail($to, $body, $subject, $from, $attachment = "", $cc = "", $post_title = "")
    {
        //other classes
        require_once MAIL2USERS_PLUGIN_DIR . "/views/email-template.php";

        $Mail2users_Templates = new Mail2users_Templates();

        $site_name = get_option('blogname');
        $site_url = get_site_url();

        //check attachment
        $attachment_url = array();
        if ($attachment) {
            $path = parse_url($attachment, PHP_URL_PATH);
            array_push($attachment_url, $_SERVER['DOCUMENT_ROOT'] . $path);
        }
        $headers[] = "From:$site_name <$from>";
        if ($cc) {
            $headers[] = "Cc:$site_name <$cc>";
        }

        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        //check option
        if (get_option('eregards')) {

            $body .= "<br><br><p>" . __('Regards', 'm2u_plugin') . "</p><p>" . get_option('eregards') . "</p>";
        }


        //remove unsubscribed users
        global $wpdb;
        $unsub_users = $wpdb->get_results('SELECT DISTINCT(meta_value) FROM wp_usermeta WHERE meta_key = "mail2users" ORDER BY umeta_id', ARRAY_A);

        $unsub_users_arr = array_column($unsub_users, 'meta_value');
        $to_arr = array_diff($to, $unsub_users_arr);

        //check template option
        if (get_option('etemplate')) {
            //simple email
            $body_html = $Mail2users_Templates->mail2users_template_simple_fn($body, $site_url, $site_name, MAIL2USERS_PLUGIN_NM, $post_title);
        } else {
            //formatted email

            $body_html = $Mail2users_Templates->mail2users_template_fn($body, $site_url, $site_name, MAIL2USERS_PLUGIN_NM, $post_title);
        }
        $count = 0;
        foreach ($to_arr as $single_user) {
            ++$count;
            $single_user = sanitize_email($single_user);
            wp_mail($single_user, $subject, $body_html, $headers, $attachment_url);

        }

        return $count;
    }

    public function mail2users_activate()
    {

    }


    public function mail2users_deactivate()
    {

    }

    //Register Meta Box
    function mail2users_mail2user_register_meta_box()
    {
        $where_meta_box_show = array('post', 'page');
        $all_post = get_post_types();
        unset($all_post['post']);
        unset($all_post['page']);

        foreach($all_post as $post_type){
            if(get_option('m2u_'.$post_type) == 'm2u_'.$post_type){
                array_push($where_meta_box_show, $post_type);
            }
        }

        add_meta_box('mail2user-meta-box-id', __('Mail to users Plugin', 'm2u_meta_box'), array($this,
            'mail2user_meta_box_callback'), $where_meta_box_show, 'side', 'high');
    }

    function mail2user_meta_box_callback($object)
    {
        wp_nonce_field(basename(__FILE__), "meta-box-nonce");
        // user  role
        global $wp_roles;
        $roles = $wp_roles->get_names();
        $groups = [];
        foreach ($roles as $role) {
            $groups[$role] = count(get_users(['blog_id' => $GLOBALS['blog_id'], 'role' => $role, 'fields' => ['user_email', 'display_name'],]));

        }
        echo '<h4>' . __('Notify users groups about post', 'm2u_notify') . '</h4>';
        echo '<ul  class="mail2users-white mail2users-group-meta">';
        foreach ($groups as $group_names => $number_users) {
            if ($number_users != 0) {

                echo "<li data-email='$group_names'>";

                echo "<input class='group_checkbox' name='" . $group_names . "' type='checkbox' value='true'>";

                echo " <span class='mail2users_display_name'>
				" . ucfirst($group_names) . "</span>

                ($number_users)</li>";

            }

        }
        echo '</ul>';
    }

    function mail2users_send_mail_on_save($post_id, $post, $update)
    {
        global $wp_roles;
        $roles = $wp_roles->get_names();
        $emails = [];
        $to = [];
        $site_name = get_option('blogname');

        $subject = get_option('esubject') ? get_option('esubject') : $post->post_title . '(' . __('post update from ', 'm2u_mail_subject') . $site_name . ')';

        $post_title = $post->post_title;
        $post_content = $post->post_content;

        $limit = get_option('elength') ? get_option('elength') : 100;

        if (strlen($post_content) > $limit)
            $post_content = substr($post_content, 0, $limit - 3) . '...';
        $body = $post_content;
        $body .= "<p><a href='" . get_permalink($post->ID) . "'>" . __('Goto post', 'm2u_goto_post') . "</a></p>";

        $from = get_option('mail_from') ? get_option('mail_from') : get_option('admin_email');


        foreach ($roles as $role) {
            if (isset($_POST[$role])) {
                $emails[] = get_users(['blog_id' => $GLOBALS['blog_id'], 'role' => $role, 'fields' => ['user_email'],]);
            }

        }

        if (count($emails)) {

            foreach ($emails as $email) {
                if ($email == 1) {
                    $to[] = $email->user_email;
                } else {
                    foreach ($email as $single_email) {
                        array_push($to, $single_email->user_email);
                    }
                }
            }

            $is_sent = $this->mail2users_send_mail($to, $body, $subject, $from, $attachment = "", $cc = "", $post_title);
        }
    }

    // un subscribe mail function
    function mail2users_custom_url_handler($url)
    {
        $last_url = wp_get_referer();
        $parts = parse_url($last_url);
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        if (isset($query['mail2users_unsubscribe'])) {

            $current_user = wp_get_current_user();

            $email = $current_user->user_email;
            add_user_meta($current_user->ID, 'mail2users', $email);
            $unsub = __('You are unsubscribed successfully', 'mail2users_unsubscribe');
            $home_url = site_url();
            echo '<script>alert("' . $unsub . '"); window.location = "' . $home_url . '"</script>';

        }
    }

    public function mail2users_unsubscribed_list()
    {
        require_once MAIL2USERS_PLUGIN_DIR . "views/unsubscribe_users.php";

    }

    public function mail2users_settings()
    {
        require_once MAIL2USERS_PLUGIN_DIR . "views/settings.php";

    }

    function mail2users_settings_options()
    {
        //register our settings
        register_setting('m2u_plugin_group', 'mail_from');
        register_setting('m2u_plugin_group', 'elength');
        register_setting('m2u_plugin_group', 'esubject');
        register_setting('m2u_plugin_group', 'eregards');
        register_setting('m2u_plugin_group', 'etemplate');

        $all_post = get_post_types();
        unset($all_post['post']);
        unset($all_post['page']);

        foreach($all_post as $post_type){
            register_setting('m2u_plugin_group', 'm2u_'.$post_type);
        }
    }
}

$Mail2Users_obj = new Mail2Users();

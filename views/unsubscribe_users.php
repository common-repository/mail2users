<?phpif (!defined('ABSPATH')) exit; // Exit if accessed directly?><h1><?php echo __('Unsubscribed Users', 'm2u_unsub_list'); ?></h1><table class="wp-list-table widefat fixed striped posts">    <thead>    <th><?php echo __('Username', 'm2u_unsub_un'); ?></th>    <th><?php echo __('Email', 'm2u_unsub_mail'); ?></th>    <th><?php echo __('Role', 'm2u_unsub_role'); ?></th>    </thead>    <tbody>    <?php    global $wpdb;    $unsub_users = $wpdb->get_results('SELECT DISTINCT(meta_value) FROM wp_usermeta WHERE meta_key = "mail2users" ORDER BY umeta_id', ARRAY_A);    $unsub_users_arr = array_column($unsub_users, 'meta_value');    if(!empty($unsub_users_arr)){    foreach ($unsub_users_arr as $unsub_user) {        echo '<tr>';        echo '<td>' . get_user_by('email', $unsub_user)->user_login . '</td>';        echo '<td>' . $unsub_user . '</td>';        echo '<td>' . get_user_by('email', $unsub_user)->roles[0] . '</td>';        echo '</tr>';    }    }    else{        echo '<tr>';        echo "<td><h4>".__('No unsubscribed user')."</h4></td>";    }    ?>    </tbody></table>
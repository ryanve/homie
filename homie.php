<?php
/*
Plugin Name: Homie
Plugin URI: http://github.com/ryanve/homie
Description: Include or exclude homepage categories.
Version: 0.1.1
Author: Ryan Van Etten
Author URI: http://ryanve.com
License: MIT
*/

call_user_func(function() {

    $plugin = array('slug' => basename(__FILE__, '.php'));
    $plugin['name'] = ucfirst($plugin['slug']);
    $plugin['option'] = 'plugin:' . $plugin['slug'];
    $plugin['prefix'] = $plugin['option'] . ':';

    $plugin['get'] = function() use (&$plugin) {
        return (array) get_option($plugin['option']);
    };

    $plugin['set'] = function($data) use (&$plugin) {
        return (null === $data 
            ? delete_option($plugin['option'])
            : update_option($plugin['option'], $data)
        ) ? $data : false;
    };

    $plugin['selects'] = array('*', __('Include'), __('Exclude'));

    is_admin() ? add_action('admin_menu', function() use (&$plugin) {
    
        register_deactivation_hook(__FILE__, function() use (&$plugin) {
            $plugin['set'](null); # removes all data
        }); #wp 2.0+
        
        $page = (array) apply_filters($plugin['prefix'] . 'page', array(
            'capability' => 'manage_options'
          , 'name' => $plugin['name']
          , 'slug' => basename(__FILE__, '.php')
          , 'add' => 'add_options_page'
          , 'parent' => 'options-general.php'
          , 'sections' => array('default')
        ));

        empty($page['fn']) and $page['fn'] = function() use (&$plugin, &$page) {
            echo '<div class="wrap">';
            function_exists('screen_icon') and screen_icon(); #wp 2.7.0+
            echo '<h2>' . $plugin['name'] . '</h2>';
            echo '<h3>' . __('Home Categories') . '</h3>';
            echo '<form method="post" action="options.php">';
            settings_fields($page['slug']); #wp 2.7.0+
            do_settings_fields($page['slug'], $page['sections'][0]); #wp 2.7.0+
            submit_button(__('Update')); #wp 3.1.0+
            echo '</form></div>';
        };
        
        # Create "Settings" link to appear on /wp-admin/plugins.php
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) use (&$page) {
            $href = $page['parent'] ? $page['parent'] . '?page=' . $page['slug'] : $page['slug'];
            $href = admin_url($href); #wp 3.0.0+
            array_unshift($links, '<a href="' . $href . '">' . __('Settings') . '</a>');
            return $links;
        }, 10, 2);
        
        # add_theme_page or add_options_page
        call_user_func_array($page['add'], array(
            $page['name']
          , $page['name']
          , $page['capability']
          , $page['slug']
          , $page['fn']
        ));

        $options = $plugin['selects'];
        $categories = get_categories();
        $curr = $plugin['get']() ?: array();
        $key = 'categories';
        $curr[$key] = isset($curr[$key]) ? $curr[$key] : array();

        foreach ($categories as $category) {
            $id = $category->term_id;
            $field = "$key-$id";
        
            register_setting($page['slug'], $field, function($value) use (&$plugin, &$curr, $key, $options, $id) {
                $i = array_search($value, $options, true);
                if ($i) $curr[$key][$id] = $value;
                else unset($curr[$key][$id]);
                $plugin['set']($curr);
                return $options[(int) $i];
            }); #wp 2.7.0+

            $label = 'display:inline-block;line-height:1;margin:.5em;font-weight:bold;width:6em;word-wrap:break-word';
            $label = "<br><label style='$label' for='$field'>" . $category->name . '</label>';

            add_settings_field($field, $label, function() use (&$curr, $field, $id, $key, $options) {
                $select = "<select name='$field' style='display:inline-block;line-height:1;margin:.5em'>"
                $value = empty($curr[$key][$id]) ? $options[0] : $curr[$key][$id];
                echo array_reduce($options, function($str, $op) use ($value) {
                    $state = $value === $op ? ' selected' : '';
                    return "$str<option value='$op'$state>$op</option>";
                }, $select) . '</select>';
            }, $page['slug'], $page['sections'][0]); #wp 2.7.0+ 
        }
    
    }) : add_action('pre_get_posts', function(&$query) use ($plugin) {
        if ( ! $query->is_main_query() || ! apply_filters($plugin['prefix'] . 'on', is_home()))
            return;
        $curr = $plugin['get']() ?: array();
        $curr['categories'] = isset($curr['categories']) ? $curr['categories'] : array();
        $ids = array();
        foreach ($curr['categories'] as $id => $selected) {
            if ($selected === $plugin['selects'][2]) $ids[] = -$id;
            elseif ($selected === $plugin['selects'][1]) $ids[] = $id;
        }
        $ids and $query->set('cat', implode(',', $ids));
    });
});
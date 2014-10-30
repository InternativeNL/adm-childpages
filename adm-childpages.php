<?php
/*
Plugin Name: Admium - Childpages
Plugin URI: www.admium.nl
Description: Display excerpt of childpages
Version: 1.0
Author: Admium
Author URI: www.admium.nl
License: GPL2
GitHub Plugin URI: AdmiumNL/adm-childpages
*/

/*
  Information:

  Add the following code to the theme's functions.php to enable page excerpts (normally only available for blogs):

  add_action( 'init', 'add_excerpts_to_pages' );
  function add_excerpts_to_pages() {
  add_post_type_support( 'page', 'excerpt' );
  }

 */

class adm_Walker_simple extends Walker_Nav_Menu {

    function display_element($element, &$children_elements, $max_depth, $depth = 0, $args, &$output) {
        $id_field = $this->db_fields['id'];
        if (is_object($args[0])) {
            $args[0]->has_children = !empty($children_elements[$element->$id_field]);
        }
        #var_dump($element);
        return parent::display_element($element, $children_elements, $max_depth, $depth, $args, $output);
    }

    // Don't start the top level  
//    function start_lvl(&$output, $depth = 0, $args = array()) {
//        if (0 == $depth)
//            return;
//        parent::start_lvl(&$output, $depth, $args);
//    }
//
//    // Don't end the top level  
//    function end_lvl(&$output, $depth = 0, $args = array()) {
//        if (0 == $depth)
//            return;
//        parent::end_lvl(&$output, $depth, $args);
//    }

    function start_el(&$output, $item, $depth, $args) {
        //check if we are in the right tree
        $indent = ( $depth ) ? str_repeat("\t", $depth) : '';

        $class_names = $value = '';

        $classes = empty($item->classes) ? array() : (array) $item->classes;
        $class_names = join(' ', apply_filters('nav_menu_css_class', array_filter($classes), $item));
        $class_names = ' class="' . esc_attr($class_names) . '"';
        $output .= $indent . '<li id="menu-dw-item-' . $item->ID . '"' . $value . $class_names . '>';

        $attributes = !empty($item->attr_title) ? ' title="' . esc_attr($item->attr_title) . '"' : '';
        $attributes .=!empty($item->target) ? ' target="' . esc_attr($item->target) . '"' : '';
        $attributes .=!empty($item->xfn) ? ' rel="' . esc_attr($item->xfn) . '"' : '';
        $attributes .=!empty($item->url) ? ' href="' . esc_attr($item->url) . '"' : '';

        $prepend = '<strong>';
        $append = '</strong>';
        $description = !empty($item->attr_title) ? '<span class="under">' . esc_attr($item->attr_title) . '</span>' : '';

        if ($depth != 0) {
            $description = $append = $prepend = "";
        }
        $item_output = $args->before;
        $item_output .= '<a' . $attributes . '>';
        $item_output .= $args->link_before . $prepend . apply_filters('the_title', $item->title, $item->ID) . $append;
        $item_output .= $description . $args->link_after;
        $item_output .= '<span>&raquo;</span></a>';
        $item_output .= $args->after;
        if ((strpos($class_names, 'current-menu-item') || strpos($class_names, 'current-page-ancestor')) && $depth == 0) {
            if ($args->has_children) {
                #$output = '</ul>';
                $output .= '<h2 class="headerSubmenu"><a' . $attributes . '>';
                $output .= apply_filters('the_title', $item->title, $item->ID);
                $output .= '</a></h2>';
            }
        } else {
//            var_dump((strpos($class_names, 'current-menu-item'));
            if($depth != 0 || $item->current || $item->current_item_ancestor || $item->current_item_parent) {
                $output .= apply_filters('walker_nav_menu_start_el', $item_output, $item, $depth, $args);
            }
            return;
        }
    }

}


if (!class_exists("adm_childpages_menu_widget")) {

    class adm_childpages_menu_widget extends WP_Widget {

        function adm_childpages_menu_widget() {
            parent::__construct('adm_childpages_menu_widget', __('Admium - Childpages'), array('description' => __('Display menu with childpages')));
        }

        function widget($args, $instance) {
            global $object_id;
            
            $found = false;
            $menus = get_terms('nav_menu');
            echo $args['before_widget'];
            foreach ($menus as $menu) {
                $menu_object = wp_get_nav_menu_items(esc_attr($menu->term_id));
                if (!$menu_object) {
                    return false;
                }
                $menu_items = wp_list_pluck($menu_object, 'object_id');
                if (!$object_id) {
                    global $post;
                    $object_id = get_queried_object_id();
                }
                // test if the specified page is in the menu or not. return true or false.
                if (in_array((int) $object_id, $menu_items)) {
                    if ($found == false) {
                        $found = true;
                        wp_nav_menu(array('menu' => $menu->term_id, 'container' => '', 'depth' => 0, 'submenu' => $object_id, 'container_class' => 'adm_childpages', 'link_after' => '', 'walker' => new adm_Walker_simple()));
                    }
                }
            }
            echo $args['after_widget'];	
        }

        // 
        function update($new_instance, $old_instance) {
            
        }

        // Settings form
        function form($instance) {
            
        }

    }

    add_action('widgets_init', 'adm_childpages_menu_widget_init');

    function adm_childpages_menu_widget_init() {
        register_widget('adm_childpages_menu_widget');
    }

}
add_filter('wp_nav_menu_objects', 'adm_submenu_limit_simple', 10, 2);
add_filter('wp_nav_menu', 'adm_nav_menu', 10, 2);

function adm_nav_menu($nav_menu, $args) {
    if (strpos($nav_menu, '</li>') === false) {
        return '';
    }
    //clean empty li's
    $changeHeader = false;
    $pattern = "/<li\b[^>]*>(.*?)<\/li>/i";
    preg_match_all($pattern, $nav_menu, $matches);
    #echo '<!-- admium';
    #var_dump($matches);
    if(is_array($matches) && is_array($matches[1])) {
        foreach($matches[1] as $id=>$link) {
            if(empty($link)) {
                $nav_menu = str_replace($matches[0][$id], '', $nav_menu);
            }   elseif(strpos($link, 'publications') && strpos($matches[0][$id],'current-menu-item') && strpos($nav_menu,'id="menu-topmenu-1"') !== false) {
                $changeHeader = true;
            }
        }
        #$pattern = "/<ul\b[^>]*>(.*?)<\/ul>/i";
        preg_match($pattern, $nav_menu, $matches);
        if(is_array($matches) && empty($matches)) {
           $nav_menu = '';
        } 
        
    }
    if($changeHeader) {
        //add H2 to header
        $pattern = "/<a\b[^>]*>(.*?)<\/a>/i";
        preg_match($pattern, $nav_menu, $matches);
        if(is_array($matches) && !empty($matches) && strpos($nav_menu, 'headerSubmenu') === false) {
            $nav_menu = str_replace($matches[0], '<h2 class="headerSubmenu">'.$matches[0].'</h2>', $nav_menu);
        }
    }
    #echo '-->';
    return $args->before_widget . $nav_menu . $args->after_widget;
}
function adm_submenu_limit_simple($items, $args) {
//var_dump($items);
    if (empty($args->submenu))
        return $items;
//    $parent_id = array_pop(wp_filter_object_list($items, array('object_id' => $args->submenu), 'and', 'menu_item_parent'));
//    if ($parent_id != 0) {
//        $children = adm_submenu_get_children_ids($parent_id, $items);
//    } else {
//        $parent_id = array_pop(wp_filter_object_list($items, array('object_id' => $args->submenu), 'and', 'ID'));
//        $children = adm_submenu_get_children_ids($parent_id, $items);
//    }
//    foreach ($items as $key => $item) {
//        if (!in_array($item->ID, $children) && $item->ID != $parent_id) {
//            unset($items[$key]);
//        }
//    }
    foreach ($items as $key => $item) {
        if (($item->current_item_ancestor || $item->current || $item->current_item_parent)) {
            
        } elseif($item->menu_item_parent == '0') {
            unset($items[$key]);
        }
    }
    //unset($items[3]);
    if (count($items) == 1) {
        $args->container_class = '';
        return array();
    }
    return $items;
}
function adm_submenu_limit($items, $args) {

    if (empty($args->submenu))
        return $items;
    $parent_id = array_pop(wp_filter_object_list($items, array('object_id' => $args->submenu), 'and', 'menu_item_parent'));
    if ($parent_id != 0) {
        $children = adm_submenu_get_children_ids($parent_id, $items);
    } else {
        $parent_id = array_pop(wp_filter_object_list($items, array('object_id' => $args->submenu), 'and', 'ID'));
        $children = adm_submenu_get_children_ids($parent_id, $items);
    }
    foreach ($items as $key => $item) {
        if (!in_array($item->ID, $children) && $item->ID != $parent_id) {
            unset($items[$key]);
        }
    }
    //unset($items[3]);
    if (count($items) == 1) {
        $args->container_class = '';
        return array();
    }
    return $items;
}

function adm_submenu_get_children_ids($id, $items) {
    $ids = wp_filter_object_list($items, array('menu_item_parent' => $id), 'and', 'ID');
    foreach ($ids as $id) {
        $ids = array_merge($ids, adm_submenu_get_children_ids($id, $items));
    }
    return $ids;
}
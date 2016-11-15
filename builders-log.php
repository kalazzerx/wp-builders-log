<?php
/*
Plugin Name: Aircraft Builders Log
Plugin URI:  http://zenith.stratman.pw/builders-log-wp-plugin/
Description: This plugin allows you to keep track of time spent on an aircraft build (or really anything). You assign an amount of time to each post, and the times can be aggregated by category and displayed on your site.
Version:     20161115
Author:      Mark A. Stratman
Author URI:  https://github.com/mstratman
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

class WP_Builders_Log_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'airplane_section_widget', // base ID
            __('Aircraft Build Time'), // Name
            array(
                'description' => __('Show time spent building your aircraft'),
            )
        );
    }

    // widget form creation
    function form($instance) {  
        if (isset($instance['title'])) {
            $title = $instance['title'];
        } else {
            $title = __('Aircraft Build Time');
        }
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> 
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <?php 
    }

    // widget update
    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        return $instance;
    }

    // widget display
    function widget($args, $instance) {
        echo $args['before_widget'];

        echo '<h4 class="widget-title">' . $instance['title'] . '</h4>';
        echo '<table><thead><tr><th>Airplane section</th><th>Build time (hrs)</th></tr></thead><tbody>';
        $sections = get_terms(array(
            'taxonomy' => WP_Builders_Log::BUILDERS_LOG_TAXONOMY,
            'hide_empty' => true
        ));
        $total_hours = 0;
        foreach ($sections as $section) {
            $min = (int) get_term_meta($section->term_id, '_build_time_total_minutes', true);
            $hours = ($min / 60);
            $total_hours += $hours;
            echo '<tr><td><a href="' . get_term_link($section) . '">' . $section->name . '</a></td><td>' . $hours . '</td></tr>';
        }
        if (count($sections) > 1) {
            echo '<tr><td><em>' . __('Total') . '</em></td><td><em>' . $total_hours . '</em></td></tr>';
        }

        echo '</tbody></table>';
        echo $args['after_widget'];
    }
}

class WP_Builders_Log {
    const BUILDERS_LOG_TAXONOMY = 'airplane_section';

    static $instance = false;
    public static function getInstance() {
        if (! self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function __construct() {

        // Make sure custom slugs work for airplane sections
        add_action('admin_init', 'flush_rewrite_rules');
        register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

        // Add a new taxonomy for airplane sections.  This will be used to force selection of
        // only a single airplane section at a time (to simplify time tracking).
        // Otherwise these are pretty much the same as categories.
        add_action('init', array(__CLASS__, 'register_taxonomy_plane_section'));

        // Remove the default controls for adding an airplane section to a post.
        add_action('admin_menu', array(__CLASS__, 'remove_default_airplane_section_meta_box'));
        // Add new controls that use radio buttons rather than multi-select.
        add_action('add_meta_boxes_post', array(__CLASS__, 'add_airplane_section_meta_box'));

        // script to keep radio buttons in sync on admin meta box form
        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_script'));
        // Allow admins to add new airplane sections from the post edit page
        add_action('wp_ajax_radio_tax_add_taxterm', array(__CLASS__,'ajax_add_term'));

        // Add a new box to the post add/edit page to record hours and minutes spent
        add_action('add_meta_boxes_post', array(__CLASS__, 'add_build_time_meta_box'));
        add_action('save_post', array($this, 'save_build_time_meta_box'));

        // Setup the widget
        add_action('widgets_init', function(){
            register_widget('WP_Builders_Log_Widget');
        });
    }

    public static function remove_default_airplane_section_meta_box() {
        remove_meta_box('tagsdiv-' . self::BUILDERS_LOG_TAXONOMY, 'post', 'normal');
    }
    public static function add_airplane_section_meta_box() {
        add_meta_box(
            'tagsdiv-new-airplane_section',  // div id
            __('Airplane Section'),          // title
            array(__CLASS__, 'airplane_section_meta_box'),      // callback
            'post','normal','high'             // post type, context, priority
        );
    }

    public static function airplane_section_meta_box($post) {
        $tax_obj = get_taxonomy(self::BUILDERS_LOG_TAXONOMY);

        $input_name = 'tax_input[' . self::BUILDERS_LOG_TAXONOMY . ']';
        $all_terms = get_terms(self::BUILDERS_LOG_TAXONOMY, array('hide_empty' => 0));
        $popular_terms = get_terms(self::BUILDERS_LOG_TAXONOMY, array(
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 10,
            'hierarchical' => false
        ));

        $post_terms = get_the_terms($post->ID, self::BUILDERS_LOG_TAXONOMY);
        $current_term = ($post_terms ? array_pop($post_terms) : false);
        $current_term_id = ($current_term ? $current_term->term_id : false);

        ?>
        <div id="taxonomy-<?php echo self::BUILDERS_LOG_TAXONOMY; ?>" class="categorydiv">
     
            <!-- Display tabs-->
            <ul id="<?php echo self::BUILDERS_LOG_TAXONOMY ; ?>-tabs" class="category-tabs">
                <li class="tabs"><a href="#<?php echo self::BUILDERS_LOG_TAXONOMY ; ?>-all" tabindex="3"><?php echo $tax_obj->labels->all_items; ?></a></li>
                <li class="hide-if-no-js"><a href="#<?php echo self::BUILDERS_LOG_TAXONOMY ; ?>-pop" tabindex="3"><?php _e( 'Most Used' ); ?></a></li>
            </ul>
     
            <!-- Display taxonomy terms -->
            <div id="<?php echo self::BUILDERS_LOG_TAXONOMY; ?>-all" class="tabs-panel">
                <ul id="<?php echo self::BUILDERS_LOG_TAXONOMY ; ?>checklist" class="list:<?php echo self::BUILDERS_LOG_TAXONOMY ?> categorychecklist form-no-clear">
                    <?php   foreach($all_terms as $term){
                        $id = self::BUILDERS_LOG_TAXONOMY .'-'.$term->slug;
                        echo "<li id='$id'><label class='selectit'>";
                        echo "<input type='radio' id='in-$id' name='{$input_name}'".checked($current_term_id,$term->term_id,false)."value='$term->slug' />$term->name<br />";
                       echo "</label></li>";
                    }?>
               </ul>
            </div>
     
            <!-- Display popular taxonomy terms -->
            <div id="<?php echo self::BUILDERS_LOG_TAXONOMY; ?>-pop" class="tabs-panel" style="display: none;">
                <ul id="<?php echo self::BUILDERS_LOG_TAXONOMY; ?>checklist-pop" class="categorychecklist form-no-clear" >
                    <?php   foreach($popular_terms as $term){
                        $id = 'popular-'.self::BUILDERS_LOG_TAXONOMY .'-'.$term->slug;
                        echo "<li id='$id'><label class='selectit'>";
                        echo "<input type='radio' id='in-$id'".checked($current_term_id,$term->term_id,false)."value='$term->slug' />$term->name<br />";
                        echo "</label></li>";
                    }?>
                </ul>
            </div>
            <p id="<?php echo self::BUILDERS_LOG_TAXONOMY; ?>-add" class="">
                <label class="screen-reader-text" for="new<?php echo self::BUILDERS_LOG_TAXONOMY; ?>"><?php echo $tax_obj->labels->add_new_item; ?></label>
                <input type="text" name="new<?php echo self::BUILDERS_LOG_TAXONOMY; ?>" id="new<?php echo self::BUILDERS_LOG_TAXONOMY; ?>" class="form-required form-input-tip" value="<?php echo esc_attr( $tax_obj->labels->new_item_name ); ?>" tabindex="3" aria-required="true"/>
                <input type="button" id="" class="radio-tax-add button" value="<?php echo esc_attr( $tax_obj->labels->add_new_item ); ?>" tabindex="3" />
                <?php wp_nonce_field( 'radio-tax-add-'.self::BUILDERS_LOG_TAXONOMY, '_wpnonce_radio-add-tag', false ); ?>
            </p>
     
        </div>
        <?php
    }

    public static function add_build_time_meta_box() {
        add_meta_box(
            'build_time_meta_box',
            __('Time Spent'),
            array(__CLASS__, 'build_time_meta_box'),
            'post','normal','high'
        );
    }
    public static function build_time_meta_box($post) {
        wp_nonce_field('set_build_time', 'set_build_time-nonce', false);

        $total_min = get_post_meta($post->ID, '_build_time_total_minutes', true);
        $hours = '';
        $min = '';
        if ($total_min) {
            $total_min = (int)$total_min;
            $hours = floor($total_min / 60);
            $min = $total_min % 60;
        }
        ?>
        <div class='inside'>
            <p>
                <label>
                    <input type="text" name="build_time_hours" value="<?php echo $hours; ?>" />
                    <?php echo __('hours') . ' ' . __('and'); ?>
                </label>
                <label>
                    <input type="text" name="build_time_minutes" value="<?php echo $min; ?>" />
                    <?php echo __('minutes'); ?>
                </label>
            </p>
        </div>
    <?php
    }
    public function save_build_time_meta_box($post_id) {
        if (!isset($_POST["set_build_time-nonce"]) || !wp_verify_nonce($_POST["set_build_time-nonce"], 'set_build_time')) {
            return $post_id;
        }

        if (!current_user_can("edit_post", $post_id)) {
            return $post_id;
        }

        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return $post_id;
        }

        $time_hours = 0;
        $time_minutes = 0;
        if (isset($_REQUEST['build_time_hours'])) {
            $time_hours = (float)$_REQUEST['build_time_hours'];
        }
        if (isset($_REQUEST['build_time_minutes'])) {
            $time_minutes = (float)$_REQUEST['build_time_minutes'];
        }

        $total_minutes = (int)($time_hours*60 + $time_minutes);
        update_post_meta($post_id, '_build_time_total_minutes', $total_minutes);

        $terms = wp_get_post_terms($post_id, self::BUILDERS_LOG_TAXONOMY);
        foreach ($terms as $term) {
            $this->update_aircraft_section_term_meta($term);
        }
    }

    public static function register_taxonomy_plane_section() {
        $labels = array(
            'name'              => _x('Airplane Sections', 'taxonomy general name'),
            'singular_name'     => _x('Airplane Section', 'taxonomy singular name'),
            'search_items'      => __('Search Sections'),
            'all_items'         => __('All Airplane Sections'),
            'parent_item'       => __('Parent Airplane Section'),
            'parent_item_colon' => __('Parent Airplane Section:'),
            'edit_item'         => __('Edit Airplane Section'),
            'update_item'       => __('Update Airplane Section'),
            'add_new_item'      => __('Add New Airplane Section'),
            'new_item_name'     => __('New Airplane Section Name'),
            'menu_name'         => __('Airplane Sections'),
        );
        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'airplane_section'],
        );
        register_taxonomy(self::BUILDERS_LOG_TAXONOMY, ['post'], $args);
    }

    public static function admin_script() {
        wp_register_script('airplane_section_taxonomy', plugins_url( '/js/airplane_section_taxonomy.js', __FILE__ ), array('jquery'), null, true ); // We specify true here to tell WordPress this script needs to be loaded in the footer
        wp_localize_script('airplane_section_taxonomy', 'airplane_section_taxonomy', array('slug'=>self::BUILDERS_LOG_TAXONOMY));
        wp_enqueue_script('airplane_section_taxonomy');
    }

    public function ajax_add_term() {
        $taxonomy = sanitize_text_field(!empty($_POST['taxonomy']) ? $_POST['taxonomy'] : '');
        $term = sanitize_text_field(!empty($_POST['term']) ? $_POST['term'] : '');
        $tax = get_taxonomy($taxonomy);
        check_ajax_referer('radio-tax-add-'.$taxonomy, '_wpnonce_radio-add-tag');
        if(!$tax || empty($term))
            exit();
        if ( !current_user_can( $tax->cap->edit_terms ) )
            die('-1');
        $term = wp_insert_term($term, $taxonomy);
        if ( !$term || is_wp_error($term) || (!$term = get_term( $term['term_id'], $taxonomy )) ) {
            //TODO Error handling
            exit();
        }
    
        $id = $taxonomy.'-'.$term->slug;
        $name = 'tax_input[' . $taxonomy . ']';
        $html ='<li id="'.$id.'"><label class="selectit"><input type="radio" checked="checked" id="in-'.$id.'" name="'.$name.'" value="'.$term->slug.'" />'. $term->name.'</label></li>';
    
        echo json_encode(array('term'=>$term->term_id,'html'=>$html));
        exit();
    }

    // $term should be a WP_Term objection from get_terms(),
    // which corresponds to an airplane section.
    // This will add up the log time for all posts in that section,
    // then store it.
    private function update_aircraft_section_term_meta($term) {
        $posts = get_posts(array(
            'post_type' => 'post',
            'numberposts' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => $term->taxonomy,
                    'field' => 'term_id',
                    'terms' => $term->term_id,
                    'include_children' => false
                )
            )
        ));
        $total_min = 0;
        foreach ($posts as $post) {
            $min = get_post_meta($post->ID, '_build_time_total_minutes', true);
            if ($min) {
                $total_min += (int)$min;
            }
        }
        update_term_meta($term->term_id, '_build_time_total_minutes', $total_min);
    }
}

$WP_Builders_Log = WP_Builders_Log::getInstance();

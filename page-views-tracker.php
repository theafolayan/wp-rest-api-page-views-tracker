<?php
/*
Plugin Name: REST API Page Views Tracker
Description: This plugin Records page views on WordPress posts, including those fetched from the REST API or WPGraphQL, displays the views in the admin post list, and includes the views in the REST API and WPGraphQL response.
Version: 1.3.1
Author: theafolayan
Author URI: https://twitter.com/theafolayan
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: page-views-tracker
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Add a custom field to track page views.
function pvt_add_custom_field()
{
    register_post_meta('post', 'page_views', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'number',
        'default' => 0,
    ));
}
add_action('init', 'pvt_add_custom_field');

// Function to increment page views.
function pvt_increment_page_views($post_id)
{
    $current_views = get_post_meta($post_id, 'page_views', true);
    $new_views = $current_views + 1;
    update_post_meta($post_id, 'page_views', $new_views);
}

// Increment page views when a post is viewed.
function pvt_track_page_views()
{
    if (is_single()) {
        global $post;
        pvt_increment_page_views($post->ID);
    }
}
add_action('wp_head', 'pvt_track_page_views');

// Increment page views via REST API.
function pvt_rest_increment_page_views($response, $post, $request)
{
    if ($request->get_method() == 'GET') {
        pvt_increment_page_views($post->ID);
    }
    return $response;
}
add_filter('rest_prepare_post', 'pvt_rest_increment_page_views', 10, 3);

// Add a custom column to the post list table.
function pvt_add_views_column($columns)
{
    $columns['page_views'] = 'Page Views';
    return $columns;
}
add_filter('manage_posts_columns', 'pvt_add_views_column');

// Display the page views in the custom column.
function pvt_display_views_column($column, $post_id)
{
    if ($column == 'page_views') {
        $page_views = get_post_meta($post_id, 'page_views', true);
        echo $page_views;
    }
}
add_action('manage_posts_custom_column', 'pvt_display_views_column', 10, 2);

// Make the page views column sortable.
function pvt_sortable_views_column($columns)
{
    $columns['page_views'] = 'page_views';
    return $columns;
}
add_filter('manage_edit-post_sortable_columns', 'pvt_sortable_views_column');

// Sort posts by page views.
function pvt_sort_views_column($query)
{
    if (!is_admin()) {
        return;
    }

    $orderby = $query->get('orderby');

    if ('page_views' == $orderby) {
        $query->set('meta_key', 'page_views');
        $query->set('orderby', 'meta_value_num');
    }
}
add_action('pre_get_posts', 'pvt_sort_views_column');

// Add page views to REST API response.
function pvt_add_views_to_rest_api($data, $post, $context)
{
    $page_views = get_post_meta($post->ID, 'page_views', true);
    $data->data['page_views'] = (int) $page_views;
    return $data;
}
add_filter('rest_prepare_post', 'pvt_add_views_to_rest_api', 10, 3);

// Add page views field to WPGraphQL.
add_action('graphql_register_types', function () {
    register_graphql_field('Post', 'pageViews', [
        'type' => 'Int',
        'description' => __('The number of page views for the post', 'page-views-tracker'),
        'resolve' => function ($post) {
            return (int) get_post_meta($post->ID, 'page_views', true);
        }
    ]);
});

// Increment page views via WPGraphQL.
add_action('graphql_register_types', function () {
    add_filter('graphql_post_object_query', function ($query_args, $source, $input, $type_name, $info) {
        if (isset($input['id'])) {
            $post_id = $input['id'];
            pvt_increment_page_views($post_id);
        }
        return $query_args;
    }, 10, 5);
});

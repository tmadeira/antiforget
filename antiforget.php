<?php
/*
Plugin Name: Admin Anti-forget Alarm
Plugin URI: http://blog.tiagomadeira.com/admin-anti-forget-alarm/
Description: Prevents users from publishing a post without excerpt or thumbnail, or with a too big excerpt, or with a too small thumbnail, or with an uppercase-only title.
Version: 1.0.0
Author: Tiago Madeira
Author URI: http://blog.tiagomadeira.com/
License: GPL3
*/

/*
 *  Copyright 2012 Tiago Madeira (tmadeira@gmail.com)
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License, version 3, as 
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

function af_get_minimum_thumbnail_size() {
    global $_wp_additional_image_sizes;
    $min_width = $_wp_additional_image_sizes['post-thumbnail']['width'];
    $min_height = $_wp_additional_image_sizes['post-thumbnail']['height'];
    return array($min_width, $min_height);
}

function af_get_error_messages() {
    list($min_width, $min_height) = af_get_minimum_thumbnail_size();
    return Array(
        1 => "Você precisa selecionar uma imagem destacada para publicar.",
        2 => "Você precisa escrever um resumo para publicar.",
        3 => "O resumo precisa ter no máximo 350 caracteres.",
        4 => "A imagem destacada precisa ter no mínimo $min_width x $min_height.",
        5 => "O título que você escreveu só tem letras maiúsculas. Se isso for mesmo muito desejado, <em>embora não seja recomendável</em>, você pode clicar em <em>&laquo;Publicar&raquo;</em> novamente para ignorar esta mensagem."
    );
}

function af_can_publish_post($id) {
    global $wpdb;
    $post = get_post($id);

    $error = array();
    $warning = array();

    if (!has_post_thumbnail($post->ID)) {
        $error[] = 1;
    } else {
        $thumb_id = get_post_thumbnail_id($post->ID);
        list($min_width, $min_height) = af_get_minimum_thumbnail_size();
        list($src, $width, $height) = wp_get_attachment_image_src($thumb_id);
        if ($width < $min_width || $height < $min_height) {
            $error[] = 4;
        }
    }
    if ($post->post_excerpt == '') {
        $error[] = 2;
    }
    if (strlen($post->post_excerpt) > 350) {
        $error[] = 3;
    }
    if (strtoupper($post->post_title) == $post->post_title) {
        $warning[] = 5;
    }
    if (count($error) != 0 || (count($warning) != 0 && $_POST["fuck"] != "you")) {
        $wpdb->query("UPDATE {$wpdb->posts} SET post_status = 'draft' WHERE ID = '{$post->ID}'");
        $location = add_query_arg("message", 10, get_edit_post_link($post->ID, 'url'));
        if (count($error)) $location = add_query_arg("error", implode(",", $error), $location);
        if (count($warning)) $location = add_query_arg("warning", implode(",", $warning), $location);
        wp_redirect(apply_filters('redirect_post_location', $location, $post->ID));
        exit;
    }
}

function af_print_messages($messages) {
    if ($_GET["error"] || $_GET["warning"]) {
        $error_messages = af_get_error_messages();
        $messages['post'][10].= "<br /><br /><strong>Não foi possível publicar seu post.</strong> Corrija os seguintes problemas:<br />";
        if ($_GET["error"]) {
            $error = explode(",", $_GET["error"]);
            foreach ($error as $m) {
                $messages['post'][10].= "<br />- " . $error_messages[$m];
            }
        }
        if ($_GET["warning"]) {
            $warning = explode(",", $_GET["warning"]);
            foreach ($warning as $m) {
                $messages['post'][10].= "<br />- " . $error_messages[$m];
            }
        }
    }
    return $messages;
}

function af_print_hidden_input($whatever) {
    if ($_GET["warning"]) {
        $whatever.= '<input type="hidden" name="fuck" value="you" />';
    }
    return $whatever;
}

add_action('publish_post', 'af_can_publish_post', -999, 1);
add_filter('post_updated_messages', 'af_print_messages');

// I know this sucks, but it looks like this is the only filter inside the WP edit form! :(
add_filter('enter_title_here', 'af_print_hidden_input');
?>

<?php
/*
Plugin Name: Grade de Atalhos
Description: Plugin para exibir atalhos organizados por grupos com contagem de cliques.
Version: 1.0
Author: Author: RAFATHS
*/

if (!defined('ABSPATH')) exit;

// CPT e Taxonomia
add_action('init', function () {
    register_post_type('atalho', [
        'labels' => ['name' => 'Atalhos', 'singular_name' => 'Atalho'],
        'public' => true,
        'supports' => ['title', 'editor'],
        'menu_icon' => 'dashicons-admin-links'
    ]);

    register_taxonomy('grupo_atalho', 'atalho', [
        'labels' => ['name' => 'Grupos', 'singular_name' => 'Grupo'],
        'public' => true,
        'hierarchical' => true,
    ]);
});

add_action('admin_head', function () {
    $screen = get_current_screen();
    if ($screen->post_type === 'atalho') {
        echo '<style>#postdivrich { display: none !important; }</style>';
    }
});

// Meta boxes
add_action('add_meta_boxes', function () {
    add_meta_box('ga_dados_atalho', 'Detalhes', function ($post) {
        $f = fn($k) => get_post_meta($post->ID, $k, true);
        ?>
        <label>Link:</label><input type="url" name="ga_link" value="<?= esc_attr($f('_ga_link')) ?>" style="width:100%"><br><br>
        <label>Descrição:</label><textarea name="ga_descricao" style="width:100%" rows="3"><?= esc_textarea($f('_ga_descricao')) ?></textarea><br><br>
        <label>Cor do Fundo:</label><input type="color" name="ga_cor_fundo" value="<?= esc_attr($f('_ga_cor_fundo')) ?>"><br><br>
        <label>Cor do Ícone:</label><input type="color" name="ga_cor_icone" value="<?= esc_attr($f('_ga_cor_icone')) ?>"><br><br>
        <label>SVG (código):</label><textarea name="ga_svg" style="width:100%" rows="5"><?= esc_textarea($f('_ga_svg')) ?></textarea>
        <?php
    }, 'atalho');
});

add_action('save_post', function ($id) {
    foreach (['ga_link', 'ga_descricao', 'ga_cor_fundo', 'ga_cor_icone'] as $key) {
        if (isset($_POST[$key])) {
            update_post_meta($id, "_$key", sanitize_text_field($_POST[$key]));
        }
    }

    // SVG com tratamento especial
    if (isset($_POST['ga_svg'])) {
        $allowed_tags = [
            'svg' => [
                'xmlns' => true, 'width' => true, 'height' => true, 'viewBox' => true, 'fill' => true,
            ],
            'path' => [
                'fill' => true, 'fill-rule' => true, 'clip-rule' => true, 'd' => true,
            ]
        ];
        $svg_clean = wp_kses($_POST['ga_svg'], $allowed_tags);
        update_post_meta($id, '_ga_svg', $svg_clean);
    }
});

// Contador de cliques
add_action('wp_ajax_ga_contador', 'ga_contador');
add_action('wp_ajax_nopriv_ga_contador', 'ga_contador');
function ga_contador() {
    if (isset($_POST['post_id'])) {
        $id = intval($_POST['post_id']);
        $cliques = intval(get_post_meta($id, '_ga_cliques', true));
        update_post_meta($id, '_ga_cliques', $cliques + 1);
    }
    wp_die();
}

// Shortcode
add_shortcode('grade_atalhos', function () {
    wp_enqueue_script('ga-click', plugin_dir_url(__FILE__) . 'js/click-tracker.js', ['jquery'], null, true);
    wp_localize_script('ga-click', 'ga_vars', ['ajax_url' => admin_url('admin-ajax.php')]);

    ob_start();

    echo '<h2>Mais Acessados</h2><div class="ga-grid">';
    $q = new WP_Query([
        'post_type' => 'atalho',
        'posts_per_page' => 9,
        'meta_key' => '_ga_cliques',
        'orderby' => 'meta_value_num',
        'order' => 'DESC'
    ]);
    while ($q->have_posts()) { $q->the_post(); ga_render_atalho(get_the_ID()); }
    wp_reset_postdata();
    echo '</div>';

    foreach (get_terms(['taxonomy' => 'grupo_atalho', 'hide_empty' => false]) as $g) {
        if (get_term_meta($g->term_id, '_ga_grupo_ativo', true) !== '1') continue;
        echo '<h2 style="color:' . esc_attr(get_term_meta($g->term_id, '_ga_grupo_cor', true)) . '">' . esc_html($g->name) . '</h2><div class="ga-grid">';
        $qq = new WP_Query([
            'post_type' => 'atalho',
            'tax_query' => [[ 'taxonomy' => 'grupo_atalho', 'terms' => $g->term_id ]]
        ]);
        while ($qq->have_posts()) { $qq->the_post(); ga_render_atalho(get_the_ID()); }
        echo '</div>';
    }

    return ob_get_clean();
});

function ga_render_atalho($id) {
    $f = fn($k) => get_post_meta($id, $k, true);
    echo '<a href="' . esc_url($f('_ga_link')) . '" class="ga-atalho" data-id="' . $id . '">
        <div class="ga-icon" style="background-color:' . esc_attr($f('_ga_cor_fundo')) . '">
            <div style="color:' . esc_attr($f('_ga_cor_icone')) . '">' . $f('_ga_svg') . '</div>
        </div>
        <div><strong>' . esc_html(get_the_title($id)) . '</strong><br><small>' . esc_html($f('_ga_descricao')) . '</small></div>
    </a>';
}

// Estilos
add_action('wp_head', function () {
    echo '<style>
        .ga-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .ga-atalho { display: flex; align-items: center; gap: 10px; padding: 10px; text-decoration: none; color: #000; background: #fff; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .ga-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
    </style>';
});

// Campos extras na taxonomia
add_action('grupo_atalho_add_form_fields', function () {
    echo '<div class="form-field"><label>Cor do Grupo</label><input type="color" name="ga_grupo_cor" value="#888888"></div>
          <div class="form-field"><label>Ativo?</label><input type="checkbox" name="ga_grupo_ativo" value="1" checked></div>';
});

add_action('grupo_atalho_edit_form_fields', function ($term) {
    $cor = get_term_meta($term->term_id, '_ga_grupo_cor', true);
    $ativo = get_term_meta($term->term_id, '_ga_grupo_ativo', true);
    ?>
    <tr class="form-field"><th><label>Cor do Grupo</label></th><td><input type="color" name="ga_grupo_cor" value="<?= esc_attr($cor) ?>"></td></tr>
    <tr class="form-field"><th><label>Ativo?</label></th><td><input type="checkbox" name="ga_grupo_ativo" value="1" <?= checked($ativo, '1', false) ?>></td></tr>
    <?php
});

add_action('created_grupo_atalho', 'ga_save_term_meta');
add_action('edited_grupo_atalho', 'ga_save_term_meta');
function ga_save_term_meta($term_id) {
    update_term_meta($term_id, '_ga_grupo_cor', sanitize_hex_color($_POST['ga_grupo_cor']));
    update_term_meta($term_id, '_ga_grupo_ativo', isset($_POST['ga_grupo_ativo']) ? '1' : '0');
}

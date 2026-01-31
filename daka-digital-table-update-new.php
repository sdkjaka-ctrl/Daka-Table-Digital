<?php
/**
 * Plugin Name: Daka Table digital
 * Plugin URI: https://dakadigital.my.id
 * Description: Tabel interaktif Fullwide dengan fitur Drag-and-Drop, Styling Elementor Lengkap, dan Menu Settings Detail.
 * Version: 1.2.9
 * Author: Mas Daka
 * Author URI: https://dakadigital.my.id
 * Text Domain: daka-digital
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('DakaTableDigital')) {

class DakaTableDigital {

    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'create_db_table'));
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_init', array($this, 'handle_table_actions'));
        add_action('admin_init', array($this, 'handle_export'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_shortcode('DakaTable', array($this, 'render_shortcode'));
        add_action('elementor/widgets/register', array($this, 'register_daka_widget'));
    }

    public function create_db_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'daka_tables';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            rows_count int(5) DEFAULT 5,
            cols_count int(5) DEFAULT 3,
            data longtext NOT NULL,
            author_id bigint(20) NOT NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'daka-edit-table') !== false) {
            wp_enqueue_script('jquery-ui-sortable');
        }
    }

    public function add_admin_menus() {
        add_menu_page('Daftar Harga', 'Daftar Harga', 'manage_options', 'daka-table', array($this, 'render_admin_page'), 'dashicons-car', 6);
        add_submenu_page('daka-table', 'Semua Table', 'Semua Table', 'manage_options', 'daka-table', array($this, 'render_admin_page'));
        add_submenu_page('daka-table', 'Tambah Baru', 'Tambah Table Baru', 'manage_options', 'daka-add-table', array($this, 'render_admin_page'));
        add_submenu_page('daka-table', 'Settings', 'Settings', 'manage_options', 'daka-settings', array($this, 'render_admin_page'));
        add_submenu_page(null, 'Sunting', 'Sunting', 'manage_options', 'daka-edit-table', array($this, 'render_admin_page'));
    }

    public function handle_table_actions() {
        global $wpdb;
        $db = $wpdb->prefix . 'daka_tables';

        if (isset($_POST['daka_submit_new'])) {
            $r = intval($_POST['rows']); $c = intval($_POST['cols']);
            $wpdb->insert($db, array('name' => sanitize_text_field($_POST['table_name']), 'rows_count' => $r, 'cols_count' => $c, 'data' => json_encode(array_fill(0, $r, array_fill(0, $c, ''))), 'author_id' => get_current_user_id()));
            wp_redirect(admin_url('admin.php?page=daka-table')); exit;
        }

        if (isset($_POST['bulk_action']) && $_POST['bulk_action'] == 'bulk-delete' && isset($_POST['table_ids'])) {
            foreach ($_POST['table_ids'] as $id) { $wpdb->delete($db, array('id' => intval($id))); }
            wp_redirect(admin_url('admin.php?page=daka-table')); exit;
        }

        if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
            $wpdb->delete($db, array('id' => intval($_GET['id'])));
            wp_redirect(admin_url('admin.php?page=daka-table')); exit;
        }
    }

    public function handle_export() {
        if (!isset($_GET['daka_export']) || !isset($_GET['id'])) return;
        global $wpdb;
        $t = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}daka_tables WHERE id = %d", $_GET['id']));
        if (!$t) return;
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename='.sanitize_title($t->name).'.csv');
        $f = fopen('php://output', 'w');
        foreach (json_decode($t->data, true) as $r) { fputcsv($f, $r); }
        fclose($f); exit;
    }

    private function render_header() {
        $cp = isset($_GET['page']) ? $_GET['page'] : 'daka-table';
        ?>
        <style>
            .daka-admin-full { margin-left: -20px; background: #fff; border-bottom: 1px solid #ddd; }
            .daka-top-bar { padding: 20px; border-bottom: 1px solid #eee; }
            .daka-top-bar h1 { margin: 0; font-size: 22px; color: #23282d; display: flex; align-items: center; }
            .daka-tabs-nav { background: #fff; border-bottom: 1px solid #ddd; }
            .daka-tabs-nav a { display: inline-block; padding: 15px 25px; text-decoration: none; color: #555; font-weight: 600; border-right: 1px solid #eee; transition: 0.3s; }
            .daka-tabs-nav a.active { background: #135e23; color: #fff; }
            .daka-notice-info { background: #fff; padding: 25px; border-left: 4px solid #135e23; margin: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .daka-bulk-bar { display: flex; justify-content: space-between; align-items: center; margin: 15px 20px; }
            .daka-fullwide-container { margin: 0 20px; background: #fff; border: 1px solid #ccd0d4; padding: 0 !important; }
            .handle { cursor: move; color: #aaa; }
        </style>
        <div class="daka-admin-full">
            <div class="daka-top-bar">
                <h1><span class="dashicons dashicons-car" style="font-size:30px; width:30px; height:30px; margin-right:10px;"></span> Daka Table digital</h1>
            </div>
            <div class="daka-tabs-nav">
                <a href="?page=daka-table" class="<?php echo ($cp == 'daka-table') ? 'active' : ''; ?>">Semua Table</a>
                <a href="?page=daka-add-table" class="<?php echo ($cp == 'daka-add-table') ? 'active' : ''; ?>">Tambah Table Baru</a>
                <a href="?page=daka-settings" class="<?php echo ($cp == 'daka-settings') ? 'active' : ''; ?>">Settings</a>
            </div>
        </div>
        <?php
    }

    public function render_admin_page() {
        echo '<div class="wrap" style="margin:0; padding:0; max-width:100%;">';
        $this->render_header();
        $page = isset($_GET['page']) ? $_GET['page'] : 'daka-table';
        if ($page == 'daka-table') $this->page_all_tables();
        elseif ($page == 'daka-add-table') $this->page_add_table();
        elseif ($page == 'daka-settings') $this->page_settings();
        elseif ($page == 'daka-edit-table') $this->page_edit_table();
        echo '</div>';
    }

    private function page_all_tables() {
        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}daka_tables ORDER BY id DESC");
        ?>
        <div class="daka-notice-info">
            <h3 style="margin-top:0;">Selamat Datang di Daka Table digital</h3>
            <p style="margin-bottom:0;">terimakasih sudah menggunakan Plugin Daka Table digital dan gunakanlah secara bijak dan bertanggung jawab.</p>
        </div>

        <div style="margin: 20px 20px 10px 20px; display: flex; align-items: center; gap: 15px;">
            <h2 style="margin:0; padding:0;">Daftar Table</h2>
            <a href="?page=daka-add-table" class="page-title-action" style="position:relative; top:0;">Tambah Tabel</a>
        </div>

        <form method="post">
            <div class="daka-bulk-bar">
                <div>
                    <select name="bulk_action"><option value="">Tindakan Massal</option><option value="bulk-delete">Hapus Permanen</option></select>
                    <input type="submit" class="button" value="Terapkan">
                </div>
                <input type="search" placeholder="Cari Table..." name="s">
            </div>

            <div class="daka-fullwide-container">
                <table class="wp-list-table widefat fixed striped" style="border:none;">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column"><input type="checkbox"></td>
                            <th width="5%">ID</th><th>Nama Table</th><th>Short Code</th><th>Penulis</th><th>Terakhir Diubah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($results): foreach ($results as $row): $edit = admin_url('admin.php?page=daka-edit-table&id='.$row->id); ?>
                        <tr>
                            <th scope="row" class="check-column"><input type="checkbox" name="table_ids[]" value="<?php echo $row->id; ?>"></th>
                            <td><?php echo $row->id; ?></td>
                            <td>
                                <strong><a href="<?php echo $edit; ?>"><?php echo esc_html($row->name); ?></a></strong>
                                <div class="row-actions">
                                    <span><a href="<?php echo $edit; ?>">Sunting</a> | </span>
                                    <span><a href="?page=daka-table&action=copy&id=<?php echo $row->id; ?>">Salin</a> | </span>
                                    <span class="trash"><a href="?page=daka-table&action=delete&id=<?php echo $row->id; ?>" style="color:red;" onclick="return confirm('Hapus permanent?')">Hapus</a></span>
                                </div>
                            </td>
                            <td><code>[DakaTable id="<?php echo $row->id; ?>"]</code></td>
                            <td><?php echo get_userdata($row->author_id)->display_name; ?></td>
                            <td><?php echo $row->last_updated; ?></td>
                        </tr>
                        <?php endforeach; else: echo '<tr><td colspan="6" style="padding:20px;">Belum ada tabel.</td></tr>'; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="daka-bulk-bar">
                <select name="bulk_action"><option value="">Tindakan Massal</option><option value="bulk-delete">Hapus Permanen</option></select>
                <input type="submit" class="button" value="Terapkan">
            </div>
        </form>
        <?php
    }

    private function page_add_table() {
        ?>
        <div class="daka-fullwide-container" style="margin: 20px; padding: 25px !important;">
            <h2>Tambah Table Baru</h2>
            <p>untuk membuat table silahkan lengkapi kolom dibawah ini</p>
            <hr style="margin: 20px 0;">
            <form method="post">
                <table class="form-table">
                    <tr><th>Nama Table</th><td><input type="text" name="table_name" class="regular-text" required></td></tr>
                    <tr><th>Jumlah Baris</th><td><input type="number" name="rows" value="5" class="small-text" min="1"></td></tr>
                    <tr><th>Jumlah Kolom</th><td><input type="number" name="cols" value="3" class="small-text" min="1"></td></tr>
                </table>
                <div style="margin-top:20px;"><?php submit_button('Tambahkan Table', 'primary', 'daka_submit_new', false); ?></div>
            </form>
        </div>
        <?php
    }

    private function page_settings() {
        ?>
        <div class="daka-fullwide-container" style="margin: 20px; padding: 30px !important;">
            <h2>Detail Plugin: Daka Table digital</h2>
            <hr style="margin: 20px 0;">
            <table class="form-table">
                <tr><th>Plugin Name</th><td>Daka Table digital</td></tr>
                <tr><th>Version</th><td>1.2.9</td></tr>
                <tr><th>Author</th><td>Mas Daka</td></tr>
                <tr><th>Description</th><td>Sematkan tabel yang indah dan interaktif ke dalam postingan dan halaman situs web WordPress Anda, tanpa perlu menulis kode! Plugin ini mendukung integrasi penuh dengan Elementor Builder.</td></tr>
                <tr><th>Support</th><td><a href="https://dakadigital.my.id" target="_blank">dakadigital.my.id</a></td></tr>
            </table>
            <div style="background: #f9f9f9; padding: 20px; border: 1px solid #eee; margin-top: 30px;">
                <h4>Fitur Utama:</h4>
                <ul>
                    <li>- Manajemen Tabel CRUD (Create, Read, Update, Delete)</li>
                    <li>- Drag-and-Drop baris tabel di admin area</li>
                    <li>- Integrasi Widget Kustom Elementor dengan Kontrol Gaya Lengkap</li>
                    <li>- Fitur Highlight Kolom dan Baris (Zebra/Label)</li>
                    <li>- Ekspor data tabel ke format CSV</li>
                </ul>
            </div>
        </div>
        <?php
    }

    private function page_edit_table() {
        global $wpdb; $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (isset($_POST['save_table'])) {
            $wpdb->update($wpdb->prefix.'daka_tables', array('data' => json_encode($_POST['cell']), 'name' => sanitize_text_field($_POST['table_name'])), array('id' => $id));
            echo '<div class="updated"><p>Perubahan Berhasil Disimpan!</p></div>';
        }
        $t = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}daka_tables WHERE id = %d", $id));
        if (!$t) return;
        $cells = json_decode($t->data, true);
        ?>
        <div style="padding: 20px;">
            <form method="post">
                <div style="margin-bottom:20px;"><?php submit_button('Simpan Perubahan', 'primary', 'save_table', false); ?></div>
                <div class="daka-fullwide-container" style="padding: 20px !important; margin: 0 0 20px 0;">
                    <h2>Informasi Table</h2>
                    <table class="form-table">
                        <tr><th>ID Table</th><td><code><?php echo $t->id; ?></code></td></tr>
                        <tr><th>Shortcode</th><td><code>[DakaTable id="<?php echo $t->id; ?>"]</code></td></tr>
                        <tr><th>Nama Table</th><td><input type="text" name="table_name" value="<?php echo esc_attr($t->name); ?>" class="regular-text"></td></tr>
                    </table>
                </div>
                <div class="daka-fullwide-container" style="padding: 20px !important; margin:0;">
                    <h2>Isi Table</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <tbody id="daka-sortable">
                            <?php foreach ($cells as $r => $rv): ?>
                                <tr>
                                    <td class="handle" width="40px"><span class="dashicons dashicons-menu"></span></td>
                                    <?php for($c=0; $c<$t->cols_count; $c++): ?>
                                        <td><input type="text" name="cell[<?php echo $r; ?>][<?php echo $c; ?>]" value="<?php echo esc_attr($rv[$c] ?? ''); ?>" style="width:100%"></td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
        <script>jQuery(document).ready(function($){ $('#daka-sortable').sortable({handle:'.handle', axis:'y'}); });</script>
        <?php
    }

    public function render_shortcode($atts) {
        global $wpdb;
        $a = shortcode_atts(array('id' => 0), $atts);
        $t = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}daka_tables WHERE id = %d", $a['id']));
        if (!$t) return '';
        $data = json_decode($t->data, true);
        $out = '<div class="daka-table-render"><table class="daka-main-table" style="width:100%; border-collapse:collapse;">';
        foreach ($data as $ri => $rv) {
            $out .= '<tr>';
            foreach ($rv as $cv) { $tag = ($ri === 0) ? 'th' : 'td'; $out .= "<$tag>".do_shortcode($cv)."</$tag>"; }
            $out .= '</tr>';
        }
        return $out . '</table></div>';
    }

    public function register_daka_widget($widgets_manager) {
        if (!did_action('elementor/loaded')) return;
        $daka_widget = new class extends \Elementor\Widget_Base {
            public function get_name() { return 'daka_table_widget'; }
            public function get_title() { return 'Kode Daka Table'; }
            public function get_icon() { return 'eicon-table'; }
            public function get_categories() { return ['general']; }
            protected function register_controls() {
                $this->start_controls_section('c', ['label' => 'Konten']);
                $this->add_control('id_sc', ['label' => 'Shortcode', 'type' => \Elementor\Controls_Manager::TEXT, 'placeholder' => '[DakaTable id="1"]']);
                $this->end_controls_section();

                $this->start_controls_section('s_t', ['label' => 'Table', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
                $this->add_control('h_tog', ['label' => 'Header Aktif', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
                $this->add_responsive_control('w', ['label' => 'Width', 'type' => \Elementor\Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .daka-main-table' => 'width:{{SIZE}}{{UNIT}};']]);
                $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'b', 'selector' => '{{WRAPPER}} .daka-main-table']);
                $this->add_control('r', ['label' => 'Radius', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'selectors' => ['{{WRAPPER}} .daka-main-table' => 'border-radius:{{TOP}}{{UNIT}}...; overflow:hidden;']]);
                $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), ['name' => 'sh', 'selector' => '{{WRAPPER}} .daka-main-table']);
                $this->end_controls_section();

                $this->start_controls_section('s_h', ['label' => 'Head', 'tab' => \Elementor\Controls_Manager::TAB_STYLE, 'condition' => ['h_tog' => 'yes']]);
                $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'h_ty', 'selector' => '{{WRAPPER}} th']);
                $this->add_control('h_c', ['label' => 'Color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} th' => 'color:{{VALUE}};']]);
                $this->add_control('h_bg', ['label' => 'BG Color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} th' => 'background-color:{{VALUE}};']]);
                $this->add_responsive_control('h_p', ['label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'selectors' => ['{{WRAPPER}} th' => 'padding:{{TOP}}{{UNIT}}...']]);
                $this->end_controls_section();

                $this->start_controls_section('s_b', ['label' => 'Body', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
                $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'b_ty', 'selector' => '{{WRAPPER}} td']);
                $this->add_control('b_c', ['label' => 'Text Color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} td' => 'color:{{VALUE}};']]);
                $this->add_control('l_c', ['label' => 'Link Color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} td a' => 'color:{{VALUE}};']]);
                $this->add_control('l_hc', ['label' => 'Link Hover Color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} td a:hover' => 'color:{{VALUE}};']]);
                $this->add_control('b_bg', ['label' => 'BG Color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} td' => 'background-color:{{VALUE}};']]);
                $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'bb', 'selector' => '{{WRAPPER}} td']);
                $this->add_control('h_type', ['label' => 'Highlight', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'none', 'options' => ['none'=>'None','even_row'=>'Even Row','first_col'=>'First Column'], 'prefix_class'=>'daka-hl-']);
                $this->add_control('h_c_hl', ['label' => 'Highlight Color', 'type' => \Elementor\Controls_Manager::COLOR, 'condition' => ['h_type!' => 'none'], 'selectors' => ['{{WRAPPER}}.daka-hl-even_row tr:nth-child(even) td, {{WRAPPER}}.daka-hl-first_col td:first-child' => 'background-color:{{VALUE}} !important;']]);
                $this->add_responsive_control('b_p', ['label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'selectors' => ['{{WRAPPER}} td' => 'padding:{{TOP}}{{UNIT}}...']]);
                $this->end_controls_section();
            }
            protected function render() { $s = $this->get_settings_for_display(); if(!empty($s['id_sc'])) echo do_shortcode($s['id_sc']); }
        };
        $widgets_manager->register($daka_widget);
    }
}
new DakaTableDigital();
}

<?php
/**
 * Plugin Name: SM Správa Rolí & Admin Menu
 * Plugin URI: https://developer.suspended.sk
 * Description: Vytvorte vlastné používateľské role, skryte položky admin menu pre konkrétne role a vypnite updaty. Kompletná správa prístupov pre WordPress admin.
 * Version: 1.0.0
 * Author: Samuel Marcinko
 * Author URI: https://developer.suspended.sk
 * Text Domain: sm-custom-roles
 * Domain Path: /languages
 * License: GPLv2 or later
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SM_Custom_Roles {

    const OPTION_ROLES       = 'sm_cr_custom_roles';
    const OPTION_HIDDEN_MENU = 'sm_cr_hidden_menu';
    const OPTION_HIDE_UPDATES = 'sm_cr_hide_updates';
    const OPTION_HIDE_ADMIN_BAR = 'sm_cr_hide_admin_bar';

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
        add_action( 'admin_menu', array( $this, 'hide_menu_items' ), 9999 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'maybe_hide_updates' ) );
        add_action( 'admin_head', array( $this, 'admin_custom_css' ) );
        add_action( 'wp_ajax_sm_cr_get_menu_items', array( $this, 'ajax_get_menu_items' ) );

        // Deaktivácia - upratanie
        register_deactivation_hook( __FILE__, array( $this, 'plugin_deactivation' ) );
    }

    /**
     * Registrácia admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Správa Rolí',
            'Správa Rolí',
            'manage_options',
            'sm-custom-roles',
            array( $this, 'render_main_page' ),
            'dashicons-admin-users',
            71
        );

        add_submenu_page(
            'sm-custom-roles',
            'Vlastné Role',
            'Vlastné Role',
            'manage_options',
            'sm-custom-roles',
            array( $this, 'render_main_page' )
        );

        add_submenu_page(
            'sm-custom-roles',
            'Skrytie Menu',
            'Skrytie Menu',
            'manage_options',
            'sm-custom-roles-menu',
            array( $this, 'render_menu_page' )
        );

        add_submenu_page(
            'sm-custom-roles',
            'Nastavenia',
            'Nastavenia',
            'manage_options',
            'sm-custom-roles-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue CSS a JS
     */
    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'sm-custom-roles' ) === false ) {
            return;
        }
        wp_enqueue_style(
            'sm-cr-admin',
            false
        );
        // Inline CSS
        wp_add_inline_style( 'sm-cr-admin', $this->get_inline_css() );
    }

    /**
     * Admin CSS pre plugin stránky
     */
    public function admin_custom_css() {
        $screen = get_current_screen();
        if ( $screen && strpos( $screen->id, 'sm-custom-roles' ) !== false ) {
            echo '<style>' . $this->get_inline_css() . '</style>';
        }
    }

    private function get_inline_css() {
        return '
        .sm-cr-wrap { max-width: 900px; }
        .sm-cr-wrap h1 { margin-bottom: 20px; }
        .sm-cr-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .sm-cr-card h2 { margin-top: 0; }
        .sm-cr-table { width: 100%; border-collapse: collapse; }
        .sm-cr-table th, .sm-cr-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e4e7;
        }
        .sm-cr-table th { background: #f8f9fa; font-weight: 600; }
        .sm-cr-caps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 6px;
            margin: 10px 0;
        }
        .sm-cr-caps-grid label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 13px;
        }
        .sm-cr-caps-grid label:hover { background: #f0f0f1; }
        .sm-cr-menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 8px;
            margin: 15px 0;
        }
        .sm-cr-menu-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 1px solid #e2e4e7;
            border-radius: 4px;
            font-size: 13px;
        }
        .sm-cr-menu-item:hover { background: #f8f9fa; }
        .sm-cr-menu-item.is-submenu { padding-left: 30px; font-size: 12px; border-style: dashed; }
        .sm-cr-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
        .sm-cr-badge-custom { background: #dff0d8; color: #3c763d; }
        .sm-cr-badge-builtin { background: #d9edf7; color: #31708f; }
        .sm-cr-section-title {
            font-size: 14px;
            font-weight: 600;
            margin: 15px 0 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #0073aa;
            color: #0073aa;
        }
        .sm-cr-role-select { min-width: 200px; padding: 6px 10px; }
        .sm-cr-actions { display: flex; gap: 8px; }
        .sm-cr-actions .button { margin: 0; }
        ';
    }

    /**
     * Spracovanie formulárov
     */
    public function handle_form_submissions() {
        // Vytvorenie novej role
        if ( isset( $_POST['sm_cr_create_role'] ) && check_admin_referer( 'sm_cr_create_role_nonce' ) ) {
            $this->create_role();
        }

        // Úprava role
        if ( isset( $_POST['sm_cr_edit_role'] ) && check_admin_referer( 'sm_cr_edit_role_nonce' ) ) {
            $this->edit_role();
        }

        // Vymazanie role
        if ( isset( $_GET['sm_cr_delete_role'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'sm_cr_delete_role' ) ) {
                $this->delete_role( sanitize_text_field( wp_unslash( $_GET['sm_cr_delete_role'] ) ) );
            }
        }

        // Uloženie nastavení skrytia menu
        if ( isset( $_POST['sm_cr_save_hidden_menu'] ) && check_admin_referer( 'sm_cr_hidden_menu_nonce' ) ) {
            $this->save_hidden_menu();
        }

        // Uloženie nastavení
        if ( isset( $_POST['sm_cr_save_settings'] ) && check_admin_referer( 'sm_cr_settings_nonce' ) ) {
            $this->save_settings();
        }
    }

    /**
     * Získanie všetkých WordPress schopností
     */
    private function get_all_capabilities() {
        global $wp_roles;
        $caps = array();
        foreach ( $wp_roles->roles as $role ) {
            $caps = array_merge( $caps, array_keys( $role['capabilities'] ) );
        }
        $caps = array_unique( $caps );
        sort( $caps );
        return $caps;
    }

    /**
     * Zoskupenie schopností podľa kategórie
     */
    private function get_grouped_capabilities() {
        $all_caps = $this->get_all_capabilities();
        $groups = array(
            'Príspevky'       => array(),
            'Stránky'         => array(),
            'Médiá'           => array(),
            'Používatelia'    => array(),
            'Pluginy'         => array(),
            'Témy (Themes)'   => array(),
            'Nastavenia'      => array(),
            'WooCommerce'     => array(),
            'Ostatné'         => array(),
        );

        foreach ( $all_caps as $cap ) {
            if ( preg_match( '/(post|posts)/', $cap ) ) {
                $groups['Príspevky'][] = $cap;
            } elseif ( preg_match( '/(page|pages)/', $cap ) ) {
                $groups['Stránky'][] = $cap;
            } elseif ( preg_match( '/(upload|media|file)/', $cap ) ) {
                $groups['Médiá'][] = $cap;
            } elseif ( preg_match( '/(user|users|role)/', $cap ) ) {
                $groups['Používatelia'][] = $cap;
            } elseif ( preg_match( '/(plugin|plugins|activate_plugins)/', $cap ) ) {
                $groups['Pluginy'][] = $cap;
            } elseif ( preg_match( '/(theme|themes|switch_themes|edit_theme)/', $cap ) ) {
                $groups['Témy (Themes)'][] = $cap;
            } elseif ( preg_match( '/(option|settings|manage_options)/', $cap ) ) {
                $groups['Nastavenia'][] = $cap;
            } elseif ( preg_match( '/(shop|product|order|coupon|woocommerce)/', $cap ) ) {
                $groups['WooCommerce'][] = $cap;
            } else {
                $groups['Ostatné'][] = $cap;
            }
        }

        // Odstrániť prázdne skupiny
        return array_filter( $groups );
    }

    /**
     * Vytvorenie novej role
     */
    private function create_role() {
        $role_slug = sanitize_key( wp_unslash( $_POST['role_slug'] ?? '' ) );
        $role_name = sanitize_text_field( wp_unslash( $_POST['role_name'] ?? '' ) );
        $clone_from = sanitize_text_field( wp_unslash( $_POST['clone_from'] ?? '' ) );
        $selected_caps = isset( $_POST['capabilities'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['capabilities'] ) ) : array();

        if ( empty( $role_slug ) || empty( $role_name ) ) {
            add_settings_error( 'sm_cr_messages', 'sm_cr_error', 'Musíte vyplniť identifikátor aj názov role.', 'error' );
            return;
        }

        // Kontrola či rola už existuje
        if ( get_role( $role_slug ) ) {
            add_settings_error( 'sm_cr_messages', 'sm_cr_error', 'Rola s týmto identifikátorom už existuje.', 'error' );
            return;
        }

        $capabilities = array();

        // Ak klonujeme z existujúcej role
        if ( ! empty( $clone_from ) && $clone_from !== 'none' ) {
            $source_role = get_role( $clone_from );
            if ( $source_role ) {
                $capabilities = $source_role->capabilities;
            }
        }

        // Pridanie vybraných schopností
        foreach ( $selected_caps as $cap ) {
            $capabilities[ $cap ] = true;
        }

        // Pridanie základnej schopnosti read
        $capabilities['read'] = true;

        add_role( $role_slug, $role_name, $capabilities );

        // Uloženie do zoznamu vlastných rolí
        $custom_roles = get_option( self::OPTION_ROLES, array() );
        $custom_roles[ $role_slug ] = $role_name;
        update_option( self::OPTION_ROLES, $custom_roles );

        add_settings_error( 'sm_cr_messages', 'sm_cr_success', 'Rola "' . esc_html( $role_name ) . '" bola úspešne vytvorená.', 'success' );
    }

    /**
     * Úprava existujúcej role
     */
    private function edit_role() {
        $role_slug = sanitize_key( wp_unslash( $_POST['edit_role_slug'] ?? '' ) );
        $selected_caps = isset( $_POST['capabilities'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['capabilities'] ) ) : array();

        if ( empty( $role_slug ) ) {
            return;
        }

        $role = get_role( $role_slug );
        if ( ! $role ) {
            add_settings_error( 'sm_cr_messages', 'sm_cr_error', 'Rola nebola nájdená.', 'error' );
            return;
        }

        // Nepovoliť úpravu roly administrator
        if ( $role_slug === 'administrator' ) {
            add_settings_error( 'sm_cr_messages', 'sm_cr_error', 'Rolu administrátora nie je možné upravovať.', 'error' );
            return;
        }

        $all_caps = $this->get_all_capabilities();

        // Odstrániť všetky existujúce schopnosti
        foreach ( $all_caps as $cap ) {
            $role->remove_cap( $cap );
        }

        // Pridať vybrané schopnosti
        $role->add_cap( 'read' ); // Vždy povoliť read
        foreach ( $selected_caps as $cap ) {
            $role->add_cap( $cap );
        }

        add_settings_error( 'sm_cr_messages', 'sm_cr_success', 'Schopnosti role boli úspešne aktualizované.', 'success' );
    }

    /**
     * Vymazanie role
     */
    private function delete_role( $role_slug ) {
        if ( in_array( $role_slug, array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ), true ) ) {
            add_settings_error( 'sm_cr_messages', 'sm_cr_error', 'Vstavaná rola nemôže byť vymazaná.', 'error' );
            return;
        }

        // Kontrola či rolu niekto používa
        $users = get_users( array( 'role' => $role_slug ) );
        if ( ! empty( $users ) ) {
            add_settings_error( 'sm_cr_messages', 'sm_cr_error', 'Rolu nemožno vymazať - je priradená ' . count( $users ) . ' používateľom. Najprv im zmeňte rolu.', 'error' );
            return;
        }

        remove_role( $role_slug );

        $custom_roles = get_option( self::OPTION_ROLES, array() );
        unset( $custom_roles[ $role_slug ] );
        update_option( self::OPTION_ROLES, $custom_roles );

        // Vymazať aj nastavenia menu pre túto rolu
        $hidden_menu = get_option( self::OPTION_HIDDEN_MENU, array() );
        unset( $hidden_menu[ $role_slug ] );
        update_option( self::OPTION_HIDDEN_MENU, $hidden_menu );

        add_settings_error( 'sm_cr_messages', 'sm_cr_success', 'Rola bola úspešne vymazaná.', 'success' );
    }

    /**
     * Uloženie skrytých menu položiek
     */
    private function save_hidden_menu() {
        $role_slug = sanitize_text_field( wp_unslash( $_POST['role_for_menu'] ?? '' ) );
        $hidden_items = isset( $_POST['hidden_menu'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['hidden_menu'] ) ) : array();
        $hidden_submenu = isset( $_POST['hidden_submenu'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['hidden_submenu'] ) ) : array();

        if ( empty( $role_slug ) ) {
            return;
        }

        $all_hidden = get_option( self::OPTION_HIDDEN_MENU, array() );
        $all_hidden[ $role_slug ] = array(
            'menu'    => $hidden_items,
            'submenu' => $hidden_submenu,
        );
        update_option( self::OPTION_HIDDEN_MENU, $all_hidden );

        add_settings_error( 'sm_cr_messages', 'sm_cr_success', 'Nastavenia menu boli úspešne uložené.', 'success' );
    }

    /**
     * Uloženie všeobecných nastavení
     */
    private function save_settings() {
        $hide_updates = isset( $_POST['hide_updates'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['hide_updates'] ) ) : array();
        $hide_admin_bar = isset( $_POST['hide_admin_bar'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['hide_admin_bar'] ) ) : array();

        update_option( self::OPTION_HIDE_UPDATES, $hide_updates );
        update_option( self::OPTION_HIDE_ADMIN_BAR, $hide_admin_bar );

        add_settings_error( 'sm_cr_messages', 'sm_cr_success', 'Nastavenia boli úspešne uložené.', 'success' );
    }

    /**
     * Skrytie položiek menu podľa role aktuálneho používateľa
     */
    public function hide_menu_items() {
        if ( current_user_can( 'manage_options' ) ) {
            // Admin vidí všetko (ak nie je admin rola v nastaveniach)
            $current_user = wp_get_current_user();
            if ( in_array( 'administrator', $current_user->roles, true ) ) {
                return;
            }
        }

        $current_user = wp_get_current_user();
        $hidden_menu = get_option( self::OPTION_HIDDEN_MENU, array() );

        foreach ( $current_user->roles as $role ) {
            if ( isset( $hidden_menu[ $role ] ) ) {
                $config = $hidden_menu[ $role ];

                // Skrytie hlavných menu položiek
                if ( ! empty( $config['menu'] ) ) {
                    foreach ( $config['menu'] as $menu_slug ) {
                        remove_menu_page( $menu_slug );
                    }
                }

                // Skrytie submenu položiek
                if ( ! empty( $config['submenu'] ) ) {
                    foreach ( $config['submenu'] as $submenu_item ) {
                        $parts = explode( '||', $submenu_item );
                        if ( count( $parts ) === 2 ) {
                            remove_submenu_page( $parts[0], $parts[1] );
                        }
                    }
                }
            }
        }
    }

    /**
     * Skrytie updatov pre dané role
     */
    public function maybe_hide_updates() {
        $hide_updates_roles = get_option( self::OPTION_HIDE_UPDATES, array() );
        if ( empty( $hide_updates_roles ) ) {
            return;
        }

        $current_user = wp_get_current_user();
        $should_hide = false;

        foreach ( $current_user->roles as $role ) {
            if ( in_array( $role, $hide_updates_roles, true ) ) {
                $should_hide = true;
                break;
            }
        }

        if ( $should_hide ) {
            // Skrytie update notifikácií
            remove_action( 'admin_notices', 'update_nag', 3 );
            remove_action( 'admin_notices', 'maintenance_nag', 10 );
            add_filter( 'pre_site_transient_update_core', '__return_null' );
            add_filter( 'pre_site_transient_update_plugins', '__return_null' );
            add_filter( 'pre_site_transient_update_themes', '__return_null' );

            // Skrytie menu item "Aktualizácie"
            remove_submenu_page( 'index.php', 'update-core.php' );

            // Skrytie update počtu v menu
            add_action( 'admin_head', function() {
                echo '<style>
                    .update-plugins, .update-count, #wp-admin-bar-updates { display: none !important; }
                    .plugin-update-tr { display: none !important; }
                </style>';
            });
        }
    }

    /**
     * AJAX - získanie menu položiek
     */
    public function ajax_get_menu_items() {
        check_ajax_referer( 'sm_cr_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Nedostatočné oprávnenia.' );
        }

        global $menu, $submenu;

        $items = array();
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( empty( $item[0] ) || $item[2] === 'sm-custom-roles' ) {
                    continue;
                }
                $label = wp_strip_all_tags( $item[0] );
                $items[] = array(
                    'type'   => 'menu',
                    'slug'   => $item[2],
                    'label'  => $label,
                    'icon'   => $item[6] ?? '',
                );

                // Submenu
                if ( isset( $submenu[ $item[2] ] ) ) {
                    foreach ( $submenu[ $item[2] ] as $sub_item ) {
                        $sub_label = wp_strip_all_tags( $sub_item[0] );
                        $items[] = array(
                            'type'        => 'submenu',
                            'parent_slug' => $item[2],
                            'slug'        => $sub_item[2],
                            'label'       => $sub_label,
                            'parent_label' => $label,
                        );
                    }
                }
            }
        }

        wp_send_json_success( $items );
    }

    /**
     * Získanie menu položiek (server-side)
     */
    private function get_admin_menu_items() {
        global $menu, $submenu;

        $items = array();
        if ( ! is_array( $menu ) ) {
            return $items;
        }

        foreach ( $menu as $item ) {
            if ( empty( $item[0] ) ) {
                continue;
            }
            // Preskočiť samého seba
            if ( $item[2] === 'sm-custom-roles' || $item[2] === 'sm-custom-roles-menu' || $item[2] === 'sm-custom-roles-settings' ) {
                continue;
            }

            $label = wp_strip_all_tags( $item[0] );
            $items[] = array(
                'type'  => 'menu',
                'slug'  => $item[2],
                'label' => $label,
            );

            if ( isset( $submenu[ $item[2] ] ) ) {
                foreach ( $submenu[ $item[2] ] as $sub_item ) {
                    $sub_label = wp_strip_all_tags( $sub_item[0] );
                    if ( empty( $sub_label ) ) {
                        continue;
                    }
                    $items[] = array(
                        'type'         => 'submenu',
                        'parent_slug'  => $item[2],
                        'slug'         => $sub_item[2],
                        'label'        => $sub_label,
                        'parent_label' => $label,
                    );
                }
            }
        }

        return $items;
    }

    /**
     * Získanie všetkých rolí
     */
    private function get_all_roles() {
        global $wp_roles;
        $roles = array();
        foreach ( $wp_roles->roles as $slug => $role ) {
            $roles[ $slug ] = $role['name'];
        }
        return $roles;
    }

    /**
     * Získanie editovateľných rolí (bez administrátora)
     */
    private function get_editable_roles() {
        $roles = $this->get_all_roles();
        unset( $roles['administrator'] );
        return $roles;
    }

    // =========================================================================
    // RENDER STRÁNOK
    // =========================================================================

    /**
     * Hlavná stránka - Správa rolí
     */
    public function render_main_page() {
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'list';
        $custom_roles = get_option( self::OPTION_ROLES, array() );

        echo '<div class="wrap sm-cr-wrap">';
        echo '<h1>Správa Používateľských Rolí</h1>';

        settings_errors( 'sm_cr_messages' );

        // Navigácia tabov
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=sm-custom-roles&tab=list' ) ) . '" class="nav-tab ' . ( $tab === 'list' ? 'nav-tab-active' : '' ) . '">Zoznam Rolí</a>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=sm-custom-roles&tab=create' ) ) . '" class="nav-tab ' . ( $tab === 'create' ? 'nav-tab-active' : '' ) . '">Vytvoriť Rolu</a>';
        if ( $tab === 'edit' ) {
            echo '<a href="#" class="nav-tab nav-tab-active">Upraviť Rolu</a>';
        }
        echo '</h2>';

        switch ( $tab ) {
            case 'create':
                $this->render_create_role_form();
                break;
            case 'edit':
                $this->render_edit_role_form();
                break;
            default:
                $this->render_roles_list( $custom_roles );
                break;
        }

        echo '</div>';
    }

    /**
     * Zoznam rolí
     */
    private function render_roles_list( $custom_roles ) {
        global $wp_roles;
        $builtin = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

        echo '<div class="sm-cr-card">';
        echo '<h2>Prehľad Všetkých Rolí</h2>';
        echo '<table class="sm-cr-table">';
        echo '<thead><tr>';
        echo '<th>Názov Role</th>';
        echo '<th>Identifikátor</th>';
        echo '<th>Typ</th>';
        echo '<th>Počet Schopností</th>';
        echo '<th>Používatelia</th>';
        echo '<th>Akcie</th>';
        echo '</tr></thead><tbody>';

        foreach ( $wp_roles->roles as $slug => $role ) {
            $is_custom = isset( $custom_roles[ $slug ] );
            $is_builtin_role = in_array( $slug, $builtin, true );
            $user_count = count( get_users( array( 'role' => $slug ) ) );
            $cap_count = count( array_filter( $role['capabilities'] ) );

            echo '<tr>';
            echo '<td><strong>' . esc_html( $role['name'] ) . '</strong></td>';
            echo '<td><code>' . esc_html( $slug ) . '</code></td>';
            echo '<td>';
            if ( $is_custom ) {
                echo '<span class="sm-cr-badge sm-cr-badge-custom">Vlastná</span>';
            } elseif ( $is_builtin_role ) {
                echo '<span class="sm-cr-badge sm-cr-badge-builtin">Vstavaná</span>';
            } else {
                echo '<span class="sm-cr-badge" style="background:#fcf8e3;color:#8a6d3b;">Plugin</span>';
            }
            echo '</td>';
            echo '<td>' . (int) $cap_count . '</td>';
            echo '<td>' . (int) $user_count . '</td>';
            echo '<td class="sm-cr-actions">';

            if ( $slug !== 'administrator' ) {
                $edit_url = admin_url( 'admin.php?page=sm-custom-roles&tab=edit&role=' . urlencode( $slug ) );
                echo '<a href="' . esc_url( $edit_url ) . '" class="button button-small">Upraviť</a>';
            }

            if ( ! $is_builtin_role && $slug !== 'administrator' ) {
                $delete_url = wp_nonce_url(
                    admin_url( 'admin.php?page=sm-custom-roles&sm_cr_delete_role=' . urlencode( $slug ) ),
                    'sm_cr_delete_role'
                );
                echo '<a href="' . esc_url( $delete_url ) . '" class="button button-small" style="color:#a00;" onclick="return confirm(\'Naozaj chcete vymazať túto rolu?\');">Vymazať</a>';
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Formulár na vytvorenie role
     */
    private function render_create_role_form() {
        $grouped_caps = $this->get_grouped_capabilities();
        $all_roles = $this->get_all_roles();

        echo '<div class="sm-cr-card">';
        echo '<h2>Vytvoriť Novú Rolu</h2>';
        echo '<form method="post">';
        wp_nonce_field( 'sm_cr_create_role_nonce' );

        echo '<table class="form-table">';

        echo '<tr><th><label for="role_name">Názov Role</label></th>';
        echo '<td><input type="text" id="role_name" name="role_name" class="regular-text" required placeholder="napr. Správca Obsahu" /></td></tr>';

        echo '<tr><th><label for="role_slug">Identifikátor (slug)</label></th>';
        echo '<td><input type="text" id="role_slug" name="role_slug" class="regular-text" required placeholder="napr. spravca_obsahu" pattern="[a-z0-9_]+" />';
        echo '<p class="description">Len malé písmená, čísla a podčiarkovník. Nedá sa neskôr zmeniť.</p></td></tr>';

        echo '<tr><th><label for="clone_from">Klonovať z existujúcej role</label></th>';
        echo '<td><select id="clone_from" name="clone_from" class="sm-cr-role-select">';
        echo '<option value="none">-- Žiadna (prázdna rola) --</option>';
        foreach ( $all_roles as $slug => $name ) {
            echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $name ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Voliteľné - nová rola zdedí všetky schopnosti vybranej role.</p></td></tr>';

        echo '</table>';

        echo '<h3 class="sm-cr-section-title">Schopnosti (Capabilities)</h3>';
        echo '<p class="description">Zaškrtnite schopnosti, ktoré chcete tejto role priradiť. Ak ste zvolili klonovanie, tieto sa pridajú k skopírovaným.</p>';

        foreach ( $grouped_caps as $group_name => $caps ) {
            echo '<h4 style="margin: 15px 0 5px; color: #23282d;">' . esc_html( $group_name ) . '</h4>';
            echo '<div class="sm-cr-caps-grid">';
            foreach ( $caps as $cap ) {
                echo '<label><input type="checkbox" name="capabilities[]" value="' . esc_attr( $cap ) . '" /> ' . esc_html( $cap ) . '</label>';
            }
            echo '</div>';
        }

        echo '<p style="margin-top: 20px;">';
        submit_button( 'Vytvoriť Rolu', 'primary', 'sm_cr_create_role', false );
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=sm-custom-roles' ) ) . '" class="button">Zrušiť</a>';
        echo '</p>';

        echo '</form>';
        echo '</div>';
    }

    /**
     * Formulár na úpravu role
     */
    private function render_edit_role_form() {
        $role_slug = isset( $_GET['role'] ) ? sanitize_text_field( wp_unslash( $_GET['role'] ) ) : '';
        $role = get_role( $role_slug );

        if ( ! $role || $role_slug === 'administrator' ) {
            echo '<div class="notice notice-error"><p>Rola nebola nájdená alebo ju nie je možné upravovať.</p></div>';
            return;
        }

        global $wp_roles;
        $role_name = $wp_roles->roles[ $role_slug ]['name'] ?? $role_slug;
        $role_caps = array_keys( array_filter( $role->capabilities ) );
        $grouped_caps = $this->get_grouped_capabilities();

        echo '<div class="sm-cr-card">';
        echo '<h2>Úprava Role: ' . esc_html( $role_name ) . ' <code>(' . esc_html( $role_slug ) . ')</code></h2>';
        echo '<form method="post">';
        wp_nonce_field( 'sm_cr_edit_role_nonce' );
        echo '<input type="hidden" name="edit_role_slug" value="' . esc_attr( $role_slug ) . '" />';

        echo '<div style="margin-bottom: 15px;">';
        echo '<button type="button" class="button" onclick="smCrSelectAll(true)">Vybrať Všetko</button> ';
        echo '<button type="button" class="button" onclick="smCrSelectAll(false)">Zrušiť Výber</button>';
        echo '</div>';

        foreach ( $grouped_caps as $group_name => $caps ) {
            echo '<h4 class="sm-cr-section-title">' . esc_html( $group_name ) . '</h4>';
            echo '<div class="sm-cr-caps-grid">';
            foreach ( $caps as $cap ) {
                $checked = in_array( $cap, $role_caps, true ) ? 'checked' : '';
                echo '<label><input type="checkbox" name="capabilities[]" value="' . esc_attr( $cap ) . '" ' . $checked . ' /> ' . esc_html( $cap ) . '</label>';
            }
            echo '</div>';
        }

        echo '<p style="margin-top: 20px;">';
        submit_button( 'Uložiť Zmeny', 'primary', 'sm_cr_edit_role', false );
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=sm-custom-roles' ) ) . '" class="button">Späť na Zoznam</a>';
        echo '</p>';

        echo '</form>';
        echo '</div>';

        // JavaScript
        echo '<script>
        function smCrSelectAll(check) {
            var boxes = document.querySelectorAll("input[name=\'capabilities[]\']");
            boxes.forEach(function(cb) { cb.checked = check; });
        }
        </script>';
    }

    /**
     * Stránka Skrytie Menu
     */
    public function render_menu_page() {
        $roles = $this->get_editable_roles();
        $hidden_menu = get_option( self::OPTION_HIDDEN_MENU, array() );
        $selected_role = isset( $_GET['role'] ) ? sanitize_text_field( wp_unslash( $_GET['role'] ) ) : '';
        $menu_items = $this->get_admin_menu_items();

        echo '<div class="wrap sm-cr-wrap">';
        echo '<h1>Skrytie Položiek Admin Menu</h1>';
        echo '<p class="description">Vyberte rolu a označte položky menu, ktoré chcete pre danú rolu skryť. Administrátorská rola vždy vidí všetko.</p>';

        settings_errors( 'sm_cr_messages' );

        // Výber role
        echo '<div class="sm-cr-card">';
        echo '<h2>Vybrať Rolu</h2>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="sm-custom-roles-menu" />';
        echo '<select name="role" class="sm-cr-role-select" onchange="this.form.submit()">';
        echo '<option value="">-- Vyberte rolu --</option>';
        foreach ( $roles as $slug => $name ) {
            $sel = selected( $selected_role, $slug, false );
            echo '<option value="' . esc_attr( $slug ) . '"' . $sel . '>' . esc_html( $name ) . '</option>';
        }
        echo '</select>';
        echo '</form>';
        echo '</div>';

        // Ak je vybraná rola, zobraziť menu položky
        if ( ! empty( $selected_role ) && isset( $roles[ $selected_role ] ) ) {
            $role_hidden = $hidden_menu[ $selected_role ] ?? array( 'menu' => array(), 'submenu' => array() );

            echo '<div class="sm-cr-card">';
            echo '<h2>Menu Položky pre Rolu: <strong>' . esc_html( $roles[ $selected_role ] ) . '</strong></h2>';
            echo '<p class="description">Zaškrtnuté položky budú <strong>skryté</strong> pre túto rolu.</p>';

            echo '<form method="post">';
            wp_nonce_field( 'sm_cr_hidden_menu_nonce' );
            echo '<input type="hidden" name="role_for_menu" value="' . esc_attr( $selected_role ) . '" />';

            echo '<div style="margin-bottom: 15px;">';
            echo '<button type="button" class="button" onclick="smCrMenuSelectAll(true)">Skryť Všetko</button> ';
            echo '<button type="button" class="button" onclick="smCrMenuSelectAll(false)">Zobraziť Všetko</button>';
            echo '</div>';

            if ( ! empty( $menu_items ) ) {
                echo '<div class="sm-cr-menu-grid">';
                foreach ( $menu_items as $item ) {
                    if ( $item['type'] === 'menu' ) {
                        $is_hidden = in_array( $item['slug'], $role_hidden['menu'] ?? array(), true );
                        echo '<div class="sm-cr-menu-item">';
                        echo '<label style="display:flex;align-items:center;gap:8px;width:100%;cursor:pointer;">';
                        echo '<input type="checkbox" name="hidden_menu[]" value="' . esc_attr( $item['slug'] ) . '" ' . checked( $is_hidden, true, false ) . ' />';
                        echo '<span class="dashicons dashicons-menu" style="color:#999;"></span>';
                        echo '<strong>' . esc_html( $item['label'] ) . '</strong>';
                        echo '</label>';
                        echo '</div>';
                    } else {
                        $submenu_key = $item['parent_slug'] . '||' . $item['slug'];
                        $is_hidden = in_array( $submenu_key, $role_hidden['submenu'] ?? array(), true );
                        echo '<div class="sm-cr-menu-item is-submenu">';
                        echo '<label style="display:flex;align-items:center;gap:8px;width:100%;cursor:pointer;">';
                        echo '<input type="checkbox" name="hidden_submenu[]" value="' . esc_attr( $submenu_key ) . '" ' . checked( $is_hidden, true, false ) . ' />';
                        echo '<span style="color:#999;">&#8627;</span> ';
                        echo esc_html( $item['label'] );
                        echo ' <small style="color:#999;">(' . esc_html( $item['parent_label'] ) . ')</small>';
                        echo '</label>';
                        echo '</div>';
                    }
                }
                echo '</div>';
            } else {
                echo '<p><em>Žiadne položky menu neboli nájdené. Uistite sa, že ste na stránke WordPress admin.</em></p>';
            }

            echo '<p style="margin-top: 20px;">';
            submit_button( 'Uložiť Nastavenia Menu', 'primary', 'sm_cr_save_hidden_menu', false );
            echo '</p>';

            echo '</form>';
            echo '</div>';
        }

        echo '</div>';

        echo '<script>
        function smCrMenuSelectAll(check) {
            var boxes = document.querySelectorAll("input[name=\'hidden_menu[]\'], input[name=\'hidden_submenu[]\']");
            boxes.forEach(function(cb) { cb.checked = check; });
        }
        </script>';
    }

    /**
     * Stránka Nastavenia
     */
    public function render_settings_page() {
        $roles = $this->get_editable_roles();
        $hide_updates_roles = get_option( self::OPTION_HIDE_UPDATES, array() );
        $hide_admin_bar_roles = get_option( self::OPTION_HIDE_ADMIN_BAR, array() );

        echo '<div class="wrap sm-cr-wrap">';
        echo '<h1>Nastavenia Správy Rolí</h1>';

        settings_errors( 'sm_cr_messages' );

        echo '<form method="post">';
        wp_nonce_field( 'sm_cr_settings_nonce' );

        // Sekcia: Skrytie updatov
        echo '<div class="sm-cr-card">';
        echo '<h2>Skrytie Aktualizácií (Updatov)</h2>';
        echo '<p class="description">Vyberte role, pre ktoré chcete skryť notifikácie o aktualizáciách WordPress, pluginov a tém.</p>';
        echo '<div class="sm-cr-caps-grid" style="margin-top: 15px;">';
        foreach ( $roles as $slug => $name ) {
            $checked = in_array( $slug, $hide_updates_roles, true ) ? 'checked' : '';
            echo '<label><input type="checkbox" name="hide_updates[]" value="' . esc_attr( $slug ) . '" ' . $checked . ' /> ' . esc_html( $name ) . '</label>';
        }
        echo '</div>';
        echo '</div>';

        // Sekcia: Skrytie admin baru na frontende
        echo '<div class="sm-cr-card">';
        echo '<h2>Skrytie Admin Panelu (Toolbar) na Frontende</h2>';
        echo '<p class="description">Vyberte role, pre ktoré chcete skryť admin panel (toolbar) pri prezeraní stránky.</p>';
        echo '<div class="sm-cr-caps-grid" style="margin-top: 15px;">';
        foreach ( $roles as $slug => $name ) {
            $checked = in_array( $slug, $hide_admin_bar_roles, true ) ? 'checked' : '';
            echo '<label><input type="checkbox" name="hide_admin_bar[]" value="' . esc_attr( $slug ) . '" ' . $checked . ' /> ' . esc_html( $name ) . '</label>';
        }
        echo '</div>';
        echo '</div>';

        echo '<p>';
        submit_button( 'Uložiť Nastavenia', 'primary', 'sm_cr_save_settings', false );
        echo '</p>';

        echo '</form>';
        echo '</div>';
    }

    /**
     * Čistenie pri deaktivácii
     */
    public function plugin_deactivation() {
        // Roly ostávajú - mazať ich by bolo deštruktívne
        // Nastavenia si ponecháme pre prípad reaktivácie
    }
}

// Inicializácia pluginu
add_action( 'plugins_loaded', function() {
    SM_Custom_Roles::get_instance();
});

// Skrytie admin baru na frontende
add_action( 'after_setup_theme', function() {
    if ( ! is_admin() && is_user_logged_in() ) {
        $hide_admin_bar_roles = get_option( SM_Custom_Roles::OPTION_HIDE_ADMIN_BAR, array() );
        if ( empty( $hide_admin_bar_roles ) ) {
            return;
        }

        $current_user = wp_get_current_user();
        foreach ( $current_user->roles as $role ) {
            if ( in_array( $role, $hide_admin_bar_roles, true ) ) {
                show_admin_bar( false );
                break;
            }
        }
    }
});

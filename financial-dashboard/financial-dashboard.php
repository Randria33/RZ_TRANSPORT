<?php
/**
 * Plugin Name: Tableau de Bord Financier Pro
 * Description: Gestion financière avancée avec import de relevés bancaires et intégration Google Drive
 * Version: 1.0.0
 * Author: Votre Nom
 * Text Domain: financial-dashboard
 * Domain Path: /languages
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Définir les constantes du plugin
define('FINANCIAL_DASHBOARD_VERSION', '1.0.0');
define('FINANCIAL_DASHBOARD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FINANCIAL_DASHBOARD_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FINANCIAL_DASHBOARD_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principale du plugin
 */
class FinancialDashboard {
    
    /**
     * Instance unique du plugin (Singleton)
     */
    private static $instance = null;
    
    /**
     * Obtenir l'instance unique
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialiser les hooks WordPress
     */
    private function init_hooks() {
        // Hook d'activation du plugin
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Hook de désactivation du plugin
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Hook d'initialisation
        add_action('init', array($this, 'init'));
        
        // Hook pour charger les scripts et styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Hook pour les menus admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Hook pour les endpoints REST API
        add_action('rest_api_init', array($this, 'init_rest_routes'));
        
        // Hook pour les settings/options
        add_action('admin_init', array($this, 'init_settings'));
        
        // Hook pour les actions AJAX (Google Drive)
        add_action('wp_ajax_google_drive_auth', array($this, 'handle_google_drive_auth'));
        add_action('wp_ajax_google_drive_files', array($this, 'handle_google_drive_files'));
        add_action('wp_ajax_google_drive_link_invoice', array($this, 'handle_google_drive_link_invoice'));
    }
    
    /**
     * Activation du plugin
     */
    public function activate() {
        // Créer les tables de base de données
        $this->create_database_tables();
        
        // Créer les rôles et capacités
        $this->create_roles_and_capabilities();
        
        // Créer les options par défaut
        $this->create_default_options();
        
        // Flush des règles de réécriture
        flush_rewrite_rules();
        
        // Log d'activation
        error_log('Financial Dashboard Plugin activé avec succès');
    }
    
    /**
     * Désactivation du plugin
     */
    public function deactivate() {
        // Flush des règles de réécriture
        flush_rewrite_rules();
        
        // Log de désactivation
        error_log('Financial Dashboard Plugin désactivé');
    }
    
    /**
     * Initialisation du plugin
     */
    public function init() {
        // Charger les traductions
        load_plugin_textdomain('financial-dashboard', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Inclure les fichiers de fonctionnalités
        $this->include_functionality_files();
    }
    
    /**
     * Inclure les fichiers de fonctionnalités
     */
    private function include_functionality_files() {
        $files = [
            'includes/database/class-database-manager.php',
            'includes/api/class-rest-api.php',
            'includes/integrations/class-google-drive-integration.php',
            'includes/functions/transactions.php',
            'includes/functions/categories.php',
            'includes/functions/imports.php',
            'includes/functions/invoices.php',
            'includes/functions/reports.php',
            'includes/functions/filters.php',
            'includes/functions/google-drive-functions.php'
        ];
        
        foreach ($files as $file) {
            $file_path = FINANCIAL_DASHBOARD_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Charger les scripts et styles (frontend)
     */
    public function enqueue_scripts() {
        // Vérifier si on est sur une page qui utilise le dashboard
        if (is_page() && has_shortcode(get_post()->post_content, 'financial_dashboard')) {
            
            // React et ReactDOM
            wp_enqueue_script(
                'react',
                'https://unpkg.com/react@18/umd/react.production.min.js',
                [],
                '18.0.0',
                true
            );
            
            wp_enqueue_script(
                'react-dom',
                'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js',
                ['react'],
                '18.0.0',
                true
            );
            
            // Recharts pour les graphiques
            wp_enqueue_script(
                'recharts',
                'https://unpkg.com/recharts@2.8.0/umd/Recharts.js',
                ['react', 'react-dom'],
                '2.8.0',
                true
            );
            
            // Google APIs Client Library
            wp_enqueue_script(
                'google-apis-client',
                'https://apis.google.com/js/api.js',
                [],
                null,
                true
            );
            
            // Script principal du dashboard
            wp_enqueue_script(
                'financial-dashboard-app',
                FINANCIAL_DASHBOARD_PLUGIN_URL . 'assets/js/dashboard-app.js',
                ['react', 'react-dom', 'recharts', 'google-apis-client'],
                FINANCIAL_DASHBOARD_VERSION,
                true
            );
            
            // Styles du dashboard
            wp_enqueue_style(
                'financial-dashboard-styles',
                FINANCIAL_DASHBOARD_PLUGIN_URL . 'assets/css/dashboard-styles.css',
                [],
                FINANCIAL_DASHBOARD_VERSION
            );
            
            // Localiser les données pour JavaScript
            wp_localize_script('financial-dashboard-app', 'financialDashboard', [
                'apiUrl' => rest_url('financial-dashboard/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'currentUser' => get_current_user_id(),
                'pluginUrl' => FINANCIAL_DASHBOARD_PLUGIN_URL,
                'googleDriveConfig' => [
                    'clientId' => get_option('financial_dashboard_google_client_id', ''),
                    'apiKey' => get_option('financial_dashboard_google_api_key', ''),
                    'discoveryDoc' => 'https://www.googleapis.com/discovery/v1/apis/drive/v3/rest',
                    'scopes' => 'https://www.googleapis.com/auth/drive.readonly'
                ],
                'ajaxUrl' => admin_url('admin-ajax.php')
            ]);
        }
    }
    
    /**
     * Charger les scripts admin
     */
    public function enqueue_admin_scripts($hook) {
        // Charger seulement sur les pages admin du plugin
        if (strpos($hook, 'financial-dashboard') !== false) {
            wp_enqueue_script(
                'financial-dashboard-admin',
                FINANCIAL_DASHBOARD_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                FINANCIAL_DASHBOARD_VERSION,
                true
            );
            
            wp_enqueue_style(
                'financial-dashboard-admin-styles',
                FINANCIAL_DASHBOARD_PLUGIN_URL . 'assets/css/admin-styles.css',
                [],
                FINANCIAL_DASHBOARD_VERSION
            );
            
            // Localiser pour l'admin
            wp_localize_script('financial-dashboard-admin', 'financialDashboardAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('financial_dashboard_admin_nonce')
            ]);
        }
    }
    
    /**
     * Ajouter les menus admin
     */
    public function add_admin_menu() {
        // Menu principal
        add_menu_page(
            __('Dashboard Financier', 'financial-dashboard'),
            __('Dashboard Financier', 'financial-dashboard'),
            'manage_options',
            'financial-dashboard',
            array($this, 'admin_dashboard_page'),
            'dashicons-chart-line',
            30
        );
        
        // Sous-menus
        add_submenu_page(
            'financial-dashboard',
            __('Transactions', 'financial-dashboard'),
            __('Transactions', 'financial-dashboard'),
            'manage_options',
            'financial-dashboard-transactions',
            array($this, 'admin_transactions_page')
        );
        
        add_submenu_page(
            'financial-dashboard',
            __('Catégories', 'financial-dashboard'),
            __('Catégories', 'financial-dashboard'),
            'manage_options',
            'financial-dashboard-categories',
            array($this, 'admin_categories_page')
        );
        
        add_submenu_page(
            'financial-dashboard',
            __('Imports', 'financial-dashboard'),
            __('Imports', 'financial-dashboard'),
            'manage_options',
            'financial-dashboard-imports',
            array($this, 'admin_imports_page')
        );
        
        add_submenu_page(
            'financial-dashboard',
            __('Google Drive', 'financial-dashboard'),
            __('Google Drive', 'financial-dashboard'),
            'manage_options',
            'financial-dashboard-google-drive',
            array($this, 'admin_google_drive_page')
        );
        
        add_submenu_page(
            'financial-dashboard',
            __('Rapports', 'financial-dashboard'),
            __('Rapports', 'financial-dashboard'),
            'manage_options',
            'financial-dashboard-reports',
            array($this, 'admin_reports_page')
        );
        
        add_submenu_page(
            'financial-dashboard',
            __('Paramètres', 'financial-dashboard'),
            __('Paramètres', 'financial-dashboard'),
            'manage_options',
            'financial-dashboard-settings',
            array($this, 'admin_settings_page')
        );
    }
    
    /**
     * Page admin principale
     */
    public function admin_dashboard_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Dashboard Financier - Administration', 'financial-dashboard') . '</h1>';
        echo '<div id="financial-dashboard-admin-app"></div>';
        echo '</div>';
    }
    
    /**
     * Page admin des transactions
     */
    public function admin_transactions_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Gestion des Transactions', 'financial-dashboard') . '</h1>';
        echo '<div id="financial-dashboard-transactions-admin"></div>';
        echo '</div>';
    }
    
    /**
     * Page admin des catégories
     */
    public function admin_categories_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Gestion des Catégories', 'financial-dashboard') . '</h1>';
        echo '<div id="financial-dashboard-categories-admin"></div>';
        echo '</div>';
    }
    
    /**
     * Page admin des imports
     */
    public function admin_imports_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Gestion des Imports', 'financial-dashboard') . '</h1>';
        echo '<div id="financial-dashboard-imports-admin"></div>';
        echo '</div>';
    }
    
    /**
     * Page admin Google Drive
     */
    public function admin_google_drive_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Configuration Google Drive', 'financial-dashboard') . '</h1>';
        echo '<div id="financial-dashboard-google-drive-admin"></div>';
        echo '</div>';
    }
    
    /**
     * Page admin des rapports
     */
    public function admin_reports_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Rapports Financiers', 'financial-dashboard') . '</h1>';
        echo '<div id="financial-dashboard-reports-admin"></div>';
        echo '</div>';
    }
    
    /**
     * Page admin des paramètres
     */
    public function admin_settings_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Paramètres du Dashboard Financier', 'financial-dashboard') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('financial_dashboard_settings');
        do_settings_sections('financial_dashboard_settings');
        submit_button();
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Initialiser les paramètres
     */
    public function init_settings() {
        register_setting('financial_dashboard_settings', 'financial_dashboard_google_client_id');
        register_setting('financial_dashboard_settings', 'financial_dashboard_google_api_key');
        register_setting('financial_dashboard_settings', 'financial_dashboard_google_client_secret');
        register_setting('financial_dashboard_settings', 'financial_dashboard_default_currency');
        register_setting('financial_dashboard_settings', 'financial_dashboard_date_format');
        
        add_settings_section(
            'financial_dashboard_google_section',
            __('Configuration Google Drive', 'financial-dashboard'),
            array($this, 'google_section_callback'),
            'financial_dashboard_settings'
        );
        
        add_settings_field(
            'google_client_id',
            __('Google Client ID', 'financial-dashboard'),
            array($this, 'google_client_id_callback'),
            'financial_dashboard_settings',
            'financial_dashboard_google_section'
        );
        
        add_settings_field(
            'google_api_key',
            __('Google API Key', 'financial-dashboard'),
            array($this, 'google_api_key_callback'),
            'financial_dashboard_settings',
            'financial_dashboard_google_section'
        );
        
        add_settings_field(
            'google_client_secret',
            __('Google Client Secret', 'financial-dashboard'),
            array($this, 'google_client_secret_callback'),
            'financial_dashboard_settings',
            'financial_dashboard_google_section'
        );
        
        add_settings_section(
            'financial_dashboard_general_section',
            __('Paramètres Généraux', 'financial-dashboard'),
            array($this, 'general_section_callback'),
            'financial_dashboard_settings'
        );
        
        add_settings_field(
            'default_currency',
            __('Devise par défaut', 'financial-dashboard'),
            array($this, 'currency_callback'),
            'financial_dashboard_settings',
            'financial_dashboard_general_section'
        );
    }
    
    /**
     * Callbacks pour les sections des paramètres
     */
    public function google_section_callback() {
        echo '<p>' . __('Configurez votre intégration Google Drive pour lier les factures aux transactions.', 'financial-dashboard') . '</p>';
        echo '<p><strong>' . __('Étapes de configuration :', 'financial-dashboard') . '</strong></p>';
        echo '<ol>';
        echo '<li>' . __('Allez sur <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>', 'financial-dashboard') . '</li>';
        echo '<li>' . __('Créez un nouveau projet ou sélectionnez un projet existant', 'financial-dashboard') . '</li>';
        echo '<li>' . __('Activez l\'API Google Drive', 'financial-dashboard') . '</li>';
        echo '<li>' . __('Créez des identifiants (OAuth 2.0 Client ID et API Key)', 'financial-dashboard') . '</li>';
        echo '<li>' . __('Copiez les identifiants ci-dessous', 'financial-dashboard') . '</li>';
        echo '</ol>';
    }
    
    public function general_section_callback() {
        echo '<p>' . __('Paramètres généraux du dashboard financier.', 'financial-dashboard') . '</p>';
    }
    
    /**
     * Callbacks pour les champs des paramètres
     */
    public function google_client_id_callback() {
        $value = get_option('financial_dashboard_google_client_id', '');
        echo '<input type="text" id="google_client_id" name="financial_dashboard_google_client_id" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Client ID obtenu depuis Google Cloud Console', 'financial-dashboard') . '</p>';
    }
    
    public function google_api_key_callback() {
        $value = get_option('financial_dashboard_google_api_key', '');
        echo '<input type="text" id="google_api_key" name="financial_dashboard_google_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('API Key pour accéder à l\'API Google Drive', 'financial-dashboard') . '</p>';
    }
    
    public function google_client_secret_callback() {
        $value = get_option('financial_dashboard_google_client_secret', '');
        echo '<input type="password" id="google_client_secret" name="financial_dashboard_google_client_secret" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Client Secret (gardé confidentiel)', 'financial-dashboard') . '</p>';
    }
    
    public function currency_callback() {
        $value = get_option('financial_dashboard_default_currency', 'EUR');
        $currencies = ['EUR' => 'Euro (€)', 'USD' => 'Dollar ($)', 'GBP' => 'Livre (£)'];
        echo '<select id="default_currency" name="financial_dashboard_default_currency">';
        foreach ($currencies as $code => $name) {
            echo '<option value="' . esc_attr($code) . '"' . selected($value, $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }
    
    /**
     * Gestionnaires AJAX pour Google Drive
     */
    public function handle_google_drive_auth() {
        check_ajax_referer('financial_dashboard_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission insuffisante', 'financial-dashboard'));
        }
        
        // Logique d'authentification Google Drive
        // À implémenter dans le fichier Google Drive
        if (class_exists('Financial_Dashboard_Google_Drive')) {
            $result = Financial_Dashboard_Google_Drive::authenticate();
            wp_send_json($result);
        }
        
        wp_send_json_error(__('Erreur d\'authentification', 'financial-dashboard'));
    }
    
    public function handle_google_drive_files() {
        check_ajax_referer('wp_rest', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die(__('Vous devez être connecté', 'financial-dashboard'));
        }
        
        if (class_exists('Financial_Dashboard_Google_Drive')) {
            $files = Financial_Dashboard_Google_Drive::get_files();
            wp_send_json_success($files);
        }
        
        wp_send_json_error(__('Erreur lors de la récupération des fichiers', 'financial-dashboard'));
    }
    
    public function handle_google_drive_link_invoice() {
        check_ajax_referer('wp_rest', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die(__('Vous devez être connecté', 'financial-dashboard'));
        }
        
        $transaction_id = intval($_POST['transaction_id']);
        $file_id = sanitize_text_field($_POST['file_id']);
        
        if (class_exists('Financial_Dashboard_Google_Drive')) {
            $result = Financial_Dashboard_Google_Drive::link_invoice_to_transaction($transaction_id, $file_id);
            wp_send_json($result);
        }
        
        wp_send_json_error(__('Erreur lors de la liaison', 'financial-dashboard'));
    }
    
    /**
     * Initialiser les routes REST API
     */
    public function init_rest_routes() {
        // Les routes seront définies dans le fichier class-rest-api.php
        if (class_exists('Financial_Dashboard_REST_API')) {
            new Financial_Dashboard_REST_API();
        }
    }
    
    /**
     * Créer les tables de base de données
     */
    private function create_database_tables() {
        // Cette fonction sera détaillée dans l'étape suivante
        if (class_exists('Financial_Dashboard_Database_Manager')) {
            Financial_Dashboard_Database_Manager::create_tables();
        }
    }
    
    /**
     * Créer les rôles et capacités
     */
    private function create_roles_and_capabilities() {
        // Ajouter des capacités pour gérer le dashboard financier
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_financial_dashboard');
            $role->add_cap('view_financial_reports');
            $role->add_cap('import_financial_data');
            $role->add_cap('manage_google_drive_integration');
        }
    }
    
    /**
     * Créer les options par défaut
     */
    private function create_default_options() {
        add_option('financial_dashboard_default_currency', 'EUR');
        add_option('financial_dashboard_date_format', 'Y-m-d');
        add_option('financial_dashboard_google_client_id', '');
        add_option('financial_dashboard_google_api_key', '');
        add_option('financial_dashboard_google_client_secret', '');
    }
}

// Shortcode pour afficher le dashboard
function financial_dashboard_shortcode($atts) {
    $atts = shortcode_atts([
        'user_id' => get_current_user_id(),
        'view' => 'full'
    ], $atts);
    
    if (!is_user_logged_in()) {
        return '<p>' . __('Vous devez être connecté pour voir le dashboard financier.', 'financial-dashboard') . '</p>';
    }
    
    return '<div id="financial-dashboard-app" data-user-id="' . esc_attr($atts['user_id']) . '" data-view="' . esc_attr($atts['view']) . '"></div>';
}
add_shortcode('financial_dashboard', 'financial_dashboard_shortcode');

// Initialiser le plugin
function financial_dashboard_init() {
    return FinancialDashboard::get_instance();
}

// Démarrer le plugin quand WordPress est prêt
add_action('plugins_loaded', 'financial_dashboard_init');

?>
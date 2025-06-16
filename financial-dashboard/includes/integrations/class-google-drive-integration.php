<?php
/**
 * Classe d'intégration Google Drive principale
 * 
 * @package FinancialDashboard
 * @subpackage Integrations
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe principale d'intégration Google Drive
 */
class Financial_Dashboard_Google_Drive_Integration {
    
    /**
     * Instance unique (Singleton)
     */
    private static $instance = null;
    
    /**
     * Configuration Google
     */
    private $client_id;
    private $client_secret;
    private $api_key;
    
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
     * Constructeur
     */
    private function __construct() {
        $this->load_config();
        $this->init_hooks();
    }
    
    /**
     * Charger la configuration
     */
    private function load_config() {
        $this->client_id = get_option('financial_dashboard_google_client_id');
        $this->client_secret = get_option('financial_dashboard_google_client_secret');
        $this->api_key = get_option('financial_dashboard_google_api_key');
    }
    
    /**
     * Initialiser les hooks
     */
    private function init_hooks() {
        // Actions AJAX pour l'interface React
        add_action('wp_ajax_fd_google_auth', [$this, 'handle_google_auth']);
        add_action('wp_ajax_fd_google_files', [$this, 'handle_get_files']);
        add_action('wp_ajax_fd_google_file_details', [$this, 'handle_get_file_details']);
        add_action('wp_ajax_fd_google_search', [$this, 'handle_search_files']);
        add_action('wp_ajax_fd_google_disconnect', [$this, 'handle_disconnect']);
        add_action('wp_ajax_fd_link_invoice', [$this, 'handle_link_invoice']);
        
        // Endpoints REST supplémentaires
        add_action('rest_api_init', [$this, 'register_rest_endpoints']);
        
        // Scripts pour l'authentification
        add_action('admin_footer', [$this, 'add_google_auth_script']);
    }
    
    /**
     * Enregistrer les endpoints REST
     */
    public function register_rest_endpoints() {
        register_rest_route('financial-dashboard/v1', '/google-drive/callback', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_oauth_callback'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
    }
    
    /**
     * Gestionnaire d'authentification Google
     */
    public function handle_google_auth() {
        check_ajax_referer('wp_rest', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Utilisateur non connecté']);
        }
        
        $action = sanitize_text_field($_POST['action_type'] ?? 'get_auth_url');
        
        switch ($action) {
            case 'get_auth_url':
                $auth_url = Financial_Dashboard_Google_Drive::get_authorization_url();
                
                if (is_wp_error($auth_url)) {
                    wp_send_json_error(['message' => $auth_url->get_error_message()]);
                }
                
                wp_send_json_success(['auth_url' => $auth_url]);
                break;
                
            case 'get_status':
                $status = Financial_Dashboard_Google_Drive::get_connection_status();
                wp_send_json_success($status);
                break;
                
            default:
                wp_send_json_error(['message' => 'Action non reconnue']);
        }
    }
    
    /**
     * Gestionnaire de callback OAuth
     */
    public function handle_oauth_callback($request) {
        $auth_code = $request->get_param('code');
        
        if (!$auth_code) {
            return new WP_Error('missing_code', 'Code d\'autorisation manquant');
        }
        
        $result = Financial_Dashboard_Google_Drive::authenticate_user($auth_code);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response($result, 200);
    }
    
    /**
     * Gestionnaire de récupération des fichiers
     */
    public function handle_get_files() {
        check_ajax_referer('wp_rest', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Utilisateur non connecté']);
        }
        
        $options = [
            'page_size' => intval($_POST['page_size'] ?? 20),
            'page_token' => sanitize_text_field($_POST['page_token'] ?? ''),
            'folder_id' => sanitize_text_field($_POST['folder_id'] ?? ''),
            'search_query' => sanitize_text_field($_POST['search_query'] ?? ''),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? '')
        ];
        
        $files = Financial_Dashboard_Google_Drive::list_files(get_current_user_id(), $options);
        
        if (is_wp_error($files)) {
            wp_send_json_error(['message' => $files->get_error_message()]);
        }
        
        wp_send_json_success($files);
    }
    
    /**
     * Gestionnaire de détails de fichier
     */
    public function handle_get_file_details() {
        check_ajax_referer('wp_rest', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Utilisateur non connecté']);
        }
        
        $file_id = sanitize_text_field($_POST['file_id'] ?? '');
        
        if (!$file_id) {
            wp_send_json_error(['message' => 'ID de fichier manquant']);
        }
        
        $file_details = Financial_Dashboard_Google_Drive::get_file_details($file_id);
        
        if (is_wp_error($file_details)) {
            wp_send_json_error(['message' => $file_details->get_error_message()]);
        }
        
        wp_send_json_success($file_details);
    }
    
    /**
     * Gestionnaire de recherche de fichiers
     */
    public function handle_search_files() {
        check_ajax_referer('wp_rest', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Utilisateur non connecté']);
        }
        
        $search_query = sanitize_text_field($_POST['search_query'] ?? '');
        $limit = intval($_POST['limit'] ?? 10);
        
        if (!$search_query) {
            wp_send_json_error(['message' => 'Terme de recherche manquant']);
        }
        
        $results = Financial_Dashboard_Google_Drive::search_files($search_query, get_current_user_id(), ['limit' => $limit]);
        
        if (is_wp_error($results)) {
            wp_send_json_error(['message' => $results->get_error_message()]);
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Gestionnaire de déconnexion
     */
    public function handle_disconnect() {
        check_ajax_referer('wp_rest', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Utilisateur non connecté']);
        }
        
        $result = Financial_Dashboard_Google_Drive::disconnect_user();
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => 'Déconnecté avec succès']);
    }
    
    /**
     * Gestionnaire de liaison de facture
     */
    public function handle_link_invoice() {
        check_ajax_referer('wp_rest', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Utilisateur non connecté']);
        }
        
        $transaction_id = intval($_POST['transaction_id'] ?? 0);
        $file_id = sanitize_text_field($_POST['file_id'] ?? '');
        $file_name = sanitize_text_field($_POST['file_name'] ?? '');
        $file_url = esc_url_raw($_POST['file_url'] ?? '');
        
        if (!$transaction_id || !$file_id) {
            wp_send_json_error(['message' => 'Données manquantes']);
        }
        
        $google_drive_data = [
            'file_id' => $file_id,
            'file_name' => $file_name,
            'file_url' => $file_url
        ];
        
        $result = Financial_Dashboard_Invoices::link_invoice_to_transaction(
            $transaction_id, 
            $google_drive_data, 
            get_current_user_id()
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Ajouter le script d'authentification Google
     */
    public function add_google_auth_script() {
        if (!$this->should_load_scripts()) {
            return;
        }
        ?>
        <script>
        // Configuration Google Drive pour React
        window.financialDashboardGoogle = {
            clientId: '<?php echo esc_js($this->client_id); ?>',
            apiKey: '<?php echo esc_js($this->api_key); ?>',
            discoveryDoc: 'https://www.googleapis.com/discovery/v1/apis/drive/v3/rest',
            scopes: 'https://www.googleapis.com/auth/drive.readonly',
            
            // Fonction d'initialisation
            init: function() {
                if (typeof gapi !== 'undefined') {
                    gapi.load('api:client', this.initializeGapi.bind(this));
                }
            },
            
            // Initialiser l'API Google
            initializeGapi: async function() {
                try {
                    await gapi.client.init({
                        apiKey: this.apiKey,
                        discoveryDocs: [this.discoveryDoc],
                    });
                    
                    window.dispatchEvent(new CustomEvent('googleApiReady'));
                } catch (error) {
                    console.error('Erreur initialisation Google API:', error);
                }
            },
            
            // Démarrer l'authentification
            authenticate: async function() {
                try {
                    const tokenClient = google.accounts.oauth2.initTokenClient({
                        client_id: this.clientId,
                        scope: this.scopes,
                        callback: this.handleAuthResponse.bind(this),
                    });
                    
                    tokenClient.requestAccessToken();
                } catch (error) {
                    console.error('Erreur authentification:', error);
                }
            },
            
            // Gérer la réponse d'authentification
            handleAuthResponse: function(response) {
                if (response.error) {
                    console.error('Erreur auth:', response.error);
                    return;
                }
                
                // Sauvegarder le token côté WordPress
                jQuery.post(ajaxurl, {
                    action: 'fd_google_auth',
                    action_type: 'save_token',
                    access_token: response.access_token,
                    nonce: financialDashboard.nonce
                }).done(function(result) {
                    if (result.success) {
                        window.dispatchEvent(new CustomEvent('googleAuthSuccess', {
                            detail: response
                        }));
                    }
                });
            }
        };
        
        // Auto-initialisation
        document.addEventListener('DOMContentLoaded', function() {
            if (window.financialDashboardGoogle) {
                window.financialDashboardGoogle.init();
            }
        });
        </script>
        <?php
    }
    
    /**
     * Vérifier si les scripts doivent être chargés
     */
    private function should_load_scripts() {
        // Charger sur les pages du plugin
        $screen = get_current_screen();
        
        if (!$screen) {
            return false;
        }
        
        return strpos($screen->id, 'financial-dashboard') !== false;
    }
    
    /**
     * Obtenir la configuration pour React
     */
    public static function get_react_config() {
        return [
            'google_drive' => [
                'client_id' => get_option('financial_dashboard_google_client_id'),
                'api_key' => get_option('financial_dashboard_google_api_key'),
                'configured' => !empty(get_option('financial_dashboard_google_client_id')),
                'endpoints' => [
                    'auth' => admin_url('admin-ajax.php?action=fd_google_auth'),
                    'files' => admin_url('admin-ajax.php?action=fd_google_files'),
                    'search' => admin_url('admin-ajax.php?action=fd_google_search'),
                    'disconnect' => admin_url('admin-ajax.php?action=fd_google_disconnect'),
                    'link_invoice' => admin_url('admin-ajax.php?action=fd_link_invoice')
                ]
            ]
        ];
    }
    
    /**
     * Valider la configuration Google
     */
    public static function validate_config() {
        $errors = [];
        
        if (empty(get_option('financial_dashboard_google_client_id'))) {
            $errors[] = 'Client ID Google manquant';
        }
        
        if (empty(get_option('financial_dashboard_google_api_key'))) {
            $errors[] = 'API Key Google manquante';
        }
        
        if (empty(get_option('financial_dashboard_google_client_secret'))) {
            $errors[] = 'Client Secret Google manquant';
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Tester la connexion Google Drive
     */
    public static function test_connection($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Vérifier la configuration
        $config_validation = self::validate_config();
        if ($config_validation !== true) {
            return new WP_Error('config_error', 'Configuration Google incomplète: ' . implode(', ', $config_validation));
        }
        
        // Vérifier les tokens utilisateur
        $status = Financial_Dashboard_Google_Drive::get_connection_status($user_id);
        
        if (!$status['connected']) {
            return new WP_Error('not_connected', $status['message']);
        }
        
        // Tester un appel API simple
        $test_files = Financial_Dashboard_Google_Drive::list_files($user_id, ['page_size' => 1]);
        
        if (is_wp_error($test_files)) {
            return $test_files;
        }
        
        return [
            'status' => 'success',
            'message' => 'Connexion Google Drive fonctionnelle',
            'user_email' => $status['user_email'] ?? 'N/A'
        ];
    }
}

// Initialiser l'intégration
Financial_Dashboard_Google_Drive_Integration::get_instance();

?>
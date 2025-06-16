<?php
/**
 * Fonctions d'intégration Google Drive
 * 
 * @package FinancialDashboard
 * @subpackage Functions
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe d'intégration Google Drive
 */
class Financial_Dashboard_Google_Drive {
    
    /**
     * URL de base de l'API Google Drive
     */
    const GOOGLE_DRIVE_API_BASE = 'https://www.googleapis.com/drive/v3';
    
    /**
     * Scopes nécessaires pour l'API
     */
    const REQUIRED_SCOPES = [
        'https://www.googleapis.com/auth/drive.readonly',
        'https://www.googleapis.com/auth/drive.metadata.readonly'
    ];
    
    /**
     * Authentifier un utilisateur avec Google Drive
     */
    public static function authenticate_user($auth_code, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Récupérer les paramètres Google
        $client_id = get_option('financial_dashboard_google_client_id');
        $client_secret = get_option('financial_dashboard_google_client_secret');
        
        if (!$client_id || !$client_secret) {
            return new WP_Error('missing_config', 'Configuration Google Drive incomplète');
        }
        
        // Échanger le code d'autorisation contre des tokens
        $token_data = self::exchange_auth_code($auth_code, $client_id, $client_secret);
        
        if (is_wp_error($token_data)) {
            return $token_data;
        }
        
        // Sauvegarder les tokens
        $save_result = self::save_user_tokens($user_id, $token_data);
        
        if (is_wp_error($save_result)) {
            return $save_result;
        }
        
        // Hook pour extensions
        do_action('financial_dashboard_google_drive_authenticated', $user_id, $token_data);
        
        return [
            'status' => 'success',
            'message' => 'Authentification Google Drive réussie',
            'expires_at' => $token_data['expires_at']
        ];
    }
    
    /**
     * Obtenir le statut de connexion Google Drive
     */
    public static function get_connection_status($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $tokens = self::get_user_tokens($user_id);
        
        if (!$tokens) {
            return [
                'connected' => false,
                'message' => 'Non connecté à Google Drive'
            ];
        }
        
        // Vérifier si les tokens sont valides
        if (strtotime($tokens->expires_at) <= time()) {
            // Essayer de rafraîchir les tokens
            $refresh_result = self::refresh_access_token($user_id);
            
            if (is_wp_error($refresh_result)) {
                return [
                    'connected' => false,
                    'message' => 'Tokens expirés, reconnexion nécessaire',
                    'error' => $refresh_result->get_error_message()
                ];
            }
            
            $tokens = self::get_user_tokens($user_id);
        }
        
        return [
            'connected' => true,
            'message' => 'Connecté à Google Drive',
            'expires_at' => $tokens->expires_at,
            'user_email' => self::get_user_google_email($tokens->access_token)
        ];
    }
    
    /**
     * Lister les fichiers Google Drive
     */
    public static function list_files($user_id = null, $options = []) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Obtenir un token valide
        $access_token = self::get_valid_access_token($user_id);
        
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        // Construire la requête
        $query_params = [
            'pageSize' => $options['page_size'] ?? 20,
            'fields' => 'files(id,name,mimeType,size,createdTime,modifiedTime,webViewLink,thumbnailLink)',
            'orderBy' => 'modifiedTime desc'
        ];
        
        // Filtrer par type de fichier (factures/documents)
        $mime_types = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        $query_params['q'] = "mimeType='" . implode("' or mimeType='", $mime_types) . "'";
        
        // Ajouter des filtres supplémentaires
        if (!empty($options['folder_id'])) {
            $query_params['q'] .= " and '" . $options['folder_id'] . "' in parents";
        }
        
        if (!empty($options['search_query'])) {
            $query_params['q'] .= " and name contains '" . addslashes($options['search_query']) . "'";
        }
        
        if (!empty($options['date_from'])) {
            $query_params['q'] .= " and modifiedTime >= '" . $options['date_from'] . "T00:00:00'";
        }
        
        if (!empty($options['page_token'])) {
            $query_params['pageToken'] = $options['page_token'];
        }
        
        // Effectuer la requête API
        $url = self::GOOGLE_DRIVE_API_BASE . '/files?' . http_build_query($query_params);
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', 'Erreur API Google Drive: ' . $response_code);
        }
        
        $data = json_decode($response_body, true);
        
        if (!$data) {
            return new WP_Error('parse_error', 'Erreur lors du parsing de la réponse');
        }
        
        // Enrichir les données des fichiers
        if (isset($data['files'])) {
            foreach ($data['files'] as &$file) {
                $file['formatted_size'] = self::format_file_size($file['size'] ?? 0);
                $file['formatted_date'] = self::format_date($file['modifiedTime'] ?? '');
                $file['is_invoice'] = self::is_likely_invoice($file['name']);
                $file['preview_url'] = self::get_preview_url($file['id'], $access_token);
            }
        }
        
        return $data;
    }
    
    /**
     * Obtenir les détails d'un fichier spécifique
     */
    public static function get_file_details($file_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $access_token = self::get_valid_access_token($user_id);
        
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        $url = self::GOOGLE_DRIVE_API_BASE . '/files/' . $file_id . '?fields=id,name,mimeType,size,createdTime,modifiedTime,webViewLink,thumbnailLink,parents';
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', 'Fichier non trouvé ou accès refusé');
        }
        
        $file_data = json_decode(wp_remote_retrieve_body($response), true);
        
        // Enrichir les données
        $file_data['formatted_size'] = self::format_file_size($file_data['size'] ?? 0);
        $file_data['formatted_date'] = self::format_date($file_data['modifiedTime'] ?? '');
        $file_data['is_invoice'] = self::is_likely_invoice($file_data['name']);
        $file_data['download_url'] = self::get_download_url($file_id, $access_token);
        
        return $file_data;
    }
    
    /**
     * Rechercher des fichiers par mots-clés
     */
    public static function search_files($search_query, $user_id = null, $options = []) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $search_options = array_merge($options, [
            'search_query' => $search_query,
            'page_size' => $options['limit'] ?? 10
        ]);
        
        return self::list_files($user_id, $search_options);
    }
    
    /**
     * Déconnecter un utilisateur de Google Drive
     */
    public static function disconnect_user($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('google_drive_tokens');
        
        // Révoquer le token côté Google (optionnel)
        $tokens = self::get_user_tokens($user_id);
        if ($tokens && $tokens->access_token) {
            self::revoke_token($tokens->access_token);
        }
        
        // Supprimer les tokens de la base de données
        $result = $wpdb->delete($table_name, ['user_id' => $user_id]);
        
        if ($result !== false) {
            // Hook pour extensions
            do_action('financial_dashboard_google_drive_disconnected', $user_id);
            
            return true;
        }
        
        return new WP_Error('db_error', 'Erreur lors de la déconnexion');
    }
    
    /**
     * Fonctions utilitaires privées
     */
    
    private static function exchange_auth_code($auth_code, $client_id, $client_secret) {
        $token_url = 'https://oauth2.googleapis.com/token';
        
        $post_data = [
            'code' => $auth_code,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => admin_url('admin.php?page=financial-dashboard-google-drive'),
            'grant_type' => 'authorization_code'
        ];
        
        $response = wp_remote_post($token_url, [
            'body' => $post_data,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('auth_error', 'Erreur d\'authentification: ' . $response_code);
        }
        
        $token_data = json_decode($response_body, true);
        
        if (!$token_data || !isset($token_data['access_token'])) {
            return new WP_Error('token_error', 'Réponse token invalide');
        }
        
        // Calculer la date d'expiration
        $expires_in = $token_data['expires_in'] ?? 3600;
        $token_data['expires_at'] = date('Y-m-d H:i:s', time() + $expires_in);
        
        return $token_data;
    }
    
    private static function save_user_tokens($user_id, $token_data) {
        global $wpdb;
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('google_drive_tokens');
        
        $tokens_record = [
            'user_id' => $user_id,
            'access_token' => $token_data['access_token'],
            'refresh_token' => $token_data['refresh_token'] ?? null,
            'token_type' => $token_data['token_type'] ?? 'Bearer',
            'expires_at' => $token_data['expires_at'],
            'scope' => isset($token_data['scope']) ? $token_data['scope'] : implode(' ', self::REQUIRED_SCOPES),
            'is_active' => 1
        ];
        
        // Vérifier si l'utilisateur a déjà des tokens
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d",
            $user_id
        ));
        
        if ($existing) {
            $result = $wpdb->update(
                $table_name,
                $tokens_record,
                ['user_id' => $user_id]
            );
        } else {
            $result = $wpdb->insert($table_name, $tokens_record);
        }
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la sauvegarde des tokens');
        }
        
        return true;
    }
    
    private static function get_user_tokens($user_id) {
        global $wpdb;
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('google_drive_tokens');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND is_active = 1",
            $user_id
        ));
    }
    
    private static function get_valid_access_token($user_id) {
        $tokens = self::get_user_tokens($user_id);
        
        if (!$tokens) {
            return new WP_Error('not_connected', 'Non connecté à Google Drive');
        }
        
        // Vérifier si le token est expiré
        if (strtotime($tokens->expires_at) <= time()) {
            $refresh_result = self::refresh_access_token($user_id);
            
            if (is_wp_error($refresh_result)) {
                return $refresh_result;
            }
            
            $tokens = self::get_user_tokens($user_id);
        }
        
        return $tokens->access_token;
    }
    
    private static function refresh_access_token($user_id) {
        $tokens = self::get_user_tokens($user_id);
        
        if (!$tokens || !$tokens->refresh_token) {
            return new WP_Error('no_refresh_token', 'Token de rafraîchissement manquant');
        }
        
        $client_id = get_option('financial_dashboard_google_client_id');
        $client_secret = get_option('financial_dashboard_google_client_secret');
        
        $refresh_url = 'https://oauth2.googleapis.com/token';
        
        $post_data = [
            'refresh_token' => $tokens->refresh_token,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'refresh_token'
        ];
        
        $response = wp_remote_post($refresh_url, [
            'body' => $post_data,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            return new WP_Error('refresh_error', 'Erreur lors du rafraîchissement du token');
        }
        
        $new_token_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$new_token_data || !isset($new_token_data['access_token'])) {
            return new WP_Error('refresh_parse_error', 'Réponse de rafraîchissement invalide');
        }
        
        // Mettre à jour les tokens
        $expires_in = $new_token_data['expires_in'] ?? 3600;
        $new_token_data['expires_at'] = date('Y-m-d H:i:s', time() + $expires_in);
        
        // Garder le refresh token s'il n'est pas fourni
        if (!isset($new_token_data['refresh_token'])) {
            $new_token_data['refresh_token'] = $tokens->refresh_token;
        }
        
        return self::save_user_tokens($user_id, $new_token_data);
    }
    
    private static function revoke_token($access_token) {
        $revoke_url = 'https://oauth2.googleapis.com/revoke?token=' . $access_token;
        
        wp_remote_post($revoke_url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);
        
        // Ne pas retourner d'erreur même si la révocation échoue
        return true;
    }
    
    private static function get_user_google_email($access_token) {
        $userinfo_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
        
        $response = wp_remote_get($userinfo_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token
            ]
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $user_data = json_decode(wp_remote_retrieve_body($response), true);
            return $user_data['email'] ?? null;
        }
        
        return null;
    }
    
    private static function format_file_size($bytes) {
        $bytes = intval($bytes);
        
        if ($bytes === 0) return '0 B';
        
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB'];
        
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    
    private static function format_date($date_string) {
        if (empty($date_string)) return '';
        
        $date = new DateTime($date_string);
        return $date->format('d/m/Y H:i');
    }
    
    private static function is_likely_invoice($filename) {
        $invoice_keywords = [
            'facture', 'invoice', 'recu', 'receipt', 'bill', 'ticket',
            'devis', 'quote', 'commande', 'order', 'bon', 'note'
        ];
        
        $filename_lower = strtolower($filename);
        
        foreach ($invoice_keywords as $keyword) {
            if (strpos($filename_lower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private static function get_preview_url($file_id, $access_token) {
        return "https://drive.google.com/file/d/{$file_id}/preview";
    }
    
    private static function get_download_url($file_id, $access_token) {
        return self::GOOGLE_DRIVE_API_BASE . '/files/' . $file_id . '?alt=media';
    }
    
    /**
     * Générer l'URL d'autorisation Google
     */
    public static function get_authorization_url() {
        $client_id = get_option('financial_dashboard_google_client_id');
        
        if (!$client_id) {
            return new WP_Error('missing_client_id', 'Client ID Google non configuré');
        }
        
        $params = [
            'client_id' => $client_id,
            'redirect_uri' => admin_url('admin.php?page=financial-dashboard-google-drive'),
            'scope' => implode(' ', self::REQUIRED_SCOPES),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
}

?>
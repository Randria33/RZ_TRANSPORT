<?php
/**
 * API REST pour le Dashboard Financier
 * 
 * @package FinancialDashboard
 * @subpackage API
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour gérer l'API REST
 */
class Financial_Dashboard_REST_API {
    
    /**
     * Namespace de l'API
     */
    const API_NAMESPACE = 'financial-dashboard/v1';
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->register_routes();
    }
    
    /**
     * Enregistrer toutes les routes
     */
    public function register_routes() {
        // Routes des transactions
        $this->register_transactions_routes();
        
        // Routes des catégories
        $this->register_categories_routes();
        
        // Routes des factures/Google Drive
        $this->register_invoices_routes();
        
        // Routes des imports
        $this->register_imports_routes();
        
        // Routes des rapports
        $this->register_reports_routes();
        
        // Routes des statistiques
        $this->register_stats_routes();
        
        // Routes Google Drive
        $this->register_google_drive_routes();
    }
    
    /**
     * Routes pour les transactions
     */
    private function register_transactions_routes() {
        // GET /transactions - Lister les transactions avec filtres
        register_rest_route(self::API_NAMESPACE, '/transactions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_transactions'],
            'permission_callback' => [$this, 'check_user_permissions'],
            'args' => [
                'page' => [
                    'default' => 1,
                    'sanitize_callback' => 'absint'
                ],
                'per_page' => [
                    'default' => 20,
                    'sanitize_callback' => 'absint'
                ],
                'type' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'category_id' => [
                    'default' => '',
                    'sanitize_callback' => 'absint'
                ],
                'date_from' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'date_to' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'amount_min' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'amount_max' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'search' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // POST /transactions - Créer une transaction
        register_rest_route(self::API_NAMESPACE, '/transactions', [
            'methods' => 'POST',
            'callback' => [$this, 'create_transaction'],
            'permission_callback' => [$this, 'check_user_permissions'],
            'args' => [
                'date' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'description' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_textarea_field'
                ],
                'amount' => [
                    'required' => true,
                    'sanitize_callback' => 'floatval'
                ],
                'type' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'category_id' => [
                    'sanitize_callback' => 'absint'
                ],
                'source' => [
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // GET /transactions/{id} - Obtenir une transaction
        register_rest_route(self::API_NAMESPACE, '/transactions/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_transaction'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
        
        // PUT /transactions/{id} - Mettre à jour une transaction
        register_rest_route(self::API_NAMESPACE, '/transactions/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_transaction'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
        
        // DELETE /transactions/{id} - Supprimer une transaction
        register_rest_route(self::API_NAMESPACE, '/transactions/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_transaction'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
        
        // POST /transactions/bulk-import - Import en masse
        register_rest_route(self::API_NAMESPACE, '/transactions/bulk-import', [
            'methods' => 'POST',
            'callback' => [$this, 'bulk_import_transactions'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
    }
    
    /**
     * Routes pour les catégories
     */
    private function register_categories_routes() {
        // GET /categories - Lister toutes les catégories
        register_rest_route(self::API_NAMESPACE, '/categories', [
            'methods' => 'GET',
            'callback' => [$this, 'get_categories'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
        
        // POST /categories - Créer une catégorie
        register_rest_route(self::API_NAMESPACE, '/categories', [
            'methods' => 'POST',
            'callback' => [$this, 'create_category'],
            'permission_callback' => [$this, 'check_user_permissions'],
            'args' => [
                'name' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'description' => [
                    'sanitize_callback' => 'sanitize_textarea_field'
                ],
                'color' => [
                    'default' => '#3B82F6',
                    'sanitize_callback' => 'sanitize_hex_color'
                ],
                'icon' => [
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // PUT /categories/{id} - Mettre à jour une catégorie
        register_rest_route(self::API_NAMESPACE, '/categories/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_category'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
        
        // DELETE /categories/{id} - Supprimer une catégorie
        register_rest_route(self::API_NAMESPACE, '/categories/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_category'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
    }
    
    /**
     * Routes pour les factures
     */
    private function register_invoices_routes() {
        // GET /invoices - Lister les factures
        register_rest_route(self::API_NAMESPACE, '/invoices', [
            'methods' => 'GET',
            'callback' => [$this, 'get_invoices'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
        
        // POST /invoices/link - Lier une facture à une transaction
        register_rest_route(self::API_NAMESPACE, '/invoices/link', [
            'methods' => 'POST',
            'callback' => [$this, 'link_invoice_to_transaction'],
            'permission_callback' => [$this, 'check_user_permissions'],
            'args' => [
                'transaction_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ],
                'google_drive_file_id' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'file_name' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'file_url' => [
                    'sanitize_callback' => 'esc_url_raw'
                ]
            ]
        ]);
        
        // POST /invoices/{id}/extract - Extraire les données d'une facture
        register_rest_route(self::API_NAMESPACE, '/invoices/(?P<id>\d+)/extract', [
            'methods' => 'POST',
            'callback' => [$this, 'extract_invoice_data'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
        
        // GET /invoices/{id}/preview - Aperçu d'une facture
        register_rest_route(self::API_NAMESPACE, '/invoices/(?P<id>\d+)/preview', [
            'methods' => 'GET',
            'callback' => [$this, 'preview_invoice'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
    }
    
    /**
     * Routes pour les imports
     */
    private function register_imports_routes() {
        // POST /imports/upload - Upload et analyse de fichier
        register_rest_route(self::API_NAMESPACE, '/imports/upload', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_import_file'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
        
        // GET /imports/{id}/preview - Aperçu des données d'import
        register_rest_route(self::API_NAMESPACE, '/imports/(?P<id>\d+)/preview', [
            'methods' => 'GET',
            'callback' => [$this, 'preview_import_data'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
        
        // POST /imports/{id}/process - Traiter l'import
        register_rest_route(self::API_NAMESPACE, '/imports/(?P<id>\d+)/process', [
            'methods' => 'POST',
            'callback' => [$this, 'process_import'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
        
        // GET /imports - Historique des imports
        register_rest_route(self::API_NAMESPACE, '/imports', [
            'methods' => 'GET',
            'callback' => [$this, 'get_imports_history'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
    }
    
    /**
     * Routes pour les rapports
     */
    private function register_reports_routes() {
        // GET /reports/summary - Résumé financier
        register_rest_route(self::API_NAMESPACE, '/reports/summary', [
            'methods' => 'GET',
            'callback' => [$this, 'get_financial_summary'],
            'permission_callback' => [$this, 'check_user_permissions'],
            'args' => [
                'date_from' => [
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'date_to' => [
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // GET /reports/evolution - Données d'évolution mensuelle
        register_rest_route(self::API_NAMESPACE, '/reports/evolution', [
            'methods' => 'GET',
            'callback' => [$this, 'get_monthly_evolution'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
        
        // GET /reports/categories - Répartition par catégories
        register_rest_route(self::API_NAMESPACE, '/reports/categories', [
            'methods' => 'GET',
            'callback' => [$this, 'get_categories_breakdown'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
        
        // POST /reports/export - Générer un export
        register_rest_route(self::API_NAMESPACE, '/reports/export', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_export'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
    }
    
    /**
     * Routes pour les statistiques
     */
    private function register_stats_routes() {
        // GET /stats/dashboard - Statistiques du tableau de bord
        register_rest_route(self::API_NAMESPACE, '/stats/dashboard', [
            'methods' => 'GET',
            'callback' => [$this, 'get_dashboard_stats'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
        
        // GET /stats/database - Statistiques de la base de données
        register_rest_route(self::API_NAMESPACE, '/stats/database', [
            'methods' => 'GET',
            'callback' => [$this, 'get_database_stats'],
            'permission_callback' => [$this, 'check_admin_permissions']
        ]);
    }
    
    /**
     * Routes pour Google Drive
     */
    private function register_google_drive_routes() {
        // POST /google-drive/auth - Authentification Google Drive
        register_rest_route(self::API_NAMESPACE, '/google-drive/auth', [
            'methods' => 'POST',
            'callback' => [$this, 'google_drive_auth'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
        
        // GET /google-drive/files - Lister les fichiers Google Drive
        register_rest_route(self::API_NAMESPACE, '/google-drive/files', [
            'methods' => 'GET',
            'callback' => [$this, 'get_google_drive_files'],
            'permission_callback' => [$this, 'check_user_permissions'],
            'args' => [
                'folder_id' => [
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'query' => [
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // GET /google-drive/status - Statut de la connexion
        register_rest_route(self::API_NAMESPACE, '/google-drive/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_google_drive_status'],
            'permission_callback' => [$this, 'check_user_permissions']
        ]);
    }
    
    /**
     * Méthodes de callback pour les transactions
     */
    public function get_transactions($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $page = $request->get_param('page');
        $per_page = min($request->get_param('per_page'), 100); // Limite max 100
        $offset = ($page - 1) * $per_page;
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('transactions');
        $categories_table = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        // Construction de la requête avec filtres
        $where_conditions = ["t.user_id = %d", "t.status = 'active'"];
        $where_values = [$user_id];
        
        // Filtres
        if ($request->get_param('type')) {
            $where_conditions[] = "t.type = %s";
            $where_values[] = $request->get_param('type');
        }
        
        if ($request->get_param('category_id')) {
            $where_conditions[] = "t.category_id = %d";
            $where_values[] = $request->get_param('category_id');
        }
        
        if ($request->get_param('date_from')) {
            $where_conditions[] = "t.date >= %s";
            $where_values[] = $request->get_param('date_from');
        }
        
        if ($request->get_param('date_to')) {
            $where_conditions[] = "t.date <= %s";
            $where_values[] = $request->get_param('date_to');
        }
        
        if ($request->get_param('amount_min')) {
            $where_conditions[] = "t.amount >= %f";
            $where_values[] = floatval($request->get_param('amount_min'));
        }
        
        if ($request->get_param('amount_max')) {
            $where_conditions[] = "t.amount <= %f";
            $where_values[] = floatval($request->get_param('amount_max'));
        }
        
        if ($request->get_param('search')) {
            $where_conditions[] = "(t.description LIKE %s OR c.name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($request->get_param('search')) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Requête principale avec jointure pour les catégories
        $sql = "
            SELECT 
                t.*,
                c.name as category_name,
                c.color as category_color,
                c.icon as category_icon,
                (SELECT COUNT(*) FROM " . Financial_Dashboard_Database_Manager::get_table_name('transaction_invoices') . " ti WHERE ti.transaction_id = t.id) as invoice_count
            FROM $table_name t
            LEFT JOIN $categories_table c ON t.category_id = c.id
            WHERE $where_clause
            ORDER BY t.date DESC, t.created_at DESC
            LIMIT %d OFFSET %d
        ";
        
        $where_values[] = $per_page;
        $where_values[] = $offset;
        
        $prepared_sql = $wpdb->prepare($sql, $where_values);
        $transactions = $wpdb->get_results($prepared_sql);
        
        // Compte total pour la pagination
        $count_sql = "
            SELECT COUNT(*)
            FROM $table_name t
            LEFT JOIN $categories_table c ON t.category_id = c.id
            WHERE $where_clause
        ";
        
        $count_values = array_slice($where_values, 0, -2); // Enlever LIMIT et OFFSET
        $total = $wpdb->get_var($wpdb->prepare($count_sql, $count_values));
        
        return new WP_REST_Response([
            'transactions' => $transactions,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => intval($total),
                'total_pages' => ceil($total / $per_page)
            ]
        ], 200);
    }
    
    public function create_transaction($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('transactions');
        
        $data = [
            'user_id' => $user_id,
            'date' => $request->get_param('date'),
            'description' => $request->get_param('description'),
            'amount' => $request->get_param('amount'),
            'type' => $request->get_param('type'),
            'category_id' => $request->get_param('category_id'),
            'source' => $request->get_param('source')
        ];
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la création de la transaction', ['status' => 500]);
        }
        
        $transaction_id = $wpdb->insert_id;
        
        // Récupérer la transaction créée avec les détails de catégorie
        $transaction = $this->get_transaction_with_details($transaction_id);
        
        return new WP_REST_Response([
            'message' => 'Transaction créée avec succès',
            'transaction' => $transaction
        ], 201);
    }
    
    public function get_transaction($request) {
        $transaction_id = $request->get_param('id');
        $transaction = $this->get_transaction_with_details($transaction_id);
        
        if (!$transaction) {
            return new WP_Error('not_found', 'Transaction non trouvée', ['status' => 404]);
        }
        
        // Vérifier que la transaction appartient à l'utilisateur
        if ($transaction->user_id != get_current_user_id()) {
            return new WP_Error('forbidden', 'Accès refusé', ['status' => 403]);
        }
        
        return new WP_REST_Response(['transaction' => $transaction], 200);
    }
    
    public function update_transaction($request) {
        global $wpdb;
        
        $transaction_id = $request->get_param('id');
        $user_id = get_current_user_id();
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('transactions');
        
        // Vérifier que la transaction existe et appartient à l'utilisateur
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
            $transaction_id, $user_id
        ));
        
        if (!$existing) {
            return new WP_Error('not_found', 'Transaction non trouvée', ['status' => 404]);
        }
        
        // Préparer les données à mettre à jour
        $data = [];
        $params = ['date', 'description', 'amount', 'type', 'category_id', 'source'];
        
        foreach ($params as $param) {
            if ($request->has_param($param)) {
                $data[$param] = $request->get_param($param);
            }
        }
        
        if (empty($data)) {
            return new WP_Error('no_data', 'Aucune donnée à mettre à jour', ['status' => 400]);
        }
        
        $result = $wpdb->update($table_name, $data, ['id' => $transaction_id]);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la mise à jour', ['status' => 500]);
        }
        
        $transaction = $this->get_transaction_with_details($transaction_id);
        
        return new WP_REST_Response([
            'message' => 'Transaction mise à jour avec succès',
            'transaction' => $transaction
        ], 200);
    }
    
    public function delete_transaction($request) {
        global $wpdb;
        
        $transaction_id = $request->get_param('id');
        $user_id = get_current_user_id();
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('transactions');
        
        // Soft delete : marquer comme supprimé
        $result = $wpdb->update(
            $table_name,
            ['status' => 'deleted'],
            ['id' => $transaction_id, 'user_id' => $user_id]
        );
        
        if ($result === false || $result === 0) {
            return new WP_Error('not_found', 'Transaction non trouvée', ['status' => 404]);
        }
        
        return new WP_REST_Response([
            'message' => 'Transaction supprimée avec succès'
        ], 200);
    }
    
    /**
     * Méthodes de callback pour les catégories
     */
    public function get_categories($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        // Récupérer les catégories par défaut + celles de l'utilisateur
        $sql = "
            SELECT *, 
                   (SELECT COUNT(*) FROM " . Financial_Dashboard_Database_Manager::get_table_name('transactions') . " t 
                    WHERE t.category_id = c.id AND t.user_id = %d AND t.status = 'active') as transaction_count
            FROM $table_name c
            WHERE (c.type = 'default' OR c.user_id = %d) 
            AND c.is_active = 1
            ORDER BY c.sort_order ASC, c.name ASC
        ";
        
        $categories = $wpdb->get_results($wpdb->prepare($sql, $user_id, $user_id));
        
        return new WP_REST_Response(['categories' => $categories], 200);
    }
    
    public function create_category($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        $name = $request->get_param('name');
        $slug = sanitize_title($name);
        
        // Vérifier l'unicité du slug
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE slug = %s",
            $slug
        ));
        
        if ($existing) {
            $slug = $slug . '-' . time();
        }
        
        $data = [
            'name' => $name,
            'slug' => $slug,
            'description' => $request->get_param('description'),
            'color' => $request->get_param('color') ?: '#3B82F6',
            'icon' => $request->get_param('icon'),
            'type' => 'custom',
            'user_id' => $user_id
        ];
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la création de la catégorie', ['status' => 500]);
        }
        
        $category_id = $wpdb->insert_id;
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $category_id
        ));
        
        return new WP_REST_Response([
            'message' => 'Catégorie créée avec succès',
            'category' => $category
        ], 201);
    }
    
    public function update_category($request) {
        global $wpdb;
        
        $category_id = $request->get_param('id');
        $user_id = get_current_user_id();
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        // Vérifier que la catégorie appartient à l'utilisateur (pas les par défaut)
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d AND type = 'custom'",
            $category_id, $user_id
        ));
        
        if (!$existing) {
            return new WP_Error('forbidden', 'Impossible de modifier cette catégorie', ['status' => 403]);
        }
        
        $data = [];
        $params = ['name', 'description', 'color', 'icon'];
        
        foreach ($params as $param) {
            if ($request->has_param($param)) {
                $data[$param] = $request->get_param($param);
            }
        }
        
        if (isset($data['name'])) {
            $data['slug'] = sanitize_title($data['name']);
        }
        
        $result = $wpdb->update($table_name, $data, ['id' => $category_id]);
        
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $category_id
        ));
        
        return new WP_REST_Response([
            'message' => 'Catégorie mise à jour avec succès',
            'category' => $category
        ], 200);
    }
    
    public function delete_category($request) {
        global $wpdb;
        
        $category_id = $request->get_param('id');
        $user_id = get_current_user_id();
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        // Vérifier que c'est une catégorie personnalisée
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d AND type = 'custom'",
            $category_id, $user_id
        ));
        
        if (!$existing) {
            return new WP_Error('forbidden', 'Impossible de supprimer cette catégorie', ['status' => 403]);
        }
        
        // Vérifier s'il y a des transactions liées
        $transactions_table = Financial_Dashboard_Database_Manager::get_table_name('transactions');
        $transaction_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $transactions_table WHERE category_id = %d AND status = 'active'",
            $category_id
        ));
        
        if ($transaction_count > 0) {
            return new WP_Error('has_transactions', 
                sprintf('Impossible de supprimer cette catégorie car elle contient %d transaction(s)', $transaction_count),
                ['status' => 400]
            );
        }
        
        // Soft delete
        $result = $wpdb->update($table_name, ['is_active' => 0], ['id' => $category_id]);
        
        return new WP_REST_Response([
            'message' => 'Catégorie supprimée avec succès'
        ], 200);
    }
    
    /**
     * Méthodes de callback pour les rapports
     */
    public function get_financial_summary($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('transactions');
        
        $date_from = $request->get_param('date_from') ?: date('Y-01-01');
        $date_to = $request->get_param('date_to') ?: date('Y-m-d');
        
        // Calculs des totaux
        $sql = "
            SELECT 
                type,
                COUNT(*) as count,
                SUM(amount) as total,
                AVG(amount) as average
            FROM $table_name 
            WHERE user_id = %d 
            AND status = 'active'
            AND date BETWEEN %s AND %s
            GROUP BY type
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $user_id, $date_from, $date_to));
        
        $summary = [
            'period' => ['from' => $date_from, 'to' => $date_to],
            'depenses' => ['count' => 0, 'total' => 0, 'average' => 0],
            'revenus' => ['count' => 0, 'total' => 0, 'average' => 0],
            'balance' => 0,
            'margin_percent' => 0
        ];
        
        foreach ($results as $result) {
            $summary[$result->type] = [
                'count' => intval($result->count),
                'total' => floatval($result->total),
                'average' => floatval($result->average)
            ];
        }
        
        $summary['balance'] = $summary['revenus']['total'] - $summary['depenses']['total'];
        
        if ($summary['revenus']['total'] > 0) {
            $summary['margin_percent'] = ($summary['balance'] / $summary['revenus']['total']) * 100;
        }
        
        return new WP_REST_Response(['summary' => $summary], 200);
    }
    
    public function get_monthly_evolution($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('transactions');
        
        $sql = "
            SELECT 
                DATE_FORMAT(date, '%Y-%m') as month,
                type,
                SUM(amount) as total
            FROM $table_name 
            WHERE user_id = %d 
            AND status = 'active'
            AND date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month, type
            ORDER BY month ASC
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $user_id));
        
        // Organiser les données par mois
        $evolution = [];
        foreach ($results as $result) {
            if (!isset($evolution[$result->month])) {
                $evolution[$result->month] = [
                    'month' => $result->month,
                    'depenses' => 0,
                    'revenus' => 0
                ];
            }
            $evolution[$result->month][$result->type] = floatval($result->total);
        }
        
        return new WP_REST_Response(['evolution' => array_values($evolution)], 200);
    }
    
    public function get_categories_breakdown($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $transactions_table = Financial_Dashboard_Database_Manager::get_table_name('transactions');
        $categories_table = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        $sql = "
            SELECT 
                c.name as category_name,
                c.color as category_color,
                COUNT(t.id) as transaction_count,
                SUM(t.amount) as total_amount
            FROM $transactions_table t
            LEFT JOIN $categories_table c ON t.category_id = c.id
            WHERE t.user_id = %d 
            AND t.status = 'active'
            AND t.type = 'depense'
            GROUP BY t.category_id, c.name, c.color
            ORDER BY total_amount DESC
        ";
        
        $breakdown = $wpdb->get_results($wpdb->prepare($sql, $user_id));
        
        // Calculer les pourcentages
        $total = array_sum(array_column($breakdown, 'total_amount'));
        
        foreach ($breakdown as &$item) {
            $item->percentage = $total > 0 ? ($item->total_amount / $total) * 100 : 0;
            $item->total_amount = floatval($item->total_amount);
        }
        
        return new WP_REST_Response(['breakdown' => $breakdown], 200);
    }
    
    /**
     * Méthodes utilitaires
     */
    private function get_transaction_with_details($transaction_id) {
        global $wpdb;
        
        $transactions_table = Financial_Dashboard_Database_Manager::get_table_name('transactions');
        $categories_table = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        $sql = "
            SELECT 
                t.*,
                c.name as category_name,
                c.color as category_color,
                c.icon as category_icon
            FROM $transactions_table t
            LEFT JOIN $categories_table c ON t.category_id = c.id
            WHERE t.id = %d
        ";
        
        return $wpdb->get_row($wpdb->prepare($sql, $transaction_id));
    }
    
    /**
     * Vérifier les permissions utilisateur
     */
    public function check_user_permissions($request) {
        return is_user_logged_in();
    }
    
    /**
     * Vérifier les permissions admin
     */
    public function check_admin_permissions($request) {
        return current_user_can('manage_options');
    }
    
    /**
     * Gestion des erreurs globales
     */
    public function handle_rest_error($result, $server, $request) {
        if (is_wp_error($result)) {
            error_log('Financial Dashboard API Error: ' . $result->get_error_message());
        }
        return $result;
    }
}

// Hook pour gérer les erreurs globales de l'API
add_filter('rest_request_after_callbacks', [new Financial_Dashboard_REST_API(), 'handle_rest_error'], 10, 3);

?>
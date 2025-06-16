<?php
/**
 * Gestionnaire de base de données pour le Dashboard Financier
 * 
 * @package FinancialDashboard
 * @subpackage Database
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour gérer les tables de base de données
 */
class Financial_Dashboard_Database_Manager {
    
    /**
     * Version de la base de données
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Préfixe des tables
     */
    private static function get_table_prefix() {
        global $wpdb;
        return $wpdb->prefix . 'financial_dashboard_';
    }
    
    /**
     * Obtenir le nom complet d'une table
     */
    public static function get_table_name($table) {
        return self::get_table_prefix() . $table;
    }
    
    /**
     * Créer toutes les tables nécessaires
     */
    public static function create_tables() {
        global $wpdb;
        
        // Charger les fonctions WordPress pour dbDelta
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Créer chaque table
        self::create_transactions_table($charset_collate);
        self::create_categories_table($charset_collate);
        self::create_invoices_table($charset_collate);
        self::create_transaction_invoices_table($charset_collate);
        self::create_imports_table($charset_collate);
        self::create_google_drive_tokens_table($charset_collate);
        self::create_reports_table($charset_collate);
        
        // Insérer les données par défaut
        self::insert_default_data();
        
        // Mettre à jour la version de la DB
        update_option('financial_dashboard_db_version', self::DB_VERSION);
        
        // Log de création
        error_log('Tables Financial Dashboard créées avec succès');
    }
    
    /**
     * Table des transactions
     */
    private static function create_transactions_table($charset_collate) {
        global $wpdb;
        
        $table_name = self::get_table_name('transactions');
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            date date NOT NULL,
            description text NOT NULL,
            amount decimal(10,2) NOT NULL,
            type enum('depense','revenu') NOT NULL DEFAULT 'depense',
            category_id bigint(20) UNSIGNED NULL,
            source varchar(255) NULL,
            reference varchar(100) NULL,
            operation_type varchar(100) NULL,
            bank_reference varchar(255) NULL,
            import_id bigint(20) UNSIGNED NULL,
            status enum('active','deleted','archived') NOT NULL DEFAULT 'active',
            metadata longtext NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY date (date),
            KEY type (type),
            KEY category_id (category_id),
            KEY import_id (import_id),
            KEY status (status),
            KEY user_date (user_id, date),
            KEY user_type (user_id, type)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Table des catégories
     */
    private static function create_categories_table($charset_collate) {
        global $wpdb;
        
        $table_name = self::get_table_name('categories');
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            description text NULL,
            color varchar(7) NULL DEFAULT '#3B82F6',
            icon varchar(50) NULL,
            type enum('default','custom') NOT NULL DEFAULT 'custom',
            user_id bigint(20) UNSIGNED NULL,
            parent_id bigint(20) UNSIGNED NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY name (name),
            KEY type (type),
            KEY user_id (user_id),
            KEY parent_id (parent_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Table des factures/documents
     */
    private static function create_invoices_table($charset_collate) {
        global $wpdb;
        
        $table_name = self::get_table_name('invoices');
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            google_drive_file_id varchar(255) NULL,
            file_name varchar(255) NOT NULL,
            file_url varchar(500) NULL,
            file_type varchar(50) NULL,
            file_size bigint(20) NULL,
            invoice_number varchar(100) NULL,
            invoice_date date NULL,
            invoice_amount decimal(10,2) NULL,
            vendor_name varchar(255) NULL,
            extraction_status enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
            extraction_data longtext NULL,
            extraction_confidence decimal(3,2) NULL,
            is_verified tinyint(1) NOT NULL DEFAULT 0,
            notes text NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY google_drive_file_id (google_drive_file_id),
            KEY extraction_status (extraction_status),
            KEY invoice_date (invoice_date),
            KEY vendor_name (vendor_name)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Table de liaison transactions-factures
     */
    private static function create_transaction_invoices_table($charset_collate) {
        global $wpdb;
        
        $table_name = self::get_table_name('transaction_invoices');
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            transaction_id bigint(20) UNSIGNED NOT NULL,
            invoice_id bigint(20) UNSIGNED NOT NULL,
            match_confidence decimal(3,2) NULL,
            match_type enum('manual','automatic','suggested') NOT NULL DEFAULT 'manual',
            verified_by bigint(20) UNSIGNED NULL,
            verified_at datetime NULL,
            notes text NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY transaction_invoice (transaction_id, invoice_id),
            KEY transaction_id (transaction_id),
            KEY invoice_id (invoice_id),
            KEY match_type (match_type),
            KEY verified_by (verified_by)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Table des imports
     */
    private static function create_imports_table($charset_collate) {
        global $wpdb;
        
        $table_name = self::get_table_name('imports');
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            file_name varchar(255) NOT NULL,
            file_type varchar(50) NOT NULL,
            file_size bigint(20) NULL,
            total_rows int(11) NOT NULL DEFAULT 0,
            processed_rows int(11) NOT NULL DEFAULT 0,
            successful_rows int(11) NOT NULL DEFAULT 0,
            failed_rows int(11) NOT NULL DEFAULT 0,
            status enum('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
            mapping_config longtext NULL,
            error_log longtext NULL,
            preview_data longtext NULL,
            import_date datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY import_date (import_date),
            KEY file_type (file_type)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Table des tokens Google Drive
     */
    private static function create_google_drive_tokens_table($charset_collate) {
        global $wpdb;
        
        $table_name = self::get_table_name('google_drive_tokens');
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            access_token text NOT NULL,
            refresh_token text NULL,
            token_type varchar(50) NOT NULL DEFAULT 'Bearer',
            expires_at datetime NOT NULL,
            scope text NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY expires_at (expires_at),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Table des rapports générés
     */
    private static function create_reports_table($charset_collate) {
        global $wpdb;
        
        $table_name = self::get_table_name('reports');
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            type enum('monthly','yearly','custom','category','export') NOT NULL,
            filters longtext NULL,
            data longtext NULL,
            file_path varchar(500) NULL,
            file_size bigint(20) NULL,
            status enum('generating','completed','failed') NOT NULL DEFAULT 'generating',
            generated_at datetime NULL,
            expires_at datetime NULL,
            download_count int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY status (status),
            KEY generated_at (generated_at),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Insérer les données par défaut
     */
    private static function insert_default_data() {
        global $wpdb;
        
        $categories_table = self::get_table_name('categories');
        
        // Vérifier si les catégories par défaut existent déjà
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $categories_table WHERE type = 'default'");
        
        if ($existing == 0) {
            $default_categories = [
                ['name' => 'Salaires', 'slug' => 'salaires', 'color' => '#10B981', 'icon' => 'briefcase'],
                ['name' => 'Alimentation', 'slug' => 'alimentation', 'color' => '#F59E0B', 'icon' => 'utensils'],
                ['name' => 'Transport', 'slug' => 'transport', 'color' => '#3B82F6', 'icon' => 'car'],
                ['name' => 'Logement', 'slug' => 'logement', 'color' => '#8B5CF6', 'icon' => 'home'],
                ['name' => 'Santé', 'slug' => 'sante', 'color' => '#EF4444', 'icon' => 'heart'],
                ['name' => 'Assurances', 'slug' => 'assurances', 'color' => '#06B6D4', 'icon' => 'shield'],
                ['name' => 'Energie', 'slug' => 'energie', 'color' => '#F97316', 'icon' => 'zap'],
                ['name' => 'Télécommunications', 'slug' => 'telecommunications', 'color' => '#6366F1', 'icon' => 'phone'],
                ['name' => 'Banque/Frais', 'slug' => 'banque-frais', 'color' => '#64748B', 'icon' => 'credit-card'],
                ['name' => 'Impôts/Taxes', 'slug' => 'impots-taxes', 'color' => '#DC2626', 'icon' => 'file-text'],
                ['name' => 'Shopping/Achats', 'slug' => 'shopping-achats', 'color' => '#EC4899', 'icon' => 'shopping-bag'],
                ['name' => 'Loisirs', 'slug' => 'loisirs', 'color' => '#14B8A6', 'icon' => 'game-controller'],
                ['name' => 'Éducation', 'slug' => 'education', 'color' => '#F59E0B', 'icon' => 'book'],
                ['name' => 'Épargne/Investissement', 'slug' => 'epargne-investissement', 'color' => '#059669', 'icon' => 'trending-up'],
                ['name' => 'Retraits espèces', 'slug' => 'retraits-especes', 'color' => '#6B7280', 'icon' => 'dollar-sign'],
                ['name' => 'Chèques', 'slug' => 'cheques', 'color' => '#9CA3AF', 'icon' => 'file'],
                ['name' => 'Prélèvements', 'slug' => 'prelevements', 'color' => '#7C3AED', 'icon' => 'repeat'],
                ['name' => 'Virements', 'slug' => 'virements', 'color' => '#2563EB', 'icon' => 'arrow-right'],
                ['name' => 'Autres', 'slug' => 'autres', 'color' => '#64748B', 'icon' => 'more-horizontal']
            ];
            
            foreach ($default_categories as $category) {
                $wpdb->insert(
                    $categories_table,
                    [
                        'name' => $category['name'],
                        'slug' => $category['slug'],
                        'color' => $category['color'],
                        'icon' => $category['icon'],
                        'type' => 'default',
                        'user_id' => null,
                        'is_active' => 1
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%d', '%d']
                );
            }
            
            error_log('Catégories par défaut insérées dans Financial Dashboard');
        }
    }
    
    /**
     * Supprimer toutes les tables (pour la désinstallation)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            'reports',
            'google_drive_tokens',
            'transaction_invoices',
            'imports',
            'invoices',
            'transactions',
            'categories'
        ];
        
        foreach ($tables as $table) {
            $table_name = self::get_table_name($table);
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
        
        // Supprimer les options
        delete_option('financial_dashboard_db_version');
        
        error_log('Tables Financial Dashboard supprimées');
    }
    
    /**
     * Vérifier si les tables existent
     */
    public static function tables_exist() {
        global $wpdb;
        
        $table_name = self::get_table_name('transactions');
        $result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        return $result === $table_name;
    }
    
    /**
     * Mettre à jour la base de données si nécessaire
     */
    public static function maybe_update_database() {
        $current_version = get_option('financial_dashboard_db_version', '0.0.0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::create_tables();
            error_log('Base de données Financial Dashboard mise à jour vers ' . self::DB_VERSION);
        }
    }
    
    /**
     * Obtenir les statistiques de la base de données
     */
    public static function get_database_stats() {
        global $wpdb;
        
        $stats = [];
        
        // Statistiques des transactions
        $transactions_table = self::get_table_name('transactions');
        $stats['transactions'] = [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $transactions_table WHERE status = 'active'"),
            'depenses' => $wpdb->get_var("SELECT COUNT(*) FROM $transactions_table WHERE type = 'depense' AND status = 'active'"),
            'revenus' => $wpdb->get_var("SELECT COUNT(*) FROM $transactions_table WHERE type = 'revenu' AND status = 'active'")
        ];
        
        // Statistiques des catégories
        $categories_table = self::get_table_name('categories');
        $stats['categories'] = [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $categories_table WHERE is_active = 1"),
            'default' => $wpdb->get_var("SELECT COUNT(*) FROM $categories_table WHERE type = 'default' AND is_active = 1"),
            'custom' => $wpdb->get_var("SELECT COUNT(*) FROM $categories_table WHERE type = 'custom' AND is_active = 1")
        ];
        
        // Statistiques des factures
        $invoices_table = self::get_table_name('invoices');
        $stats['invoices'] = [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $invoices_table"),
            'linked' => $wpdb->get_var("
                SELECT COUNT(DISTINCT i.id) 
                FROM $invoices_table i 
                INNER JOIN " . self::get_table_name('transaction_invoices') . " ti ON i.id = ti.invoice_id
            "),
            'extracted' => $wpdb->get_var("SELECT COUNT(*) FROM $invoices_table WHERE extraction_status = 'completed'")
        ];
        
        // Statistiques des imports
        $imports_table = self::get_table_name('imports');
        $stats['imports'] = [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $imports_table"),
            'completed' => $wpdb->get_var("SELECT COUNT(*) FROM $imports_table WHERE status = 'completed'"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $imports_table WHERE status = 'failed'")
        ];
        
        return $stats;
    }
    
    /**
     * Nettoyer les données anciennes
     */
    public static function cleanup_old_data() {
        global $wpdb;
        
        // Supprimer les rapports expirés
        $reports_table = self::get_table_name('reports');
        $wpdb->query("
            DELETE FROM $reports_table 
            WHERE expires_at < NOW() 
            AND expires_at IS NOT NULL
        ");
        
        // Supprimer les tokens Google Drive expirés non actifs
        $tokens_table = self::get_table_name('google_drive_tokens');
        $wpdb->query("
            DELETE FROM $tokens_table 
            WHERE expires_at < NOW() 
            AND is_active = 0
        ");
        
        // Supprimer les imports anciens (plus de 30 jours)
        $imports_table = self::get_table_name('imports');
        $wpdb->query("
            DELETE FROM $imports_table 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) 
            AND status IN ('completed', 'failed', 'cancelled')
        ");
        
        error_log('Nettoyage des données anciennes Financial Dashboard effectué');
    }
}

// Ajouter une tâche cron pour le nettoyage automatique
if (!wp_next_scheduled('financial_dashboard_cleanup')) {
    wp_schedule_event(time(), 'daily', 'financial_dashboard_cleanup');
}

add_action('financial_dashboard_cleanup', array('Financial_Dashboard_Database_Manager', 'cleanup_old_data'));

?>
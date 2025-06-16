<?php
/**
 * Script d'installation automatique
 * 
 * @package FinancialDashboard
 * @subpackage Install
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe d'installation automatique
 */
class Financial_Dashboard_Installer {
    
    /**
     * Version requise de WordPress
     */
    const MIN_WP_VERSION = '5.0';
    
    /**
     * Version requise de PHP
     */
    const MIN_PHP_VERSION = '7.4';
    
    /**
     * Extensions PHP requises
     */
    const REQUIRED_EXTENSIONS = ['json', 'curl', 'openssl'];
    
    /**
     * Exécuter l'installation complète
     */
    public static function run_installation() {
        $results = [];
        
        // Vérifications préalables
        $results['prerequisites'] = self::check_prerequisites();
        
        if (!$results['prerequisites']['success']) {
            return $results;
        }
        
        // Installation de la base de données
        $results['database'] = self::install_database();
        
        // Configuration des options par défaut
        $results['options'] = self::setup_default_options();
        
        // Création des rôles et capacités
        $results['roles'] = self::setup_roles_and_capabilities();
        
        // Configuration des répertoires
        $results['directories'] = self::setup_directories();
        
        // Installation des tâches cron
        $results['cron'] = self::setup_cron_jobs();
        
        // Tests post-installation
        $results['tests'] = self::run_post_install_tests();
        
        // Génération du rapport d'installation
        $results['summary'] = self::generate_install_summary($results);
        
        return $results;
    }
    
    /**
     * Vérifications préalables
     */
    private static function check_prerequisites() {
        $checks = [];
        $success = true;
        
        // Version WordPress
        global $wp_version;
        $wp_check = version_compare($wp_version, self::MIN_WP_VERSION, '>=');
        $checks['wordpress_version'] = [
            'requirement' => 'WordPress >= ' . self::MIN_WP_VERSION,
            'current' => $wp_version,
            'status' => $wp_check ? 'OK' : 'FAIL'
        ];
        if (!$wp_check) $success = false;
        
        // Version PHP
        $php_check = version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=');
        $checks['php_version'] = [
            'requirement' => 'PHP >= ' . self::MIN_PHP_VERSION,
            'current' => PHP_VERSION,
            'status' => $php_check ? 'OK' : 'FAIL'
        ];
        if (!$php_check) $success = false;
        
        // Extensions PHP
        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $ext_check = extension_loaded($extension);
            $checks['php_extensions'][$extension] = [
                'requirement' => "Extension {$extension}",
                'status' => $ext_check ? 'OK' : 'FAIL'
            ];
            if (!$ext_check) $success = false;
        }
        
        // Permissions de fichiers
        $upload_dir = wp_upload_dir();
        $writable = is_writable($upload_dir['basedir']);
        $checks['file_permissions'] = [
            'requirement' => 'Répertoire uploads accessible en écriture',
            'path' => $upload_dir['basedir'],
            'status' => $writable ? 'OK' : 'FAIL'
        ];
        if (!$writable) $success = false;
        
        // Base de données
        global $wpdb;
        $db_check = $wpdb->check_connection();
        $checks['database_connection'] = [
            'requirement' => 'Connexion base de données',
            'status' => $db_check ? 'OK' : 'FAIL'
        ];
        if (!$db_check) $success = false;
        
        return [
            'success' => $success,
            'checks' => $checks,
            'message' => $success ? 'Prérequis satisfaits' : 'Certains prérequis ne sont pas satisfaits'
        ];
    }
    
    /**
     * Installation de la base de données
     */
    private static function install_database() {
        try {
            Financial_Dashboard_Database_Manager::create_tables();
            
            // Vérifier que les tables ont été créées
            $tables_created = Financial_Dashboard_Database_Manager::tables_exist();
            
            if ($tables_created) {
                $stats = Financial_Dashboard_Database_Manager::get_database_stats();
                
                return [
                    'success' => true,
                    'message' => 'Base de données installée avec succès',
                    'tables_created' => 7,
                    'default_categories' => $stats['categories']['default'] ?? 0
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la création des tables'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur base de données: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Configuration des options par défaut
     */
    private static function setup_default_options() {
        $default_options = [
            'financial_dashboard_version' => FINANCIAL_DASHBOARD_VERSION,
            'financial_dashboard_installed_date' => current_time('mysql'),
            'financial_dashboard_default_currency' => 'EUR',
            'financial_dashboard_date_format' => 'Y-m-d',
            'financial_dashboard_enable_notifications' => 1,
            'financial_dashboard_auto_backup' => 1,
            'financial_dashboard_retention_days' => 365
        ];
        
        $added = 0;
        foreach ($default_options as $option => $value) {
            if (add_option($option, $value)) {
                $added++;
            }
        }
        
        return [
            'success' => true,
            'message' => "Options configurées ({$added}/" . count($default_options) . ")",
            'options_set' => $added
        ];
    }
    
    /**
     * Configuration des rôles et capacités
     */
    private static function setup_roles_and_capabilities() {
        $capabilities_added = 0;
        
        // Capacités pour l'administrateur
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_caps = [
                'manage_financial_dashboard',
                'view_financial_reports',
                'import_financial_data',
                'manage_google_drive_integration',
                'export_financial_data'
            ];
            
            foreach ($admin_caps as $cap) {
                $admin_role->add_cap($cap);
                $capabilities_added++;
            }
        }
        
        // Créer un rôle "Financial Manager" si nécessaire
        $manager_role = add_role('financial_manager', 'Financial Manager', [
            'read' => true,
            'view_financial_reports' => true,
            'import_financial_data' => true
        ]);
        
        return [
            'success' => true,
            'message' => 'Rôles et capacités configurés',
            'capabilities_added' => $capabilities_added,
            'roles_created' => $manager_role ? 1 : 0
        ];
    }
    
    /**
     * Configuration des répertoires
     */
    private static function setup_directories() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/financial-dashboard';
        
        $directories = [
            $base_dir,
            $base_dir . '/imports',
            $base_dir . '/exports',
            $base_dir . '/backups',
            $base_dir . '/temp'
        ];
        
        $created = 0;
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                if (wp_mkdir_p($dir)) {
                    $created++;
                    
                    // Ajouter un fichier .htaccess pour sécurité
                    file_put_contents($dir . '/.htaccess', "deny from all\n");
                }
            } else {
                $created++;
            }
        }
        
        return [
            'success' => $created === count($directories),
            'message' => "Répertoires créés ({$created}/" . count($directories) . ")",
            'directories_created' => $created
        ];
    }
    
    /**
     * Configuration des tâches cron
     */
    private static function setup_cron_jobs() {
        $cron_jobs = [
            'financial_dashboard_cleanup' => 'daily',
            'financial_dashboard_backup' => 'weekly',
            'financial_dashboard_stats_update' => 'hourly'
        ];
        
        $scheduled = 0;
        foreach ($cron_jobs as $hook => $recurrence) {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time(), $recurrence, $hook);
                $scheduled++;
            }
        }
        
        return [
            'success' => true,
            'message' => "Tâches cron configurées ({$scheduled})",
            'jobs_scheduled' => $scheduled
        ];
    }
    
    /**
     * Tests post-installation
     */
    private static function run_post_install_tests() {
        $tests = [];
        
        // Test de création d'une transaction
        $test_user_id = 1; // Admin
        $transaction_data = [
            'date' => current_time('Y-m-d'),
            'description' => 'Test installation',
            'amount' => 1.00,
            'type' => 'depense'
        ];
        
        $transaction_id = Financial_Dashboard_Transactions::create_transaction($transaction_data, $test_user_id);
        $tests['transaction_creation'] = [
            'test' => 'Création de transaction',
            'status' => !is_wp_error($transaction_id) ? 'OK' : 'FAIL',
            'details' => is_wp_error($transaction_id) ? $transaction_id->get_error_message() : 'Transaction créée'
        ];
        
        // Nettoyer la transaction de test
        if (!is_wp_error($transaction_id)) {
            Financial_Dashboard_Transactions::delete_transaction($transaction_id, $test_user_id);
        }
        
        // Test de l'API REST
        $routes = rest_get_server()->get_routes();
        $api_routes = array_filter(array_keys($routes), function($route) {
            return strpos($route, '/financial-dashboard/v1/') === 0;
        });
        
        $tests['api_endpoints'] = [
            'test' => 'Endpoints API REST',
            'status' => count($api_routes) > 0 ? 'OK' : 'FAIL',
            'details' => count($api_routes) . ' endpoints enregistrés'
        ];
        
        // Test de la configuration Google Drive
        $google_config = Financial_Dashboard_Google_Drive_Integration::validate_config();
        $tests['google_drive_config'] = [
            'test' => 'Configuration Google Drive',
            'status' => $google_config === true ? 'OK' : 'WARNING',
            'details' => $google_config === true ? 'Configuration complète' : 'Configuration à compléter'
        ];
        
        $success_count = count(array_filter($tests, function($test) {
            return $test['status'] === 'OK';
        }));
        
        return [
            'success' => $success_count === count($tests),
            'tests' => $tests,
            'passed' => $success_count,
            'total' => count($tests)
        ];
    }
    
    /**
     * Générer le résumé d'installation
     */
    private static function generate_install_summary($results) {
        $overall_success = true;
        $summary_points = [];
        
        foreach ($results as $section => $data) {
            if (isset($data['success']) && !$data['success']) {
                $overall_success = false;
            }
            
            if (isset($data['message'])) {
                $summary_points[] = $data['message'];
            }
        }
        
        return [
            'success' => $overall_success,
            'status' => $overall_success ? 'INSTALLATION RÉUSSIE' : 'INSTALLATION AVEC AVERTISSEMENTS',
            'points' => $summary_points,
            'next_steps' => [
                'Configurez vos clés Google Drive dans les paramètres',
                'Créez vos premières catégories personnalisées',
                'Importez vos premiers relevés bancaires',
                'Explorez les fonctionnalités de rapports'
            ],
            'timestamp' => current_time('mysql')
        ];
    }
    
    /**
     * Désinstallation
     */
    public static function uninstall() {
        // Supprimer les tables
        Financial_Dashboard_Database_Manager::drop_tables();
        
        // Supprimer les options
        $options_to_delete = [
            'financial_dashboard_version',
            'financial_dashboard_installed_date',
            'financial_dashboard_default_currency',
            'financial_dashboard_date_format',
            'financial_dashboard_google_client_id',
            'financial_dashboard_google_api_key',
            'financial_dashboard_google_client_secret'
        ];
        
        foreach ($options_to_delete as $option) {
            delete_option($option);
        }
        
        // Supprimer les tâches cron
        wp_clear_scheduled_hook('financial_dashboard_cleanup');
        wp_clear_scheduled_hook('financial_dashboard_backup');
        wp_clear_scheduled_hook('financial_dashboard_stats_update');
        
        // Supprimer les capacités
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->remove_cap('manage_financial_dashboard');
            $admin_role->remove_cap('view_financial_reports');
            $admin_role->remove_cap('import_financial_data');
        }
        
        // Supprimer le rôle personnalisé
        remove_role('financial_manager');
        
        // Supprimer les répertoires (optionnel, peut contenir des données utilisateur)
        // self::cleanup_directories();
        
        return true;
    }
    
    /**
     * Nettoyer les répertoires
     */
    private static function cleanup_directories() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/financial-dashboard';
        
        if (file_exists($base_dir)) {
            self::delete_directory($base_dir);
        }
    }
    
    /**
     * Supprimer un répertoire récursivement
     */
    private static function delete_directory($dir) {
        if (!file_exists($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
}

// Hooks d'activation et désactivation
register_activation_hook(FINANCIAL_DASHBOARD_PLUGIN_BASENAME, function() {
    $results = Financial_Dashboard_Installer::run_installation();
    
    // Sauvegarder les résultats d'installation
    update_option('financial_dashboard_install_results', $results);
    
    // Rediriger vers la page de bienvenue après activation
    add_option('financial_dashboard_redirect_to_welcome', true);
});

register_uninstall_hook(FINANCIAL_DASHBOARD_PLUGIN_BASENAME, function() {
    Financial_Dashboard_Installer::uninstall();
});

// Hook pour redirection après activation
add_action('admin_init', function() {
    if (get_option('financial_dashboard_redirect_to_welcome')) {
        delete_option('financial_dashboard_redirect_to_welcome');
        
        if (!isset($_GET['activate-multi'])) {
            wp_redirect(admin_url('admin.php?page=financial-dashboard&welcome=1'));
            exit;
        }
    }
});

?>
<?php
/**
 * Fonctions de gestion des catégories
 * 
 * @package FinancialDashboard
 * @subpackage Functions
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de gestion des catégories
 */
class Financial_Dashboard_Categories {
    
    /**
     * Obtenir toutes les catégories d'un utilisateur
     */
    public static function get_user_categories($user_id = null, $include_stats = false) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        $sql = "
            SELECT c.*" . ($include_stats ? ",
                   (SELECT COUNT(*) FROM " . Financial_Dashboard_Database_Manager::get_table_name('transactions') . " t 
                    WHERE t.category_id = c.id AND t.user_id = %d AND t.status = 'active') as transaction_count,
                   (SELECT SUM(t.amount) FROM " . Financial_Dashboard_Database_Manager::get_table_name('transactions') . " t 
                    WHERE t.category_id = c.id AND t.user_id = %d AND t.status = 'active' AND t.type = 'depense') as total_amount" : "") . "
            FROM $table_name c
            WHERE (c.type = 'default' OR c.user_id = %d) 
            AND c.is_active = 1
            ORDER BY c.sort_order ASC, c.name ASC
        ";
        
        if ($include_stats) {
            $categories = $wpdb->get_results($wpdb->prepare($sql, $user_id, $user_id, $user_id));
        } else {
            $categories = $wpdb->get_results($wpdb->prepare($sql, $user_id));
        }
        
        return $categories;
    }
    
    /**
     * Créer une nouvelle catégorie personnalisée
     */
    public static function create_category($data, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Validation des données
        $validated_data = self::validate_category_data($data);
        if (is_wp_error($validated_data)) {
            return $validated_data;
        }
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        // Générer un slug unique
        $slug = sanitize_title($validated_data['name']);
        $original_slug = $slug;
        $counter = 1;
        
        while (self::slug_exists($slug)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        $category_data = [
            'name' => $validated_data['name'],
            'slug' => $slug,
            'description' => $validated_data['description'] ?? '',
            'color' => $validated_data['color'] ?? '#3B82F6',
            'icon' => $validated_data['icon'] ?? 'folder',
            'type' => 'custom',
            'user_id' => $user_id,
            'parent_id' => $validated_data['parent_id'] ?? null,
            'sort_order' => self::get_next_sort_order($user_id)
        ];
        
        $result = $wpdb->insert($table_name, $category_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la création de la catégorie');
        }
        
        $category_id = $wpdb->insert_id;
        
        // Hook pour extensions
        do_action('financial_dashboard_category_created', $category_id, $category_data);
        
        return $category_id;
    }
    
    /**
     * Mettre à jour une catégorie
     */
    public static function update_category($category_id, $data, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Vérifier que c'est une catégorie personnalisée de l'utilisateur
        if (!self::user_owns_category($category_id, $user_id)) {
            return new WP_Error('forbidden', 'Impossible de modifier cette catégorie');
        }
        
        // Validation des données
        $validated_data = self::validate_category_data($data, false);
        if (is_wp_error($validated_data)) {
            return $validated_data;
        }
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        $update_data = [];
        $allowed_fields = ['name', 'description', 'color', 'icon', 'parent_id', 'sort_order'];
        
        foreach ($allowed_fields as $field) {
            if (isset($validated_data[$field])) {
                $update_data[$field] = $validated_data[$field];
            }
        }
        
        // Mettre à jour le slug si le nom change
        if (isset($validated_data['name'])) {
            $new_slug = sanitize_title($validated_data['name']);
            
            // Vérifier l'unicité du nouveau slug
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE slug = %s AND id != %d",
                $new_slug, $category_id
            ));
            
            if (!$existing) {
                $update_data['slug'] = $new_slug;
            }
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_data', 'Aucune donnée valide à mettre à jour');
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $category_id]
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la mise à jour de la catégorie');
        }
        
        // Hook pour extensions
        do_action('financial_dashboard_category_updated', $category_id, $update_data);
        
        return true;
    }
    
    /**
     * Supprimer une catégorie (soft delete)
     */
    public static function delete_category($category_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Vérifier que c'est une catégorie personnalisée de l'utilisateur
        if (!self::user_owns_category($category_id, $user_id)) {
            return new WP_Error('forbidden', 'Impossible de supprimer cette catégorie');
        }
        
        // Vérifier s'il y a des transactions liées
        $transaction_count = self::get_category_transaction_count($category_id, $user_id);
        if ($transaction_count > 0) {
            return new WP_Error('has_transactions', 
                sprintf('Impossible de supprimer cette catégorie car elle contient %d transaction(s)', $transaction_count)
            );
        }
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        $result = $wpdb->update(
            $table_name,
            ['is_active' => 0],
            ['id' => $category_id]
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la suppression de la catégorie');
        }
        
        // Hook pour extensions
        do_action('financial_dashboard_category_deleted', $category_id);
        
        return true;
    }
    
    /**
     * Obtenir une catégorie par ID
     */
    public static function get_category($category_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        $sql = "
            SELECT * FROM $table_name 
            WHERE id = %d 
            AND (type = 'default' OR user_id = %d)
            AND is_active = 1
        ";
        
        return $wpdb->get_row($wpdb->prepare($sql, $category_id, $user_id));
    }
    
    /**
     * Réorganiser l'ordre des catégories
     */
    public static function reorder_categories($category_orders, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        foreach ($category_orders as $category_id => $sort_order) {
            // Vérifier que l'utilisateur possède la catégorie
            if (self::user_owns_category($category_id, $user_id)) {
                $wpdb->update(
                    $table_name,
                    ['sort_order' => intval($sort_order)],
                    ['id' => $category_id]
                );
            }
        }
        
        return true;
    }
    
    /**
     * Obtenir les statistiques d'utilisation des catégories
     */
    public static function get_category_usage_stats($user_id = null, $date_from = null, $date_to = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $transactions_table = Financial_Dashboard_Database_Manager::get_table_name('transactions');
        $categories_table = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        $where_conditions = ["t.user_id = %d", "t.status = 'active'"];
        $where_values = [$user_id];
        
        if ($date_from) {
            $where_conditions[] = "t.date >= %s";
            $where_values[] = $date_from;
        }
        
        if ($date_to) {
            $where_conditions[] = "t.date <= %s";
            $where_values[] = $date_to;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "
            SELECT 
                c.id,
                c.name,
                c.color,
                c.icon,
                COUNT(t.id) as transaction_count,
                SUM(CASE WHEN t.type = 'depense' THEN t.amount ELSE 0 END) as total_depenses,
                SUM(CASE WHEN t.type = 'revenu' THEN t.amount ELSE 0 END) as total_revenus,
                AVG(t.amount) as average_amount
            FROM $categories_table c
            LEFT JOIN $transactions_table t ON c.id = t.category_id AND $where_clause
            WHERE (c.type = 'default' OR c.user_id = %d) AND c.is_active = 1
            GROUP BY c.id, c.name, c.color, c.icon
            HAVING transaction_count > 0
            ORDER BY total_depenses DESC
        ";
        
        $where_values[] = $user_id;
        
        return $wpdb->get_results($wpdb->prepare($sql, $where_values));
    }
    
    /**
     * Validation des données de catégorie
     */
    private static function validate_category_data($data, $required_all = true) {
        $errors = [];
        $validated = [];
        
        // Nom
        if ($required_all && empty($data['name'])) {
            $errors[] = 'Le nom est requis';
        } elseif (!empty($data['name'])) {
            $name = sanitize_text_field($data['name']);
            if (strlen($name) < 2) {
                $errors[] = 'Le nom doit contenir au moins 2 caractères';
            } elseif (strlen($name) > 100) {
                $errors[] = 'Le nom ne peut pas dépasser 100 caractères';
            } else {
                $validated['name'] = $name;
            }
        }
        
        // Description (optionnelle)
        if (!empty($data['description'])) {
            $validated['description'] = sanitize_textarea_field($data['description']);
        }
        
        // Couleur
        if (!empty($data['color'])) {
            $color = sanitize_hex_color($data['color']);
            if (!$color) {
                $errors[] = 'Format de couleur invalide';
            } else {
                $validated['color'] = $color;
            }
        }
        
        // Icône (optionnelle)
        if (!empty($data['icon'])) {
            $validated['icon'] = sanitize_text_field($data['icon']);
        }
        
        // Catégorie parente (optionnelle)
        if (!empty($data['parent_id'])) {
            $validated['parent_id'] = absint($data['parent_id']);
        }
        
        // Ordre de tri (optionnel)
        if (isset($data['sort_order'])) {
            $validated['sort_order'] = absint($data['sort_order']);
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_error', implode(', ', $errors));
        }
        
        return $validated;
    }
    
    /**
     * Vérifier si un slug existe déjà
     */
    private static function slug_exists($slug) {
        global $wpdb;
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE slug = %s",
            $slug
        ));
        
        return $count > 0;
    }
    
    /**
     * Obtenir le prochain ordre de tri
     */
    private static function get_next_sort_order($user_id) {
        global $wpdb;
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        $max_order = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(sort_order) FROM $table_name WHERE user_id = %d OR type = 'default'",
            $user_id
        ));
        
        return ($max_order ? $max_order : 0) + 10;
    }
    
    /**
     * Vérifier si un utilisateur possède une catégorie
     */
    private static function user_owns_category($category_id, $user_id) {
        global $wpdb;
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('categories');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE id = %d AND user_id = %d AND type = 'custom'",
            $category_id, $user_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Compter les transactions d'une catégorie
     */
    private static function get_category_transaction_count($category_id, $user_id) {
        global $wpdb;
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('transactions');
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE category_id = %d AND user_id = %d AND status = 'active'",
            $category_id, $user_id
        ));
    }
    
    /**
     * Exporter les catégories
     */
    public static function export_categories($user_id = null) {
        $categories = self::get_user_categories($user_id, true);
        
        $export_data = [];
        foreach ($categories as $category) {
            $export_data[] = [
                'nom' => $category->name,
                'description' => $category->description,
                'couleur' => $category->color,
                'icone' => $category->icon,
                'type' => $category->type,
                'transactions' => $category->transaction_count ?? 0,
                'montant_total' => $category->total_amount ?? 0
            ];
        }
        
        return $export_data;
    }
}

?>
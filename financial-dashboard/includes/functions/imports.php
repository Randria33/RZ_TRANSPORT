<?php
/**
 * Fonctions de gestion des imports de fichiers bancaires
 * 
 * @package FinancialDashboard
 * @subpackage Functions
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de gestion des imports
 */
class Financial_Dashboard_Imports {
    
    /**
     * Types de fichiers supportés
     */
    const SUPPORTED_TYPES = ['xls', 'xlsx', 'csv', 'qif'];
    
    /**
     * Taille maximale de fichier (en bytes)
     */
    const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    
    /**
     * Traiter l'upload d'un fichier d'import
     */
    public static function handle_file_upload($file_data, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Validation du fichier uploadé
        $validation = self::validate_upload_file($file_data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Créer l'enregistrement d'import
        $import_id = self::create_import_record($file_data, $user_id);
        if (is_wp_error($import_id)) {
            return $import_id;
        }
        
        // Traiter le fichier selon son type
        $extension = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
        
        try {
            switch ($extension) {
                case 'xls':
                case 'xlsx':
                    $preview_data = self::process_excel_file($file_data['tmp_name'], $import_id);
                    break;
                    
                case 'csv':
                    $preview_data = self::process_csv_file($file_data['tmp_name'], $import_id);
                    break;
                    
                case 'qif':
                    $preview_data = self::process_qif_file($file_data['tmp_name'], $import_id);
                    break;
                    
                default:
                    return new WP_Error('unsupported_type', 'Type de fichier non supporté');
            }
            
            // Mettre à jour l'enregistrement avec les données de preview
            self::update_import_preview($import_id, $preview_data);
            
            return [
                'import_id' => $import_id,
                'preview_data' => $preview_data,
                'stats' => [
                    'total_rows' => count($preview_data),
                    'detected_transactions' => count(array_filter($preview_data, function($row) {
                        return !empty($row['amount']) && !empty($row['description']);
                    }))
                ]
            ];
            
        } catch (Exception $e) {
            self::update_import_status($import_id, 'failed', $e->getMessage());
            return new WP_Error('processing_error', 'Erreur lors du traitement: ' . $e->getMessage());
        }
    }
    
    /**
     * Traiter un fichier Excel
     */
    private static function process_excel_file($file_path, $import_id) {
        // Utiliser une librairie PHP pour lire Excel (simulation ici)
        // En production, utiliser PhpSpreadsheet ou similar
        
        $preview_data = [];
        
        // Simulation de lecture Excel
        // Dans la vraie implémentation, utiliser PhpSpreadsheet
        $sample_data = [
            [
                'Date d\'opération' => '2025-06-13',
                'Type de l\'opération' => 'CARTE',
                'Montant' => '-25.50',
                'Détail 1' => 'RESTAURANT ABC',
                'Détail 2' => 'PARIS',
                'Référence de l\'opération' => 'REF123'
            ],
            [
                'Date d\'opération' => '2025-06-12',
                'Type de l\'opération' => 'VIREMENT',
                'Montant' => '2500.00',
                'Détail 1' => 'SALAIRE ENTREPRISE XYZ',
                'Détail 2' => 'VIREMENT INSTANTANE',
                'Référence de l\'opération' => 'REF124'
            ]
        ];
        
        foreach ($sample_data as $index => $row) {
            $processed_row = self::process_bank_row($row, $index);
            if ($processed_row) {
                $preview_data[] = $processed_row;
            }
        }
        
        return $preview_data;
    }
    
    /**
     * Traiter un fichier CSV
     */
    private static function process_csv_file($file_path, $import_id) {
        $preview_data = [];
        
        if (($handle = fopen($file_path, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ",");
            $row_index = 0;
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE && $row_index < 100) {
                if (count($data) == count($headers)) {
                    $row = array_combine($headers, $data);
                    $processed_row = self::process_bank_row($row, $row_index);
                    
                    if ($processed_row) {
                        $preview_data[] = $processed_row;
                    }
                }
                $row_index++;
            }
            fclose($handle);
        }
        
        return $preview_data;
    }
    
    /**
     * Traiter un fichier QIF
     */
    private static function process_qif_file($file_path, $import_id) {
        $content = file_get_contents($file_path);
        $lines = explode("\n", $content);
        
        $preview_data = [];
        $current_transaction = [];
        $index = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) continue;
            
            $code = substr($line, 0, 1);
            $value = substr($line, 1);
            
            switch ($code) {
                case 'D': // Date
                    $current_transaction['date'] = $value;
                    break;
                case 'T': // Montant
                case '$':
                    $current_transaction['amount'] = $value;
                    break;
                case 'P': // Payee/Description
                    $current_transaction['description'] = $value;
                    break;
                case 'L': // Catégorie
                    $current_transaction['category'] = $value;
                    break;
                case 'M': // Memo
                    $current_transaction['memo'] = $value;
                    break;
                case '^': // Fin de transaction
                    if (!empty($current_transaction)) {
                        $processed_row = self::process_qif_transaction($current_transaction, $index);
                        if ($processed_row) {
                            $preview_data[] = $processed_row;
                        }
                        $index++;
                    }
                    $current_transaction = [];
                    break;
            }
        }
        
        return $preview_data;
    }
    
    /**
     * Traiter une ligne de données bancaires
     */
    private static function process_bank_row($row, $index) {
        // Détecter les colonnes automatiquement
        $date = self::extract_date_from_row($row);
        $amount = self::extract_amount_from_row($row);
        $description = self::extract_description_from_row($row);
        $operation_type = self::extract_operation_type_from_row($row);
        $reference = self::extract_reference_from_row($row);
        
        if (!$date || $amount === null || !$description) {
            return null; // Ligne invalide
        }
        
        $amount_float = floatval(str_replace(',', '.', str_replace(' ', '', $amount)));
        $type = $amount_float < 0 ? 'depense' : 'revenu';
        $amount_abs = abs($amount_float);
        
        // Catégorisation automatique
        $category_id = null;
        if ($type === 'depense') {
            $category_id = Financial_Dashboard_Transactions::auto_categorize_transaction(
                $operation_type, 
                $description, 
                $amount_abs
            );
        }
        
        return [
            'row_index' => $index,
            'date' => self::format_date($date),
            'description' => sanitize_text_field($description),
            'amount' => $amount_abs,
            'type' => $type,
            'category_id' => $category_id,
            'operation_type' => sanitize_text_field($operation_type),
            'reference' => sanitize_text_field($reference),
            'original_amount' => $amount_float,
            'raw_data' => $row
        ];
    }
    
    /**
     * Traiter une transaction QIF
     */
    private static function process_qif_transaction($transaction, $index) {
        if (!isset($transaction['date']) || !isset($transaction['amount']) || !isset($transaction['description'])) {
            return null;
        }
        
        $amount_float = floatval($transaction['amount']);
        $type = $amount_float < 0 ? 'depense' : 'revenu';
        $amount_abs = abs($amount_float);
        
        $category_id = null;
        if ($type === 'depense' && isset($transaction['category'])) {
            // Essayer de mapper avec les catégories existantes
            $category_id = self::map_qif_category($transaction['category']);
        }
        
        return [
            'row_index' => $index,
            'date' => self::format_date($transaction['date']),
            'description' => sanitize_text_field($transaction['description']),
            'amount' => $amount_abs,
            'type' => $type,
            'category_id' => $category_id,
            'memo' => isset($transaction['memo']) ? sanitize_text_field($transaction['memo']) : '',
            'qif_category' => isset($transaction['category']) ? sanitize_text_field($transaction['category']) : '',
            'original_amount' => $amount_float
        ];
    }
    
    /**
     * Confirmer et traiter l'import final
     */
    public static function process_import($import_id, $confirmed_data, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Vérifier que l'import appartient à l'utilisateur
        $import_record = self::get_import_record($import_id, $user_id);
        if (!$import_record) {
            return new WP_Error('not_found', 'Import non trouvé');
        }
        
        if ($import_record->status !== 'pending') {
            return new WP_Error('invalid_status', 'Cet import a déjà été traité');
        }
        
        // Mettre à jour le statut
        self::update_import_status($import_id, 'processing');
        
        $imported_count = 0;
        $failed_count = 0;
        $errors = [];
        
        foreach ($confirmed_data as $index => $transaction_data) {
            // Préparer les données pour la création de transaction
            $clean_data = [
                'date' => $transaction_data['date'],
                'description' => $transaction_data['description'],
                'amount' => $transaction_data['amount'],
                'type' => $transaction_data['type'],
                'category_id' => $transaction_data['category_id'] ?? null,
                'source' => $transaction_data['type'] === 'revenu' ? 'Import bancaire' : null,
                'operation_type' => $transaction_data['operation_type'] ?? null,
                'reference' => $transaction_data['reference'] ?? null,
                'import_id' => $import_id,
                'metadata' => [
                    'import_row' => $transaction_data['row_index'] ?? $index,
                    'original_amount' => $transaction_data['original_amount'] ?? $transaction_data['amount']
                ]
            ];
            
            $result = Financial_Dashboard_Transactions::create_transaction($clean_data, $user_id);
            
            if (is_wp_error($result)) {
                $failed_count++;
                $errors[] = "Ligne " . ($index + 1) . ": " . $result->get_error_message();
            } else {
                $imported_count++;
            }
        }
        
        // Mettre à jour les statistiques de l'import
        global $wpdb;
        $imports_table = Financial_Dashboard_Database_Manager::get_table_name('imports');
        
        $wpdb->update(
            $imports_table,
            [
                'status' => $failed_count > 0 && $imported_count === 0 ? 'failed' : 'completed',
                'processed_rows' => $imported_count + $failed_count,
                'successful_rows' => $imported_count,
                'failed_rows' => $failed_count,
                'error_log' => !empty($errors) ? json_encode($errors) : null,
                'completed_at' => current_time('mysql')
            ],
            ['id' => $import_id]
        );
        
        // Hook pour extensions
        do_action('financial_dashboard_import_completed', $import_id, $imported_count, $failed_count);
        
        return [
            'import_id' => $import_id,
            'imported' => $imported_count,
            'failed' => $failed_count,
            'errors' => $errors,
            'status' => $failed_count > 0 && $imported_count === 0 ? 'failed' : 'completed'
        ];
    }
    
    /**
     * Obtenir l'historique des imports
     */
    public static function get_imports_history($user_id = null, $limit = 20) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('imports');
        
        $sql = "
            SELECT *
            FROM $table_name 
            WHERE user_id = %d
            ORDER BY created_at DESC
            LIMIT %d
        ";
        
        $imports = $wpdb->get_results($wpdb->prepare($sql, $user_id, $limit));
        
        // Décoder les données JSON
        foreach ($imports as &$import) {
            if ($import->error_log) {
                $import->error_log = json_decode($import->error_log, true);
            }
            if ($import->preview_data) {
                $import->preview_data = json_decode($import->preview_data, true);
            }
            if ($import->mapping_config) {
                $import->mapping_config = json_decode($import->mapping_config, true);
            }
        }
        
        return $imports;
    }
    
    /**
     * Supprimer un import et ses transactions associées
     */
    public static function delete_import($import_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Vérifier que l'import appartient à l'utilisateur
        $import_record = self::get_import_record($import_id, $user_id);
        if (!$import_record) {
            return new WP_Error('not_found', 'Import non trouvé');
        }
        
        // Supprimer les transactions associées (soft delete)
        $transactions_table = Financial_Dashboard_Database_Manager::get_table_name('transactions');
        $wpdb->update(
            $transactions_table,
            ['status' => 'deleted'],
            ['import_id' => $import_id, 'user_id' => $user_id]
        );
        
        // Supprimer l'enregistrement d'import
        $imports_table = Financial_Dashboard_Database_Manager::get_table_name('imports');
        $wpdb->update(
            $imports_table,
            ['status' => 'cancelled'],
            ['id' => $import_id, 'user_id' => $user_id]
        );
        
        return true;
    }
    
    /**
     * Fonctions utilitaires privées
     */
    
    private static function validate_upload_file($file_data) {
        // Vérifier les erreurs d'upload
        if ($file_data['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'Erreur lors de l\'upload du fichier');
        }
        
        // Vérifier la taille
        if ($file_data['size'] > self::MAX_FILE_SIZE) {
            return new WP_Error('file_too_large', 'Le fichier est trop volumineux (max 10MB)');
        }
        
        // Vérifier l'extension
        $extension = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::SUPPORTED_TYPES)) {
            return new WP_Error('unsupported_type', 'Type de fichier non supporté. Types acceptés: ' . implode(', ', self::SUPPORTED_TYPES));
        }
        
        return true;
    }
    
    private static function create_import_record($file_data, $user_id) {
        global $wpdb;
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('imports');
        
        $import_data = [
            'user_id' => $user_id,
            'file_name' => sanitize_file_name($file_data['name']),
            'file_type' => strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION)),
            'file_size' => $file_data['size'],
            'status' => 'pending'
        ];
        
        $result = $wpdb->insert($table_name, $import_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la création de l\'enregistrement d\'import');
        }
        
        return $wpdb->insert_id;
    }
    
    private static function update_import_preview($import_id, $preview_data) {
        global $wpdb;
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('imports');
        
        $wpdb->update(
            $table_name,
            [
                'total_rows' => count($preview_data),
                'preview_data' => json_encode(array_slice($preview_data, 0, 10)) // Stocker seulement les 10 premiers
            ],
            ['id' => $import_id]
        );
    }
    
    private static function update_import_status($import_id, $status, $error_message = null) {
        global $wpdb;
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('imports');
        
        $update_data = ['status' => $status];
        
        if ($error_message) {
            $update_data['error_log'] = json_encode([$error_message]);
        }
        
        $wpdb->update($table_name, $update_data, ['id' => $import_id]);
    }
    
    private static function get_import_record($import_id, $user_id) {
        global $wpdb;
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('imports');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
            $import_id, $user_id
        ));
    }
    
    private static function extract_date_from_row($row) {
        $date_fields = ['Date d\'opération', 'Date', 'date', 'Date opération', 'Date de valeur'];
        
        foreach ($date_fields as $field) {
            if (isset($row[$field]) && !empty($row[$field])) {
                return $row[$field];
            }
        }
        
        return null;
    }
    
    private static function extract_amount_from_row($row) {
        $amount_fields = ['Montant', 'montant', 'Amount', 'Débit', 'Crédit', 'Solde'];
        
        foreach ($amount_fields as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                return $row[$field];
            }
        }
        
        return null;
    }
    
    private static function extract_description_from_row($row) {
        $description_fields = ['Détail 1', 'Description', 'description', 'Libellé', 'libelle', 'Payee'];
        
        $description_parts = [];
        
        // Essayer plusieurs champs et les combiner
        for ($i = 1; $i <= 6; $i++) {
            $field = 'Détail ' . $i;
            if (isset($row[$field]) && !empty(trim($row[$field]))) {
                $description_parts[] = trim($row[$field]);
            }
        }
        
        if (!empty($description_parts)) {
            return implode(' ', $description_parts);
        }
        
        // Fallback sur les champs classiques
        foreach ($description_fields as $field) {
            if (isset($row[$field]) && !empty(trim($row[$field]))) {
                return trim($row[$field]);
            }
        }
        
        return null;
    }
    
    private static function extract_operation_type_from_row($row) {
        $type_fields = ['Type de l\'opération', 'Type', 'type', 'Opération', 'Mode'];
        
        foreach ($type_fields as $field) {
            if (isset($row[$field]) && !empty($row[$field])) {
                return $row[$field];
            }
        }
        
        return '';
    }
    
    private static function extract_reference_from_row($row) {
        $ref_fields = ['Référence de l\'opération', 'Référence', 'reference', 'Ref', 'ID'];
        
        foreach ($ref_fields as $field) {
            if (isset($row[$field]) && !empty($row[$field])) {
                return $row[$field];
            }
        }
        
        return '';
    }
    
    private static function format_date($date_string) {
        if ($date_string instanceof DateTime) {
            return $date_string->format('Y-m-d');
        }
        
        // Essayer différents formats de date
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d'];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $date_string);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        // Fallback: essayer strtotime
        $timestamp = strtotime($date_string);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        return date('Y-m-d'); // Date actuelle en fallback
    }
    
    private static function map_qif_category($qif_category) {
        // Mapping des catégories QIF vers nos catégories
        $mapping = [
            'Food' => 'Alimentation',
            'Gas' => 'Transport',
            'Groceries' => 'Alimentation',
            'Restaurant' => 'Alimentation',
            'Salary' => 'Salaires',
            'Utilities' => 'Energie',
            'Phone' => 'Télécommunications',
            'Insurance' => 'Assurances',
            'Medical' => 'Santé',
            'Shopping' => 'Shopping/Achats',
            'Entertainment' => 'Loisirs',
            'Education' => 'Éducation',
            'Investment' => 'Épargne/Investissement',
            'Tax' => 'Impôts/Taxes',
            'Bank Fee' => 'Banque/Frais'
        ];
        
        if (isset($mapping[$qif_category])) {
            return Financial_Dashboard_Transactions::auto_categorize_transaction('', $mapping[$qif_category]);
        }
        
        return null;
    }
}

?>
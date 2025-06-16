<?php
/**
 * Fonctions de gestion des factures et documents
 * 
 * @package FinancialDashboard
 * @subpackage Functions
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de gestion des factures
 */
class Financial_Dashboard_Invoices {
    
    /**
     * Lier une facture Google Drive à une transaction
     */
    public static function link_invoice_to_transaction($transaction_id, $google_drive_data, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Vérifier que la transaction appartient à l'utilisateur
        $transaction = Financial_Dashboard_Transactions::get_transaction($transaction_id, $user_id);
        if (!$transaction) {
            return new WP_Error('not_found', 'Transaction non trouvée');
        }
        
        // Créer ou récupérer l'enregistrement de facture
        $invoice_id = self::create_or_get_invoice($google_drive_data, $user_id);
        if (is_wp_error($invoice_id)) {
            return $invoice_id;
        }
        
        // Créer la liaison transaction-facture
        $link_id = self::create_transaction_invoice_link($transaction_id, $invoice_id, 'manual', $user_id);
        if (is_wp_error($link_id)) {
            return $link_id;
        }
        
        // Hook pour extensions
        do_action('financial_dashboard_invoice_linked', $transaction_id, $invoice_id, $link_id);
        
        return [
            'invoice_id' => $invoice_id,
            'link_id' => $link_id,
            'message' => 'Facture liée avec succès'
        ];
    }
    
    /**
     * Créer ou récupérer une facture
     */
    private static function create_or_get_invoice($google_drive_data, $user_id) {
        global $wpdb;
        
        $invoices_table = Financial_Dashboard_Database_Manager::get_table_name('invoices');
        
        // Vérifier si la facture existe déjà
        $existing_invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $invoices_table WHERE google_drive_file_id = %s AND user_id = %d",
            $google_drive_data['file_id'], $user_id
        ));
        
        if ($existing_invoice) {
            return $existing_invoice->id;
        }
        
        // Créer une nouvelle facture
        $invoice_data = [
            'user_id' => $user_id,
            'google_drive_file_id' => sanitize_text_field($google_drive_data['file_id']),
            'file_name' => sanitize_text_field($google_drive_data['file_name']),
            'file_url' => esc_url_raw($google_drive_data['file_url'] ?? ''),
            'file_type' => self::get_file_type_from_name($google_drive_data['file_name']),
            'file_size' => isset($google_drive_data['file_size']) ? absint($google_drive_data['file_size']) : null,
            'extraction_status' => 'pending'
        ];
        
        $result = $wpdb->insert($invoices_table, $invoice_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la création de la facture');
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Créer une liaison transaction-facture
     */
    private static function create_transaction_invoice_link($transaction_id, $invoice_id, $match_type = 'manual', $user_id = null) {
        global $wpdb;
        
        $links_table = Financial_Dashboard_Database_Manager::get_table_name('transaction_invoices');
        
        // Vérifier si la liaison existe déjà
        $existing_link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $links_table WHERE transaction_id = %d AND invoice_id = %d",
            $transaction_id, $invoice_id
        ));
        
        if ($existing_link) {
            return $existing_link->id;
        }
        
        $link_data = [
            'transaction_id' => $transaction_id,
            'invoice_id' => $invoice_id,
            'match_type' => $match_type,
            'match_confidence' => $match_type === 'manual' ? 1.0 : 0.8
        ];
        
        $result = $wpdb->insert($links_table, $link_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la création de la liaison');
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Extraire les données d'une facture
     */
    public static function extract_invoice_data($invoice_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Récupérer la facture
        $invoice = self::get_invoice($invoice_id, $user_id);
        if (!$invoice) {
            return new WP_Error('not_found', 'Facture non trouvée');
        }
        
        // Mettre à jour le statut d'extraction
        self::update_invoice_extraction_status($invoice_id, 'processing');
        
        try {
            // Simuler l'extraction (en production, utiliser une API OCR)
            $extracted_data = self::simulate_ocr_extraction($invoice);
            
            // Mettre à jour avec les données extraites
            $invoices_table = Financial_Dashboard_Database_Manager::get_table_name('invoices');
            
            $wpdb->update(
                $invoices_table,
                [
                    'extraction_status' => 'completed',
                    'extraction_data' => json_encode($extracted_data),
                    'extraction_confidence' => $extracted_data['confidence'] ?? 0.85,
                    'invoice_number' => $extracted_data['invoice_number'] ?? null,
                    'invoice_date' => $extracted_data['invoice_date'] ?? null,
                    'invoice_amount' => $extracted_data['invoice_amount'] ?? null,
                    'vendor_name' => $extracted_data['vendor_name'] ?? null
                ],
                ['id' => $invoice_id]
            );
            
            // Hook pour extensions
            do_action('financial_dashboard_invoice_extracted', $invoice_id, $extracted_data);
            
            return [
                'invoice_id' => $invoice_id,
                'extraction_data' => $extracted_data,
                'status' => 'completed'
            ];
            
        } catch (Exception $e) {
            self::update_invoice_extraction_status($invoice_id, 'failed', $e->getMessage());
            return new WP_Error('extraction_error', 'Erreur lors de l\'extraction: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtenir les factures d'un utilisateur
     */
    public static function get_user_invoices($user_id = null, $filters = []) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $invoices_table = Financial_Dashboard_Database_Manager::get_table_name('invoices');
        $links_table = Financial_Dashboard_Database_Manager::get_table_name('transaction_invoices');
        $transactions_table = Financial_Dashboard_Database_Manager::get_table_name('transactions');
        
        $where_conditions = ["i.user_id = %d"];
        $where_values = [$user_id];
        
        // Appliquer les filtres
        if (!empty($filters['extraction_status'])) {
            $where_conditions[] = "i.extraction_status = %s";
            $where_values[] = $filters['extraction_status'];
        }
        
        if (!empty($filters['linked_only'])) {
            $where_conditions[] = "ti.transaction_id IS NOT NULL";
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "i.created_at >= %s";
            $where_values[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "i.created_at <= %s";
            $where_values[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "
            SELECT 
                i.*,
                COUNT(ti.id) as linked_transactions_count,
                GROUP_CONCAT(t.description SEPARATOR '; ') as linked_descriptions
            FROM $invoices_table i
            LEFT JOIN $links_table ti ON i.id = ti.invoice_id
            LEFT JOIN $transactions_table t ON ti.transaction_id = t.id AND t.status = 'active'
            WHERE $where_clause
            GROUP BY i.id
            ORDER BY i.created_at DESC
        ";
        
        $invoices = $wpdb->get_results($wpdb->prepare($sql, $where_values));
        
        // Décoder les données JSON
        foreach ($invoices as &$invoice) {
            if ($invoice->extraction_data) {
                $invoice->extraction_data = json_decode($invoice->extraction_data, true);
            }
        }
        
        return $invoices;
    }
    
    /**
     * Obtenir une facture par ID
     */
    public static function get_invoice($invoice_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('invoices');
        
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
            $invoice_id, $user_id
        ));
        
        if ($invoice && $invoice->extraction_data) {
            $invoice->extraction_data = json_decode($invoice->extraction_data, true);
        }
        
        return $invoice;
    }
    
    /**
     * Supprimer une facture et ses liaisons
     */
    public static function delete_invoice($invoice_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Vérifier que la facture appartient à l'utilisateur
        $invoice = self::get_invoice($invoice_id, $user_id);
        if (!$invoice) {
            return new WP_Error('not_found', 'Facture non trouvée');
        }
        
        // Supprimer les liaisons
        $links_table = Financial_Dashboard_Database_Manager::get_table_name('transaction_invoices');
        $wpdb->delete($links_table, ['invoice_id' => $invoice_id]);
        
        // Supprimer la facture
        $invoices_table = Financial_Dashboard_Database_Manager::get_table_name('invoices');
        $wpdb->delete($invoices_table, ['id' => $invoice_id]);
        
        // Hook pour extensions
        do_action('financial_dashboard_invoice_deleted', $invoice_id);
        
        return true;
    }
    
    /**
     * Délier une facture d'une transaction
     */
    public static function unlink_invoice_from_transaction($transaction_id, $invoice_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Vérifier que la transaction appartient à l'utilisateur
        $transaction = Financial_Dashboard_Transactions::get_transaction($transaction_id, $user_id);
        if (!$transaction) {
            return new WP_Error('not_found', 'Transaction non trouvée');
        }
        
        $links_table = Financial_Dashboard_Database_Manager::get_table_name('transaction_invoices');
        
        $result = $wpdb->delete($links_table, [
            'transaction_id' => $transaction_id,
            'invoice_id' => $invoice_id
        ]);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la suppression de la liaison');
        }
        
        // Hook pour extensions
        do_action('financial_dashboard_invoice_unlinked', $transaction_id, $invoice_id);
        
        return true;
    }
    
    /**
     * Obtenir les factures liées à une transaction
     */
    public static function get_transaction_invoices($transaction_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $invoices_table = Financial_Dashboard_Database_Manager::get_table_name('invoices');
        $links_table = Financial_Dashboard_Database_Manager::get_table_name('transaction_invoices');
        
        $sql = "
            SELECT 
                i.*,
                ti.match_confidence,
                ti.match_type,
                ti.verified_at,
                ti.notes as link_notes
            FROM $invoices_table i
            INNER JOIN $links_table ti ON i.id = ti.invoice_id
            WHERE ti.transaction_id = %d AND i.user_id = %d
            ORDER BY ti.created_at DESC
        ";
        
        $invoices = $wpdb->get_results($wpdb->prepare($sql, $transaction_id, $user_id));
        
        // Décoder les données JSON
        foreach ($invoices as &$invoice) {
            if ($invoice->extraction_data) {
                $invoice->extraction_data = json_decode($invoice->extraction_data, true);
            }
        }
        
        return $invoices;
    }
    
    /**
     * Recherche automatique de factures pour une transaction
     */
    public static function suggest_invoices_for_transaction($transaction_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $transaction = Financial_Dashboard_Transactions::get_transaction($transaction_id, $user_id);
        if (!$transaction) {
            return [];
        }
        
        // Récupérer toutes les factures non liées
        $all_invoices = self::get_user_invoices($user_id, ['linked_only' => false]);
        $suggestions = [];
        
        foreach ($all_invoices as $invoice) {
            $score = self::calculate_match_score($transaction, $invoice);
            
            if ($score > 0.3) { // Seuil de pertinence
                $suggestions[] = [
                    'invoice' => $invoice,
                    'score' => $score,
                    'reasons' => self::get_match_reasons($transaction, $invoice)
                ];
            }
        }
        
        // Trier par score décroissant
        usort($suggestions, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return array_slice($suggestions, 0, 5); // Top 5 suggestions
    }
    
    /**
     * Vérifier une liaison facture-transaction
     */
    public static function verify_invoice_link($transaction_id, $invoice_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $links_table = Financial_Dashboard_Database_Manager::get_table_name('transaction_invoices');
        
        $result = $wpdb->update(
            $links_table,
            [
                'verified_by' => $user_id,
                'verified_at' => current_time('mysql')
            ],
            [
                'transaction_id' => $transaction_id,
                'invoice_id' => $invoice_id
            ]
        );
        
        return $result !== false;
    }
    
    /**
     * Fonctions utilitaires privées
     */
    
    private static function get_file_type_from_name($file_name) {
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $type_mapping = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        return $type_mapping[$extension] ?? 'application/octet-stream';
    }
    
    private static function update_invoice_extraction_status($invoice_id, $status, $error_message = null) {
        global $wpdb;
        
        $table_name = Financial_Dashboard_Database_Manager::get_table_name('invoices');
        
        $update_data = ['extraction_status' => $status];
        
        if ($error_message) {
            $update_data['notes'] = $error_message;
        }
        
        $wpdb->update($table_name, $update_data, ['id' => $invoice_id]);
    }
    
    private static function simulate_ocr_extraction($invoice) {
        // Simulation d'extraction OCR
        // En production, intégrer avec une API OCR comme Google Vision, AWS Textract, etc.
        
        $file_name = strtolower($invoice->file_name);
        
        // Données simulées basées sur le nom du fichier
        $extracted_data = [
            'confidence' => 0.85,
            'invoice_number' => 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'invoice_date' => date('Y-m-d', strtotime('-' . rand(1, 30) . ' days')),
            'invoice_amount' => rand(10, 1000) + (rand(0, 99) / 100),
            'vendor_name' => self::extract_vendor_from_filename($file_name),
            'line_items' => [
                [
                    'description' => 'Service/Produit',
                    'quantity' => 1,
                    'unit_price' => rand(10, 500),
                    'total' => rand(10, 500)
                ]
            ],
            'tax_amount' => rand(1, 100),
            'currency' => 'EUR'
        ];
        
        return $extracted_data;
    }
    
    private static function extract_vendor_from_filename($file_name) {
        // Essayer d'extraire le nom du vendeur depuis le nom du fichier
        $common_vendors = [
            'edf' => 'EDF',
            'orange' => 'Orange',
            'sfr' => 'SFR',
            'free' => 'Free',
            'carrefour' => 'Carrefour',
            'leclerc' => 'E.Leclerc',
            'total' => 'Total',
            'shell' => 'Shell',
            'amazon' => 'Amazon',
            'fnac' => 'FNAC'
        ];
        
        foreach ($common_vendors as $key => $vendor) {
            if (strpos($file_name, $key) !== false) {
                return $vendor;
            }
        }
        
        return 'Fournisseur inconnu';
    }
    
    private static function calculate_match_score($transaction, $invoice) {
        $score = 0;
        
        // Score basé sur la date (plus proche = meilleur score)
        if ($invoice->invoice_date) {
            $date_diff = abs(strtotime($transaction->date) - strtotime($invoice->invoice_date));
            $days_diff = $date_diff / (60 * 60 * 24);
            
            if ($days_diff <= 7) {
                $score += 0.3;
            } elseif ($days_diff <= 30) {
                $score += 0.2;
            } elseif ($days_diff <= 90) {
                $score += 0.1;
            }
        }
        
        // Score basé sur le montant
        if ($invoice->invoice_amount) {
            $amount_diff = abs($transaction->amount - $invoice->invoice_amount);
            $amount_ratio = $amount_diff / max($transaction->amount, $invoice->invoice_amount);
            
            if ($amount_ratio <= 0.05) { // 5% de différence
                $score += 0.4;
            } elseif ($amount_ratio <= 0.10) { // 10% de différence
                $score += 0.3;
            } elseif ($amount_ratio <= 0.20) { // 20% de différence
                $score += 0.2;
            }
        }
        
        // Score basé sur les mots-clés communs
        $transaction_words = explode(' ', strtolower($transaction->description));
        $invoice_words = explode(' ', strtolower($invoice->file_name . ' ' . ($invoice->vendor_name ?? '')));
        
        $common_words = array_intersect($transaction_words, $invoice_words);
        $score += min(0.3, count($common_words) * 0.1);
        
        return $score;
    }
    
    private static function get_match_reasons($transaction, $invoice) {
        $reasons = [];
        
        // Raisons basées sur la date
        if ($invoice->invoice_date) {
            $days_diff = abs(strtotime($transaction->date) - strtotime($invoice->invoice_date)) / (60 * 60 * 24);
            
            if ($days_diff <= 7) {
                $reasons[] = 'Dates proches (' . round($days_diff) . ' jour(s))';
            }
        }
        
        // Raisons basées sur le montant
        if ($invoice->invoice_amount) {
            $amount_diff = abs($transaction->amount - $invoice->invoice_amount);
            if ($amount_diff <= $transaction->amount * 0.1) {
                $reasons[] = 'Montants similaires (' . $amount_diff . '€ de différence)';
            }
        }
        
        // Raisons basées sur les mots-clés
        $transaction_words = explode(' ', strtolower($transaction->description));
        $invoice_words = explode(' ', strtolower($invoice->file_name));
        $common_words = array_intersect($transaction_words, $invoice_words);
        
        if (!empty($common_words)) {
            $reasons[] = 'Mots-clés communs: ' . implode(', ', array_slice($common_words, 0, 3));
        }
        
        return $reasons;
    }
}

?>
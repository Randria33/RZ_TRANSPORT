/**
 * Composant de gestion des factures
 */

import React, { useState, useEffect } from 'react';
import { FileText, Eye, Link, Unlink, Download, CheckCircle, AlertCircle, Plus } from 'lucide-react';
import GoogleDriveIntegration from './GoogleDriveIntegration';

const InvoiceManager = ({ transactionId, onInvoiceUpdate }) => {
  const [linkedInvoices, setLinkedInvoices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showGoogleDrive, setShowGoogleDrive] = useState(false);
  const [extracting, setExtracting] = useState({});

  useEffect(() => {
    if (transactionId) {
      loadLinkedInvoices();
    }
  }, [transactionId]);

  const loadLinkedInvoices = async () => {
    setLoading(true);

    try {
      const response = await fetch(`${financialDashboard.apiUrl}invoices?transaction_id=${transactionId}`, {
        headers: {
          'X-WP-Nonce': financialDashboard.nonce
        }
      });

      const result = await response.json();

      if (result.success) {
        setLinkedInvoices(result.data || []);
      }
    } catch (error) {
      console.error('Erreur chargement factures:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleInvoiceLinked = (linkData) => {
    // Actualiser la liste des factures liées
    loadLinkedInvoices();
    
    // Fermer l'interface Google Drive
    setShowGoogleDrive(false);
    
    // Notifier le parent
    if (onInvoiceUpdate) {
      onInvoiceUpdate(linkData);
    }
  };

  const unlinkInvoice = async (invoiceId) => {
    if (!confirm('Êtes-vous sûr de vouloir délier cette facture ?')) {
      return;
    }

    try {
      const response = await fetch(`${financialDashboard.apiUrl}invoices/${invoiceId}/unlink`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': financialDashboard.nonce
        }
      });

      const result = await response.json();

      if (result.success) {
        loadLinkedInvoices();
        if (onInvoiceUpdate) {
          onInvoiceUpdate({ action: 'unlinked', invoiceId });
        }
      } else {
        alert('Erreur lors de la déconnexion: ' + result.message);
      }
    } catch (error) {
      console.error('Erreur déconnexion facture:', error);
      alert('Erreur lors de la déconnexion');
    }
  };

  const extractInvoiceData = async (invoiceId) => {
    setExtracting(prev => ({ ...prev, [invoiceId]: true }));

    try {
      const response = await fetch(`${financialDashboard.apiUrl}invoices/${invoiceId}/extract`, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': financialDashboard.nonce
        }
      });

      const result = await response.json();

      if (result.success) {
        // Actualiser la liste pour voir les données extraites
        loadLinkedInvoices();
        
        if (onInvoiceUpdate) {
          onInvoiceUpdate({ 
            action: 'extracted', 
            invoiceId, 
            extractedData: result.data.extraction_data 
          });
        }
      } else {
        alert('Erreur lors de l\'extraction: ' + result.message);
      }
    } catch (error) {
      console.error('Erreur extraction:', error);
      alert('Erreur lors de l\'extraction');
    } finally {
      setExtracting(prev => ({ ...prev, [invoiceId]: false }));
    }
  };

  const previewInvoice = (invoice) => {
    if (invoice.file_url) {
      window.open(invoice.file_url, '_blank');
    } else if (invoice.google_drive_file_id) {
      window.open(`https://drive.google.com/file/d/${invoice.google_drive_file_id}/view`, '_blank');
    }
  };

  const getExtractionStatusIcon = (status) => {
    switch (status) {
      case 'completed':
        return <CheckCircle className="h-4 w-4 text-green-500" />;
      case 'processing':
        return <div className="animate-spin h-4 w-4 border-2 border-blue-500 border-t-transparent rounded-full" />;
      case 'failed':
        return <AlertCircle className="h-4 w-4 text-red-500" />;
      default:
        return <AlertCircle className="h-4 w-4 text-gray-400" />;
    }
  };

  const getExtractionStatusText = (status) => {
    switch (status) {
      case 'completed':
        return 'Extrait';
      case 'processing':
        return 'En cours...';
      case 'failed':
        return 'Échec';
      default:
        return 'En attente';
    }
  };

  if (loading) {
    return (
      <div className="p-4 text-center">
        <div className="animate-spin h-6 w-6 border-2 border-blue-500 border-t-transparent rounded-full mx-auto mb-2"></div>
        <p className="text-sm text-gray-600">Chargement des factures...</p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Factures liées */}
      {linkedInvoices.length > 0 && (
        <div>
          <h4 className="text-sm font-medium text-gray-700 mb-3">Factures liées</h4>
          <div className="space-y-2">
            {linkedInvoices.map((invoice) => (
              <div key={invoice.id} className="bg-gray-50 rounded-lg p-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center flex-1 min-w-0">
                    <FileText className="h-4 w-4 text-blue-500 mr-2 flex-shrink-0" />
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-gray-900 truncate">
                        {invoice.file_name}
                      </p>
                      <div className="flex items-center space-x-3 text-xs text-gray-500">
                        <span>Taille: {invoice.formatted_size || 'N/A'}</span>
                        {invoice.invoice_amount && (
                          <span>Montant: {invoice.invoice_amount}€</span>
                        )}
                        <div className="flex items-center space-x-1">
                          {getExtractionStatusIcon(invoice.extraction_status)}
                          <span>{getExtractionStatusText(invoice.extraction_status)}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div className="flex items-center space-x-1 ml-2">
                    {/* Aperçu */}
                    <button
                      onClick={() => previewInvoice(invoice)}
                      className="text-blue-500 hover:text-blue-700 p-1"
                      title="Voir la facture"
                    >
                      <Eye className="h-4 w-4" />
                    </button>
                    
                    {/* Extraction */}
                    {invoice.extraction_status !== 'completed' && (
                      <button
                        onClick={() => extractInvoiceData(invoice.id)}
                        disabled={extracting[invoice.id]}
                        className="text-orange-500 hover:text-orange-700 p-1 disabled:opacity-50"
                        title="Extraire les données"
                      >
                        <Download className="h-4 w-4" />
                      </button>
                    )}
                    
                    {/* Délier */}
                    <button
                      onClick={() => unlinkInvoice(invoice.id)}
                      className="text-red-500 hover:text-red-700 p-1"
                      title="Délier la facture"
                    >
                      <Unlink className="h-4 w-4" />
                    </button>
                  </div>
                </div>
                
                {/* Données extraites */}
                {invoice.extraction_status === 'completed' && invoice.extraction_data && (
                  <div className="mt-3 pt-3 border-t border-gray-200">
                    <div className="grid grid-cols-2 gap-3 text-xs">
                      {invoice.extraction_data.invoice_number && (
                        <div>
                          <span className="font-medium text-gray-600">N° Facture:</span>
                          <span className="ml-1">{invoice.extraction_data.invoice_number}</span>
                        </div>
                      )}
                      {invoice.extraction_data.invoice_date && (
                        <div>
                          <span className="font-medium text-gray-600">Date:</span>
                          <span className="ml-1">{invoice.extraction_data.invoice_date}</span>
                        </div>
                      )}
                      {invoice.extraction_data.vendor_name && (
                        <div>
                          <span className="font-medium text-gray-600">Fournisseur:</span>
                          <span className="ml-1">{invoice.extraction_data.vendor_name}</span>
                        </div>
                      )}
                      {invoice.extraction_data.invoice_amount && (
                        <div>
                          <span className="font-medium text-gray-600">Montant:</span>
                          <span className="ml-1">{invoice.extraction_data.invoice_amount}€</span>
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      )}
      
      {/* Bouton pour lier une nouvelle facture */}
      <div>
        <button
          onClick={() => setShowGoogleDrive(!showGoogleDrive)}
          className="flex items-center text-blue-600 hover:text-blue-800 text-sm"
        >
          <Plus className="h-4 w-4 mr-1" />
          {linkedInvoices.length > 0 ? 'Lier une autre facture' : 'Lier une facture'}
        </button>
      </div>
      
      {/* Interface Google Drive */}
      {showGoogleDrive && (
        <div className="mt-4">
          <GoogleDriveIntegration
            transactionId={transactionId}
            onInvoiceLinked={handleInvoiceLinked}
            showInModal={true}
          />
        </div>
      )}
    </div>
  );
};

export default InvoiceManager;
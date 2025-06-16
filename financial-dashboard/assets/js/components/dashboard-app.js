/**
 * Application principale du Dashboard Financier
 * Mise à jour pour inclure Google Drive
 */

import React, { useState, useEffect, useMemo } from 'react';
import { createRoot } from 'react-dom/client';
import GoogleDriveIntegration from './components/GoogleDriveIntegration';
import InvoiceManager from './components/InvoiceManager';

// ... (garder le code existant du tableau de bord) ...

// Composant mis à jour pour les transactions avec gestion des factures
const TransactionRow = ({ transaction, onUpdate, onDelete, categories }) => {
  const [showInvoiceManager, setShowInvoiceManager] = useState(false);

  const handleInvoiceUpdate = (updateData) => {
    // Notifier le parent de la mise à jour
    if (onUpdate) {
      onUpdate(transaction.id, { ...transaction, invoices_updated: Date.now() });
    }
  };

  return (
    <tr className="hover:bg-gray-50">
      <td className="px-4 py-3 text-sm text-gray-900">{transaction.date}</td>
      <td className="px-4 py-3 text-sm text-gray-900">
        {transaction.description.length > 40 
          ? `${transaction.description.substring(0, 40)}...`
          : transaction.description
        }
      </td>
      <td className="px-4 py-3 text-sm text-gray-600">
        {transaction.category_name || transaction.source}
      </td>
      <td className={`px-4 py-3 text-sm font-medium ${
        transaction.type === 'depense' ? 'text-red-600' : 'text-green-600'
      }`}>
        {transaction.type === 'depense' ? '-' : '+'}
        {transaction.montant.toLocaleString()} €
      </td>
      <td className="px-4 py-3 text-sm">
        <TransactionBadge type={transaction.type} />
      </td>
      <td className="px-4 py-3 text-sm">
        <div className="relative">
          <button
            onClick={() => setShowInvoiceManager(!showInvoiceManager)}
            className="flex items-center text-blue-600 hover:text-blue-800 text-xs"
          >
            <FileText className="h-3 w-3 mr-1" />
            {transaction.invoice_count > 0 ? `${transaction.invoice_count} facture(s)` : 'Lier'}
          </button>
          
          {showInvoiceManager && (
            <div className="absolute z-10 top-6 right-0 bg-white border rounded-lg shadow-lg p-4 w-80">
              <div className="flex justify-between items-center mb-3">
                <h4 className="font-medium">Factures</h4>
                <button
                  onClick={() => setShowInvoiceManager(false)}
                  className="text-gray-400 hover:text-gray-600"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
              
              <InvoiceManager
                transactionId={transaction.id}
                onInvoiceUpdate={handleInvoiceUpdate}
              />
            </div>
          )}
        </div>
      </td>
      <td className="px-4 py-3 text-sm">
        <button
          onClick={() => onDelete(transaction.id)}
          className="text-red-500 hover:text-red-700 p-1"
        >
          <Trash2 className="h-4 w-4" />
        </button>
      </td>
    </tr>
  );
};

// Composant pour la page d'administration Google Drive
const GoogleDriveAdminPage = () => {
  const [connectionStatus, setConnectionStatus] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    checkGoogleDriveStatus();
  }, []);

  const checkGoogleDriveStatus = async () => {
    try {
      const response = await fetch(financialDashboard.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'fd_google_auth',
          action_type: 'get_status',
          nonce: financialDashboard.nonce
        })
      });

      const result = await response.json();
      
      if (result.success) {
        setConnectionStatus(result.data);
      }
    } catch (error) {
      console.error('Erreur vérification statut Google:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="p-8 text-center">
        <div className="animate-spin h-8 w-8 border-4 border-blue-500 border-t-transparent rounded-full mx-auto mb-4"></div>
        <p>Vérification du statut Google Drive...</p>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto p-6">
      <div className="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 className="text-2xl font-bold text-gray-800 mb-4">Configuration Google Drive</h2>
        
        {connectionStatus && (
          <div className={`p-4 rounded-lg mb-4 ${
            connectionStatus.connected 
              ? 'bg-green-50 border border-green-200' 
              : 'bg-yellow-50 border border-yellow-200'
          }`}>
            <div className="flex items-center">
              {connectionStatus.connected ? (
                <CheckCircle className="h-5 w-5 text-green-500 mr-2" />
              ) : (
                <AlertCircle className="h-5 w-5 text-yellow-500 mr-2" />
              )}
              <span className={`font-medium ${
                connectionStatus.connected ? 'text-green-800' : 'text-yellow-800'
              }`}>
                {connectionStatus.message}
              </span>
            </div>
            
            {connectionStatus.user_email && (
              <p className="text-sm text-green-700 mt-1">
                Compte connecté: {connectionStatus.user_email}
              </p>
            )}
          </div>
        )}
      </div>

      <GoogleDriveIntegration />
      
      <div className="mt-6 bg-white rounded-lg shadow-md p-6">
        <h3 className="text-lg font-semibold text-gray-800 mb-4">Instructions</h3>
        <div className="space-y-3 text-sm text-gray-600">
          <div className="flex items-start">
            <span className="font-semibold text-blue-600 mr-2">1.</span>
            <span>Connectez votre compte Google Drive en cliquant sur le bouton de connexion</span>
          </div>
          <div className="flex items-start">
            <span className="font-semibold text-blue-600 mr-2">2.</span>
            <span>Autorisez l'accès en lecture à vos fichiers Google Drive</span>
          </div>
          <div className="flex items-start">
            <span className="font-semibold text-blue-600 mr-2">3.</span>
            <span>Parcourez vos fichiers et liez vos factures aux transactions correspondantes</span>
          </div>
          <div className="flex items-start">
            <span className="font-semibold text-blue-600 mr-2">4.</span>
            <span>Utilisez l'extraction automatique pour récupérer les données des factures</span>
          </div>
        </div>
      </div>
    </div>
  );
};

// Initialisation selon la page
document.addEventListener('DOMContentLoaded', function() {
  // Page principale du dashboard
  const dashboardElement = document.getElementById('financial-dashboard-app');
  if (dashboardElement) {
    const root = createRoot(dashboardElement);
    root.render(<TableauDeBordFinancier />);
  }

  // Page d'administration Google Drive
  const googleDriveElement = document.getElementById('financial-dashboard-google-drive-admin');
  if (googleDriveElement) {
    const root = createRoot(googleDriveElement);
    root.render(<GoogleDriveAdminPage />);
  }
});
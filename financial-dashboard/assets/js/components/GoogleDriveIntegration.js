/**
 * Composant d'intégration Google Drive
 */

import React, { useState, useEffect } from 'react';
import { Cloud, CloudOff, Eye, Link, Download, Search, Folder, FileText, Image, File } from 'lucide-react';

const GoogleDriveIntegration = ({ 
  transactionId = null, 
  onInvoiceLinked = null,
  showInModal = false 
}) => {
  const [connected, setConnected] = useState(false);
  const [loading, setLoading] = useState(true);
  const [files, setFiles] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [currentFolder, setCurrentFolder] = useState(null);
  const [selectedFile, setSelectedFile] = useState(null);
  const [userEmail, setUserEmail] = useState('');
  const [nextPageToken, setNextPageToken] = useState('');
  const [hasMore, setHasMore] = useState(false);

  // Vérifier le statut de connexion au chargement
  useEffect(() => {
    checkConnectionStatus();
  }, []);

  const checkConnectionStatus = async () => {
    setLoading(true);
    
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
        setConnected(result.data.connected);
        setUserEmail(result.data.user_email || '');
        
        if (result.data.connected) {
          loadFiles();
        }
      }
    } catch (error) {
      console.error('Erreur vérification statut:', error);
    } finally {
      setLoading(false);
    }
  };

  const initiateGoogleAuth = async () => {
    setLoading(true);
    
    try {
      // Utiliser l'API Google Identity Services
      if (window.google && window.google.accounts) {
        const client = window.google.accounts.oauth2.initCodeClient({
          client_id: financialDashboard.googleDriveConfig.clientId,
          scope: financialDashboard.googleDriveConfig.scopes,
          ux_mode: 'popup',
          callback: handleAuthResponse,
        });
        
        client.requestCode();
      } else {
        // Fallback: redirection vers l'URL d'autorisation
        const authResponse = await fetch(financialDashboard.ajaxUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'fd_google_auth',
            action_type: 'get_auth_url',
            nonce: financialDashboard.nonce
          })
        });
        
        const authResult = await authResponse.json();
        
        if (authResult.success) {
          window.open(authResult.data.auth_url, 'google-auth', 'width=500,height=600');
        }
      }
    } catch (error) {
      console.error('Erreur authentification:', error);
      setLoading(false);
    }
  };

  const handleAuthResponse = async (response) => {
    if (response.error) {
      console.error('Erreur auth Google:', response.error);
      setLoading(false);
      return;
    }

    try {
      const saveResponse = await fetch(`${financialDashboard.apiUrl}google-drive/callback`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': financialDashboard.nonce
        },
        body: JSON.stringify({
          code: response.code
        })
      });

      const saveResult = await saveResponse.json();

      if (saveResult.status === 'success') {
        setConnected(true);
        loadFiles();
      }
    } catch (error) {
      console.error('Erreur sauvegarde auth:', error);
    } finally {
      setLoading(false);
    }
  };

  const loadFiles = async (pageToken = '', append = false) => {
    setLoading(true);

    try {
      const response = await fetch(financialDashboard.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'fd_google_files',
          page_size: 20,
          page_token: pageToken,
          folder_id: currentFolder || '',
          search_query: searchQuery,
          nonce: financialDashboard.nonce
        })
      });

      const result = await response.json();

      if (result.success) {
        const newFiles = result.data.files || [];
        
        if (append) {
          setFiles(prev => [...prev, ...newFiles]);
        } else {
          setFiles(newFiles);
        }
        
        setNextPageToken(result.data.nextPageToken || '');
        setHasMore(!!result.data.nextPageToken);
      }
    } catch (error) {
      console.error('Erreur chargement fichiers:', error);
    } finally {
      setLoading(false);
    }
  };

  const searchFiles = async () => {
    if (!searchQuery.trim()) {
      loadFiles();
      return;
    }

    setLoading(true);

    try {
      const response = await fetch(financialDashboard.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'fd_google_search',
          search_query: searchQuery,
          limit: 20,
          nonce: financialDashboard.nonce
        })
      });

      const result = await response.json();

      if (result.success) {
        setFiles(result.data.files || []);
        setNextPageToken('');
        setHasMore(false);
      }
    } catch (error) {
      console.error('Erreur recherche:', error);
    } finally {
      setLoading(false);
    }
  };

  const linkInvoiceToTransaction = async (file) => {
    if (!transactionId) {
      alert('Aucune transaction sélectionnée');
      return;
    }

    setLoading(true);

    try {
      const response = await fetch(financialDashboard.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'fd_link_invoice',
          transaction_id: transactionId,
          file_id: file.id,
          file_name: file.name,
          file_url: file.webViewLink,
          nonce: financialDashboard.nonce
        })
      });

      const result = await response.json();

      if (result.success) {
        alert('Facture liée avec succès !');
        if (onInvoiceLinked) {
          onInvoiceLinked(result.data);
        }
      } else {
        alert('Erreur lors de la liaison: ' + result.data.message);
      }
    } catch (error) {
      console.error('Erreur liaison facture:', error);
      alert('Erreur lors de la liaison');
    } finally {
      setLoading(false);
    }
  };

  const disconnectGoogleDrive = async () => {
    if (!confirm('Êtes-vous sûr de vouloir déconnecter Google Drive ?')) {
      return;
    }

    setLoading(true);

    try {
      const response = await fetch(financialDashboard.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'fd_google_disconnect',
          nonce: financialDashboard.nonce
        })
      });

      const result = await response.json();

      if (result.success) {
        setConnected(false);
        setFiles([]);
        setUserEmail('');
        alert('Déconnecté avec succès');
      }
    } catch (error) {
      console.error('Erreur déconnexion:', error);
    } finally {
      setLoading(false);
    }
  };

  const getFileIcon = (file) => {
    const mimeType = file.mimeType || '';
    
    if (mimeType.includes('image/')) {
      return <Image className="h-4 w-4 text-green-500" />;
    } else if (mimeType.includes('pdf')) {
      return <FileText className="h-4 w-4 text-red-500" />;
    } else if (mimeType.includes('folder')) {
      return <Folder className="h-4 w-4 text-blue-500" />;
    } else {
      return <File className="h-4 w-4 text-gray-500" />;
    }
  };

  const formatFileDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  };

  // Interface de connexion
  if (!connected) {
    return (
      <div className="bg-white rounded-lg shadow-md p-6">
        <div className="text-center">
          <CloudOff className="h-12 w-12 text-gray-400 mx-auto mb-4" />
          <h3 className="text-lg font-semibold text-gray-800 mb-2">
            Connecter Google Drive
          </h3>
          <p className="text-gray-600 mb-6">
            Connectez votre Google Drive pour lier vos factures aux transactions
          </p>
          
          <button
            onClick={initiateGoogleAuth}
            disabled={loading}
            className="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center mx-auto"
          >
            <Cloud className="h-4 w-4 mr-2" />
            {loading ? 'Connexion...' : 'Connecter Google Drive'}
          </button>
          
          {!financialDashboard.googleDriveConfig.clientId && (
            <div className="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
              <p className="text-sm text-yellow-800">
                ⚠️ Configuration Google Drive incomplète. 
                <a href="/wp-admin/admin.php?page=financial-dashboard-settings" className="underline">
                  Configurer maintenant
                </a>
              </p>
            </div>
          )}
        </div>
      </div>
    );
  }

  // Interface principale
  return (
    <div className="bg-white rounded-lg shadow-md">
      {/* En-tête */}
      <div className="p-4 border-b border-gray-200">
        <div className="flex items-center justify-between">
          <div className="flex items-center">
            <Cloud className="h-5 w-5 text-green-500 mr-2" />
            <div>
              <h3 className="text-lg font-semibold text-gray-800">Google Drive</h3>
              {userEmail && (
                <p className="text-sm text-gray-600">Connecté: {userEmail}</p>
              )}
            </div>
          </div>
          
          <button
            onClick={disconnectGoogleDrive}
            className="text-red-500 hover:text-red-700 text-sm"
          >
            Déconnecter
          </button>
        </div>
      </div>

      {/* Barre de recherche */}
      <div className="p-4 border-b border-gray-200">
        <div className="flex gap-2">
          <div className="flex-1 relative">
            <Search className="h-4 w-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              onKeyPress={(e) => e.key === 'Enter' && searchFiles()}
              placeholder="Rechercher des fichiers..."
              className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <button
            onClick={searchFiles}
            disabled={loading}
            className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors disabled:opacity-50"
          >
            Rechercher
          </button>
        </div>
      </div>

      {/* Liste des fichiers */}
      <div className="max-h-96 overflow-y-auto">
        {loading && files.length === 0 ? (
          <div className="p-8 text-center">
            <div className="animate-spin h-8 w-8 border-4 border-blue-500 border-t-transparent rounded-full mx-auto mb-4"></div>
            <p className="text-gray-600">Chargement des fichiers...</p>
          </div>
        ) : files.length === 0 ? (
          <div className="p-8 text-center">
            <FileText className="h-12 w-12 text-gray-400 mx-auto mb-4" />
            <p className="text-gray-600">Aucun fichier trouvé</p>
          </div>
        ) : (
          <div className="divide-y divide-gray-200">
            {files.map((file) => (
              <div key={file.id} className="p-4 hover:bg-gray-50 transition-colors">
                <div className="flex items-center justify-between">
                  <div className="flex items-center flex-1 min-w-0">
                    {getFileIcon(file)}
                    <div className="ml-3 flex-1 min-w-0">
                      <p className="text-sm font-medium text-gray-900 truncate">
                        {file.name}
                      </p>
                      <div className="flex items-center text-xs text-gray-500 space-x-4">
                        <span>{file.formatted_size}</span>
                        <span>{formatFileDate(file.modifiedTime)}</span>
                        {file.is_invoice && (
                          <span className="bg-green-100 text-green-800 px-2 py-1 rounded-full">
                            Facture
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                  
                  <div className="flex items-center space-x-2 ml-4">
                    {file.thumbnailLink && (
                      <button
                        onClick={() => window.open(file.webViewLink, '_blank')}
                        className="text-blue-500 hover:text-blue-700 p-1"
                        title="Aperçu"
                      >
                        <Eye className="h-4 w-4" />
                      </button>
                    )}
                    
                    {transactionId && (
                      <button
                        onClick={() => linkInvoiceToTransaction(file)}
                        className="text-green-500 hover:text-green-700 p-1"
                        title="Lier à la transaction"
                      >
                        <Link className="h-4 w-4" />
                      </button>
                    )}
                    
                    <button
                      onClick={() => window.open(file.webViewLink, '_blank')}
                      className="text-gray-500 hover:text-gray-700 p-1"
                      title="Ouvrir dans Google Drive"
                    >
                      <Download className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Bouton charger plus */}
        {hasMore && !loading && (
          <div className="p-4 text-center border-t border-gray-200">
            <button
              onClick={() => loadFiles(nextPageToken, true)}
              className="text-blue-600 hover:text-blue-800 text-sm"
            >
              Charger plus de fichiers
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

export default GoogleDriveIntegration;
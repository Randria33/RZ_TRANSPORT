# RZ_TRANSPORT 📊 Financial Dashboard Pro - WordPress Plugin

Un plugin WordPress complet pour la gestion financière avancée avec intégration Google Drive, import de relevés bancaires et rapports intelligents.

## ✨ Fonctionnalités

### 💰 Gestion des Transactions
- ✅ Création, modification, suppression de transactions
- ✅ Catégorisation automatique intelligente (19+ règles)
- ✅ Filtres avancés (date, montant, type, catégorie)
- ✅ Import de relevés bancaires (Excel, CSV, QIF)
- ✅ Statistiques en temps réel

### 🏷️ Catégories Intelligentes
- ✅ 19 catégories par défaut pré-configurées
- ✅ Création de catégories personnalisées
- ✅ Réorganisation et hiérarchie
- ✅ Statistiques d'utilisation

### 📄 Gestion des Factures
- ✅ Intégration Google Drive complète
- ✅ Liaison factures ↔ transactions
- ✅ Extraction automatique de données (OCR ready)
- ✅ Prévisualisation intégrée
- ✅ Suggestions de rapprochement

### 📊 Rapports et Analytics
- ✅ Tableaux de bord interactifs
- ✅ Graphiques d'évolution mensuelle
- ✅ Répartition par catégories
- ✅ Calculs de marges et bénéfices
- ✅ Exports CSV/PDF

### ☁️ Intégration Google Drive
- ✅ Authentification OAuth2 sécurisée
- ✅ Navigation et recherche dans Drive
- ✅ Détection automatique des factures
- ✅ Gestion des tokens avec refresh automatique

## 📋 Prérequis

- **WordPress**: >= 5.0
- **PHP**: >= 7.4
- **Extensions PHP**: json, curl, openssl
- **Base de données**: MySQL/MariaDB
- **Permissions**: Écriture dans /wp-content/uploads/

## 🚀 Installation

### Installation Automatique
1. Téléchargez le plugin depuis le dépôt
2. Allez dans **Extensions > Ajouter**
3. Cliquez sur **Téléverser une extension**
4. Sélectionnez le fichier ZIP du plugin
5. Cliquez sur **Installer maintenant**
6. **Activez** le plugin

### Installation Manuelle
1. Décompressez le fichier ZIP
2. Uploadez le dossier dans `/wp-content/plugins/`
3. Activez le plugin depuis l'admin WordPress

## ⚙️ Configuration

### 1. Configuration de Base
Après activation, rendez-vous dans **Dashboard Financier > Paramètres** :
- Devise par défaut
- Format de date
- Options de sauvegarde

### 2. Configuration Google Drive (Optionnelle)
1. Allez sur [Google Cloud Console](https://console.cloud.google.com/)
2. Créez un nouveau projet
3. Activez l'API Google Drive
4. Créez des identifiants OAuth 2.0
5. Ajoutez vos clés dans **Dashboard Financier > Google Drive**

#### Paramètres Google Drive requis :
- **Client ID** : Votre Google Client ID
- **Client Secret** : Votre Google Client Secret  
- **API Key** : Votre Google API Key

## 📱 Utilisation

### Tableau de Bord Principal
Utilisez le shortcode `[financial_dashboard]` sur n'importe quelle page :

```php
[financial_dashboard view="full"]
[financial_dashboard user_id="123" view="compact"]
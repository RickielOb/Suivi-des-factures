# AquaWatt — Suivi des factures eau / électricité

Application PHP de gestion des relevés de compteur, de la facturation et des paiements.
Base de données **MySQL hébergée sur Railway**, accès via PDO — voir [lib/db.php](lib/db.php).

## Configuration

La connexion est lue depuis la variable d'environnement `MYSQL_URL` (ou `DATABASE_URL`),
avec repli sur l'URL interne Railway définie dans [config.php](config.php) :

```
mysql://root:********@mysql.railway.internal:3306/railway
```

- **Déployé sur Railway** : rien à faire, l'hôte `mysql.railway.internal` est résolu
  automatiquement dans le réseau privé du projet.
- **En local** : cet hôte n'est pas joignable. Utiliser l'URL publique
  (`MYSQL_PUBLIC_URL` dans l'onglet *Variables* du service MySQL) :

```bash
set MYSQL_URL=mysql://root:MOTDEPASSE@xxxx.proxy.rlwy.net:PORT/railway
```

## Lancement

```bash
php -S localhost:8000 -t .
```

1. Ouvrir <http://localhost:8000/install.php> une seule fois (création des tables + données de démonstration).
2. Puis <http://localhost:8000/login.php>.

Extension requise : `pdo_mysql`.

## Comptes

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | admin@aquawatt.ga | admin123 |
| Agent de facturation | agent@aquawatt.ga | agent123 |
| Abonné | abonne@aquawatt.ga | abonne123 |

## Modèle de données

- `abonne(id, nom, telephone, adresse, numero_compteur, type_abonnement)`
- `releve(id, abonne_id, mois, consommation, date_releve)`
- `facture(id, abonne_id, releve_id, montant, date_emission, statut)`
- `paiement(id, facture_id, montant, date, mode)`
- `utilisateur(id, nom, email, mot_de_passe, role, abonne_id)` — authentification

Clés étrangères InnoDB, contrainte d'unicité `(abonne_id, mois)` sur les relevés.

## Fonctionnalités

- **Relevés** : saisie mensuelle, un seul relevé par abonné et par mois.
- **Facturation automatique** : à l'enregistrement d'un relevé, la facture est générée
  (`consommation × tarif`, tarifs définis dans [config.php](config.php)).
- **Paiements** : encaissement total ou partiel ; le statut de la facture
  (`impayee` / `partielle` / `payee`) est recalculé automatiquement.
- **Suivi des impayés** : tableau de bord, soldes par abonné, top des impayés.
- **Historique de consommation** par abonné (fiche abonné + espace abonné).
- **Recherche / filtres** par abonné, compteur, mois, statut et mode de paiement.
- **Rôles** : l'admin gère tout (y compris les suppressions), l'agent saisit relevés,
  factures et paiements, l'abonné consulte uniquement son espace.

## Arborescence

```
config.php          connexion MySQL, tarifs, devise
install.php         création des tables + démo
lib/db.php          couche PDO (db_all, db_one, db_exec…)
lib/auth.php        session, rôles
lib/helpers.php     formatage, statut des factures
views/              header / footer
assets/style.css    feuille de style (aucune dépendance externe)
index.php  abonnes.php  releves.php  factures.php  paiements.php  mon-espace.php
login.php  logout.php
```

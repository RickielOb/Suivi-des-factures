# AquaWatt — Suivi des factures eau / électricité

Application PHP de gestion des relevés de compteur, de la facturation et des paiements.
La base de données est hébergée sur **Turso (libSQL)** et interrogée via son API HTTP
(PHP n'ayant pas de driver libSQL natif) — voir [lib/db.php](lib/db.php).

## Configuration

`TURSO_URL` et `TURSO_TOKEN` dans [config.php](config.php). Extension requise : `curl`.

## Lancement

```bash
C:\xampp\php\php.exe -S localhost:8000 -t "D:\Devoir Php"
```

1. Ouvrir <http://localhost:8000/install.php> une seule fois (création des tables + données de démonstration).
2. Puis <http://localhost:8000/login.php>.

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

Contrainte d'unicité `(abonne_id, mois)` sur les relevés, `numero_compteur` unique.

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
config.php          identifiants Turso, tarifs, devise
install.php         création des tables + démo
lib/db.php          client HTTP Turso (db_all, db_one, db_exec…)
lib/auth.php        session, rôles
lib/helpers.php     formatage, statut des factures
views/              header / footer
assets/style.css    feuille de style (aucune dépendance externe)
index.php  abonnes.php  releves.php  factures.php  paiements.php  mon-espace.php
login.php  logout.php
```

> Le jeton Turso placé dans `config.php` a une durée de vie limitée (7 jours à sa création,
> soit jusqu'au 12/08/2026). S'il expire, en générer un nouveau avec
> `turso db tokens create projet-wen-dynamique-rickiel` et remplacer `TURSO_TOKEN`.

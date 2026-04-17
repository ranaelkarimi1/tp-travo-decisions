# TP Travo — Module Décisions

Module de workflow de validation des décisions pour l'application Travo.

## Démarrage

- Base de données : `mysql -u root travo < database/schema.sql`
- Serveur : `php -S localhost:8000 -t public`
- Identifiants de démo : `demo@travo.test` / `password123`

---

## Architecture du module Décisions

### Vue d'ensemble

- `app/Core/DecisionStatus.php` — Machine à états (source de vérité)
- `app/Core/DecisionWorkflow.php` — Service : transitions atomiques
- `app/Core/DecisionPolicy.php` — Règles d'autorisation
- `app/Models/Decision.php` — Accès données table decisions
- `app/Models/DecisionLog.php` — Accès données table decision_logs
- `app/Controllers/DecisionController.php` — HTTP : auth + délégation au workflow
- `app/Views/decisions/index.php` — Liste par chantier
- `app/Views/decisions/create.php` — Formulaire de création
- `app/Views/decisions/show.php` — Détail + historique + actions

### Responsabilités par couche

| Couche | Rôle | Ce qu'elle NE fait PAS |
|---|---|---|
| Controller | Authentifier, charger, autoriser, déléguer, rediriger | Pas de SQL, pas de règle métier |
| Policy | Répondre à « cet utilisateur peut-il agir sur cette décision ? » | Ne change jamais l'état |
| Workflow (service) | Appliquer les transitions de manière transactionnelle | Pas de




## Choix d'architecture — justifications

### 1. Pourquoi un service `DecisionWorkflow` plutôt que des méthodes sur le modèle ?

Le cahier des charges impose trois invariants :

1. Aucun changement d'état sans log correspondant.
2. Aucune transition illégale.
3. Une décision approuvée est immuable.

Si on laissait les contrôleurs appeler directement `Decision::updateStatus()`, il faudrait répéter ces vérifications dans chaque action (submit, approve, reject, cancel, revise). Un oubli = un bug de sécurité métier.

En centralisant toutes les transitions dans un seul service (`DecisionWorkflow`), et en passant toutes les modifications de statut par la méthode privée `transition()`, on obtient une garantie structurelle : il est impossible de changer un statut sans passer par la machinerie qui valide la transition et écrit le log.

### 2. Comment garantir qu'un changement d'état produit TOUJOURS un log ?

La méthode `DecisionWorkflow::transition()` ouvre une transaction PDO, met à jour le statut ET écrit le log, puis commit. Si n'importe quelle étape échoue, les deux opérations sont rollback ensemble. Le statut en base et le log ne peuvent donc jamais être désynchronisés.

Extrait simplifié :

- `beginTransaction()`
- `decisions->updateStatus(...)`
- `logs->create(...)`
- `commit()` — ou `rollBack()` si une exception est levée à n'importe quelle étape

### 3. Comment empêcher les transitions absurdes ?

La matrice des transitions autorisées est déclarée une seule fois dans `DecisionStatus::TRANSITIONS` :

- `draft` → `pending`, `cancelled`
- `pending` → `approved`, `rejected`, `cancelled`
- `rejected` → `draft`
- `approved` → (état final, aucune transition)
- `cancelled` → (état final, aucune transition)

Cette matrice est utilisée à deux endroits :

- Côté serveur, dans `DecisionWorkflow::transition()`, via `DecisionStatus::canTransition()`. Toute tentative de transition absente de la matrice lève une `DomainException`.
- Côté vue, dans `show.php`, via `DecisionStatus::availableTransitions()`. Les boutons d'action affichés correspondent exactement aux transitions légales.

Principe : « si un bouton est visible, l'action est autorisée ». Inversement, un utilisateur qui forge une requête POST vers `/decisions/X/approve` alors qu'il n'y a pas de bouton visible sera rejeté par le serveur.

### 4. Où se trouve le contrôle d'autorisation ?

Dans une classe dédiée : `DecisionPolicy`. Règle : un utilisateur ne peut agir que sur les décisions des chantiers qu'il possède.

Pourquoi une classe à part :

- La règle est nommée une seule fois, à un seul endroit
- Si elle évolue (rôles, partage de chantier entre plusieurs users), on modifie UN fichier
- Chaque action du contrôleur appelle `DecisionPolicy::authorizeOrFail()` avant toute opération : impossible de l'oublier sans que la protection saute de manière visible

### 5. Pourquoi aucune suppression physique ?

Le cahier des charges interdit la suppression d'une décision en attente. À la place, l'état `cancelled` sert de corbeille logique : la décision reste en base, avec un log complet expliquant qui l'a annulée et pourquoi. Ça préserve la piste d'audit. Aucune route DELETE n'est exposée pour les décisions.

### 6. Pourquoi une décision approuvée est-elle immuable ?

Métier : une décision approuvée engage le chantier. La rouvrir sans trace reviendrait à réécrire l'histoire. Techniquement, `approved` est un état final dans la matrice (`TRANSITIONS[approved] = []`), et `DecisionWorkflow::transition()` refuse explicitement toute action sur une décision approuvée.

---





## Cycle de vie d'une décision

Transitions autorisées :

- `draft` → `pending` (envoi en validation) ou `cancelled` (annulation)
- `pending` → `approved` (acceptation), `rejected` (refus) ou `cancelled` (annulation)
- `rejected` → `draft` (retravail et re-soumission)
- `approved` → état final, immuable
- `cancelled` → état final, définitif

---

## Schéma de la base

### Table `decisions`

| Colonne | Type | Rôle |
|---|---|---|
| id | INT PK | Identifiant |
| project_id | INT FK | Chantier de rattachement |
| user_id | INT FK | Auteur |
| title | VARCHAR(255) | Titre |
| description | TEXT | Description |
| status | ENUM | draft / pending / approved / rejected / cancelled |
| response_comment | TEXT NULL | Commentaire de validation ou de refus |
| validated_at | DATETIME NULL | Rempli uniquement lors du passage à approved |
| created_at / updated_at | TIMESTAMP | Gérés par MySQL |

### Table `decision_logs`

| Colonne | Type | Rôle |
|---|---|---|
| id | INT PK | Identifiant |
| decision_id | INT FK | Décision concernée |
| user_id | INT FK | Utilisateur qui a déclenché la transition |
| from_status | VARCHAR(20) | État d'origine (chaîne vide à la création) |
| to_status | VARCHAR(20) | État de destination |
| message | TEXT | Message explicatif |
| created_at | TIMESTAMP | Date de la transition |

Le journal est append-only : aucune route ne permet de le modifier ou de le supprimer.

---

## Routes

| Méthode | URL | Action |
|---|---|---|
| GET | `/projects/{id}/decisions` | Liste des décisions du chantier |
| GET | `/projects/{id}/decisions/create` | Formulaire de création |
| POST | `/projects/{id}/decisions/store` | Création (état initial : draft) |
| GET | `/decisions/{id}` | Détail + historique + actions disponibles |
| POST | `/decisions/{id}/submit` | draft → pending |
| POST | `/decisions/{id}/approve` | pending → approved |
| POST | `/decisions/{id}/reject` | pending → rejected |
| POST | `/decisions/{id}/cancel` | draft ou pending → cancelled |
| POST | `/decisions/{id}/revise` | rejected → draft |
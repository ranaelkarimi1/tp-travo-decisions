<?php

/**
 * DecisionStatus
 * ---------------
 * Source unique de vérité pour les états d'une décision et les transitions autorisées.
 *
 * Cette classe centralise :
 *   - la liste des états possibles (draft, pending, approved, rejected, cancelled)
 *   - la matrice des transitions légales (depuis chaque état, quels états sont atteignables)
 *   - les libellés affichables côté vue
 *
 * Le DecisionWorkflow (service) et les vues s'appuient tous les deux sur cette classe,
 * ce qui garantit qu'un bouton visible à l'écran correspond toujours à une transition
 * réellement autorisée côté serveur.
 */
class DecisionStatus
{
    public const DRAFT     = 'draft';
    public const PENDING   = 'pending';
    public const APPROVED  = 'approved';
    public const REJECTED  = 'rejected';
    public const CANCELLED = 'cancelled';

    /**
     * Matrice des transitions autorisées.
     * Clé = état courant, valeur = liste des états vers lesquels on peut aller.
     */
    public const TRANSITIONS = [
        self::DRAFT     => [self::PENDING, self::CANCELLED],
        self::PENDING   => [self::APPROVED, self::REJECTED, self::CANCELLED],
        self::REJECTED  => [self::DRAFT],
        self::APPROVED  => [],   // état final : plus aucune transition
        self::CANCELLED => [],   // état final : plus aucune transition
    ];

    /**
     * Libellés affichables (FR).
     */
    public const LABELS = [
        self::DRAFT     => 'Brouillon',
        self::PENDING   => 'En attente',
        self::APPROVED  => 'Approuvée',
        self::REJECTED  => 'Rejetée',
        self::CANCELLED => 'Annulée',
    ];

    /**
     * Libellés des actions (ce que voit l'utilisateur sur le bouton).
     */
    public const ACTION_LABELS = [
        self::PENDING   => 'Envoyer en validation',
        self::APPROVED  => 'Approuver',
        self::REJECTED  => 'Rejeter',
        self::CANCELLED => 'Annuler',
        self::DRAFT     => 'Remettre en brouillon',
    ];

    public static function all(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    public static function exists(string $status): bool
    {
        return array_key_exists($status, self::TRANSITIONS);
    }

    /**
     * La transition from -> to est-elle autorisée ?
     */
    public static function canTransition(string $from, string $to): bool
    {
        if (!self::exists($from) || !self::exists($to)) {
            return false;
        }
        return in_array($to, self::TRANSITIONS[$from], true);
    }

    /**
     * Quels sont les états accessibles depuis cet état ?
     * Utilisé par la vue pour afficher les bons boutons.
     */
    public static function availableTransitions(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public static function isFinal(string $status): bool
    {
        return self::exists($status) && self::availableTransitions($status) === [];
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }

    public static function actionLabel(string $targetStatus): string
    {
        return self::ACTION_LABELS[$targetStatus] ?? $targetStatus;
    }
}
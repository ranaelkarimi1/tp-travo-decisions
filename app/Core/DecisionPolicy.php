<?php

/**
 * DecisionPolicy
 * --------------
 * Classe dédiée à l'autorisation des actions sur les décisions.
 *
 * Règle métier (cahier des charges) :
 *   "Un utilisateur ne peut agir que sur les décisions des chantiers qui lui
 *    appartiennent."
 *
 * POURQUOI UNE CLASSE À PART
 * --------------------------
 * On pourrait mettre ce contrôle d'accès dans chaque méthode du DecisionController,
 * mais on finirait par le dupliquer 8 fois. En l'isolant dans une classe Policy :
 *   - la règle est nommée une seule fois, à un seul endroit
 *   - si elle évolue (ex : ajout de rôles, partage de chantiers), on modifie UN fichier
 *   - le contournement devient impossible tant que chaque action passe par
 *     authorizeOrFail() avant d'agir.
 */
class DecisionPolicy
{
    private Project $projects;

    public function __construct()
    {
        $this->projects = new Project();
    }

    /**
     * L'utilisateur peut-il agir sur cette décision ?
     * Oui ssi il est propriétaire du chantier auquel la décision est rattachée.
     */
    public function canActOn(int $userId, array $decision): bool
    {
        $project = $this->projects->getByIdForUser((int) $decision['project_id'], $userId);
        return $project !== null;
    }

    /**
     * L'utilisateur peut-il créer une décision dans ce chantier ?
     * Même règle : il doit être propriétaire du chantier.
     */
    public function canCreateIn(int $userId, int $projectId): bool
    {
        return $this->projects->getByIdForUser($projectId, $userId) !== null;
    }

    /**
     * Version "throw" utilisée par le contrôleur : stoppe net en cas d'accès non autorisé.
     */
    public function authorizeOrFail(int $userId, array $decision): void
    {
        if (!$this->canActOn($userId, $decision)) {
            http_response_code(403);
            die('Accès interdit : cette décision ne vous appartient pas.');
        }
    }
}
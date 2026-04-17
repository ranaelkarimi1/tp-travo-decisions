<?php

/**
 * Decision
 * --------
 * Accès aux données pour la table `decisions`.
 *
 * Cette classe est volontairement "bête" : elle ne connaît ni les règles de transition
 * d'état, ni les règles d'autorisation. Elle se contente de lire et d'écrire en base.
 * Toute la logique métier vit dans DecisionWorkflow.
 *
 * IMPORTANT : la méthode updateStatus() ne doit être appelée QUE depuis
 * DecisionWorkflow::transition(), jamais directement depuis un contrôleur.
 * C'est ce qui garantit qu'un changement d'état s'accompagne toujours d'un log.
 */
class Decision
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * Liste des décisions d'un chantier, plus récentes d'abord.
     */
    public function getAllByProjectId(int $projectId): array
    {
        $sql = "SELECT * FROM decisions
                WHERE project_id = :project_id
                ORDER BY created_at DESC, id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['project_id' => $projectId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM decisions WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Crée une décision (toujours à l'état draft - voir DecisionWorkflow::create()).
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO decisions (project_id, user_id, title, description, status)
                VALUES (:project_id, :user_id, :title, :description, :status)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'project_id'  => $data['project_id'],
            'user_id'     => $data['user_id'],
            'title'       => $data['title'],
            'description' => $data['description'],
            'status'      => $data['status'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Met à jour le statut d'une décision.
     * Si on passe à "approved", on enregistre aussi la date de validation.
     *
     * NE PAS APPELER DIRECTEMENT : passer par DecisionWorkflow::transition().
     */
    public function updateStatus(int $id, string $newStatus, ?string $responseComment = null): bool
    {
        $validatedAt = ($newStatus === DecisionStatus::APPROVED) ? date('Y-m-d H:i:s') : null;

        $sql = "UPDATE decisions
                SET status = :status,
                    response_comment = :response_comment,
                    validated_at = :validated_at
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id'               => $id,
            'status'           => $newStatus,
            'response_comment' => $responseComment,
            'validated_at'     => $validatedAt,
        ]);
    }

    /**
     * Met à jour le contenu (titre + description) d'une décision.
     * Autorisé seulement en draft (règle métier vérifiée dans le contrôleur / service).
     */
    public function updateContent(int $id, string $title, string $description): bool
    {
        $sql = "UPDATE decisions
                SET title = :title, description = :description
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id'          => $id,
            'title'       => $title,
            'description' => $description,
        ]);
    }
}
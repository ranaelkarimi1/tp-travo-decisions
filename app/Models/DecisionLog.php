<?php

/**
 * DecisionLog
 * -----------
 * Accès aux données pour la table `decision_logs`.
 *
 * Chaque ligne représente une transition d'état d'une décision.
 * Les logs ne sont jamais modifiés ni supprimés : c'est un journal append-only
 * qui constitue la piste d'audit complète du workflow.
 */
class DecisionLog
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO decision_logs (decision_id, user_id, from_status, to_status, message)
                VALUES (:decision_id, :user_id, :from_status, :to_status, :message)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'decision_id' => $data['decision_id'],
            'user_id'     => $data['user_id'],
            'from_status' => $data['from_status'],
            'to_status'   => $data['to_status'],
            'message'     => $data['message'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Historique complet d'une décision, plus ancien d'abord.
     * On joint users pour avoir le nom de l'auteur de chaque transition.
     */
    public function getByDecisionId(int $decisionId): array
    {
        $sql = "SELECT l.*, u.name AS user_name
                FROM decision_logs l
                LEFT JOIN users u ON u.id = l.user_id
                WHERE l.decision_id = :decision_id
                ORDER BY l.created_at ASC, l.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['decision_id' => $decisionId]);
        return $stmt->fetchAll();
    }
}
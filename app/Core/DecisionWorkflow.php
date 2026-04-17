<?php

/**
 * DecisionWorkflow
 * ================
 * Service métier responsable de TOUTES les transitions d'état d'une décision.
 *
 * POURQUOI CE SERVICE EXISTE
 * --------------------------
 * Le cahier des charges impose trois invariants :
 *   1. Aucun changement d'état sans log correspondant.
 *   2. Aucune transition illégale (ex: approved -> pending).
 *   3. Une décision approuvée est immuable.
 *
 * Plutôt que de répartir ces règles dans plusieurs contrôleurs (et risquer d'en oublier
 * une), on les centralise dans une seule classe : DecisionWorkflow. Toutes les méthodes
 * qui changent l'état (submit, approve, reject, cancel, revise) passent par une unique
 * méthode privée `transition()` qui :
 *
 *   a) ouvre une transaction SQL,
 *   b) valide que la transition est autorisée (via DecisionStatus::canTransition),
 *   c) met à jour le statut,
 *   d) écrit un log,
 *   e) commit (ou rollback si n'importe laquelle des étapes échoue).
 *
 * Conséquence : il est STRUCTURELLEMENT impossible de changer un statut sans écrire
 * un log, parce que les deux opérations vivent dans la même transaction atomique.
 * C'est la réponse à la question "comment garantir que le changement d'état et
 * sa journalisation ne peuvent jamais être dissociés ?" du cahier des charges.
 *
 * Les contrôleurs n'appellent JAMAIS Decision::updateStatus() directement. Ils
 * appellent uniquement les méthodes publiques de ce service (submit, approve, etc.).
 */
class DecisionWorkflow
{
    private PDO $pdo;
    private Decision $decisions;
    private DecisionLog $logs;

    public function __construct()
    {
        $this->pdo       = Database::getConnection();
        $this->decisions = new Decision();
        $this->logs      = new DecisionLog();
    }

    // ---------------------------------------------------------------------
    // Création
    // ---------------------------------------------------------------------

    /**
     * Crée une décision à l'état draft + écrit le premier log.
     * La création est aussi une "transition" (depuis un état fictif '' vers 'draft'),
     * pour que l'historique soit complet dès le départ.
     */
    public function create(int $projectId, int $userId, string $title, string $description): int
    {
        $this->pdo->beginTransaction();
        try {
            $decisionId = $this->decisions->create([
                'project_id'  => $projectId,
                'user_id'     => $userId,
                'title'       => $title,
                'description' => $description,
                'status'      => DecisionStatus::DRAFT,
            ]);

            $this->logs->create([
                'decision_id' => $decisionId,
                'user_id'     => $userId,
                'from_status' => '',
                'to_status'   => DecisionStatus::DRAFT,
                'message'     => 'Décision créée (brouillon).',
            ]);

            $this->pdo->commit();
            return $decisionId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ---------------------------------------------------------------------
    // Transitions publiques (une méthode par action métier)
    // ---------------------------------------------------------------------

    public function submit(int $decisionId, int $userId): void
    {
        $this->transition(
            $decisionId,
            DecisionStatus::PENDING,
            $userId,
            'Décision envoyée en validation.'
        );
    }

    public function approve(int $decisionId, int $userId, ?string $comment = null): void
    {
        $message = 'Décision approuvée.' . ($comment ? ' Commentaire : ' . $comment : '');
        $this->transition(
            $decisionId,
            DecisionStatus::APPROVED,
            $userId,
            $message,
            $comment
        );
    }

    public function reject(int $decisionId, int $userId, ?string $comment = null): void
    {
        $message = 'Décision rejetée.' . ($comment ? ' Motif : ' . $comment : '');
        $this->transition(
            $decisionId,
            DecisionStatus::REJECTED,
            $userId,
            $message,
            $comment
        );
    }

    public function cancel(int $decisionId, int $userId, ?string $comment = null): void
    {
        $message = 'Décision annulée.' . ($comment ? ' Motif : ' . $comment : '');
        $this->transition(
            $decisionId,
            DecisionStatus::CANCELLED,
            $userId,
            $message,
            $comment
        );
    }

    /**
     * Remet une décision rejetée à l'état brouillon pour retravail.
     */
    public function revise(int $decisionId, int $userId): void
    {
        $this->transition(
            $decisionId,
            DecisionStatus::DRAFT,
            $userId,
            'Décision remise en brouillon pour révision.'
        );
    }

    // ---------------------------------------------------------------------
    // Cœur du service : la transition atomique
    // ---------------------------------------------------------------------

    /**
     * Unique point d'entrée pour tout changement d'état.
     *
     * @throws DomainException si la transition est illégale ou la décision finale.
     * @throws RuntimeException si la décision est introuvable.
     */
    private function transition(
        int $decisionId,
        string $toStatus,
        int $userId,
        string $logMessage,
        ?string $responseComment = null
    ): void {
        $this->pdo->beginTransaction();
        try {
            $decision = $this->decisions->getById($decisionId);
            if ($decision === null) {
                throw new RuntimeException("Décision #{$decisionId} introuvable.");
            }

            $fromStatus = $decision['status'];

            // Règle : une décision approuvée est immuable.
            if ($fromStatus === DecisionStatus::APPROVED) {
                throw new DomainException('Une décision approuvée ne peut plus être modifiée.');
            }

            // Règle : la transition doit figurer dans la matrice autorisée.
            if (!DecisionStatus::canTransition($fromStatus, $toStatus)) {
                throw new DomainException(
                    "Transition interdite : {$fromStatus} → {$toStatus}."
                );
            }

            // 1) Mise à jour du statut
            $this->decisions->updateStatus($decisionId, $toStatus, $responseComment);

            // 2) Écriture du log (dans la MÊME transaction)
            $this->logs->create([
                'decision_id' => $decisionId,
                'user_id'     => $userId,
                'from_status' => $fromStatus,
                'to_status'   => $toStatus,
                'message'     => $logMessage,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
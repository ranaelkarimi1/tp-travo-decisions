<?php
/** @var array $decision */
/** @var array $project */
/** @var array $logs */
/** @var array $available */  // liste des statuts atteignables depuis l'état courant
?>

<section class="container">
    <p>
        <a href="<?= BASE_URL ?>/projects/<?= (int) $project['id'] ?>/decisions">&larr; Retour aux décisions</a>
    </p>

    <h1><?= htmlspecialchars($decision['title']) ?></h1>
    <p>
        <strong>Statut :</strong>
        <span class="badge badge-<?= htmlspecialchars($decision['status']) ?>">
            <?= htmlspecialchars(DecisionStatus::label($decision['status'])) ?>
        </span>
    </p>
    <p><strong>Chantier :</strong> <?= htmlspecialchars($project['title']) ?></p>
    <p><strong>Créée le :</strong> <?= htmlspecialchars($decision['created_at']) ?></p>
    <?php if ($decision['validated_at']): ?>
        <p><strong>Validée le :</strong> <?= htmlspecialchars($decision['validated_at']) ?></p>
    <?php endif; ?>

    <h2>Description</h2>
    <p><?= nl2br(htmlspecialchars($decision['description'])) ?></p>

    <?php if ($decision['response_comment']): ?>
        <h2>Commentaire de réponse</h2>
        <p><em><?= nl2br(htmlspecialchars($decision['response_comment'])) ?></em></p>
    <?php endif; ?>

    <!--
      Actions disponibles : calculées par le contrôleur via
      DecisionStatus::availableTransitions($decision['status']).
      Principe : si un bouton est visible ici, le serveur acceptera l'action.
    -->
    <h2>Actions</h2>
    <?php if (empty($available)): ?>
        <p><em>Aucune action possible sur cette décision (état final).</em></p>
    <?php else: ?>
        <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
            <?php foreach ($available as $targetStatus):
                // Map chaque statut cible vers la route qui le déclenche.
                $routeMap = [
                    DecisionStatus::PENDING   => 'submit',
                    DecisionStatus::APPROVED  => 'approve',
                    DecisionStatus::REJECTED  => 'reject',
                    DecisionStatus::CANCELLED => 'cancel',
                    DecisionStatus::DRAFT     => 'revise',
                ];
                $route = $routeMap[$targetStatus] ?? null;
                if ($route === null) continue;

                $needsComment = in_array($targetStatus, [
                    DecisionStatus::APPROVED,
                    DecisionStatus::REJECTED,
                    DecisionStatus::CANCELLED,
                ], true);
            ?>
                <form method="POST" action="<?= BASE_URL ?>/decisions/<?= (int) $decision['id'] ?>/<?= $route ?>" style="margin:0;">
                    <?php if ($needsComment): ?>
                        <input type="text" name="comment" placeholder="Commentaire (optionnel)" style="padding:.4rem;">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-<?= htmlspecialchars($targetStatus) ?>">
                        <?= htmlspecialchars(DecisionStatus::actionLabel($targetStatus)) ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!--
      Historique complet : chaque transition d'état a produit une ligne dans
      decision_logs (garanti par DecisionWorkflow::transition() qui écrit le
      statut et le log dans la même transaction).
    -->
    <h2>Historique</h2>
    <?php if (empty($logs)): ?>
        <p><em>Aucun événement enregistré.</em></p>
    <?php else: ?>
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="text-align:left; border-bottom:2px solid #ccc;">
                    <th style="padding:.5rem;">Date</th>
                    <th style="padding:.5rem;">Transition</th>
                    <th style="padding:.5rem;">Par</th>
                    <th style="padding:.5rem;">Message</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:.5rem;"><?= htmlspecialchars($log['created_at']) ?></td>
                    <td style="padding:.5rem;">
                        <?php if ($log['from_status']): ?>
                            <?= htmlspecialchars(DecisionStatus::label($log['from_status'])) ?>
                        <?php else: ?>
                            <em>création</em>
                        <?php endif; ?>
                        &rarr;
                        <?= htmlspecialchars(DecisionStatus::label($log['to_status'])) ?>
                    </td>
                    <td style="padding:.5rem;"><?= htmlspecialchars($log['user_name'] ?? '#' . $log['user_id']) ?></td>
                    <td style="padding:.5rem;"><?= htmlspecialchars($log['message']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<style>
    .badge { padding: 2px 8px; border-radius: 10px; font-size: 0.85em; }
    .badge-draft     { background:#e0e0e0; color:#333; }
    .badge-pending   { background:#fff3cd; color:#856404; }
    .badge-approved  { background:#d4edda; color:#155724; }
    .badge-rejected  { background:#f8d7da; color:#721c24; }
    .badge-cancelled { background:#d6d8db; color:#383d41; }
    .btn { padding:.4rem .8rem; border:none; border-radius:4px; color:#fff; cursor:pointer; }
    .btn-pending   { background:#0d6efd; }
    .btn-approved  { background:#198754; }
    .btn-rejected  { background:#dc3545; }
    .btn-cancelled { background:#6c757d; }
    .btn-draft     { background:#ffc107; color:#333; }
</style>
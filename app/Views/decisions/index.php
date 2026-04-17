<?php /** @var array $project */ /** @var array $decisions */ ?>

<section class="container">
    <p>
        <a href="<?= BASE_URL ?>/projects/<?= (int) $project['id'] ?>">&larr; Retour au chantier</a>
    </p>

    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Décisions — <?= htmlspecialchars($project['title']) ?></h1>
        <a class="btn" href="<?= BASE_URL ?>/projects/<?= (int) $project['id'] ?>/decisions/create">
            + Nouvelle décision
        </a>
    </div>

    <?php if (empty($decisions)): ?>
        <p><em>Aucune décision pour ce chantier pour le moment.</em></p>
    <?php else: ?>
        <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
            <thead>
                <tr style="text-align:left; border-bottom:2px solid #ccc;">
                    <th style="padding:.5rem;">Titre</th>
                    <th style="padding:.5rem;">Statut</th>
                    <th style="padding:.5rem;">Créée le</th>
                    <th style="padding:.5rem;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($decisions as $d): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:.5rem;"><?= htmlspecialchars($d['title']) ?></td>
                    <td style="padding:.5rem;">
                        <span class="badge badge-<?= htmlspecialchars($d['status']) ?>">
                            <?= htmlspecialchars(DecisionStatus::label($d['status'])) ?>
                        </span>
                    </td>
                    <td style="padding:.5rem;"><?= htmlspecialchars($d['created_at']) ?></td>
                    <td style="padding:.5rem;">
                        <a href="<?= BASE_URL ?>/decisions/<?= (int) $d['id'] ?>">Voir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<style>
    .badge { padding: 2px 8px; border-radius: 10px; font-size: 0.85em; background:#eee; }
    .badge-draft     { background:#e0e0e0; color:#333; }
    .badge-pending   { background:#fff3cd; color:#856404; }
    .badge-approved  { background:#d4edda; color:#155724; }
    .badge-rejected  { background:#f8d7da; color:#721c24; }
    .badge-cancelled { background:#d6d8db; color:#383d41; text-decoration:line-through; }
    .btn { display:inline-block; padding:.4rem .8rem; background:#0d6efd; color:#fff; text-decoration:none; border-radius:4px; }
</style>
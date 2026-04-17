<?php /** @var array $project */ ?>

<section class="container">
    <p>
        <a href="<?= BASE_URL ?>/projects/<?= (int) $project['id'] ?>/decisions">&larr; Retour aux décisions</a>
    </p>

    <h1>Nouvelle décision</h1>
    <p><em>Chantier : <?= htmlspecialchars($project['title']) ?></em></p>

    <form method="POST" action="<?= BASE_URL ?>/projects/<?= (int) $project['id'] ?>/decisions/store">
        <div style="margin-bottom:1rem;">
            <label for="title">Titre</label><br>
            <input type="text" id="title" name="title" required maxlength="255" style="width:100%; padding:.5rem;">
        </div>

        <div style="margin-bottom:1rem;">
            <label for="description">Description</label><br>
            <textarea id="description" name="description" required rows="6" style="width:100%; padding:.5rem;"></textarea>
        </div>

        <p><small>La décision sera créée à l'état <strong>brouillon</strong>. Vous pourrez ensuite l'envoyer en validation.</small></p>

        <button type="submit" class="btn">Créer la décision</button>
    </form>
</section>

<style>
    .btn { display:inline-block; padding:.5rem 1rem; background:#0d6efd; color:#fff; border:none; border-radius:4px; cursor:pointer; }
</style>
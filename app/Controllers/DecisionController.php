<?php

class DecisionController extends Controller
{
    private Decision $decisionModel;
    private DecisionLog $logModel;
    private Project $projectModel;
    private DecisionWorkflow $workflow;
    private DecisionPolicy $policy;

    public function __construct()
    {
        $this->decisionModel = new Decision();
        $this->logModel      = new DecisionLog();
        $this->projectModel  = new Project();
        $this->workflow      = new DecisionWorkflow();
        $this->policy        = new DecisionPolicy();
    }

    public function index($projectId): void
    {
        $userId = $this->requireAuth();
        $projectId = (int) $projectId;

        $project = $this->projectModel->getByIdForUser($projectId, $userId);
        if (!$project) {
            $this->notFound();
            return;
        }

        $decisions = $this->decisionModel->getAllByProjectId($projectId);

        $this->view('decisions/index', [
            'project'   => $project,
            'decisions' => $decisions,
        ]);
    }

    public function create($projectId): void
    {
        $userId = $this->requireAuth();
        $projectId = (int) $projectId;

        if (!$this->policy->canCreateIn($userId, $projectId)) {
            $this->notFound();
            return;
        }

        $project = $this->projectModel->getByIdForUser($projectId, $userId);

        $this->view('decisions/create', ['project' => $project]);
    }

    public function store($projectId): void
    {
        $userId = $this->requireAuth();
        $projectId = (int) $projectId;

        if (!$this->policy->canCreateIn($userId, $projectId)) {
            Notification::setFlash('error', 'Action non autorisée.');
            $this->redirect('/projects');
            return;
        }

        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '' || $description === '') {
            Notification::setFlash('error', 'Le titre et la description sont obligatoires.');
            $this->redirect('/projects/' . $projectId . '/decisions/create');
            return;
        }

        try {
            $decisionId = $this->workflow->create($projectId, $userId, $title, $description);
            Notification::setFlash('success', 'Décision créée en brouillon.');
            $this->redirect('/decisions/' . $decisionId);
        } catch (\Throwable $e) {
            Notification::setFlash('error', 'Erreur lors de la création : ' . $e->getMessage());
            $this->redirect('/projects/' . $projectId . '/decisions/create');
        }
    }

    public function show($id): void
    {
        $userId = $this->requireAuth();
        $id = (int) $id;

        $decision = $this->decisionModel->getById($id);
        if (!$decision) {
            $this->notFound();
            return;
        }

        $this->policy->authorizeOrFail($userId, $decision);

        $project   = $this->projectModel->getById((int) $decision['project_id']);
        $logs      = $this->logModel->getByDecisionId($id);
        $available = DecisionStatus::availableTransitions($decision['status']);

        $this->view('decisions/show', [
            'decision'  => $decision,
            'project'   => $project,
            'logs'      => $logs,
            'available' => $available,
        ]);
    }

    public function submit($id): void
    {
        $this->runTransition((int) $id, function (int $decisionId, int $userId) {
            $this->workflow->submit($decisionId, $userId);
        }, 'Décision envoyée en validation.');
    }

    public function approve($id): void
    {
        $this->runTransition((int) $id, function (int $decisionId, int $userId) {
            $comment = trim($_POST['comment'] ?? '') ?: null;
            $this->workflow->approve($decisionId, $userId, $comment);
        }, 'Décision approuvée.');
    }

    public function reject($id): void
    {
        $this->runTransition((int) $id, function (int $decisionId, int $userId) {
            $comment = trim($_POST['comment'] ?? '') ?: null;
            $this->workflow->reject($decisionId, $userId, $comment);
        }, 'Décision rejetée.');
    }

    public function cancel($id): void
    {
        $this->runTransition((int) $id, function (int $decisionId, int $userId) {
            $comment = trim($_POST['comment'] ?? '') ?: null;
            $this->workflow->cancel($decisionId, $userId, $comment);
        }, 'Décision annulée.');
    }

    public function revise($id): void
    {
        $this->runTransition((int) $id, function (int $decisionId, int $userId) {
            $this->workflow->revise($decisionId, $userId);
        }, 'Décision remise en brouillon.');
    }

    private function runTransition(int $id, callable $action, string $successMessage): void
    {
        $userId = $this->requireAuth();

        $decision = $this->decisionModel->getById($id);
        if (!$decision) {
            $this->notFound();
            return;
        }

        $this->policy->authorizeOrFail($userId, $decision);

        try {
            $action($id, $userId);
            Notification::setFlash('success', $successMessage);
        } catch (DomainException $e) {
            Notification::setFlash('error', $e->getMessage());
        } catch (\Throwable $e) {
            Notification::setFlash('error', 'Erreur inattendue : ' . $e->getMessage());
        }

        $this->redirect('/decisions/' . $id);
    }
}
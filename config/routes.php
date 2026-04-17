<?php

//Routes pour la page d'accueil et autre pages statiques
$router->get('/', [HomeController::class, 'index']);
$router->get('/about', [HomeController::class, 'about']);

// Routes pour les projets
$router->get('/projects', [ProjectController::class, 'index']);
$router->get('/projects/create', [ProjectController::class, 'create']); //Afficher le formulaire de création
$router->post('/projects/store', [ProjectController::class, 'store']); //Ajouter le projet en BDD
$router->get('/projects/{id}/edit', [ProjectController::class, 'edit']);
$router->post('/projects/{id}/update', [ProjectController::class, 'update']);
$router->post('/projects/{id}/delete', [ProjectController::class, 'destroy']);
$router->get('/projects/{id}', [ProjectController::class, 'show']);

// Routes pour les updates
$router->get('/projects/{id}/updates', [UpdateController::class, 'index']);
$router->get('/projects/{id}/updates/create', [UpdateController::class, 'create']);
$router->post('/projects/{id}/updates/store', [UpdateController::class, 'store']);

// Routes pour la connexion et l'inscription
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/account', [AuthController::class, 'account']);

$router->post('/media/store', [MediaController::class, 'store']);




// Routes pour les décisions
$router->get('/projects/{id}/decisions', [DecisionController::class, 'index']);
$router->get('/projects/{id}/decisions/create', [DecisionController::class, 'create']);
$router->post('/projects/{id}/decisions/store', [DecisionController::class, 'store']);
$router->get('/decisions/{id}', [DecisionController::class, 'show']);
$router->post('/decisions/{id}/submit', [DecisionController::class, 'submit']);
$router->post('/decisions/{id}/approve', [DecisionController::class, 'approve']);
$router->post('/decisions/{id}/reject', [DecisionController::class, 'reject']);
$router->post('/decisions/{id}/cancel', [DecisionController::class, 'cancel']);
$router->post('/decisions/{id}/revise', [DecisionController::class, 'revise']);
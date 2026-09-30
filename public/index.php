<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use HireCraft\Controllers\AdminController;
use HireCraft\Controllers\ArtisanSearchController;
use HireCraft\Controllers\AuthController;
use HireCraft\Controllers\FavoriteController;
use HireCraft\Controllers\HireController;
use HireCraft\Controllers\HomeController;
use HireCraft\Controllers\JobController;
use HireCraft\Controllers\MessageController;
use HireCraft\Controllers\MilestoneController;
use HireCraft\Controllers\NotificationController;
use HireCraft\Controllers\ProfileController;
use HireCraft\Controllers\SafetyController;
use HireCraft\Controllers\VerificationController;
use HireCraft\Support\Router;

$router = new Router();

$router->get('/', function () {
    (new HomeController())->index();
});
$router->get('/dashboard', function () {
    (new HomeController())->dashboard();
});

$router->get('/register', function () {
    (new AuthController())->showRegister();
});
$router->post('/register', function () {
    (new AuthController())->register();
});
$router->get('/login', function () {
    (new AuthController())->showLogin();
});
$router->post('/login', function () {
    (new AuthController())->login();
});
$router->post('/logout', function () {
    (new AuthController())->logout();
});

$router->get('/profile/setup', function () {
    (new ProfileController())->showSetup();
});
$router->post('/profile/setup', function () {
    (new ProfileController())->saveSetup();
});
$router->get('/profile/portfolio', function () {
    (new ProfileController())->showPortfolio();
});
$router->post('/profile/portfolio', function () {
    (new ProfileController())->addPortfolioItem();
});
$router->post('/profile/portfolio/{id}/delete', function (array $args) {
    (new ProfileController())->deletePortfolioItem($args);
});
$router->get('/profile/picture', function () {
    (new ProfileController())->showAvatar();
});
$router->post('/profile/picture', function () {
    (new ProfileController())->saveAvatar();
});
$router->post('/profile/picture/remove', function () {
    (new ProfileController())->removeAvatar();
});
$router->get('/artisans/{id}', function (array $args) {
    (new ProfileController())->showArtisanProfile($args);
});
$router->get('/find-artisan', function () {
    (new ArtisanSearchController())->index();
});
$router->get('/favorites', function () {
    (new FavoriteController())->index();
});
$router->post('/artisans/{id}/favorite', function (array $args) {
    (new FavoriteController())->add($args);
});
$router->post('/artisans/{id}/unfavorite', function (array $args) {
    (new FavoriteController())->remove($args);
});

$router->get('/report', function () {
    (new SafetyController())->showReportForm();
});
$router->post('/reports', function () {
    (new SafetyController())->createReport();
});
$router->get('/jobs/{id}/dispute/new', function (array $args) {
    (new SafetyController())->showDisputeForm($args);
});
$router->post('/jobs/{id}/dispute', function (array $args) {
    (new SafetyController())->openDispute($args);
});
$router->get('/jobs/{id}/dispute', function (array $args) {
    (new SafetyController())->showDispute($args);
});
$router->post('/jobs/{id}/dispute/respond', function (array $args) {
    (new SafetyController())->respondToDispute($args);
});

$router->get('/verifications', function () {
    (new VerificationController())->index();
});
$router->post('/verifications', function () {
    (new VerificationController())->submit();
});

$router->get('/notifications', function () {
    (new NotificationController())->index();
});
$router->get('/notifications/{id}', function (array $args) {
    (new NotificationController())->open($args);
});

$router->get('/jobs/new', function () {
    (new JobController())->showNew();
});
$router->post('/jobs', function () {
    (new JobController())->create();
});
$router->get('/jobs/{id}/edit', function (array $args) {
    (new JobController())->showEdit($args);
});
$router->post('/jobs/{id}/edit', function (array $args) {
    (new JobController())->update($args);
});
$router->get('/jobs/{id}/results', function (array $args) {
    (new JobController())->showResults($args);
});
$router->get('/jobs/{id}/compare', function (array $args) {
    (new JobController())->compare($args);
});
$router->post('/jobs/{id}/feedback', function (array $args) {
    (new JobController())->submitFeedback($args);
});

$router->get('/jobs/{id}/messages/{other}', function (array $args) {
    (new MessageController())->thread($args);
});
$router->post('/jobs/{id}/messages/{other}', function (array $args) {
    (new MessageController())->send($args);
});

$router->post('/jobs/{id}/request/{artisan}', function (array $args) {
    (new HireController())->requestQuotation($args);
});
$router->get('/jobs/{id}', function (array $args) {
    (new HireController())->detail($args);
});
$router->post('/jobs/{id}/quotations/{quotation}/accept', function (array $args) {
    (new HireController())->acceptQuotation($args);
});
$router->post('/jobs/{id}/quotations/{quotation}/decline', function (array $args) {
    (new HireController())->declineQuotation($args);
});
$router->post('/jobs/{id}/start', function (array $args) {
    (new HireController())->startJob($args);
});
$router->post('/jobs/{id}/complete', function (array $args) {
    (new HireController())->completeJob($args);
});
$router->post('/jobs/{id}/confirm', function (array $args) {
    (new HireController())->confirmJob($args);
});
$router->post('/jobs/{id}/milestones', function (array $args) {
    (new MilestoneController())->add($args);
});
$router->post('/milestones/{id}/status', function (array $args) {
    (new MilestoneController())->updateStatus($args);
});

$router->get('/jobs/{id}/review', function (array $args) {
    (new HireController())->showReview($args);
});
$router->post('/jobs/{id}/review', function (array $args) {
    (new HireController())->submitReview($args);
});

$router->get('/artisan/jobs', function () {
    (new HireController())->browseJobs();
});
$router->post('/artisan/jobs/{id}/apply', function (array $args) {
    (new HireController())->applyToJob($args);
});
$router->get('/artisan/requests', function () {
    (new HireController())->myRequests();
});
$router->post('/artisan/requests/{id}/quote', function (array $args) {
    (new HireController())->submitQuotation($args);
});
$router->post('/artisan/requests/{id}/decline', function (array $args) {
    (new HireController())->declineRequest($args);
});

$router->get('/admin/artisans', function () {
    (new AdminController())->artisans();
});
$router->get('/admin/jobs', function () {
    (new AdminController())->jobs();
});
$router->post('/admin/artisans/{id}/approve', function (array $args) {
    (new AdminController())->approveArtisan($args);
});
$router->post('/admin/artisans/{id}/reject', function (array $args) {
    (new AdminController())->rejectArtisan($args);
});
$router->post('/admin/artisans/{id}/suspend', function (array $args) {
    (new AdminController())->suspendArtisan($args);
});
$router->get('/admin/weights', function () {
    (new AdminController())->weights();
});
$router->post('/admin/weights/save', function () {
    (new AdminController())->saveWeights();
});
$router->get('/admin/benchmarks', function () {
    (new AdminController())->benchmarks();
});
$router->post('/admin/benchmarks', function () {
    (new AdminController())->updateBenchmarks();
});
$router->get('/admin/reports', function () {
    (new AdminController())->reports();
});
$router->post('/admin/reports/{id}/act/{action}', function (array $args) {
    (new AdminController())->actOnReport($args);
});
$router->get('/admin/disputes', function () {
    (new AdminController())->disputes();
});
$router->post('/admin/disputes/{id}/resolve', function (array $args) {
    (new AdminController())->resolveDispute($args);
});
$router->get('/admin/verifications', function () {
    (new AdminController())->verifications();
});
$router->post('/admin/verifications/{id}/{action}', function (array $args) {
    (new AdminController())->reviewVerification($args);
});

$router->get('/admin/evaluation', function () {
    (new AdminController())->evaluation();
});
$router->get('/admin/evaluation/export', function () {
    (new AdminController())->exportEvaluation();
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

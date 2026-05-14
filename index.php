@<?php
/*
 * index.php
 * Public landing page — shows a hero, event details and a preview of recent
 * contestants. Links to login/vote/results depending on authentication state.
 */
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

// Fetch a small set of recent contestants to display on the homepage
$previewStmt = $pdo->query("SELECT id, name, gender, photo, bio FROM contestants ORDER BY created_at DESC LIMIT 6");
$previewContestants = $previewStmt->fetchAll();
?>
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="hero-card p-4 p-lg-5">
                    <span class="badge badge-gold">Varsity Ball Voting</span>
                    <h1 class="display-5 mt-3">Vote for the stars of UMU Rubaga</h1>
                    <p class="mt-3 text-muted">Celebrate charisma, leadership, and style. Rate every contestant across each category to crown Mr &amp; Mrs UMU Rubaga.</p>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <?php if (is_logged_in()): ?>
                            <a class="btn btn-primary btn-lg" href="vote.php">Start Voting</a>
                            <a class="btn btn-outline-light btn-lg" href="results.php">View Results</a>
                        <?php else: ?>
                            <a class="btn btn-primary btn-lg" href="login.php">Login with Google</a>
                            <a class="btn btn-outline-light btn-lg" href="results.php">View Results</a>
                        <?php endif; ?>
                    </div>
                    <div class="mt-4">
                        <i class="bi bi-calendar-event"></i>
                        <span class="ms-2" id="countdown" data-event-date="<?php echo h($config['app']['event_date']); ?>"></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card-dark p-4">
                    <div class="section-title">
                        <span>Contestants</span>
                        <h2 class="mb-0">Meet the nominees</h2>
                    </div>
                    <div class="row g-3">
                        <?php if ($previewContestants): ?>
                            <?php foreach ($previewContestants as $contestant): ?>
                                <div class="col-6">
                                    <div class="card-dark p-3">
                                        <img class="contestant-img" src="<?php echo h(asset_url($contestant['photo'], $config)); ?>" alt="<?php echo h($contestant['name']); ?>">
                                        <h5 class="mt-3 mb-1"><?php echo h($contestant['name']); ?></h5>
                                        <small class="text-muted text-uppercase"><?php echo h($contestant['gender']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <p class="text-muted mb-0">Contestants will appear here once the admin adds them.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card-dark p-4 h-100">
                    <h3>Authentic voting</h3>
                    <p class="text-muted">Google login ensures only verified UMU students cast a single vote.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-dark p-4 h-100">
                    <h3>Score based</h3>
                    <p class="text-muted">Rate contestants 1-5 across every category to crown the real favorites.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-dark p-4 h-100">
                    <h3>Live results</h3>
                    <p class="text-muted">Track category leaders and the overall Mr &amp; Mrs UMU Rubaga in real time.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<section class="pb-5">
    <div class="container">
        <div class="card-dark p-4">
            <div class="section-title">
                <span>How it works</span>
                <h2 class="mb-0">How winners are awarded</h2>
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="card-dark p-3 h-100">
                        <h5>Step 1</h5>
                        <p class="text-muted mb-0">Every student rates each contestant from 1 to 5 in every category.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-dark p-3 h-100">
                        <h5>Step 2</h5>
                        <p class="text-muted mb-0">For each category, the system calculates the average score per contestant.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-dark p-3 h-100">
                        <h5>Step 3</h5>
                        <p class="text-muted mb-0">Category winners are the highest average in that category.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-dark p-3 h-100">
                        <h5>Step 4</h5>
                        <p class="text-muted mb-0">Overall Mr &amp; Mrs UMU Rubaga are the highest combined average.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

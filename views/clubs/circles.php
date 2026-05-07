<?php

require_once BASE_PATH . '/app/views/partials/header.php';
require_once BASE_PATH . '/app/views/partials/navbar.php';

// Build quick lookup of circles the user has joined
$myCircleIds = array_column($myCircles, 'id');
?>

<div class="container py-4">
  <h1 class="section-title mb-2">Interest Circles</h1>
  <p class="text-muted mb-4">Join circles based on your reading interests. Each circle is a micro-community.</p>

  <!-- Join / Create Circle Form -->
  <div class="card border-0 shadow-sm mb-4 p-4" style="max-width:480px;">
    <h5 class="fw-bold mb-2">Join a Circle by Tag</h5>
    <form method="POST" action="<?= BASE_URL ?>index.php?page=circles&action=joinCircle">
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-tag"></i></span>
        <input type="text" name="tag" class="form-control"
               placeholder="e.g. sci-fi, poetry, manga…" required>
        <button type="submit" class="btn btn-primary">Join / Create</button>
      </div>
      <div class="form-text">If the circle doesn't exist yet, it will be created automatically.</div>
    </form>
  </div>

  <div class="row g-4">
    <!-- All Circles -->
    <div class="col-md-8">
      <h5 class="fw-bold mb-3">All Circles</h5>
      <?php if (empty($allCircles)): ?>
        <p class="text-muted">No circles yet. Be the first to create one!</p>
      <?php else: ?>
      <div class="row g-3">
        <?php foreach ($allCircles as $circle): ?>
        <div class="col-sm-6 col-md-4">
          <div class="card border-0 shadow-sm h-100 text-center p-3">
            <div class="fw-bold mb-1"># <?= sanitize($circle['tag']) ?></div>
            <div class="text-muted small"><?= (int)$circle['member_count'] ?> members</div>
            <?php if (!in_array($circle['id'], $myCircleIds)): ?>
            <form method="POST" action="<?= BASE_URL ?>index.php?page=circles&action=joinCircle" class="mt-2">
              <input type="hidden" name="tag" value="<?= sanitize($circle['tag']) ?>">
              <button type="submit" class="btn btn-outline-primary btn-sm">Join</button>
            </form>
            <?php else: ?>
              <span class="badge bg-success mt-2">✓ Joined</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- My Circles -->
    <div class="col-md-4">
      <h5 class="fw-bold mb-3">My Circles</h5>
      <?php if (empty($myCircles)): ?>
        <p class="text-muted small">You haven't joined any circles yet.</p>
      <?php else: ?>
      <div class="list-group shadow-sm">
        <?php foreach ($myCircles as $c): ?>
        <div class="list-group-item d-flex align-items-center gap-2">
          <i class="bi bi-circle-fill text-accent" style="font-size:.5rem;"></i>
          <span># <?= sanitize($c['tag']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/app/views/partials/footer.php'; ?>

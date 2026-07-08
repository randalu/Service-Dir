<footer class="bg-dark text-white mt-5 pt-4 pb-3">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <h6 class="fw-bold mb-3">About</h6>
        <p class="text-white-50 small">Raddoluwa/Seeduwa Service Directory is your local online hub to find and promote services in your area. Free listings for everyone!</p>
      </div>
      <div class="col-md-4">
        <h6 class="fw-bold mb-3">Quick Links</h6>
        <ul class="list-unstyled small">
          <li class="mb-1"><a href="index.php" class="text-white-50 text-decoration-none">Home</a></li>
          <li class="mb-1"><a href="register.php" class="text-white-50 text-decoration-none">Register</a></li>
          <li class="mb-1"><a href="login.php" class="text-white-50 text-decoration-none">Login</a></li>
          <li class="mb-1"><a href="dashboard.php" class="text-white-50 text-decoration-none">Dashboard</a></li>
        </ul>
      </div>
      <div class="col-md-4">
        <h6 class="fw-bold mb-3">Contact</h6>
        <ul class="list-unstyled small text-white-50">
          <li class="mb-1">info@servicedirectory.lk</li>
          <li class="mb-1">+94 77 123 4567</li>
          <li>Raddoluwa, Sri Lanka</li>
        </ul>
      </div>
    </div>
    <hr class="border-secondary my-3" style="opacity:0.15">
    <div class="text-center small text-white-50">
      &copy; <?= date("Y") ?> <?= htmlspecialchars(getSetting('site_name', 'Raddoluwa/Seeduwa Service Directory')) ?>. All rights reserved.
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
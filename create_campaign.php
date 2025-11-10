<?php
require_once __DIR__ . '/app.php';

$errors = [];
$successId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = create_campaign($_POST, $_FILES['image'] ?? null);
        $successId = $id;
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Campaign · No Starve</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <header class="site-header" role="banner">
        <div class="container header-inner">
            <a href="/index.php#hero" class="brand" aria-label="No Starve home">No Starve</a>
            <nav id="primary-navigation" class="nav-links" role="navigation" aria-label="Primary">
                <a href="/index.php#hero">Home</a>
                <a href="/create_campaign.php">Create Campaign</a>
            </nav>
        </div>
    </header>

    <main class="container" style="max-width: var(--content-max); padding: var(--content-pad);">
        <section class="card-plain" aria-label="Create Campaign">
            <h2 class="section-title">Create Campaign</h2>

            <?php if (!empty($errors)): ?>
                <div class="card-plain" role="alert">
                    <strong>Error:</strong>
                    <ul class="list-clean">
                        <?php foreach ($errors as $err): ?>
                            <li><?= h($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($successId): ?>
                <div class="card-plain" role="status">
                    Campaign created successfully. ID: <?= h((string)$successId) ?>
                </div>
            <?php endif; ?>

            <form class="form-grid" method="post" enctype="multipart/form-data">
                <div class="form-field">
                    <label for="contributor_name">Contributor Name</label>
                    <input type="text" id="contributor_name" name="contributor_name" required>
                </div>

                <div class="form-field">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" required autocomplete="off" aria-autocomplete="list" aria-controls="location-suggestions">
                    <input type="hidden" id="latitude" name="latitude">
                    <input type="hidden" id="longitude" name="longitude">
                    <div id="location-suggestions" class="card-plain" role="listbox" style="position: absolute; z-index: 10; display: none; max-height: 220px; overflow: auto;"></div>
                </div>

                <div class="form-field">
                    <label for="crowd_size">Crowd Size</label>
                    <input type="number" id="crowd_size" name="crowd_size" min="0" step="1" required>
                </div>

                <div class="form-field">
                    <label for="closing_time">Closing Time</label>
                    <input type="datetime-local" id="closing_time" name="closing_time" required>
                </div>

                <div class="form-field">
                    <label for="image">Upload Image</label>
                    <input type="file" id="image" name="image" accept="image/*">
                </div>

                <div class="actions">
                    <button class="btn" type="submit">Create</button>
                </div>
            </form>
        </section>
    </main>

    <script>
    (function(){
        const input = document.getElementById('location');
        const box = document.getElementById('location-suggestions');
        const latEl = document.getElementById('latitude');
        const lonEl = document.getElementById('longitude');
        let timer = null;

        function hideSuggestions(){ box.style.display = 'none'; box.innerHTML=''; }

        function renderSuggestions(items){
            if (!items || items.length === 0) { hideSuggestions(); return; }
            box.innerHTML = '';
            items.forEach(function(it, idx){
                const opt = document.createElement('div');
                opt.setAttribute('role','option');
                opt.textContent = it.label || (it.lat + ',' + it.lon);
                opt.style.padding = '8px 10px';
                opt.style.cursor = 'pointer';
                opt.addEventListener('mousedown', function(e){ e.preventDefault(); });
                opt.addEventListener('click', function(){
                    input.value = it.label;
                    latEl.value = it.lat || '';
                    lonEl.value = it.lon || '';
                    hideSuggestions();
                });
                box.appendChild(opt);
            });
            const rect = input.getBoundingClientRect();
            box.style.width = rect.width + 'px';
            box.style.display = 'block';
        }

        input.addEventListener('input', function(){
            latEl.value = '';
            lonEl.value = '';
            const q = input.value.trim();
            if (timer) clearTimeout(timer);
            if (!q) { hideSuggestions(); return; }
            timer = setTimeout(function(){
                fetch('/geocode.php?q=' + encodeURIComponent(q))
                  .then(function(r){ return r.json(); })
                  .then(function(json){ renderSuggestions(json.results || []); })
                  .catch(function(){ hideSuggestions(); });
            }, 250);
        });

        document.addEventListener('click', function(e){
            if (e.target !== input && !box.contains(e.target)) hideSuggestions();
        });
    })();
    </script>

    <footer class="site-footer">
        <div class="container footer-inner">
            <small>&copy; <?= date('Y') ?> No Starve</small>
        </div>
    </footer>
</body>
</html>
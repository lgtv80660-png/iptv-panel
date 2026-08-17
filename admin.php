<?php
session_start();
require 'config.php';
require 'db_migrations.php';
ensure_panel_schema($pdo);
try { $pdo->query("ALTER TABLE fournisseurs ADD COLUMN mac_address VARCHAR(64) DEFAULT NULL"); } catch (Throwable $e) {}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged']);
    header("Location: admin.php");
    exit;
}

$login_error = '';
if (isset($_POST['login_btn'])) {
    $user_input = trim($_POST['username'] ?? '');
    $pass_input = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$user_input]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && $pass_input === $admin['password']) {
        $_SESSION['admin_logged'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $login_error = "Identifiant ou mot de passe incorrect.";
    }
}

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true):
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - IPTV Proxy Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-dark: #0f1219; --panel-bg: #1a1d26; --accent: #00d2ff; --text-main: #e0e0e0; --border-color: #2d3240; }
        body { background-color: var(--bg-dark); color: var(--text-main); font-family: 'Segoe UI', sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-panel { background: var(--panel-bg); padding: 40px; border-radius: 12px; border: 1px solid var(--border-color); width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .form-control { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: #fff; padding: 12px; }
        .form-control:focus { background-color: var(--bg-dark); color: #fff; border-color: var(--accent); box-shadow: none; }
        .btn-primary { background: var(--accent); border: none; padding: 12px; font-weight: bold; color: #000; }
        .btn-primary:hover { opacity: 0.9; }
    </style>
    <link rel="stylesheet" href="assets/gpanel.css">
</head>
<body>
    <div class="login-panel">
        <div class="text-center mb-4">
            <img src="assets/g-panel-logo.png" alt="G-PANEL" style="width:260px;max-width:100%;height:auto;display:block;margin:0 auto 14px;">
            <p class="text-muted" style="font-size:14px;">Espace Administrateur sécurisé</p>
        </div>
        <?php if($login_error): ?>
            <div class="alert alert-danger py-2" style="font-size:13px;"><?= $login_error ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label text-muted" style="font-size:12px; text-transform:uppercase;">Utilisateur</label>
                <input type="text" name="username" class="form-control" required autocomplete="off">
            </div>
            <div class="mb-4">
                <label class="form-label text-muted" style="font-size:12px; text-transform:uppercase;">Mot de passe</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" name="login_btn" class="btn btn-primary w-100">Se connecter</button>
        </form>
    </div>
<script src="assets/gpanel-ui.js"></script>
</body>
</html>
<?php 
exit; 
endif; 

// --- CODE DU PANEL ADMIN ---
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_dir = dirname($_SERVER['PHP_SELF']);
if ($base_dir === '\\' || $base_dir === '/') $base_dir = '';
$server_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $base_dir;

$message = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] == '1') $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Ajout effectué avec succès !</div>';
    if ($_GET['success'] == 'updated') $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Élément mis à jour !</div>';
    if ($_GET['success'] == 'deleted') $message = '<div class="alert alert-warning"><i class="fas fa-trash"></i> Élément supprimé !</div>';
}

// --- AJOUT CLIENT ---
if (isset($_POST['add_client'])) {
    $stmt = $pdo->prepare("INSERT INTO clients (username, password) VALUES (?, ?)");
    if($stmt->execute([$_POST['username'], $_POST['password']])) {
        header("Location: admin.php?success=1"); exit;
    }
}

// --- MODIFICATION CLIENT ---
if (isset($_POST['edit_client_btn'])) {
    $stmt = $pdo->prepare("UPDATE clients SET username = ?, password = ? WHERE id = ?");
    if($stmt->execute([$_POST['edit_client_username'], $_POST['edit_client_password'], $_POST['client_id']])) {
        header("Location: admin.php?success=updated"); exit;
    }
}

// --- SUPPRESSION CLIENT ---
if (isset($_POST['delete_client'])) {
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
    if($stmt->execute([$_POST['client_id']])) {
        header("Location: admin.php?success=deleted"); exit;
    }
}

// --- AJOUT SOURCE ---
if (isset($_POST['add_source'])) {
    $nom = $_POST['nom'];
    $type = $_POST['type'];
    $url_base = $_POST['url_base'];
    $user = $_POST['user'] ?? null;
    $pass = $_POST['pass'] ?? null;
    $mac_address = $_POST['mac_address'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO fournisseurs (nom, type, url_base, user, pass, mac_address) VALUES (?, ?, ?, ?, ?, ?)");
    if($stmt->execute([$nom, $type, $url_base, $user, $pass, $mac_address])) {
        // Après création, importer uniquement cette nouvelle source.
        $new_source_id = (int)$pdo->lastInsertId();
        header("Location: importer.php?fournisseur_id=" . $new_source_id);
        exit;
    }
}

// --- MODIFICATION SOURCE ---
if (isset($_POST['edit_source_btn'])) {
    $stmt = $pdo->prepare("UPDATE fournisseurs SET nom = ?, url_base = ?, user = ?, pass = ?, mac_address = ? WHERE id = ?");
    if($stmt->execute([$_POST['edit_nom'], $_POST['edit_url'], $_POST['edit_user'], $_POST['edit_pass'], $_POST['edit_mac_address'] ?? null, $_POST['source_id']])) {
        header("Location: admin.php?success=updated"); exit;
    }
}

// --- SUPPRESSION SOURCE ---
if (isset($_POST['delete_source'])) {
    $id = (int)($_POST['source_id'] ?? 0);
    if ($id > 0) {
        try {
            $pdo->beginTransaction();
            // Delete all content belonging to this supplier, including categories.
            $pdo->prepare("DELETE FROM streams WHERE fournisseur_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM categories WHERE fournisseur_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM fournisseurs WHERE id = ?")->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
    header("Location: admin.php?success=deleted"); exit;
}

$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_sources = $pdo->query("SELECT COUNT(*) FROM fournisseurs")->fetchColumn();

$clients = $pdo->query("SELECT * FROM clients ORDER BY id DESC")->fetchAll();
$fournisseurs = $pdo->query("SELECT * FROM fournisseurs ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPTV Proxy Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-dark: #0f1219; --panel-bg: #1a1d26; --accent: #00d2ff; --accent-hover: #3a7bd5; --text-main: #e0e0e0; --text-muted: #8b92a5; --border-color: #2d3240; }
        body { background-color: var(--bg-dark); color: var(--text-main); font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .sidebar { background-color: var(--panel-bg); height: 100vh; position: fixed; width: 260px; border-right: 1px solid var(--border-color); padding-top: 20px; z-index: 1000; }
        .sidebar .brand { color: #fff; font-size: 22px; font-weight: 700; text-align: center; margin-bottom: 40px; letter-spacing: 1px; }
        .sidebar .brand span { color: var(--accent); }
        .sidebar a { color: var(--text-muted); text-decoration: none; display: block; padding: 15px 25px; font-size: 15px; transition: 0.3s; border-left: 4px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background: linear-gradient(90deg, rgba(0, 210, 255, 0.1), transparent); color: #fff; border-left: 4px solid var(--accent); }
        .sidebar a i { margin-right: 12px; width: 20px; text-align: center; }
        .main-content { margin-left: 260px; padding: 30px; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); }
        .user-profile { display: flex; align-items: center; gap: 15px; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent-hover)); display: flex; align-items: center; justify-content: center; font-weight: bold; color: #fff; }
        .glass-panel { background: var(--panel-bg); border-radius: 12px; padding: 25px; margin-bottom: 25px; border: 1px solid var(--border-color); box-shadow: 0 8px 16px rgba(0,0,0,0.2); }
        .stat-card { display: flex; align-items: center; gap: 20px; }
        .stat-card .icon { font-size: 40px; color: var(--accent); background: rgba(0, 210, 255, 0.1); padding: 15px; border-radius: 12px; }
        .stat-card h3 { margin: 0; font-size: 28px; color: #fff; font-weight: 700; }
        .stat-card p { margin: 0; color: var(--text-muted); font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
        .form-control, .form-select { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: #fff; padding: 12px 15px; }
        .form-control:focus, .form-select:focus { background-color: var(--bg-dark); color: #fff; border-color: var(--accent); box-shadow: 0 0 0 0.25rem rgba(0, 210, 255, 0.25); }
        .form-label { color: var(--text-muted); font-size: 13px; text-transform: uppercase; }
        .btn-primary { background: linear-gradient(90deg, var(--accent-hover), var(--accent)); border: none; padding: 10px 25px; font-weight: 600; letter-spacing: 0.5px; }
        .btn-primary:hover { opacity: 0.9; }
        .btn-info-custom { background: rgba(0, 210, 255, 0.1); border: 1px solid var(--accent); color: var(--accent); padding: 5px 12px; border-radius: 6px; font-size: 13px; transition: 0.3s; }
        .btn-info-custom:hover { background: var(--accent); color: #fff; }
        .table { color: var(--text-main); margin-top: 15px; }
        .table th { background-color: var(--bg-dark); color: var(--text-muted); border-bottom: 2px solid var(--border-color); font-weight: 600; text-transform: uppercase; font-size: 12px; padding: 15px; }
        .table td { background-color: transparent; border-bottom: 1px solid var(--border-color); vertical-align: middle; padding: 15px; color: #fff;}
        .badge-active { background: rgba(40, 167, 69, 0.2); color: #28a745; padding: 5px 10px; border-radius: 6px; font-size: 12px; }
        .type-badge { background: rgba(0, 210, 255, 0.1); color: var(--accent); padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .modal-content { background-color: var(--panel-bg); border: 1px solid var(--border-color); color: #fff; }
        .modal-header { border-bottom: 1px solid var(--border-color); }
        .modal-footer { border-top: 1px solid var(--border-color); }
        .copy-box { background: var(--bg-dark); padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); font-family: monospace; font-size: 13px; margin-bottom: 15px; word-break: break-all; }
        .copy-box span { color: var(--accent); }
    </style>
    <link rel="stylesheet" href="assets/gpanel.css">
</head>
<body>
    <aside class="sidebar">
        <div class="brand"><img class="brand-full" src="assets/g-panel-logo.png" alt="G-PANEL"><img class="brand-mark" src="assets/g-panel-mark.png" alt="G-PANEL"></div><div class="gp-brand-mini">IPTV MANAGEMENT SYSTEM</div>
        <div class="gp-section-label">Navigation</div><a href="admin.php" class="active"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a>
        <a href="editor.php"><i class="fas fa-folder-open"></i> Gestion des Bouquets</a>
        <div class="gp-section-label">Gestion</div><a href="#clients"><i class="fas fa-users"></i> Gestion Clients</a>
        <a href="#sources"><i class="fas fa-server"></i> Fournisseurs (Sources)</a>
        <a href="importer.php" target="_blank"><i class="fas fa-sync-alt"></i> Forcer l'importation</a>
        <a href="admin.php?logout=1" style="color: #ff4757; margin-top: 30px;"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="gp-page-title"><div><div class="eyebrow">G-PANEL / Overview</div><h1>Tableau de bord</h1></div><span class="gp-chip"><i class="fas fa-shield-halved"></i> Système opérationnel</span></div>
            <div class="user-profile">
                <div class="avatar">Z</div>
                <div>
                    <div style="font-weight: 600; color: #fff;">Admin</div>
                    <div style="font-size: 12px; color: var(--text-muted);">Administrateur</div>
                </div>
            </div>
        </header>

        <?= $message ?>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="glass-panel stat-card">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <div>
                        <h3><?= $total_clients ?></h3>
                        <p>Clients Actifs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="glass-panel stat-card">
                    <div class="icon"><i class="fas fa-satellite-dish"></i></div>
                    <div>
                        <h3><?= $total_sources ?></h3>
                        <p>Sources Connectées</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4">
                <div class="glass-panel" id="clients">
                    <h5 class="mb-4"><i class="fas fa-user-plus text-primary me-2"></i> Nouveau Client</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nom d'utilisateur</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Mot de passe</label>
                            <input type="text" name="password" class="form-control" required>
                        </div>
                        <button type="submit" name="add_client" class="btn btn-primary w-100"><i class="fas fa-plus me-2"></i>Créer l'accès</button>
                    </form>
                </div>

                <div class="glass-panel" id="sources">
                    <h5 class="mb-4"><i class="fas fa-link text-primary me-2"></i> Ajouter une Source</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nom d'identification</label>
                            <input type="text" name="nom" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type de source</label>
                            <select name="type" id="source_type" class="form-select" onchange="toggleFields()">
                                <option value="xtream">API Xtream Codes</option>
                                <option value="m3u">Lien M3U Direct</option>
                                <option value="stalker">Portail Stalker (MAC)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">URL de base / Host</label>
                            <input type="url" name="url_base" class="form-control" required placeholder="http://server.com:8080">
                        </div>
                        
                        <!-- Champs pour Xtream -->
                        <div class="row" id="auth_fields_xtream">
                            <div class="col-6 mb-3">
                                <label class="form-label">Utilisateur</label>
                                <input type="text" name="user" class="form-control">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Mot de passe</label>
                                <input type="text" name="pass" class="form-control">
                            </div>
                        </div>

                        <!-- Champ pour Stalker (Adresse MAC) -->
                        <div class="mb-3" id="auth_fields_stalker" style="display: none;">
                            <label class="form-label">Adresse MAC</label>
                            <input type="text" name="mac_address" class="form-control" placeholder="00:1A:79:XX:XX:XX">
                        </div>

                        <button type="submit" name="add_source" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Enregistrer la source</button>
                    </form>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="glass-panel">
                    <h5 class="mb-4">Liste des Clients</h5>
                    <div class="table-responsive">
                        <table class="table table-borderless table-hover">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Password</th>
                                    <th>Statut</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $c): ?>
                                <tr>
                                    <td class="fw-bold"><i class="fas fa-user-circle me-2 text-muted"></i><?= htmlspecialchars($c['username']) ?></td>
                                    <td><code><?= htmlspecialchars($c['password']) ?></code></td>
                                    <td><span class="badge-active"><i class="fas fa-circle me-1" style="font-size:8px;"></i> Actif</span></td>
                                    <td class="text-end">
                                        <!-- Bouton Lien M3U -->
                                        <button class="btn btn-sm btn-info-custom me-1" onclick="showCredentials('<?= htmlspecialchars($c['username']) ?>', '<?= htmlspecialchars($c['password']) ?>')">
                                            <i class="fas fa-link"></i> Lien
                                        </button>
                                        <!-- Bouton Modifier Client -->
                                        <button class="btn btn-sm btn-outline-info me-1" onclick="editClientModal('<?= $c['id'] ?>', '<?= addslashes(htmlspecialchars($c['username'])) ?>', '<?= addslashes(htmlspecialchars($c['password'])) ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <!-- Bouton Supprimer Client -->
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce client ?');">
                                            <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
                                            <button type="submit" name="delete_client" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="glass-panel">
                    <h5 class="mb-4">Sources (Fournisseurs)</h5>
                    <div class="table-responsive">
                        <table class="table table-borderless table-hover">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Type</th>
                                    <th>URL / Host</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fournisseurs as $f): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($f['nom']) ?></td>
                                    <td><span class="type-badge"><?= strtoupper($f['type']) ?></span></td>
                                    <td style="max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <a href="<?= htmlspecialchars($f['url_base']) ?>" target="_blank" class="text-muted"><?= htmlspecialchars($f['url_base']) ?></a>
                                        <?php if (strtolower($f['type']) === 'stalker' && !empty($f['mac_address'])): ?>
                                            <div><small class="text-info"><i class="fas fa-network-wired me-1"></i><?= htmlspecialchars($f['mac_address']) ?></small></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="importer.php?fournisseur_id=<?= (int)$f['id'] ?>" class="btn btn-sm btn-outline-success me-2" title="Importer / actualiser uniquement cette source">
                                            <i class="fas fa-sync-alt"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-info me-2" onclick="editSourceModal('<?= $f['id'] ?>', '<?= addslashes(htmlspecialchars($f['nom'])) ?>', '<?= addslashes(htmlspecialchars($f['url_base'])) ?>', '<?= addslashes(htmlspecialchars($f['user'])) ?>', '<?= addslashes(htmlspecialchars($f['pass'])) ?>', '<?= addslashes(htmlspecialchars($f['mac_address'] ?? '')) ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ ATTENTION : Cela supprimera définitivement ce fournisseur ET TOUTES ses chaînes de votre base de données. Continuer ?');">
                                            <input type="hidden" name="source_id" value="<?= $f['id'] ?>">
                                            <button type="submit" name="delete_source" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-center" style="color:#536a83;font-size:11px;padding:8px 0 4px;letter-spacing:.4px;">G-PANEL • IPTV MANAGEMENT SYSTEM</div>
    </main>

    <!-- Modal Affichage Lien M3U Xtream -->
    <div class="modal fade" id="credentialsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-link text-primary me-2"></i> Lien M3U & API Xtream</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted mb-3" style="font-size:13px;">Copiez et envoyez ce lien directement au client :</p>
            <div class="copy-box" id="modal-link-box"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Modification Client -->
    <div class="modal fade" id="editClientModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-user-edit text-primary me-2"></i> Modifier le client</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST">
              <div class="modal-body">
                <input type="hidden" name="client_id" id="edit_client_id">
                <div class="mb-3">
                    <label class="form-label">Nom d'utilisateur</label>
                    <input type="text" name="edit_client_username" id="edit_client_username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mot de passe</label>
                    <input type="text" name="edit_client_password" id="edit_client_password" class="form-control" required>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" name="edit_client_btn" class="btn btn-primary">Enregistrer</button>
              </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Modification Fournisseur -->
    <div class="modal fade" id="editSourceModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-edit text-primary me-2"></i> Modifier le fournisseur</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST">
              <div class="modal-body">
                <input type="hidden" name="source_id" id="edit_source_id">
                <div class="mb-3">
                    <label class="form-label">Nom d'identification</label>
                    <input type="text" name="edit_nom" id="edit_source_nom" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">URL de base / Host</label>
                    <input type="url" name="edit_url" id="edit_source_url" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Utilisateur</label>
                        <input type="text" name="edit_user" id="edit_source_user" class="form-control">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Mot de passe</label>
                        <input type="text" name="edit_pass" id="edit_source_pass" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Adresse MAC Stalker</label>
                    <input type="text" name="edit_mac_address" id="edit_source_mac" class="form-control" placeholder="00:1A:79:XX:XX:XX">
                    <div class="form-text text-muted">Utilisée uniquement pour les fournisseurs Stalker.</div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" name="edit_source_btn" class="btn btn-primary">Enregistrer</button>
              </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const serverBaseUrl = "<?= $server_url ?>";

        function toggleFields() {
            const type = document.getElementById('source_type').value;
            const authXtream = document.getElementById('auth_fields_xtream');
            const authStalker = document.getElementById('auth_fields_stalker');

            if (type === 'xtream') {
                authXtream.style.display = 'flex';
                authStalker.style.display = 'none';
            } else if (type === 'm3u') {
                authXtream.style.display = 'none';
                authStalker.style.display = 'none';
            } else if (type === 'stalker') {
                authXtream.style.display = 'none';
                authStalker.style.display = 'block';
            }
        }
        toggleFields();

        function showCredentials(username, password) {
            let m3uLink = `${serverBaseUrl}/get.php?username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}&type=m3u_plus`;
            document.getElementById('modal-link-box').innerText = m3uLink;
            new bootstrap.Modal(document.getElementById('credentialsModal')).show();
        }

        function editClientModal(id, username, password) {
            document.getElementById('edit_client_id').value = id;
            document.getElementById('edit_client_username').value = username;
            document.getElementById('edit_client_password').value = password;
            new bootstrap.Modal(document.getElementById('editClientModal')).show();
        }

        function editSourceModal(id, nom, url, user, pass, mac) {
            document.getElementById('edit_source_id').value = id;
            document.getElementById('edit_source_nom').value = nom;
            document.getElementById('edit_source_url').value = url;
            document.getElementById('edit_source_user').value = user;
            document.getElementById('edit_source_pass').value = pass;
            document.getElementById('edit_source_mac').value = mac || '';
            new bootstrap.Modal(document.getElementById('editSourceModal')).show();
        }
    </script>
<script src="assets/gpanel-ui.js"></script>
</body>
</html>
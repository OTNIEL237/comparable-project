<?php
require_once 'classes/Database.php';
require_once 'classes/Rapport.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (isset($_GET['id'])) {
    $rapport_id = intval($_GET['id']);
    $db = Database::getConnection();
    
    $sql = "SELECT r.*, u.nom AS stag_nom, u.prenom AS stag_prenom
            FROM rapports r
            JOIN utilisateurs u ON r.stagiaire_id = u.id
            WHERE r.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $rapport_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $rapport = $result->fetch_assoc();
    } else {
        die("Rapport non trouvé");
    }
} else {
    die("ID de rapport non spécifié");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport: <?= htmlspecialchars($rapport['titre']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f5ff;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }
        
        .rapport-container {
            max-width: 1000px;
            margin: 30px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 102, 204, 0.15);
            overflow: hidden;
        }
        
        .rapport-header {
            background: linear-gradient(135deg, #0066cc, #0099ff);
            color: white;
            padding: 35px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .rapport-header:before {
            content: "";
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .rapport-header:after {
            content: "";
            position: absolute;
            bottom: -80px;
            left: -30px;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
        }
        
        .rapport-title {
            font-size: 32px;
            margin-bottom: 15px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .rapport-subtitle {
            font-size: 18px;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        .rapport-meta {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 25px;
            position: relative;
            z-index: 1;
        }
        
        .meta-item {
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.25);
        }
        
        .rapport-body {
            padding: 40px;
        }
        
        .rapport-section {
            margin-bottom: 35px;
        }
        
        .section-title {
            color: #0066cc;
            font-size: 22px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e1ecff;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }
        
        .section-content {
            padding: 20px;
            background: #f8fbff;
            border-radius: 10px;
            white-space: pre-line;
            line-height: 1.8;
            font-size: 16px;
            border-left: 4px solid #0066cc;
            box-shadow: inset 0 0 10px rgba(0, 102, 204, 0.05);
        }
        
        .status-badge {
            padding: 7px 18px;
            border-radius: 30px;
            font-weight: 600;
            margin-left: 10px;
            display: inline-block;
        }
        
        .status-en_attente { background: #fff8e6; color: #cc7a00; }
        .status-validé { background: #e6ffe6; color: #00802b; }
        .status-rejeté { background: #ffe6e6; color: #cc0000; }
        
        .actions {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-download {
            background: #0066cc;
            color: white;
            border: 2px solid #0055aa;
        }
        
        .btn-download:hover {
            background: #0055aa;
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(0, 102, 204, 0.3);
        }
        
        .btn-back {
            background: white;
            color: #0066cc;
            border: 2px solid #d1e3f8;
        }
        
        .btn-back:hover {
            background: #f1f8ff;
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(0, 0, 0, 0.1);
        }
        
        .comment-section {
            background: #fff8f0;
            border-left: 4px solid #ff9900;
            padding: 20px;
            border-radius: 0 10px 10px 0;
            margin-top: 15px;
            font-size: 16px;
            line-height: 1.7;
        }
        
        @media (max-width: 768px) {
            .rapport-body {
                padding: 25px;
            }
            
            .rapport-title {
                font-size: 26px;
            }
            
            .rapport-header {
                padding: 25px 20px;
            }
            
            .actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .meta-item {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="rapport-container">
        <div class="rapport-header">
            <h1 class="rapport-title"><?= htmlspecialchars($rapport['titre']) ?></h1>
            <div class="rapport-subtitle">Rapport <?= ucfirst($rapport['type']) ?> de stage</div>
            
            <div class="rapport-meta">
                <div class="meta-item">
                    <i class="fas fa-user-graduate"></i>
                    <?= htmlspecialchars($rapport['stag_prenom'] . ' ' . $rapport['stag_nom']) ?>
                </div>
                <div class="meta-item">
                    <i class="fas fa-calendar"></i>
                    Soumis le: <?= date('d/m/Y', strtotime($rapport['date_soumission'])) ?>
                </div>
                <div class="meta-item">
                    <i class="fas fa-tag"></i>
                    Statut: 
                    <span class="status-badge status-<?= str_replace(' ', '_', $rapport['statut']) ?>">
                        <?= ucfirst($rapport['statut']) ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="rapport-body">
            <div class="rapport-section">
                <h2 class="section-title">
                    <i class="fas fa-tasks"></i>
                    Activités réalisées
                </h2>
                <div class="section-content"><?= htmlspecialchars($rapport['activites']) ?></div>
            </div>
            
            <div class="rapport-section">
                <h2 class="section-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Difficultés rencontrées
                </h2>
                <div class="section-content"><?= htmlspecialchars($rapport['difficultes']) ?></div>
            </div>
            
            <div class="rapport-section">
                <h2 class="section-title">
                    <i class="fas fa-lightbulb"></i>
                    Solutions apportées
                </h2>
                <div class="section-content"><?= htmlspecialchars($rapport['solutions']) ?></div>
            </div>
            
            <?php if (!empty($rapport['commentaire_encadreur'])): ?>
            <div class="rapport-section">
                <h2 class="section-title">
                    <i class="fas fa-comments"></i>
                    Commentaire de l'encadreur
                </h2>
                <div class="comment-section">
                    <?= htmlspecialchars($rapport['commentaire_encadreur']) ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="actions">
                <a href="telecharger_rapport.php?id=<?= $rapport['id'] ?>" class="btn btn-download">
                    <i class="fas fa-download"></i> Télécharger PDF
                </a>
                <a href="dashboardEncadreur.php?tab=rapports" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Retour aux rapports
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>
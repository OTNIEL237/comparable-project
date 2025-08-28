<?php
/**
 * Script pour télécharger les rapports PDF de manière sécurisée.
 * Il nettoie le tampon de sortie pour éviter la corruption des fichiers.
 */

// 1. Démarrer le tampon de sortie et la session au tout début du script.
// ob_start() capture toute sortie précoce (erreurs, espaces) pour qu'elle ne corrompe pas le fichier.
ob_start();
session_start();

require_once 'classes/Database.php';
require_once 'classes/Rapport.php';

// 2. Autoriser les stagiaires à télécharger leurs propres rapports.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['stagiaire', 'encadreur', 'admin'])) {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

if (isset($_GET['id'])) {
    $rapport_id = intval($_GET['id']);
    $conn = Database::getConnection();

    $sql = "SELECT r.fichier_pdf, r.titre, u.nom, u.prenom 
            FROM rapports r
            JOIN utilisateurs u ON r.stagiaire_id = u.id
            WHERE r.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $rapport_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $rapport = $result->fetch_assoc();

        // 3. Vérifier que le nom du fichier existe dans la base de données.
        if ($rapport && !empty($rapport['fichier_pdf'])) {
            $file_path = __DIR__ . '/uploads/rapports/' . $rapport['fichier_pdf'];

            // 4. Vérifier que le fichier existe physiquement et est lisible.
            if (file_exists($file_path) && is_readable($file_path)) {
                // 5. Nettoyer le nom du fichier pour le téléchargement.
                $safe_titre = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $rapport['titre']);
                $file_name = "Rapport_" . $rapport['prenom'] . "_" . $rapport['nom'] . "_" . $safe_titre . ".pdf";

                // 6. Vider le tampon de sortie pour supprimer toute sortie non désirée. C'est l'étape la plus importante.
                ob_end_clean();

                // 7. Envoyer des en-têtes HTTP complets pour un téléchargement fiable.
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $file_name . '"');
                header('Content-Transfer-Encoding: binary');
                header('Content-Length: ' . filesize($file_path));
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');

                // 8. Envoyer le contenu du fichier et arrêter le script.
                readfile($file_path);
                exit;
            }
        }
    }
}

// Si on arrive ici, c'est qu'il y a eu un problème.
ob_end_clean(); // Nettoie aussi en cas d'erreur pour n'afficher que le message d'erreur.
header('HTTP/1.1 404 Not Found');
echo "Fichier non trouvé ou rapport invalide.";
exit();
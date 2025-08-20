<?php
require_once 'Database.php';

class Evaluation
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    /**
     * Récupère ou génère une évaluation complète pour un stagiaire.
     */
    public function getEvaluationForStagiaire($stagiaire_id)
    {
        // On calcule toujours les dernières statistiques en direct
        $stats = $this->calculerToutesStatistiques($stagiaire_id);
        
        $sql = "SELECT * FROM evaluations WHERE stagiaire_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $stagiaire_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $evaluation_db = $result->fetch_assoc();
            return array_merge($evaluation_db, $stats);
        } else {
            return array_merge([
                'id' => null, 'stagiaire_id' => $stagiaire_id,
                'date_evaluation' => null // La virgule a été supprimée
            ], $stats);
        }
    }

    /**
     * Enregistre les commentaires et les notes calculées.
     */
    public function sauvegarder($data)
    {
        $stats = $this->calculerToutesStatistiques($data['stagiaire_id']);

        if (isset($data['evaluation_id']) && !empty($data['evaluation_id'])) {
            // UPDATE
            $sql = "UPDATE evaluations SET 
                        note_presence = ?, note_taches = ?, note_rapports = ?, note_globale = ?
                    WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ddddi", 
                $stats['note_presence'], $stats['note_taches'], 
                $stats['note_rapports'], $stats['note_globale'], $data['evaluation_id']
            );
        } else {
            // INSERT
            $sql = "INSERT INTO evaluations (stagiaire_id, encadreur_id, note_presence, note_taches, note_rapports, note_globale)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iisddd", 
                $data['stagiaire_id'], $data['encadreur_id'],
                $stats['note_presence'], $stats['note_taches'], $stats['note_rapports'], $stats['note_globale']
            );
        }
        return $stmt->execute();
    }
    /**
     * Fonction centrale qui calcule toutes les statistiques et les scores.
     * CORRECTION : Renvoie un tableau avec les bonnes clés attendues par la vue.
     */
    private function calculerToutesStatistiques($stagiaire_id)
    {
        // 1. Calcul de la note de présence
        $stats_presence = $this->calculerStatsPresence($stagiaire_id);
        $total_jours_ouvrables = $stats_presence['present'] + $stats_presence['retard'] + $stats_presence['absent'];
        $note_presence = 100.0;
        if ($total_jours_ouvrables > 0) {
            $jours_effectifs = $stats_presence['present'] + ($stats_presence['retard'] * 0.5); // Pénalité pour les retards
            $note_presence = round(($jours_effectifs / $total_jours_ouvrables) * 100, 2);
        }

        // 2. Calcul de la note des tâches
        $stats_taches = $this->calculerStatsTaches($stagiaire_id);
        $note_taches = 100.0;
        if ($stats_taches['total'] > 0) {
            $note_taches = round(($stats_taches['terminee_a_temps'] / $stats_taches['total']) * 100, 2);
        }

        // 3. Calcul de la note des rapports
        $stats_rapports = $this->calculerStatsRapports($stagiaire_id);
        $note_rapports = 100.0;
        if ($stats_rapports['total'] > 0) {
            $note_rapports = round(($stats_rapports['valide'] / $stats_rapports['total']) * 100, 2);
        }

        // 4. Calcul de la note globale
        $note_globale = round(($note_presence + $note_taches + $note_rapports) / 3, 2);

        // 5. Retourner le tableau avec les clés correctes
        return [
            'note_presence' => $note_presence,
            'note_taches' => $note_taches,
            'note_rapports' => $note_rapports,
            'note_globale' => $note_globale
        ];
    }
    
    // --- Fonctions de calcul détaillées ---
    
    private function calculerStatsPresence($stagiaire_id) {
        $sql_stagiaire = "SELECT date_debut, date_fin FROM stagiaire WHERE id_utilisateur = ?";
        $stmt_stagiaire = $this->conn->prepare($sql_stagiaire);
        $stmt_stagiaire->bind_param("i", $stagiaire_id);
        $stmt_stagiaire->execute();
        $stagiaire_info = $stmt_stagiaire->get_result()->fetch_assoc();
        $stmt_stagiaire->close();

        if (!$stagiaire_info) return ['present' => 0, 'retard' => 0, 'absent' => 0];

        $date_debut_stage = new DateTime($stagiaire_info['date_debut']);
        $date_fin_stage = new DateTime($stagiaire_info['date_fin']);
        $aujourdhui = new DateTime('today');
        $date_fin_calcul = ($aujourdhui < $date_fin_stage) ? $aujourdhui : $date_fin_stage;

        $sql_presence = "SELECT statut_journee, COUNT(*) as count FROM presence WHERE stagiaire_id = ? AND date BETWEEN ? AND ? GROUP BY statut_journee";
        $stmt_presence = $this->conn->prepare($sql_presence);
        $date_debut_str = $date_debut_stage->format('Y-m-d');
        $date_fin_str = $date_fin_calcul->format('Y-m-d');
        $stmt_presence->bind_param("iss", $stagiaire_id, $date_debut_str, $date_fin_str);
        $stmt_presence->execute();
        $result = $stmt_presence->get_result();

        $stats = ['present' => 0, 'retard' => 0, 'absent' => 0];
        $jours_pointes = 0;
        while ($row = $result->fetch_assoc()) {
            if (isset($stats[$row['statut_journee']])) {
                $stats[$row['statut_journee']] = (int)$row['count'];
                $jours_pointes += (int)$row['count'];
            }
        }

        $total_jours_ouvrables = 0;
        $jour_courant = clone $date_debut_stage;
        while ($jour_courant <= $date_fin_calcul) {
            if ((int)$jour_courant->format('N') <= 5) {
                $total_jours_ouvrables++;
            }
            $jour_courant->modify('+1 day');
        }

        $stats['absent'] = max(0, $total_jours_ouvrables - $jours_pointes);
        return $stats;
    }

    private function calculerStatsTaches($stagiaire_id) {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN statut = 'terminee' AND date(date_completion) <= date_echeance THEN 1 ELSE 0 END) as terminee_a_temps
                FROM taches WHERE stagiaire_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $stagiaire_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return ['total' => (int)$result['total'], 'terminee_a_temps' => (int)$result['terminee_a_temps']];
    }

    private function calculerStatsRapports($stagiaire_id) {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN statut = 'validé' THEN 1 ELSE 0 END) as valide
                FROM rapports WHERE stagiaire_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $stagiaire_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return ['total' => (int)$result['total'], 'valide' => (int)$result['valide']];
    }
}
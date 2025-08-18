<?php
require_once 'Database.php';

class Presence
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    /**
     * Gère le pointage (arrivée, pause, départ) pour un stagiaire.
     */
    public function marquerPresence($stagiaire_id, $action, $localisation = null)
    {
        $date_jour = date('Y-m-d');
        $heure_actuelle = date('H:i:s');

        $sql_check = "SELECT * FROM presence WHERE stagiaire_id = ? AND date = ?";
        $stmt_check = $this->conn->prepare($sql_check);
        $stmt_check->bind_param("is", $stagiaire_id, $date_jour);
        $stmt_check->execute();
        $presence_aujourdhui = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        $sql = "";
        $params = [];
        $types = "";

        if ($action === 'arrivee') {
            if ($presence_aujourdhui) return false; // Déjà pointé

            $statut_journee = (strtotime($heure_actuelle) > strtotime('08:15:00')) ? 'retard' : 'present';
            $sql = "INSERT INTO presence (stagiaire_id, date, heure_arrivee, localisation_arrivee, statut_journee) VALUES (?, ?, ?, ?, ?)";
            $params = [$stagiaire_id, $date_jour, $heure_actuelle, $localisation, $statut_journee];
            $types = "issss";
        } elseif ($presence_aujourdhui) {
            $presence_id = $presence_aujourdhui['id'];
            switch ($action) {
                case 'fin_pause':
                    if ($presence_aujourdhui['heure_fin_pause']) return false;
                    $sql = "UPDATE presence SET heure_fin_pause = ? WHERE id = ?";
                    $params = [$heure_actuelle, $presence_id];
                    $types = "si";
                    break;
                case 'depart':
                    if ($presence_aujourdhui['heure_depart']) return false;
                    $sql = "UPDATE presence SET heure_depart = ? WHERE id = ?";
                    $params = [$heure_actuelle, $presence_id];
                    $types = "si";
                    break;
            }
        }

        if (!empty($sql)) {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) return false;
            $stmt->bind_param($types, ...$params);
            return $stmt->execute();
        }
        return false;
    }

    /**
     * Récupère le statut de pointage pour la journée en cours.
     */
    public function getStatutPointageAujourdhui($stagiaire_id)
    {
        $date_jour = date('Y-m-d');
        $sql = "SELECT heure_arrivee, heure_fin_pause, heure_depart FROM presence WHERE stagiaire_id = ? AND date = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $stagiaire_id, $date_jour);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: ['heure_arrivee' => null, 'heure_fin_pause' => null, 'heure_depart' => null];
    }

    /**
     * Récupère les données de présence pour un mois donné, en tenant compte de la période de stage.
     */
    public function getPresencePourMois($stagiaire_id, $annee, $mois)
    {
        // 1. Récupérer les dates de stage du stagiaire
        $sql_stagiaire = "SELECT date_debut, date_fin FROM stagiaire WHERE id_utilisateur = ?";
        $stmt_stagiaire = $this->conn->prepare($sql_stagiaire);
        $stmt_stagiaire->bind_param("i", $stagiaire_id);
        $stmt_stagiaire->execute();
        $stagiaire_info = $stmt_stagiaire->get_result()->fetch_assoc();
        $stmt_stagiaire->close();

        if (!$stagiaire_info || !$stagiaire_info['date_debut'] || !$stagiaire_info['date_fin']) {
            return []; // Ne rien faire si pas de période de stage
        }

        $date_debut_stage = new DateTime($stagiaire_info['date_debut']);
        $date_fin_stage = new DateTime($stagiaire_info['date_fin']);
        $date_debut_mois = new DateTime("$annee-$mois-01");
        $date_fin_mois = (clone $date_debut_mois)->modify('last day of this month');
        $events = [];

        // 2. Récupérer les présences du mois
        $sql_presence = "SELECT * FROM presence WHERE stagiaire_id = ? AND date BETWEEN ? AND ?";
        $stmt_presence = $this->conn->prepare($sql_presence);
        $date_debut_str = $date_debut_mois->format('Y-m-d');
        $date_fin_str = $date_fin_mois->format('Y-m-d');
        $stmt_presence->bind_param("iss", $stagiaire_id, $date_debut_str, $date_fin_str);
        $stmt_presence->execute();
        $result_presence = $stmt_presence->get_result();
        
        $presences = [];
        while ($row = $result_presence->fetch_assoc()) {
            $presences[$row['date']] = $row;
        }
        $stmt_presence->close();

        // 3. Parcourir chaque jour du mois
        $jour_courant = clone $date_debut_mois;
        $aujourdhui = new DateTime('today');

        while ($jour_courant <= $date_fin_mois) {
            $date_str = $jour_courant->format('Y-m-d');
            $jour_semaine = (int)$jour_courant->format('N');

            if ($jour_courant >= $date_debut_stage && $jour_courant <= $date_fin_stage) {
                if (isset($presences[$date_str])) {
                    $row = $presences[$date_str];
                    $title = ($row['statut_journee'] === 'retard') ? 'Retard' : 'Présent';
                    $color = ($row['statut_journee'] === 'retard') ? '#ffc107' : '#28a745';
                    $events[] = [
                        'title' => $title,
                        'start' => $date_str,
                        'color' => $color,
                        'extendedProps' => [
                            'arrivee' => $row['heure_arrivee'],
                            'fin_pause' => $row['heure_fin_pause'],
                            'depart' => $row['heure_depart'],
                            'localisation' => $row['localisation_arrivee']
                        ]
                    ];
                } elseif ($jour_semaine <= 5 && $jour_courant < $aujourdhui) {
                    $events[] = ['title' => 'Absent', 'start' => $date_str, 'color' => '#dc3545'];
                }
            }
            $jour_courant->modify('+1 day');
        }
        return $events;
    }

        /**
     * NOUVELLE FONCTION : Récupère les données de présence pour une semaine donnée.
     * @param int $stagiaire_id L'ID de l'utilisateur stagiaire.
     * @param string $date_ref Une date (ex: '2025-08-18') pour déterminer la semaine à afficher.
     * @return array Un tableau des jours de la semaine avec les données de présence.
     */
    public function getPresencePourSemaine($stagiaire_id, $date_ref = 'today') {
        // Déterminer les dates de début (lundi) et de fin (vendredi) de la semaine
        $ref = new DateTime($date_ref);
        $jour_semaine = (int)$ref->format('N'); // 1 pour Lundi, 7 pour Dimanche
        
        $debut_semaine = (clone $ref)->modify('-' . ($jour_semaine - 1) . ' days');
        $fin_semaine = (clone $debut_semaine)->modify('+4 days'); // Lundi + 4 jours = Vendredi

        // Récupérer les dates de début et de fin du stage
        $sql_stagiaire = "SELECT date_debut, date_fin FROM stagiaire WHERE id_utilisateur = ?";
        $stmt_stagiaire = $this->conn->prepare($sql_stagiaire);
        $stmt_stagiaire->bind_param("i", $stagiaire_id);
        $stmt_stagiaire->execute();
        $stagiaire_info = $stmt_stagiaire->get_result()->fetch_assoc();
        $stmt_stagiaire->close();

        if (!$stagiaire_info) return [];
        $date_debut_stage = new DateTime($stagiaire_info['date_debut']);
        $date_fin_stage = new DateTime($stagiaire_info['date_fin']);

        // Récupérer toutes les présences enregistrées pour cette semaine
        $sql_presence = "SELECT * FROM presence WHERE stagiaire_id = ? AND date BETWEEN ? AND ?";
        $stmt_presence = $this->conn->prepare($sql_presence);
        $date_debut_str = $debut_semaine->format('Y-m-d');
        $date_fin_str = $fin_semaine->format('Y-m-d');
        $stmt_presence->bind_param("iss", $stagiaire_id, $date_debut_str, $date_fin_str);
        $stmt_presence->execute();
        $result_presence = $stmt_presence->get_result();
        
        $presences = [];
        while($row = $result_presence->fetch_assoc()) {
            $presences[$row['date']] = $row;
        }

        // Construire le tableau final pour les 5 jours ouvrables
        $semaine_data = [];
        $jour_courant = clone $debut_semaine;
        $aujourdhui = new DateTime('today');

        for ($i = 0; $i < 5; $i++) {
            $date_str = $jour_courant->format('Y-m-d');
            $jour_data = [
                'date' => $date_str,
                'nom_jour' => $this->traduireJour($jour_courant->format('l')),
                'statut' => 'futur', // Statut par défaut
                'details' => null
            ];

            // Le jour est-il dans la période de stage ?
            if ($jour_courant >= $date_debut_stage && $jour_courant <= $date_fin_stage) {
                if (isset($presences[$date_str])) {
                    $p = $presences[$date_str];
                    $jour_data['statut'] = $p['statut_journee']; // 'present' ou 'retard'
                    $jour_data['details'] = [
                        'arrivee' => $p['heure_arrivee'],
                        'fin_pause' => $p['heure_fin_pause'],
                        'depart' => $p['heure_depart']
                    ];
                } elseif ($jour_courant < $aujourdhui) {
                    $jour_data['statut'] = 'absent';
                }
            } else {
                $jour_data['statut'] = 'hors_stage';
            }
            
            $semaine_data[] = $jour_data;
            $jour_courant->modify('+1 day');
        }
        
        return $semaine_data;
    }

    private function traduireJour($jour_anglais) {
        $jours = [
            'Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi', 'Sunday' => 'Dimanche'
        ];
        return $jours[$jour_anglais] ?? $jour_anglais;
    }
}
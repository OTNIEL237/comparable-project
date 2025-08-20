<?php
// Protection pour s'assurer que les variables existent et ont une valeur par défaut
$stagiaire_nom = isset($stagiaire_info) ? htmlspecialchars($stagiaire_info['prenom'] . ' ' . $stagiaire_info['nom']) : 'Stagiaire';
$note_presence = $evaluation_data['note_presence'] ?? 0;
$note_taches = $evaluation_data['note_taches'] ?? 0;
$note_rapports = $evaluation_data['note_rapports'] ?? 0;
$note_globale = $evaluation_data['note_globale'] ?? 0;
?>
<div class="evaluation-content">
    <div class="evaluation-header">
        <h1>Rapport de Performance Global</h1>
        <h2>Stagiaire : <?php echo $stagiaire_nom; ?></h2>
    </div>

    <!-- Section des cercles de performance -->
    <div class="evaluation-section performance-overview">
        <div class="global-score-wrap">
            <div class="stat-circle-wrap large">
                <svg class="stat-circle" viewBox="0 0 36 36">
                    <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path class="circle circle-global" stroke-dasharray="<?php echo $note_globale; ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                </svg>
                <div class="stat-circle-info">
                    <span class="stat-value"><?php echo $note_globale; ?><small>%</small></span>
                    <span class="stat-label">Score Global</span>
                </div>
            </div>
        </div>
        <div class="detailed-scores-grid">
            <!-- Cercle Présence -->
            <div class="stat-circle-wrap">
                <svg class="stat-circle" viewBox="0 0 36 36">
                    <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path class="circle circle-success" stroke-dasharray="<?php echo $note_presence; ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                </svg>
                 <div class="stat-circle-info small">
                    <span class="stat-value"><?php echo $note_presence; ?><small>%</small></span>
                </div>
                <span class="stat-label">Présence</span>
            </div>
            <!-- Cercle Tâches -->
            <div class="stat-circle-wrap">
                <svg class="stat-circle" viewBox="0 0 36 36">
                    <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path class="circle circle-warning" stroke-dasharray="<?php echo $note_taches; ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                </svg>
                 <div class="stat-circle-info small">
                    <span class="stat-value"><?php echo $note_taches; ?><small>%</small></span>
                </div>
                <span class="stat-label">Tâches</span>
            </div>
            <!-- Cercle Rapports -->
            <div class="stat-circle-wrap">
                <svg class="stat-circle" viewBox="0 0 36 36">
                    <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path class="circle circle-danger" stroke-dasharray="<?php echo $note_rapports; ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                </svg>
                 <div class="stat-circle-info small">
                    <span class="stat-value"><?php echo $note_rapports; ?><small>%</small></span>
                </div>
                <span class="stat-label">Rapports</span>
            </div>
        </div>
    </div>

    
// ===================================================================
// ==       FICHIER JAVASCRIPT COMPLET POUR LE DASHBOARD ENCADREUR      ==
// ===================================================================

// Variable globale pour suivre la modale actuellement ouverte
let currentModal = null;

// S'exécute lorsque le DOM est entièrement chargé
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    setupEventListeners();
});

/**
 * Initialise les éléments de base du dashboard comme la barre latérale.
 */
function initializeDashboard() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('active'));
    }
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768 && sidebar && !sidebar.contains(e.target) && !e.target.closest('.sidebar-toggle')) {
            sidebar.classList.remove('active');
        }
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768 && sidebar) {
            sidebar.classList.remove('active');
        }
    });
}

/**
 * Met en place tous les écouteurs d'événements pour les formulaires et les actions globales.
 */
function setupEventListeners() {
    document.getElementById('formNouveauMessage')?.addEventListener('submit', (e) => { e.preventDefault(); envoyerMessage(); });
    document.getElementById('formValidationRapport')?.addEventListener('submit', (e) => { e.preventDefault(); confirmerValidationRapport(); });
    document.getElementById('formNouvelleTache')?.addEventListener('submit', (e) => { e.preventDefault(); enregistrerTache('creer_tache', 'formNouvelleTache', 'modalNouvelleTache'); });
    document.getElementById('formModifierTache')?.addEventListener('submit', (e) => { e.preventDefault(); enregistrerTache('modifier_tache', 'formModifierTache', 'modalModifierTache'); });
    document.getElementById('formTheme')?.addEventListener('submit', (e) => { e.preventDefault(); enregistrerTheme(e.target); });
    document.getElementById('formAttribuerTheme')?.addEventListener('submit', (e) => { e.preventDefault(); confirmerAttributionTheme(e.target); });
    document.getElementById('formAttribuerThemePourStagiaire')?.addEventListener('submit', (e) => { e.preventDefault(); confirmerAttributionThemePourStagiaire(e.target); });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && currentModal) {
            fermerModal(currentModal);
        }
    });
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) {
            fermerModal(e.target.id);
        }
    });
}


// ===================================================================
// ==                  FONCTIONS POUR LES MODALES                   ==
// ===================================================================

function ouvrirModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('active'), 10);
        currentModal = modalId;
        const firstInput = modal.querySelector('input, select, textarea');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 110);
        }
    }
}

function fermerModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
            const form = modal.querySelector('form');
            if (form) {
                form.reset();
            }
        }, 300);
        currentModal = null;
    }
}


// ===================================================================
// ==                   GESTION DE LA MESSAGERIE                    ==
// ===================================================================

function ouvrirNouveauMessage() {
    ouvrirModal('modalNouveauMessage');
}

async function envoyerMessage() {
    const form = document.getElementById('formNouveauMessage');
    await soumettreFormulaire(form, 'envoyer_message', 'Message envoyé avec succès !', 'modalNouveauMessage');
}

async function ouvrirMessage(messageId) {
    const formDataLu = new FormData();
    formDataLu.append('action', 'marquer_lu');
    formDataLu.append('message_id', messageId);
    await fetch(window.location.href, { // Utilise la page actuelle (dashboardEncadreur.php)
        method: 'POST',
        body: formDataLu
    });

    const messageElement = document.querySelector(`.message-item[onclick="ouvrirMessage(${messageId})"]`);
    if (messageElement) {
        messageElement.classList.remove('unread');
    }

    try {
        // CORRECTION : On appelle votre fichier get_message.php qui fonctionne
        const response = await fetch(`get_message.php?id=${messageId}`);
        if (!response.ok) {
            throw new Error('Réponse du serveur non valide.');
        }

        const message = await response.json();
        if (message.error) {
            throw new Error(message.error);
        }

        document.getElementById('message-subject').textContent = message.sujet;
        document.getElementById('message-from').innerHTML = `<strong>De:</strong> ${message.exp_prenom} ${message.exp_nom}`;
        document.getElementById('message-date').textContent = `Date: ${new Date(message.date_envoi).toLocaleString('fr-FR')}`;
        document.getElementById('message-content').innerHTML = message.contenu.replace(/\n/g, '<br>');

        const piecesContainer = document.getElementById('message-pieces-jointes');
        piecesContainer.innerHTML = '';
        if (message.pieces_jointes && message.pieces_jointes.length > 0) {
            let list = '<strong>Pièces jointes:</strong><ul>';
            message.pieces_jointes.forEach(pj => list += `<li><a href="uploads/messages/${pj.chemin}" target="_blank">${pj.nom_fichier}</a></li>`);
            piecesContainer.innerHTML = list + '</ul>';
        }
        ouvrirModal('modalAfficherMessage');
    } catch (error) {
        console.error("Erreur de chargement du message:", error);
        afficherNotification("Impossible de charger le message.", "error");
    }
}


// ===================================================================
// ==          GESTION DES THÈMES ET STAGIAIRES                     ==
// ===================================================================

async function ouvrirModalTheme(themeId = null) {
    const form = document.getElementById('formTheme');
    form.reset();
    document.getElementById('theme_id').value = '';
    const modalTitle = document.getElementById('modalThemeTitle');

    if (themeId) {
        modalTitle.textContent = 'Modifier le Thème';
        const formData = new FormData();
        formData.append('action', 'get_theme_details');
        formData.append('theme_id', themeId);
        
        try {
            const response = await fetch(window.location.href, { method: 'POST', body: formData });
            const data = await response.json();
            if (data) {
                document.getElementById('theme_id').value = data.id;
                document.getElementById('theme_titre').value = data.titre;
                document.getElementById('theme_filiere').value = data.filiere;
                document.getElementById('theme_description').value = data.description;
                document.getElementById('theme_date_debut').value = data.date_debut;
                document.getElementById('theme_date_fin').value = data.date_fin;
            }
        } catch(error) {
            afficherNotification("Erreur lors du chargement du thème.", "error");
        }
    } else {
        modalTitle.textContent = 'Nouveau Thème';
    }
    ouvrirModal('modalTheme');
}

async function enregistrerTheme(form) {
    const themeId = form.querySelector('#theme_id').value;
    const action = themeId ? 'modifier_theme' : 'creer_theme';
    await soumettreFormulaire(form, action, 'Thème enregistré avec succès !', 'modalTheme');
}

async function supprimerTheme(themeId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce thème ?')) return;
    const formData = new FormData();
    formData.append('theme_id', themeId);
    await soumettreFormulaire(formData, 'supprimer_theme', 'Thème supprimé avec succès !');
}

function ouvrirModalAttribuer(themeId) {
    document.getElementById('attribuer_theme_id').value = themeId;
    ouvrirModal('modalAttribuerTheme');
}

async function confirmerAttributionTheme(form) {
    await soumettreFormulaire(form, 'attribuer_theme', 'Thème attribué avec succès !', 'modalAttribuerTheme');
}

async function consulterStagiaire(stagiaireId) {
    const formData = new FormData();
    formData.append('action', 'get_stagiaire_details');
    formData.append('stagiaire_id', stagiaireId);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) {
            const details = data.stagiaireDetails;
            document.getElementById('consulterModalTitle').textContent = `Détails de ${details.prenom} ${details.nom}`;
            document.getElementById('consulterStagiaireEmail').textContent = details.email;
            document.getElementById('consulterStagiaireTel').textContent = details.telephone || 'Non renseigné';
            document.getElementById('consulterStagiaireSexe').textContent = details.sex === 'M' ? 'Masculin' : 'Féminin';
            document.getElementById('consulterStagiaireFiliere').textContent = details.filiere;
            document.getElementById('consulterStagiaireNiveau').textContent = details.niveau;
            const dateDebut = new Date(details.date_debut).toLocaleDateString('fr-FR');
            const dateFin = new Date(details.date_fin).toLocaleDateString('fr-FR');
            document.getElementById('consulterStagiairePeriode').textContent = `Du ${dateDebut} au ${dateFin}`;
            ouvrirModal('modalConsulterStagiaire');
        } else {
            afficherNotification("Impossible de charger les informations.", "error");
        }
    } catch (error) {
        afficherNotification("Erreur technique.", "error");
    }
}

async function ouvrirModalAttribuerAStagiaire(stagiaireId, stagiaireNom) {
    document.getElementById('attribuerModalTitle').textContent = `Attribuer un thème à ${stagiaireNom}`;
    document.getElementById('attribuer_form_stagiaire_id').value = stagiaireId;
    const formData = new FormData();
    formData.append('action', 'get_stagiaire_details');
    formData.append('stagiaire_id', stagiaireId);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const data = await response.json();
        const themeSelect = document.getElementById('attribuer_theme_id_select');
        themeSelect.innerHTML = ''; 
        if (data.availableThemes && data.availableThemes.length > 0) {
            data.availableThemes.forEach(theme => {
                const option = document.createElement('option');
                option.value = theme.id;
                option.textContent = theme.titre;
                themeSelect.appendChild(option);
            });
        } else {
            const option = document.createElement('option');
            option.disabled = true;
            option.selected = true;
            option.textContent = '-- Aucun thème disponible --';
            themeSelect.appendChild(option);
        }
        ouvrirModal('modalAttribuerThemeAStagiaire');
    } catch (error) {
        afficherNotification("Erreur au chargement des thèmes.", "error");
    }
}

async function confirmerAttributionThemePourStagiaire(form) {
    const formData = new FormData(form);
    if (!formData.get('theme_id')) {
        afficherNotification("Veuillez sélectionner un thème.", "warning");
        return;
    }
    await soumettreFormulaire(form, 'attribuer_theme', 'Thème attribué avec succès !', 'modalAttribuerThemeAStagiaire');
}


// ===================================================================
// ==                    GESTION DES RAPPORTS                       ==
// ===================================================================

function validerRapport(rapportId, statut) {
    document.getElementById('validationRapportId').value = rapportId;
    document.getElementById('validationStatut').value = statut;
    const modal = document.getElementById('modalValidationRapport');
    modal.querySelector('.modal-header h3').textContent = statut === 'validé' ? 'Valider le rapport' : 'Rejeter le rapport';
    const confirmBtn = modal.querySelector('button[type="submit"]');
    confirmBtn.className = `btn btn-${statut === 'validé' ? 'success' : 'danger'}`;
    confirmBtn.innerHTML = statut === 'validé' ? '<i class="fas fa-check"></i> Valider' : '<i class="fas fa-times"></i> Rejeter';
    ouvrirModal('modalValidationRapport');
}

async function confirmerValidationRapport() {
    const form = document.getElementById('formValidationRapport');
    await soumettreFormulaire(form, 'valider_rapport', 'Statut du rapport mis à jour !', 'modalValidationRapport');
}


// ===================================================================
// ==                     GESTION DES TÂCHES                        ==
// ===================================================================

async function enregistrerTache(action, formId, modalId) {
    const form = document.getElementById(formId);
    await soumettreFormulaire(form, action, 'Tâche enregistrée avec succès !', modalId);
}

async function modifierTache(tacheId) {
    const formData = new FormData();
    formData.append('action', 'get_tache');
    formData.append('tache_id', tacheId);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const tache = await response.json();
        document.getElementById('editTacheId').value = tache.id;
        document.getElementById('editStagiaireId').value = tache.stagiaire_id;
        document.getElementById('editTitre').value = tache.titre;
        document.getElementById('editDescription').value = tache.description;
        document.getElementById('editDateEcheance').value = tache.date_echeance;
        document.getElementById('fichierActuel').textContent = tache.nom_fichier_original || 'Aucun';
        ouvrirModal('modalModifierTache');
    } catch (error) {
        afficherNotification('Impossible de charger les détails de la tâche.', 'error');
    }
}

async function supprimerTache(tacheId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette tâche ?')) return;
    const formData = new FormData();
    formData.append('tache_id', tacheId);
    await soumettreFormulaire(formData, 'supprimer_tache', 'Tâche supprimée avec succès !');
}


// ===================================================================
// ==         FONCTION UTILITAIRE CENTRALE POUR LES FORMULAIRES     ==
// ===================================================================

async function soumettreFormulaire(formOrData, action, successMessage, modalToClose = null) {
    const formData = formOrData instanceof FormData ? formOrData : new FormData(formOrData);
    if (!formData.has('action')) {
        formData.append('action', action);
    }

    let submitBtn = null;
    if (formOrData instanceof HTMLElement) {
        submitBtn = formOrData.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
        }
    }

    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            afficherNotification(successMessage, 'success');
            if (modalToClose) {
                fermerModal(modalToClose);
            }
            setTimeout(() => window.location.reload(), 1200);
        } else {
            afficherNotification(result.message || "Une erreur est survenue.", 'error');
        }
    } catch (error) {
        console.error("Erreur de soumission:", error);
        afficherNotification('Erreur de connexion ou réponse invalide.', 'error');
    }
}

// ===================================================================
// ==          FILTRES ET RECHERCHES (FONCTIONS MANQUANTES)         ==
// ===================================================================

function filtrerMessages(filtre) {
    const url = new URL(window.location);
    url.searchParams.set('tab', 'messagerie');
    url.searchParams.set('filter', filtre);
    url.searchParams.delete('search'); // Réinitialiser la recherche lors du filtrage
    window.location.href = url.toString();
}

function rechercherMessages(terme) {
    // Utilise un délai pour ne pas recharger la page à chaque frappe
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        const url = new URL(window.location);
        url.searchParams.set('tab', 'messagerie');
        if (terme.trim()) {
            url.searchParams.set('search', terme);
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }, 500);
}

function filtrerRapports(filtre) {
    const url = new URL(window.location);
    url.searchParams.set('tab', 'rapports');
    url.searchParams.set('filter', filtre);
    url.searchParams.delete('search');
    window.location.href = url.toString();
}

function rechercherRapports(terme) {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        const url = new URL(window.location);
        url.searchParams.set('tab', 'rapports');
        if (terme.trim()) {
            url.searchParams.set('search', terme);
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }, 500);
}

function rechercherTaches(terme) {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        const url = new URL(window.location);
        url.searchParams.set('tab', 'taches');
        if (terme.trim()) {
            url.searchParams.set('search', terme);
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }, 500);
}


// ===================================================================
// ==                   FONCTION DE NOTIFICATION                    ==
// ===================================================================

function afficherNotification(message, type = 'info') {
    const container = document.getElementById('notifications');
    if (!container) return;
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    let icon = 'fas fa-info-circle';
    if (type === 'success') icon = 'fas fa-check-circle';
    if (type === 'error') icon = 'fas fa-exclamation-triangle';
    notification.innerHTML = `<i class="${icon}"></i><span>${message}</span><button onclick="this.parentElement.remove()">&times;</button>`;
    container.appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
}
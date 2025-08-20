// ===================================================================
// ==        FICHIER JAVASCRIPT COMPLET POUR LE DASHBOARD STAGIAIRE       ==
// ===================================================================

// Variable globale pour suivre la modale actuellement ouverte
let currentModal = null;
let reponseDestinataireId = null;
let reponseSujetOriginal = '';

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
    document.getElementById('formNouveauMessage')?.addEventListener('submit', (e) => {
        e.preventDefault();
        envoyerMessage();
    });
    document.getElementById('formNouveauRapport')?.addEventListener('submit', (e) => {
        e.preventDefault();
        creerRapport();
    });

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

// Fonctions spécifiques pour ouvrir les modales
function ouvrirNouveauMessage() {
    ouvrirModal('modalNouveauMessage');
}

function ouvrirNouveauRapport() {
    ouvrirModal('modalNouveauRapport');
}

function ouvrirModalVoirTheme() {
    ouvrirModal('modalVoirTheme');
}


// ===================================================================
// ==                   GESTION DE LA MESSAGERIE                    ==
// ===================================================================

async function envoyerMessage() {
    const form = document.getElementById('formNouveauMessage');
    const formData = new FormData(form);
    formData.append('action', 'envoyer_message');

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            afficherNotification('Message envoyé avec succès !', 'success');
            fermerModal('modalNouveauMessage');
            if (window.location.search.includes('tab=messagerie')) {
                setTimeout(() => window.location.reload(), 1000);
            }
        } else {
            afficherNotification("Erreur lors de l'envoi du message.", 'error');
        }
    } catch (error) {
        afficherNotification('Erreur de connexion.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

async function ouvrirMessage(messageId) {
    try {
        const formData = new FormData();
        formData.append('action', 'marquer_lu');
        formData.append('message_id', messageId);
        await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        const messageElement = document.querySelector(`.message-item[onclick="ouvrirMessage(${messageId})"]`);
        if (messageElement) {
            messageElement.classList.remove('unread');
        }

        const response = await fetch(`get_message.php?id=${messageId}`); // Assume get_message.php exists
        const message = await response.json();
        if (message.error) {
            throw new Error(message.error);
        }

        document.getElementById('message-subject').textContent = message.sujet;
        document.getElementById('message-from').innerHTML = `<strong>De:</strong> ${message.exp_prenom} ${message.exp_nom}`;
        document.getElementById('message-date').textContent = `Date: ${new Date(message.date_envoi).toLocaleString()}`;
        document.getElementById('message-content').innerHTML = message.contenu.replace(/\n/g, '<br>');

        const attachmentsContainer = document.getElementById('message-attachments');
        attachmentsContainer.innerHTML = '';
        if (message.pieces_jointes && message.pieces_jointes.length > 0) {
            let list = '<strong>Pièces jointes:</strong><ul>';
            message.pieces_jointes.forEach(pj => {
                list += `<li><a href="uploads/messages/${pj.chemin}" target="_blank">${pj.nom_fichier}</a></li>`;
            });
            attachmentsContainer.innerHTML = list + '</ul>';
        }
        const boutonRepondre = document.getElementById('boutonRepondreMessage');
        if (message.expediteur_id !== currentUserId) {
            // Si ce n'est pas un message que j'ai envoyé, je peux répondre
            boutonRepondre.style.display = 'inline-block';
            reponseDestinataireId = message.expediteur_id; // On répond à l'expéditeur
            reponseSujetOriginal = message.sujet;
        } else {
            // C'est un message que j'ai envoyé, je ne peux pas y répondre
            boutonRepondre.style.display = 'none';
        }

        ouvrirModal('modalVoirMessage');
    } catch (error) {
        afficherNotification('Impossible de charger le message.', 'error');
    }
}

function filtrerMessages(filtre) {
    const url = new URL(window.location);
    url.searchParams.set('tab', 'messagerie');
    url.searchParams.set('filter', filtre);
    window.location.href = url.toString();
}

function rechercherMessages(terme) {
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

/**
 * Prépare le formulaire de nouveau message pour une réponse.
 */
function repondreAuMessage() {
    // Fermer la modale de lecture
    fermerModal('modalVoirMessage');

    // Ouvrir la modale de nouveau message
    const modalNouveauMessage = document.getElementById('modalNouveauMessage');
    const form = modalNouveauMessage.querySelector('form');
    
    // Pré-remplir le destinataire
    const destinataireSelect = form.querySelector('select[name="destinataire_id"]');
    destinataireSelect.value = reponseDestinataireId;

    // Pré-remplir le sujet
    const sujetInput = form.querySelector('input[name="sujet"]');
    if (!reponseSujetOriginal.toLowerCase().startsWith('re:')) {
        sujetInput.value = 'Re: ' + reponseSujetOriginal;
    } else {
        sujetInput.value = reponseSujetOriginal;
    }

    // Mettre le focus sur le contenu du message
    const contenuTextarea = form.querySelector('textarea[name="contenu"]');
    contenuTextarea.focus();
    
    // Ouvrir la modale
    ouvrirModal('modalNouveauMessage');
}


// ===================================================================
// ==                     GESTION DES RAPPORTS                      ==
// ===================================================================

async function creerRapport() {
    const form = document.getElementById('formNouveauRapport');
    const formData = new FormData(form);
    formData.append('action', 'creer_rapport');

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création...';

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            afficherNotification('Rapport créé avec succès !', 'success');
            fermerModal('modalNouveauRapport');
            setTimeout(() => window.location.href = '?tab=rapports', 1000);
        } else {
            afficherNotification('Erreur : ' + (result.message || 'Inconnue'), 'error');
        }
    } catch (error) {
        afficherNotification('Erreur de connexion.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

function filtrerRapports(filtre) {
    const url = new URL(window.location);
    url.searchParams.set('tab', 'rapports');
    url.searchParams.set('filter', filtre);
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

function voirRapport(rapportId) {
    window.location.href = `voir_rapport.php?id=${rapportId}`;
}

function telechargerRapport(rapportId) {
    window.location.href = `generer_pdf.php?id=${rapportId}`;
}


// ===================================================================
// ==                      GESTION DES TÂCHES                       ==
// ===================================================================

async function terminerTache(tacheId) {
    if (!confirm('Voulez-vous vraiment marquer cette tâche comme terminée ?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'terminer_tache');
    formData.append('tache_id', tacheId);

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            afficherNotification('Tâche marquée comme terminée !', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            afficherNotification('Erreur lors de la mise à jour.', 'error');
        }
    } catch (error) {
        afficherNotification('Erreur de connexion.', 'error');
    }
}

function filtrerTaches(filtre) {
    const url = new URL(window.location);
    url.searchParams.set('tab', 'taches');
    url.searchParams.set('filter', filtre);
    window.location.href = url.toString();
}

async function voirTache(tacheId) {
    const modalBody = document.getElementById('tacheModalBody');
    const modalTitle = document.getElementById('tacheModalTitle');
    
    // Afficher un spinner de chargement
    modalBody.innerHTML = '<div class="loading-spinner"></div>';
    ouvrirModal('modalVoirTache');

    const formData = new FormData();
    formData.append('action', 'get_tache_details');
    formData.append('tache_id', tacheId);

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            const tache = result.data;
            
            // Mettre à jour le titre de la modale
            modalTitle.textContent = tache.titre;

            // Construire le contenu HTML des détails
            let contenuHtml = `
                <div class="tache-details-view">
                    <div class="detail-item">
                        <span class="detail-label"><i class="fas fa-calendar-alt"></i> Date d'échéance :</span>
                        <span class="detail-value">${new Date(tache.date_echeance).toLocaleDateString('fr-FR')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><i class="fas fa-info-circle"></i> Statut :</span>
                        <span class="detail-value status-badge status-${tache.statut.replace('_', '-')}">${tache.statut.replace('_', ' ')}</span>
                    </div>
                    <hr>
                    <h4><i class="fas fa-align-left"></i> Description</h4>
                    <p class="tache-description">${tache.description.replace(/\n/g, '<br>')}</p>
            `;

            if (tache.nom_fichier_original) {
                contenuHtml += `
                    <hr>
                    <h4><i class="fas fa-paperclip"></i> Fichier Joint</h4>
                    <p>
                        <a href="uploads/taches/${tache.fichier_joint}" target="_blank" class="btn btn-sm">
                            <i class="fas fa-download"></i> ${tache.nom_fichier_original}
                        </a>
                    </p>
                `;
            }

            contenuHtml += '</div>'; // Fin de .tache-details-view
            modalBody.innerHTML = contenuHtml;

        } else {
            modalBody.innerHTML = `<p class="text-danger">${result.message || 'Une erreur est survenue.'}</p>`;
        }

    } catch (error) {
        modalBody.innerHTML = `<p class="text-danger">Impossible de charger les détails de la tâche.</p>`;
        console.error("Erreur lors de la récupération de la tâche:", error);
    }
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
// ==                     GESTION DE LA PRÉSENCE                    ==
// ===================================================================

async function marquerAction(presenceAction) {
    const geoInfoSpan = document.getElementById('geo-info').querySelector('span');
    geoInfoSpan.textContent = "Obtention de votre position...";

    try {
        const position = await new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true
        }));
        const {
            latitude,
            longitude
        } = position.coords;
        const geoResponse = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}`);
        const geoData = await geoResponse.json();
        const address = geoData.address;
        const formattedAddress = `${address.road || ''}, ${address.city || ''}`.trim();

        geoInfoSpan.textContent = `Lieu : ${formattedAddress}`;

        const formData = new FormData();
        formData.append('action', 'marquer_presence');
        formData.append('presence_action', presenceAction);
        formData.append('localisation', formattedAddress);

        const presResponse = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const presData = await presResponse.json();

        if (presData.success) {
            afficherNotification('Votre présence a été enregistrée !', 'success');
            // Redirige vers l'onglet présence pour voir la mise à jour
            setTimeout(() => window.location.href = '?tab=presences', 1500);
        } else {
            afficherNotification("Action déjà effectuée ou impossible.", 'error');
        }
    } catch (error) {
        geoInfoSpan.textContent = "Géolocalisation refusée. Veuillez l'autoriser.";
        afficherNotification("Vous devez autoriser la géolocalisation pour pointer.", "error");
    }
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
    if (type === 'success') {
        icon = 'fas fa-check-circle';
    }
    if (type === 'error') {
        icon = 'fas fa-exclamation-triangle';
    }

    notification.innerHTML = `<i class="${icon}"></i><span>${message}</span><button class="close-btn" onclick="this.parentElement.remove()">&times;</button>`;

    container.appendChild(notification);
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

async function voirRapport(rapportId) {
    const modalBody = document.getElementById('rapportModalBody');
    const modalTitle = document.getElementById('rapportModalTitle');
    const modalFooter = document.getElementById('rapportModalFooter');

    // Afficher un spinner de chargement
    modalBody.innerHTML = '<div class="loading-spinner"></div>';
    modalTitle.textContent = 'Chargement du rapport...';
    ouvrirModal('modalVoirRapport');

    const formData = new FormData();
    formData.append('action', 'get_rapport_details');
    formData.append('rapport_id', rapportId);

    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();

        if (result.success) {
            const rapport = result.data;
            
            modalTitle.textContent = rapport.titre;

            // Construire le contenu HTML
            let contenuHtml = `
                <div class="rapport-details-view">
                    <div class="rapport-meta">
                        <div class="meta-item"><strong>Stagiaire:</strong> ${rapport.stag_prenom} ${rapport.stag_nom}</div>
                        <div class="meta-item"><strong>Date:</strong> ${new Date(rapport.date_soumission).toLocaleDateString('fr-FR')}</div>
                        <div class="meta-item"><strong>Type:</strong> ${rapport.type}</div>
                        <div class="meta-item"><strong>Statut:</strong> <span class="status-badge status-${rapport.statut.replace(' ', '_')}">${rapport.statut}</span></div>
                    </div>
                    <hr>
                    <h4><i class="fas fa-tasks"></i> Activités Réalisées</h4>
                    <p class="section-content">${rapport.activites.replace(/\n/g, '<br>')}</p>
                    <hr>
                    <h4><i class="fas fa-exclamation-triangle"></i> Difficultés Rencontrées</h4>
                    <p class="section-content">${rapport.difficultes.replace(/\n/g, '<br>')}</p>
                    <hr>
                    <h4><i class="fas fa-lightbulb"></i> Solutions Apportées</h4>
                    <p class="section-content">${rapport.solutions.replace(/\n/g, '<br>')}</p>
            `;

            if (rapport.commentaire_encadreur) {
                contenuHtml += `
                    <hr>
                    <h4><i class="fas fa-comments"></i> Commentaire de l'Encadreur</h4>
                    <p class="section-content comment">${rapport.commentaire_encadreur.replace(/\n/g, '<br>')}</p>
                `;
            }
            
            contenuHtml += `</div>`;
            modalBody.innerHTML = contenuHtml;
            
            // Mettre à jour le pied de page avec les boutons
            modalFooter.innerHTML = `
                <button type="button" class="btn btn-secondary" onclick="fermerModal('modalVoirRapport')">Fermer</button>
                <a href="telecharger_rapport.php?id=${rapport.id}" class="btn btn-primary"><i class="fas fa-download"></i> Télécharger PDF</a>
            `;

        } else {
            modalBody.innerHTML = `<p class="text-danger">${result.message}</p>`;
        }
    } catch (error) {
        modalBody.innerHTML = `<p class="text-danger">Impossible de charger les détails du rapport.</p>`;
    }
}
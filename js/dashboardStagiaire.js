// ===================================================================
// ==        FICHIER JAVASCRIPT COMPLET POUR LE DASHBOARD STAGIAIRE       ==
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
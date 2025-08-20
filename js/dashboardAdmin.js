// ===================================================================
// ==        FICHIER JAVASCRIPT COMPLET POUR LE DASHBOARD ADMIN         ==
// ===================================================================

let currentModal = null;

// S'exécute lorsque le DOM est entièrement chargé
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    setupEventListeners();
});

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
}

function setupEventListeners() {
    // Écouteur unique pour le formulaire utilisateur (création ET modification)
    document.getElementById('formUtilisateur')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const action = document.getElementById('user_action').value;
        const successMessage = action === 'creer_utilisateur' ? 'Utilisateur créé avec succès !' : 'Utilisateur mis à jour avec succès !';
        soumettreFormulaire(this, action, successMessage, 'modalUtilisateur');
    });

    // Écouteur pour le formulaire d'affectation
    document.getElementById('formAffecterEncadreur')?.addEventListener('submit', function(e) {
        e.preventDefault();
        soumettreFormulaire(this, 'affecter_encadreur', 'Affectation mise à jour !', 'modalAffecterEncadreur');
    });

    // Actions globales (fermeture de modale)
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
// ==                  GESTION DES UTILISATEURS                     ==
// ===================================================================

function toggleRoleFields(roleValue = null) {
    const role = roleValue || document.getElementById('roleSelect').value;
    const stagiaireFields = document.getElementById('stagiaireFields');
    const encadreurFields = document.getElementById('encadreurFields');

    stagiaireFields.style.display = 'none';
    encadreurFields.style.display = 'none';
    stagiaireFields.querySelectorAll('input').forEach(input => input.required = false);
    encadreurFields.querySelectorAll('input').forEach(input => input.required = false);

    if (role === 'stagiaire') {
        stagiaireFields.style.display = 'block';
        stagiaireFields.querySelectorAll('input').forEach(input => input.required = true);
    } else if (role === 'encadreur') {
        encadreurFields.style.display = 'block';
        encadreurFields.querySelectorAll('input').forEach(input => input.required = true);
    }
}

function ouvrirModalCreerUtilisateur() {
    const form = document.getElementById('formUtilisateur');
    form.reset();
    document.getElementById('modalUtilisateurTitle').textContent = 'Créer un nouvel utilisateur';
    document.getElementById('modalUtilisateurSubmitBtn').textContent = 'Créer l\'utilisateur';
    document.getElementById('user_action').value = 'creer_utilisateur';
    document.getElementById('user_id').value = '';
    
    const passwordField = form.querySelector('[name="password"]');
    passwordField.previousElementSibling.innerHTML = 'Mot de passe *';
    passwordField.placeholder = '';
    passwordField.required = true;
    
    toggleRoleFields('');
    document.getElementById('roleSelect').disabled = false;
    
    ouvrirModal('modalUtilisateur');
}

async function ouvrirModalModifierUtilisateur(userId) {
    const formData = new FormData();
    formData.append('action', 'get_utilisateur_details');
    formData.append('user_id', userId);
    
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (!result.success) throw new Error(result.message || "Données non trouvées");
        
        const user = result.data;
        const form = document.getElementById('formUtilisateur');
        form.reset();

        document.getElementById('modalUtilisateurTitle').textContent = `Modifier ${user.prenom} ${user.nom}`;
        document.getElementById('modalUtilisateurSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Mettre à jour';
        document.getElementById('user_action').value = 'modifier_utilisateur';
        document.getElementById('user_id').value = user.id;

        form.querySelector('[name="prenom"]').value = user.prenom;
        form.querySelector('[name="nom"]').value = user.nom;
        form.querySelector('[name="email"]').value = user.email;
        form.querySelector('[name="telephone"]').value = user.telephone;
        form.querySelector('[name="sex"]').value = user.sex;
        form.querySelector('[name="role"]').value = user.role;
        
        const passwordField = form.querySelector('[name="password"]');
        passwordField.previousElementSibling.innerHTML = 'Nouveau mot de passe';
        passwordField.placeholder = "Laisser vide pour ne pas changer";
        passwordField.required = false;

        toggleRoleFields(user.role);
        if (user.role === 'stagiaire') {
            form.querySelector('[name="filiere"]').value = user.filiere;
            form.querySelector('[name="niveau"]').value = user.niveau;
            form.querySelector('[name="date_debut"]').value = user.date_debut;
            form.querySelector('[name="date_fin"]').value = user.date_fin;
        } else if (user.role === 'encadreur') {
            form.querySelector('[name="poste"]').value = user.poste;
            form.querySelector('[name="service"]').value = user.service;
        }
        
        document.getElementById('roleSelect').disabled = true;
        ouvrirModal('modalUtilisateur');
    } catch (error) {
        afficherNotification("Impossible de charger les données de l'utilisateur.", "error");
    }
}

async function supprimerUtilisateur(userId) {
    if (confirm('Attention : Cette action est irréversible. Voulez-vous vraiment supprimer cet utilisateur ?')) {
        const formData = new FormData();
        formData.append('user_id', userId);
        await soumettreFormulaire(formData, 'supprimer_utilisateur', 'Utilisateur supprimé avec succès !');
    }
}

function ouvrirModalAffecter(stagiaireId, encadreurActuelId) {
    document.getElementById('affecter_stagiaire_id').value = stagiaireId;
    document.getElementById('affecter_encadreur_id').value = encadreurActuelId || "";
    ouvrirModal('modalAffecterEncadreur');
}

function changerStatut(userId, nouveauStatut) {
    if (!confirm(`Voulez-vous vraiment ${nouveauStatut === 'actif' ? 'débloquer' : 'bloquer'} cet utilisateur ?`)) return;
    const formData = new FormData();
    formData.append('action', 'changer_statut');
    formData.append('user_id', userId);
    formData.append('statut', nouveauStatut);
    soumettreFormulaire(formData, null, 'Statut mis à jour !');
}


// ===================================================================
// ==         FONCTION UTILITAIRE CENTRALE POUR LES FORMULAIRES     ==
// ===================================================================

async function soumettreFormulaire(formOrData, action, successMessage, modalToClose = null) {
    const formData = formOrData instanceof FormData ? formOrData : new FormData(formOrData);
    if (!formData.has('action') && action) {
        formData.append('action', action);
    }
    
    let submitBtn = (formOrData instanceof HTMLElement) ? formOrData.querySelector('button[type="submit"]') : null;
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
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
        afficherNotification('Erreur de connexion.', 'error');
    } finally {
        if (submitBtn) {
            // Le rechargement se chargera de réinitialiser le bouton.
        }
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
    if (type === 'success') icon = 'fas fa-check-circle';
    if (type === 'error') icon = 'fas fa-exclamation-triangle';
    notification.innerHTML = `<i class="${icon}"></i><span>${message}</span><button onclick="this.parentElement.remove()">&times;</button>`;
    container.appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
}
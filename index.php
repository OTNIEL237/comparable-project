<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparable - Gestion de Stagiaires Simplifiée</title>
    
    <!-- Google Fonts pour une typographie professionnelle -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
<link rel="stylesheet" href="css/style.css">
</head>
<body>

    <header>
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">
                    <img src="comparable.png" alt="Logo Comparable">
                </a>
                <ul class="nav-links">
                    <li><a href="#hero">Accueil</a></li>
                    <li><a href="#about">À Propos</a></li>
                    <li><a href="#services">Services</a></li>
                </ul>
                <a href="login.php" class="btn btn-login">Se Connecter</a>
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <!-- SECTION HERO -->
        <section id="hero">
            <div class="container">
                <div class="hero-content">
                    <div class="hero-text">
                        <h1>Optimisez la <span>performance</span> de vos stagiaires.</h1>
                        <p>
                            Comparable est la solution digitale tout-en-un qui simplifie le suivi, la gestion et l'évaluation de vos programmes de stage pour un succès garanti.
                        </p>
                        <a href="login.php" class="btn btn-primary">Commencer Maintenant</a>
                    </div>
                    <div class="hero-image">
                </div>
            </div>
        </section>

        <!-- SECTION SERVICES -->
        <section id="services">
            <div class="container">
                <div class="section-title">
                    <h2>Nos Services Clés</h2>
                </div>
                <div class="services-grid">
                    <div class="service-card">
                        <div class="icon"><i class="fas fa-tasks"></i></div>
                        <h3>Suivi des Tâches</h3>
                        <p>Attribuez, suivez et validez les tâches de vos stagiaires en temps réel depuis un dashboard centralisé.</p>
                    </div>
                    <div class="service-card">
                        <div class="icon"><i class="fas fa-file-signature"></i></div>
                        <h3>Gestion des Rapports</h3>
                        <p>Simplifiez la soumission et la validation des rapports de stage avec un système de suivi de statut intuitif.</p>
                    </div>
                    <div class="service-card">
                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                        <h3>Suivi des Présences</h3>
                        <p>Un système de pointage moderne avec géolocalisation pour un suivi précis et fiable des présences.</p>
                    </div>
                    <div class="service-card">
                        <div class="icon"><i class="fas fa-chart-line"></i></div>
                        <h3>Évaluation des Performances</h3>
                        <p>Obtenez des statistiques détaillées sur la ponctualité et la performance de chaque stagiaire.</p>
                    </div>
                    <div class="service-card">
                        <div class="icon"><i class="fas fa-lightbulb"></i></div>
                        <h3>Attribution de Thèmes</h3>
                        <p>Gérez et attribuez facilement des thèmes de stage pour garantir un encadrement structuré et pertinent.</p>
                    </div>
                    <div class="service-card">
                        <div class="icon"><i class="fas fa-comments"></i></div>
                        <h3>Messagerie Intégrée</h3>
                        <p>Communiquez de manière fluide et sécurisée avec vos stagiaires directement sur la plateforme.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION À PROPOS -->
        <section id="about">
            <div class="container">
                <div class="about-content">
                    <div class="about-text">
                        <h2>L'entreprise qui répond à tous vos besoins informatiques.</h2>
                        <p>
                            <strong>Comparable</strong> est une société de services informatiques dont l’activité principale est centrée sur les nouvelles technologies digitales. Notre but est de développer durablement des solutions qui correspondent non seulement à vos attentes, mais aussi à une réelle avancée technologique dans votre domaine d’activité.
                        </p>
                        <h3>Pourquoi nous choisir ?</h3>
                        <p>
                            Parce que nous avons l'expertise et l'expérience dans le domaine de l'ingénierie logicielle, appuyées par des produits robustes qui répondent à toutes les exigences du système d'information de nos clients, quelle que soit leur taille.
                        </p>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h4>À Propos de Comparable</h4>
                    <p>Éditeur de logiciels, Comparable SARL regroupe les compétences d’une SSII et celles d’un cabinet de formation et de conseil en management.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Plan du Site</h4>
                    <a href="#hero">Accueil</a>
                    <a href="#about">À Propos</a>
                    <a href="#services">Services</a>
                    <a href="login.php">Se Connecter</a>
                </div>
                <div class="footer-col">
                    <h4>Nos Services</h4>
                    <p>Ingénierie Logicielle</p>
                    <p>Conception de Site Web</p>
                    <p>Formations & Conseils</p>
                    <p>Infogérance</p>
                </div>
                <div class="footer-col">
                    <h4>Nous Contacter</h4>
                    <p><i class="fas fa-map-marker-alt"></i> Douala, Akwa, face Hôtel Astoria</p>
                    <p><i class="fas fa-phone"></i> (237) 695 180 534</p>
                    <p><i class="fas fa-phone"></i> +1 (438) 878-8666</p>
                    <p><i class="fas fa-envelope"></i> contact@comparablesa.com</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; Copyright <?php echo date('Y'); ?>. Comparable SARL. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script>
        // Script simple pour le menu hamburger sur mobile
        const hamburger = document.querySelector('.hamburger');
        const navLinks = document.querySelector('.nav-links');

        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navLinks.classList.toggle('active');
        });
    </script>

</body>
</html>
<?php
/**
 * Classe Database - Gestion de la connexion à la base de données
 * Fichier à placer dans : comparable-project/classes/Database.php
 */
class Database {
    private static $instance = null;
    private static $connection = null;
    
    // Configuration de la base de données
    private static $host = "localhost";
    private static $username = "root";
    private static $password = "";
    private static $database = "gestion_stagiaires";
    private static $port = 3307;
    
    /**
     * Constructeur privé pour empêcher l'instanciation directe
     */
    private function __construct() {}
    
    /**
     * Obtenir l'instance unique de la classe (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtenir la connexion à la base de données
     * @return mysqli Connexion à la base de données
     */
    public static function getConnection() {
        if (self::$connection === null) {
            self::$connection = new mysqli(
                self::$host, 
                self::$username, 
                self::$password, 
                self::$database, 
                self::$port
            );
            
            // Vérifier la connexion
            if (self::$connection->connect_error) {
                die("Erreur de connexion : " . self::$connection->connect_error);
            }
            
            // Définir l'encodage
            self::$connection->set_charset("utf8");
        }
        
        return self::$connection;
    }
    
    /**
     * Fermer la connexion
     */
    public static function closeConnection() {
        if (self::$connection !== null) {
            self::$connection->close();
            self::$connection = null;
        }
    }
}
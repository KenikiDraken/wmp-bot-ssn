<?php
function getDBConnection() {
    $dsn = 'mysql:host=localhost;dbname=hsbeyyyy_chatbot_ssn;charset=utf8mb4';
    $username = 'hsbeyyyy_chatssn_usr';
    $password = ')>k2`IK,yE';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    return new PDO($dsn, $username, $password, $options);
}

// Fonction pour analyser l'entrée de l'utilisateur
function analyserMessage($message) {
    // Convertir le message en minuscule pour simplifier la comparaison
    $message = strtolower($message);
    
    // Liste des intentions et des mots-clés associés
    $intents = [
        'produit' => ['EnergiePlus','carte','carte personnalisée', 'multi-énergie', 'solution prépayée', 'offre Sahel Négoce', 'carte de fidélité'],
        'prix' => ['coût', 'tarif', 'prix d\'abonnement', 'tarif mensuel', 'montant', 'coût total', 'prix unitaire'],
        'rechargement' => ['ajouter crédit', 'recharger', 'mettre à jour solde', 'approvisionner', 'ajouter balance', 'crédit supplémentaire'],
        'guichets' => ['guichet', 'emplacement NITA', 'zone de recharge','endroid de depot' 'guichet NITA', 'point de dépôt'],
        'agent' => ['support client', 'conseiller', 'chat en direct', 'assistance humaine', 'service d\'aide', 'contact direct'],
        'remerciements' => ['merci', 'merci beaucoup', 'thanks', 'thank you', 'gratitude', 'merci pour l\'assistance'],
        'assistance' => ['aide', 'assistance', 'problème', 'difficulté', 'support technique', 'urgence', 'réponse immédiate'],
        'questions_generales' => ['informations', 'détails', 'explication', 'questions fréquentes', 'clarification', 'précisions'],
        'services_associes' => ['vidange', 'entretien véhicule', 'lavage', 'réparation', 'services supplémentaires', 'prestations annexes']
    ];
 
    
    // Détection de l'intention à partir des mots-clés
    foreach ($intents as $intention => $keywords) {
        foreach ($keywords as $keyword) {
            // Si un mot-clé est trouvé dans le message
            if (strpos($message, $keyword) !== false) {
                return $intention; // Retourner l'intention
            }
        }
    }
    
    // Si aucune intention n'est détectée, renvoyer une réponse par défaut
    return null;
}

function sendTextMessage($numero, $message) {
    $url = "https://waplatform.qwiper.com/api/21ea6f64-4058-467d-97ee-b72bab0849f5/contact/send-message";

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
    $headers = array(
       "Authorization: Bearer 8uy6SaQC8DWGIdSHC9HWox1MfU3CaHJ3gN8Dl63c81YFo0QAdQgsis22vRGOzxZ8",
       "Content-Type: application/json",
    );
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    
    $data = json_encode([
        "phone_number" => $numero,
        "message_body" => $message,
        "contact" => [
            "first_name" => "",
            "last_name" => "",
            "email" => "",
            "country" => "",
            "language_code" => "fr"
        ]
    ]);
    
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    
    //for debug only!
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    
    $resp = curl_exec($curl);
    curl_close($curl);
    return $resp;
}

function authenticateRequest() {
    // Vérification si le paramètre 'token' existe dans l'URL
    $token = $_GET['token'] ?? null;

    if (!$token) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Non autorisé. Token manquant.']);
        exit;
    }

    $validApiKey = '21ea6f64-4058-467d-97ee-b72bab0849f5'; // À générer et sécuriser dans un fichier d’environnement

    if ($token != $validApiKey) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Non autorisé. Token invalide.']);
        exit;
    }
}

function decodeMessageBody($json) {
    // Décoder le JSON en tableau associatif PHP
    $data = json_decode($json, true);

    // Vérifier si la structure du JSON contient les éléments nécessaires
    if (isset($data['message']['body']) && isset($data['contact']['phone_number'])) {
        // Extraire les informations nécessaires
        $body = $data['message']['body'];
        $from = $data['contact']['phone_number'];

        // Décoder les caractères Unicode (emoji inclus)
        $decodedBody = json_decode('"' . $body . '"');
        
        // Retourner les résultats sous forme de tableau
        return [
            'body' => $decodedBody,
            'from' => $from
        ];
    } else {
        return null; // Retourner null si les données ne sont pas valides
    }
}

function validateSession($sessionToken) {
    $db = getDBConnection();

    // Vérifie si la session existe et n'est pas expirée
    $stmt = $db->prepare("
        SELECT id, user_id, expires_at 
        FROM sessions 
        WHERE session_token = ? AND is_active = 1
    ");
    $stmt->execute([$sessionToken]);
    $session = $stmt->fetch();

    if (!$session) {
        return ['status' => 'error', 'message' => 'Session invalide ou non trouvée.'];
    }

    // Vérifie l'expiration
    if (new DateTime() > new DateTime($session['expires_at'])) {
        // Marque la session comme inactive
        $stmt = $db->prepare("UPDATE sessions SET is_active = 0 WHERE id = ?");
        $stmt->execute([$session['id']]);
        return ['status' => 'error', 'message' => 'Session expirée.'];
    }

    return ['status' => 'success', 'session' => $session];
}

function startSession($phoneNumber) {
    $db = getDBConnection();

    // Vérifie si l'utilisateur existe
    $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = ?");
    $stmt->execute([$phoneNumber]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $db->prepare("INSERT INTO users (phone_number) VALUES (?)");
        $stmt->execute([$phoneNumber]);
        $userId = $db->lastInsertId();
    } else {
        $userId = $user['id'];
    }

    // Date d'expiration (30 minutes)
    $expiresAt = (new DateTime())->modify('+30 minutes')->format('Y-m-d H:i:s');

    // Vérifie si une session active existe
    $stmt = $db->prepare("
        SELECT session_token FROM sessions 
        WHERE user_id = ? AND is_active = 1 AND expires_at > NOW()
    ");
    $stmt->execute([$userId]);
    $session = $stmt->fetch();

    if (!$session) {
        // Crée une nouvelle session
        $sessionToken = bin2hex(random_bytes(16));
        $stmt = $db->prepare("
            INSERT INTO sessions (user_id, session_token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $sessionToken, $expiresAt]);
    } else {
        $sessionToken = $session['session_token'];
    }

    return $sessionToken;
}

function endSession($sessionToken) {
    $db = getDBConnection();

    $stmt = $db->prepare("UPDATE sessions SET is_active = 0 WHERE session_token = ?");
    $stmt->execute([$sessionToken]);

    return ['status' => 'success', 'message' => 'Session terminée.'];
}

function storeUserData($userId, $keyName, $value) {
    $db = getDBConnection();

    // Vérifiez d'abord si la donnée existe déjà
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_data WHERE user_id = ? AND key_name = ?");
    $stmt->execute([$userId, $keyName]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        // Si la donnée existe, effectuez une mise à jour
        $stmt = $db->prepare("UPDATE user_data SET value = ? WHERE user_id = ? AND key_name = ?");
        $stmt->execute([$value, $userId, $keyName]);
        $message = 'Donnée mise à jour.';
    } else {
        // Sinon, insérez une nouvelle donnée
        $stmt = $db->prepare("INSERT INTO user_data (user_id, key_name, value) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $keyName, $value]);
        $message = 'Donnée enregistrée.';
    }

    return ['status' => 'success', 'message' => $message];
}

function getUserData($userId, $keyName) {
    $db = getDBConnection();

    $stmt = $db->prepare("SELECT value FROM user_data WHERE user_id = ? AND key_name = ?");
    $stmt->execute([$userId, $keyName]);

    return $stmt->fetchColumn();
}

function updateSessionStep($sessionId, $step) {
    $db = getDBConnection();

    $stmt = $db->prepare("UPDATE sessions SET current_step = ?, last_interaction = NOW() WHERE id = ?");
    $stmt->execute([$step, $sessionId]);
}


function getSessionStep($sessionToken) {
    $db = getDBConnection();

    $stmt = $db->prepare("SELECT current_step FROM sessions WHERE session_token = ?");
    $stmt->execute([$sessionToken]);

    return $stmt->fetchColumn();
}

function getOrCreateUser($phoneNumber) {
    $db = getDBConnection();

    // Vérifier si l'utilisateur existe déjà
    $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = ?");
    $stmt->execute([$phoneNumber]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        // Créer un nouvel utilisateur si inexistant
        $stmt = $db->prepare("INSERT INTO users (phone_number) VALUES (?)");
        $stmt->execute([$phoneNumber]);
        $userId = $db->lastInsertId();
    }

    return $userId;
}

function getOrCreateSession($userId) {
    $db = getDBConnection();

    // Vérifier si une session active existe
    $stmt = $db->prepare("
        SELECT * FROM sessions 
        WHERE user_id = ? AND last_interaction > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    $stmt->execute([$userId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        // Créer une nouvelle session avec un current_step par défaut (par ex. "welcome")
        $sessionToken = bin2hex(random_bytes(16));
        $stmt = $db->prepare("
            INSERT INTO sessions (session_token, user_id, current_step) VALUES (?, ?, 'welcome')
        ");
        $stmt->execute([$sessionToken, $userId]);

        $sessionId = $db->lastInsertId();
        $session = [
            'id' => $sessionId,
            'session_token' => $sessionToken,
            'user_id' => $userId,
            'current_step' => 'welcome',
            'last_interaction' => date('Y-m-d H:i:s')
        ];
    }

    return $session;
}

function deleteSession($sessionId) {
    $db = getDBConnection();

    $stmt = $db->prepare("DELETE FROM sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
}
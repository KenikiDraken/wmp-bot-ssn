<?php
require 'includes.php';
require 'IA_messages.php';

// Auth
authenticateRequest();

// Récupérer les données du WebHook
$contenuRecu = file_get_contents('php://input');

if (!empty($contenuRecu)) {
    
    // Décoder et stocker sans des variables
    $result = decodeMessageBody($contenuRecu);
    $phoneNumber = $result['from'] ?? null; // Numéro de téléphone de l'utilisateur
    $userMessage = $result['body'] ?? null; // Message envoyé par l'utilisateur

    // Petite limitation de nombre d'utilisateur au chatbot
    if ($phoneNumber != "22797927042" && $phoneNumber != "22789323500") {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Vous ne faites pas partis de la bêta.']);
        exit;
    }    

    if (!$phoneNumber || !$userMessage) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Numéro de téléphone ou message utilisateur manquant.']);
        exit;
    }

    // Identifier ou créer l'utilisateur
    $userId = getOrCreateUser($phoneNumber);

    // Identifier ou créer une session pour l'utilisateur
    $session = getOrCreateSession($userId);

    // Charger les informations de session
    $sessionId = $session['id'];
    $currentStep = $session['current_step'];

    // Gestion des étapes du chatbot
    if ($currentStep === 'welcome' || $currentStep === null) {
        
        $botMessage = $welcomeMessages[array_rand($welcomeMessages)];
        sendTextMessage($phoneNumber, $botMessage);
        updateSessionStep($sessionId, 'menu_principal');

    } elseif ($currentStep === 'welcome_2' || $currentStep === null) {
        
            $botMessage = $welcomeSuccessMessages[array_rand($welcomeSuccessMessages)];
            sendTextMessage($phoneNumber, $botMessage);
            updateSessionStep($sessionId, 'menu_principal');

    } elseif ($currentStep === 'welcome_3' || $currentStep === null) {
        
        $botMessage = $welcomeErrorMessages[array_rand($welcomeErrorMessages)];
        sendTextMessage($phoneNumber, $botMessage);
        updateSessionStep($sessionId, 'menu_principal');
    
    } elseif ($currentStep === 'menu_principal') {

        // Analyser le message de l'utilisateur pour détecter l'intention
        $intention = analyserMessage($userMessage);

        if ($intention) {
        
            // Vérifier quel bouton a été cliqué
            switch ($intention) {

                case 'produit':
                    // Info sur les produit a offrir
                    $botMessage = $productInfoMessages[array_rand($productInfoMessages)];
                    sendTextMessage($phoneNumber, $botMessage);
                    updateSessionStep($sessionId, 'demander_type_produit');
                    break;
    
                case 'prix':
                    // Description des prix (frais cartes et les recharges)
                    $botMessage = $priceInfoMessages[array_rand($priceInfoMessages)];
                    sendTextMessage($phoneNumber, $botMessage);
                    updateSessionStep($sessionId, 'demander_produit');
                    break;
    
                case 'rechargement':
                    // Comment mettre a jour ou faire les rechargement
                    $botMessage = $rechargeInfoMessages[array_rand($rechargeInfoMessages)];
                    sendTextMessage($phoneNumber, $botMessage);
                    updateSessionStep($sessionId, 'demander_montant_rechargement');
                    break;
    
                case 'guichets':
                    // Pour les guichets ou on peut faire les recharges des cartes;
                    $botMessage = $guichetsInfoMessages[array_rand($guichetsInfoMessages)];
                    sendTextMessage($phoneNumber, $botMessage);
                    updateSessionStep($sessionId, 'demander_emplacement_guichet');
                    break;

                case 'agent':
                    // Pour les Clients cherchant de l'assistance humaine
                    $botMessage = $agentInfoMessages[array_rand($agentInfoMessages)];
                    sendTextMessage($phoneNumber, $botMessage);
                    updateSessionStep($sessionId, 'attente_agent');
                    break;
    
                case 'assistance':
                    // Pour dese probleme imprevu des la par du client
                    $botMessage = $assistanceMessages[array_rand($assistanceMessages)];
                    sendTextMessage($phoneNumber, $botMessage);
                    updateSessionStep($sessionId, 'demander_probleme');
                    break;
    
                case 'questions_generales':
                    // Redirigeant vers explication du produit
                    $botMessage = $productInfoMessages[array_rand($productInfoMessages)];
                    sendTextMessage($phoneNumber, $botMessage);
                    updateSessionStep($sessionId, 'demander_question_generale');
                    break;
    
                case 'services_associes':
                    // Pour des services supplementaire a propos de la carte EnergiePlus
                    $botMessage = $servicesAssociesMessages[array_rand($servicesAssociesMessages)];
                    sendTextMessage($phoneNumber, $botMessage);
                    updateSessionStep($sessionId, 'demander_service_associe');
                    break;
                
                case 'remerciements':
                    //...
                    $botMessage = $thankYouMessages[array_rand($thankYouMessages)];
                    sendTextMessage($phoneNumber, $botMessage);
                    updateSessionStep($sessionId, 'welcome_2');
                    break;
        
                default:
                    break;
            }

        } else {
            // Si aucune intention n'est détectée dans le message
            $botMessage = $confusionMessages[array_rand($confusionMessages)];
            sendTextMessage($phoneNumber, $botMessage);
        }

    } else{ // Genre si $currentStep === 'demander_type_produit' || $currentStep === 'demander_produit' || ......
            // Retour au menu pour une suite(redemander) de demander au client.
            $botMessage = $welcomeSuccessMessages[array_rand($welcomeSuccessMessages)];
            sendTextMessage($phoneNumber, $botMessage);
            updateSessionStep($sessionId, 'menu_principal');

    }

} else {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé.']);
    exit;
}